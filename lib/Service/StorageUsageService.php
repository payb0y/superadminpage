<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Service;

use OCP\Files\IRootFolder;
use OCP\ICacheFactory;
use OCP\IDBConnection;

use Psr\Log\LoggerInterface;

class StorageUsageService {
    private const CACHE_TTL = 300;

    public function __construct(
        private IDBConnection $db,
        private IRootFolder $rootFolder,
        private ICacheFactory $cacheFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function getOrganizationUsage(int $organizationId): array {
        $cache = $this->cacheFactory->createDistributed('superadminpage_storage');
        $cacheKey = 'organization_' . $organizationId;
        $cached = $cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $usage = $this->calculateOrganizationUsage($organizationId);
        $cache->set($cacheKey, $usage, self::CACHE_TTL);

        return $usage;
    }

    private function calculateOrganizationUsage(int $organizationId): array {
        $plan = $this->getPlanEntitlements($organizationId);
        $private = $this->getPrivateUsage($organizationId);
        $shared = $this->getSharedUsage($organizationId);

        $privateCapacity = $plan === null ? null : $private['memberCount'] * $plan['privateStoragePerUser'];
        $sharedCapacity = $plan === null ? null : $shared['projectCount'] * $plan['sharedStoragePerProject'];
        $capacity = $privateCapacity === null || $sharedCapacity === null
            ? null
            : $privateCapacity + $sharedCapacity;
        $used = $private['usedBytes'] + $shared['usedBytes'];

        $private['capacityBytes'] = $privateCapacity;
        $shared['capacityBytes'] = $sharedCapacity;

        return [
            'usedBytes' => $used,
            'capacityBytes' => $capacity,
            'percentage' => $capacity !== null && $capacity > 0 ? round(($used / $capacity) * 100, 1) : null,
            'private' => $private,
            'shared' => $shared,
            'complete' => $private['unknownCount'] === 0 && $shared['unknownCount'] === 0,
            'calculatedAt' => gmdate(DATE_ATOM),
        ];
    }

    private function getPlanEntitlements(int $organizationId): ?array {
        $sql = "
            SELECT p.private_storage_per_user, p.shared_storage_per_project
            FROM *PREFIX*subscriptions s
            INNER JOIN *PREFIX*plans p ON p.id = s.plan_id
            WHERE s.organization_id = ?
            ORDER BY s.started_at DESC
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$organizationId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return [
            'privateStoragePerUser' => max(0, (int)$row['private_storage_per_user']),
            'sharedStoragePerProject' => max(0, (int)$row['shared_storage_per_project']),
        ];
    }

    private function getPrivateUsage(int $organizationId): array {
        $stmt = $this->db->prepare('SELECT user_uid FROM *PREFIX*organization_members WHERE organization_id = ?');
        $stmt->execute([$organizationId]);
        $members = $stmt->fetchAll();
        $usedBytes = 0;
        $unknownCount = 0;

        foreach ($members as $member) {
            $userId = (string)$member['user_uid'];
            try {
                // Mounted Team Folders are accounted for separately as shared storage.
                $size = $this->rootFolder->getUserFolder($userId)->getSize(false);
                if ($size < 0) {
                    $unknownCount++;
                    continue;
                }
                $usedBytes += $size;
            } catch (\Throwable $e) {
                $unknownCount++;
                $this->logger->warning('Unable to read organization member storage usage', [
                    'organizationId' => $organizationId,
                    'userId' => $userId,
                    'exception' => $e,
                ]);
            }
        }

        return [
            'usedBytes' => $usedBytes,
            'memberCount' => count($members),
            'unknownCount' => $unknownCount,
        ];
    }

    private function getSharedUsage(int $organizationId): array {
        $sql = "
            SELECT cp.id AS project_id, gf.folder_id, fc.size
            FROM *PREFIX*custom_projects cp
            LEFT JOIN *PREFIX*group_folders gf ON gf.folder_id = cp.group_folder_id
            LEFT JOIN *PREFIX*filecache fc
                   ON fc.fileid = gf.root_id
                  AND fc.storage = gf.storage_id
            WHERE cp.organization_id = ?
              AND cp.group_folder_id IS NOT NULL
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$organizationId]);
        $folders = $stmt->fetchAll();
        $usedBytes = 0;
        $unknownCount = 0;

        foreach ($folders as $folder) {
            if ($folder['size'] === null || (int)$folder['size'] < 0) {
                $unknownCount++;
                continue;
            }
            $usedBytes += (int)$folder['size'];
        }

        return [
            'usedBytes' => $usedBytes,
            'projectCount' => count($folders),
            'unknownCount' => $unknownCount,
        ];
    }
}
