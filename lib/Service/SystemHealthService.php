<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Service;

use OCP\Http\Client\IClientService;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IDBConnection;

class SystemHealthService {

    private IConfig $config;
    private ICacheFactory $cacheFactory;
    private IDBConnection $db;
    private IClientService $clientService;

    public function __construct(
        IConfig $config,
        ICacheFactory $cacheFactory,
        IDBConnection $db,
        IClientService $clientService,
    ) {
        $this->config = $config;
        $this->cacheFactory = $cacheFactory;
        $this->db = $db;
        $this->clientService = $clientService;
    }

    public function getSnapshot(): array {
        return [
            'cpu'              => $this->gatherCpu(),
            'memory'           => $this->gatherMemory(),
            'swap'             => $this->gatherSwap(),
            'disk'             => $this->gatherDisk(),
            'uptime'           => $this->gatherUptime(),
            'network'          => $this->gatherNetwork(),
            'users'            => $this->gatherActiveUsers(),
            'services'         => $this->gatherServices(),
            'nextcloudVersion' => $this->gatherNextcloudVersion(),
            'snapshotAt'       => time(),
        ];
    }

    private function gatherCpu(): ?array {
        $load = function_exists('sys_getloadavg') ? @sys_getloadavg() : null;
        if (!is_array($load) || count($load) < 3) {
            return null;
        }
        return [
            'load1'  => (float)$load[0],
            'load5'  => (float)$load[1],
            'load15' => (float)$load[2],
            'cores'  => $this->cpuCores(),
        ];
    }

    private function cpuCores(): ?int {
        $cpuinfo = @file_get_contents('/proc/cpuinfo');
        if (!is_string($cpuinfo) || $cpuinfo === '') {
            return null;
        }
        $count = preg_match_all('/^processor\s*:/m', $cpuinfo);
        return $count > 0 ? $count : null;
    }

    private function gatherMemory(): ?array {
        $meminfo = $this->readMeminfo();
        if ($meminfo === null) {
            return null;
        }
        $totalKb = $meminfo['MemTotal'] ?? null;
        $availKb = $meminfo['MemAvailable'] ?? null;
        if ($totalKb === null || $availKb === null || $totalKb <= 0) {
            return null;
        }
        $totalBytes = $totalKb * 1024;
        $usedBytes  = max(0, ($totalKb - $availKb) * 1024);
        return [
            'totalBytes' => $totalBytes,
            'usedBytes'  => $usedBytes,
            'percent'    => (int)round($usedBytes / $totalBytes * 100),
        ];
    }

    private function gatherSwap(): ?array {
        $meminfo = $this->readMeminfo();
        if ($meminfo === null) {
            return null;
        }
        $totalKb = $meminfo['SwapTotal'] ?? null;
        $freeKb  = $meminfo['SwapFree'] ?? null;
        if ($totalKb === null || $freeKb === null || $totalKb <= 0) {
            return null;
        }
        $totalBytes = $totalKb * 1024;
        $usedBytes  = max(0, ($totalKb - $freeKb) * 1024);
        return [
            'totalBytes' => $totalBytes,
            'usedBytes'  => $usedBytes,
            'percent'    => (int)round($usedBytes / $totalBytes * 100),
        ];
    }

    /**
     * @return array<string, int>|null  keys are meminfo labels, values in kB
     */
    private function readMeminfo(): ?array {
        $raw = @file_get_contents('/proc/meminfo');
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $out = [];
        foreach (explode("\n", $raw) as $line) {
            if (preg_match('/^([A-Za-z()_]+):\s+(\d+)\s*kB/', $line, $m) === 1) {
                $out[$m[1]] = (int)$m[2];
            }
        }
        return $out !== [] ? $out : null;
    }

    private function gatherDisk(): ?array {
        $path = (string)$this->config->getSystemValue('datadirectory', '');
        if ($path === '') {
            return null;
        }
        $total = @disk_total_space($path);
        $free  = @disk_free_space($path);
        if ($total === false || $free === false || $total <= 0) {
            return [
                'totalBytes' => null,
                'usedBytes'  => null,
                'percent'    => null,
                'path'       => $path,
            ];
        }
        $used = max(0.0, $total - $free);
        return [
            'totalBytes' => (int)$total,
            'usedBytes'  => (int)$used,
            'percent'    => (int)round($used / $total * 100),
            'path'       => $path,
        ];
    }

    private function gatherUptime(): ?array {
        $raw = @file_get_contents('/proc/uptime');
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $parts = preg_split('/\s+/', trim($raw));
        if (!is_array($parts) || count($parts) === 0) {
            return null;
        }
        $seconds = (int)floor((float)$parts[0]);
        if ($seconds <= 0) {
            return null;
        }
        return [
            'seconds'  => $seconds,
            'bootedAt' => time() - $seconds,
        ];
    }

    private function gatherNextcloudVersion(): ?string {
        if (class_exists(\OC_Util::class) && method_exists(\OC_Util::class, 'getVersionString')) {
            $v = (string)\OC_Util::getVersionString();
            return $v !== '' ? $v : null;
        }
        return null;
    }

    private function gatherNetwork(): ?array {
        $iface = $this->primaryInterface();
        if ($iface === null) {
            return null;
        }
        $counters = $this->readInterfaceCounters($iface);
        if ($counters === null) {
            return null;
        }
        [$rxBytes, $txBytes] = $counters;

        $hostname = gethostname();
        if ($hostname === false || $hostname === '') {
            $hostname = null;
        }

        $rxRate = null;
        $txRate = null;
        if ($this->cacheFactory->isLocalCacheAvailable()) {
            $cache = $this->cacheFactory->createLocal('oca_superadminpage_netstats');
            $now = time();
            $prev = $cache->get('snapshot');
            if (is_array($prev)
                && isset($prev['ts'], $prev['rx'], $prev['tx'], $prev['iface'])
                && $prev['iface'] === $iface
                && ($now - (int)$prev['ts']) >= 1
            ) {
                $dt = $now - (int)$prev['ts'];
                $rxRate = max(0.0, ($rxBytes - (int)$prev['rx']) / $dt);
                $txRate = max(0.0, ($txBytes - (int)$prev['tx']) / $dt);
            }
            $cache->set('snapshot', [
                'ts'    => $now,
                'rx'    => $rxBytes,
                'tx'    => $txBytes,
                'iface' => $iface,
            ]);
        }

        return [
            'hostname'      => $hostname,
            'interface'     => $iface,
            'rxBytesPerSec' => $rxRate,
            'txBytesPerSec' => $txRate,
            'rxBytesTotal'  => $rxBytes,
            'txBytesTotal'  => $txBytes,
        ];
    }

    private function primaryInterface(): ?string {
        $route = @file_get_contents('/proc/net/route');
        if (is_string($route) && $route !== '') {
            $best = null;
            $bestMetric = PHP_INT_MAX;
            foreach (preg_split('/\r?\n/', $route) ?: [] as $i => $line) {
                if ($i === 0 || $line === '') {
                    continue; // header / blank
                }
                $cols = preg_split('/\s+/', trim($line));
                if (!is_array($cols) || count($cols) < 7) {
                    continue;
                }
                [$iface, $dest, $gateway] = [$cols[0], $cols[1], $cols[2]];
                $metric = (int)$cols[6];
                if ($dest !== '00000000' || $gateway === '00000000') {
                    continue; // not a default route
                }
                if ($metric < $bestMetric) {
                    $bestMetric = $metric;
                    $best = $iface;
                }
            }
            if ($best !== null) {
                return $best;
            }
        }

        // Fallback: first physical-looking iface in /proc/net/dev.
        $dev = @file_get_contents('/proc/net/dev');
        if (!is_string($dev) || $dev === '') {
            return null;
        }
        foreach (preg_split('/\r?\n/', $dev) ?: [] as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }
            $name = trim(strstr($line, ':', true));
            if ($name === '' || $name === 'lo'
                || strpos($name, 'docker') === 0
                || strpos($name, 'veth') === 0
                || strpos($name, 'br-') === 0) {
                continue;
            }
            return $name;
        }
        return null;
    }

    /**
     * @return array{0:int,1:int}|null  [rxBytes, txBytes]
     */
    private function readInterfaceCounters(string $iface): ?array {
        $dev = @file_get_contents('/proc/net/dev');
        if (!is_string($dev) || $dev === '') {
            return null;
        }
        foreach (preg_split('/\r?\n/', $dev) ?: [] as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }
            $name = trim(strstr($line, ':', true));
            if ($name !== $iface) {
                continue;
            }
            $rest = substr($line, strpos($line, ':') + 1);
            $fields = preg_split('/\s+/', trim($rest));
            if (!is_array($fields) || count($fields) < 16) {
                return null;
            }
            // After the iface name: 0=rx_bytes, 1=rx_packets, ..., 8=tx_bytes
            return [(int)$fields[0], (int)$fields[8]];
        }
        return null;
    }

    private function gatherActiveUsers(): ?array {
        $cacheAvailable = $this->cacheFactory->isLocalCacheAvailable();
        $cache = $cacheAvailable
            ? $this->cacheFactory->createLocal('oca_superadminpage_userstats')
            : null;

        if ($cache !== null) {
            $cached = $cache->get('snapshot');
            if (is_array($cached)
                && isset($cached['total'], $cached['last5min'], $cached['last1hour'], $cached['last24hour'])
            ) {
                return $cached;
            }
        }

        try {
            $now = time();
            $payload = [
                'total'      => $this->countAllUsers(),
                'last5min'   => $this->countActiveUsersSince($now - 300),
                'last1hour'  => $this->countActiveUsersSince($now - 3600),
                'last24hour' => $this->countActiveUsersSince($now - 86400),
            ];
        } catch (\Throwable $e) {
            return null;
        }

        if ($cache !== null) {
            $cache->set('snapshot', $payload, 30);
        }
        return $payload;
    }

    private function countAllUsers(): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->count('*'))
            ->from('users');
        $stmt = $qb->executeQuery();
        $n = (int)$stmt->fetchOne();
        $stmt->closeCursor();
        return $n;
    }

    private function countActiveUsersSince(int $unixTs): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(DISTINCT uid)'))
            ->from('authtoken')
            ->where($qb->expr()->gte(
                'last_activity',
                $qb->createNamedParameter($unixTs, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)
            ));
        $stmt = $qb->executeQuery();
        $n = (int)$stmt->fetchOne();
        $stmt->closeCursor();
        return $n;
    }

    /**
     * @return array<int, array<string, mixed>>  one entry per monitored service
     */
    private function gatherServices(): array {
        $cache = $this->cacheFactory->isLocalCacheAvailable()
            ? $this->cacheFactory->createLocal('oca_superadminpage_servicestats')
            : null;

        if ($cache !== null) {
            $cached = $cache->get('snapshot');
            if (is_array($cached)) {
                return $cached;
            }
        }

        $services = [$this->checkPdfToImage()];

        if ($cache !== null) {
            $cache->set('snapshot', $services, 15);
        }
        return $services;
    }

    private function checkPdfToImage(): array {
        $base = rtrim(
            (string)$this->config->getSystemValue('superadminpage.pdf_to_image_url', 'https://pdf2img.loket.site'),
            '/'
        );

        $service = [
            'key'       => 'pdf-to-image',
            'name'      => 'PDF → Image',
            'status'    => 'down',
            'detail'    => 'unreachable',
            'latencyMs' => null,
            'url'       => $base,
        ];

        $start = microtime(true);
        try {
            $resp = $this->clientService->newClient()->get($base . '/health', [
                'timeout'         => 3,
                'connect_timeout' => 2,
                'nocache'         => true,
                'http_errors'     => false,
            ]);
            $service['latencyMs'] = (int)round((microtime(true) - $start) * 1000);

            $code = $resp->getStatusCode();
            $body = json_decode((string)$resp->getBody(), true);
            $popplerOk = is_array($body) && ($body['poppler'] ?? null) === true;

            if ($code === 200 && $popplerOk) {
                $service['status'] = 'ok';
                $service['detail'] = 'poppler available';
            } else {
                $service['status'] = 'degraded';
                $service['detail'] = (is_array($body) && isset($body['status']))
                    ? (string)$body['status']
                    : 'unexpected response';
            }
        } catch (\Throwable $e) {
            // Leave the default 'down' shape (latencyMs stays null).
        }

        return $service;
    }
}
