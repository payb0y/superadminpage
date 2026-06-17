<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Service;

use OCP\App\IAppManager;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Geocodes the physical address of any project. Mirrors adminpage's
 * GeocodeService but drops the org-scope check — a super-admin can read
 * any project across the platform.
 *
 * Persists positive AND negative results in superadminpage_geocode_cache
 * so we never send the same address to Nominatim twice (per the OSMF
 * usage policy).
 */
class GeocodeService {

    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';
    private const HTTP_TIMEOUT_SECONDS = 10;

    private IDBConnection $db;
    private IClientService $clientService;
    private IAppManager $appManager;
    private IConfig $config;
    private LoggerInterface $logger;

    public function __construct(
        IDBConnection $db,
        IClientService $clientService,
        IAppManager $appManager,
        IConfig $config,
        LoggerInterface $logger
    ) {
        $this->db = $db;
        $this->clientService = $clientService;
        $this->appManager = $appManager;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @return array Status-tagged result:
     *   ['status' => 'no_project']
     *   ['status' => 'no_address']
     *   ['status' => 'not_found',  'addrHash' => string, 'fromCache' => bool]
     *   ['status' => 'unavailable']
     *   ['status' => 'ok', 'lat' => float, 'lng' => float, 'displayName' => ?string,
     *                      'source' => string, 'addrHash' => string, 'fromCache' => bool]
     */
    public function geocodeProject(int $projectId): array {
        $sql = "SELECT loc_street, loc_city, loc_zip
                FROM *PREFIX*custom_projects
                WHERE id = ?
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $projectId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if (!$row) {
            return ['status' => 'no_project'];
        }

        $street = trim((string)($row['loc_street'] ?? ''));
        $city   = trim((string)($row['loc_city']   ?? ''));
        $zip    = trim((string)($row['loc_zip']    ?? ''));

        if ($street === '' && $city === '' && $zip === '') {
            return ['status' => 'no_address'];
        }

        $normalized = strtolower($street) . '|' . strtolower($city) . '|' . strtolower($zip);
        $addrHash = hash('sha256', $normalized);

        $cached = $this->lookupCache($addrHash);
        if ($cached !== null) {
            if ($cached['lat'] === null || $cached['lng'] === null) {
                return [
                    'status'    => 'not_found',
                    'addrHash'  => $addrHash,
                    'fromCache' => true,
                ];
            }
            return [
                'status'      => 'ok',
                'lat'         => (float)$cached['lat'],
                'lng'         => (float)$cached['lng'],
                'displayName' => $cached['display_name'],
                'source'      => $cached['source'],
                'addrHash'    => $addrHash,
                'fromCache'   => true,
            ];
        }

        $query = $this->buildQueryString($street, $city, $zip);
        $hit = $this->callNominatim($query);

        if ($hit === null) {
            // Transient failure — don't cache; let the next click retry.
            return ['status' => 'unavailable'];
        }

        if ($hit === []) {
            // Nominatim returned no match — cache a negative entry.
            $this->insertCache($addrHash, null, null, null, 'nominatim');
            return [
                'status'    => 'not_found',
                'addrHash'  => $addrHash,
                'fromCache' => false,
            ];
        }

        $lat = (float)$hit['lat'];
        $lng = (float)$hit['lng'];
        $displayName = $hit['display_name'] ?? null;

        $this->insertCache($addrHash, $lat, $lng, $displayName, 'nominatim');

        return [
            'status'      => 'ok',
            'lat'         => $lat,
            'lng'         => $lng,
            'displayName' => $displayName,
            'source'      => 'nominatim',
            'addrHash'    => $addrHash,
            'fromCache'   => false,
        ];
    }

    /**
     * @return array|null  cached row, or null if no row
     */
    private function lookupCache(string $addrHash): ?array {
        $stmt = $this->db->prepare(
            "SELECT lat, lng, display_name, source
             FROM *PREFIX*superadminpage_geocode_cache
             WHERE addr_hash = ? LIMIT 1"
        );
        $stmt->bindValue(1, $addrHash, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function insertCache(
        string $addrHash,
        ?float $lat,
        ?float $lng,
        ?string $displayName,
        string $source
    ): void {
        // Tolerate concurrent inserts: two simultaneous expand-on-the-
        // same-address requests both miss cache, both hit Nominatim, both
        // try to INSERT. The second one trips the PK uniqueness — swallow
        // it. matches adminpage's approach.
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO *PREFIX*superadminpage_geocode_cache
                 (addr_hash, lat, lng, display_name, source, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bindValue(1, $addrHash, \PDO::PARAM_STR);
            if ($lat === null) {
                $stmt->bindValue(2, null, \PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(2, number_format($lat, 7, '.', ''), \PDO::PARAM_STR);
            }
            if ($lng === null) {
                $stmt->bindValue(3, null, \PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(3, number_format($lng, 7, '.', ''), \PDO::PARAM_STR);
            }
            if ($displayName === null) {
                $stmt->bindValue(4, null, \PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(4, mb_substr($displayName, 0, 255), \PDO::PARAM_STR);
            }
            $stmt->bindValue(5, $source, \PDO::PARAM_STR);
            $stmt->bindValue(6, time(), \PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Throwable $e) {
            // Concurrent insert raced us to the unique addr_hash; ignore.
            $this->logger->debug('Geocode cache insert collision (ignored)', [
                'app' => 'superadminpage',
                'exception' => $e,
            ]);
        }
    }

    private function buildQueryString(string $street, string $city, string $zip): string {
        $parts = array_filter([$street, $zip, $city], static fn($p) => $p !== '');
        return implode(', ', $parts);
    }

    /**
     * @return array|null
     *   - associative ['lat'=>..., 'lng'=>..., 'display_name'=>...] on a hit
     *   - [] (empty array) when Nominatim returned no match
     *   - null on transient failure (timeout / non-2xx / unparseable response)
     */
    private function callNominatim(string $query) {
        $userAgent = sprintf(
            'Nextcloud-SuperAdminPage/%s (%s)',
            $this->appManager->getAppVersion('superadminpage'),
            $this->resolveInstanceHost()
        );
        try {
            $client = $this->clientService->newClient();
            $response = $client->get(self::NOMINATIM_URL, [
                'query' => [
                    'format' => 'jsonv2',
                    'limit'  => 1,
                    'q'      => $query,
                ],
                'headers' => [
                    'User-Agent' => $userAgent,
                    'Accept'     => 'application/json',
                ],
                'timeout' => self::HTTP_TIMEOUT_SECONDS,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Nominatim request failed', [
                'app' => 'superadminpage',
                'exception' => $e,
            ]);
            return null;
        }

        if ($response->getStatusCode() !== 200) {
            $this->logger->warning('Nominatim non-200', [
                'app'    => 'superadminpage',
                'status' => $response->getStatusCode(),
            ]);
            return null;
        }

        $body = (string)$response->getBody();
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return null;
        }
        if (empty($decoded)) {
            return [];
        }
        $first = $decoded[0];
        if (!isset($first['lat'], $first['lon'])) {
            return [];
        }
        return [
            'lat'          => $first['lat'],
            'lng'          => $first['lon'],
            'display_name' => $first['display_name'] ?? null,
        ];
    }

    private function resolveInstanceHost(): string {
        $cli = (string)$this->config->getSystemValue('overwrite.cli.url', '');
        if ($cli !== '') {
            $host = parse_url($cli, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return $host;
            }
        }
        $trusted = $this->config->getSystemValue('trusted_domains', []);
        if (is_array($trusted) && isset($trusted[0]) && is_string($trusted[0]) && $trusted[0] !== '') {
            return $trusted[0];
        }
        return 'unknown-host';
    }
}
