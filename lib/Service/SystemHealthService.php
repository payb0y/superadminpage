<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Service;

use OCP\IConfig;

class SystemHealthService {

    private IConfig $config;

    public function __construct(IConfig $config) {
        $this->config = $config;
    }

    public function getSnapshot(): array {
        return [
            'cpu'              => $this->gatherCpu(),
            'memory'           => $this->gatherMemory(),
            'swap'             => $this->gatherSwap(),
            'disk'             => $this->gatherDisk(),
            'uptime'           => $this->gatherUptime(),
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
}
