<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Service;

use OCP\IDBConnection;

class BackupAdminService {
    use SqlDialectTrait;

    private const STATUSES = ['queued', 'running', 'completed', 'failed', 'expired', 'deleted'];
    private const TYPES = ['full', 'incremental'];
    private const TRIGGERS = ['manual', 'scheduled'];

    public function __construct(private IDBConnection $db) {
    }

    /**
     * @return array{jobs:list<array<string,mixed>>,limit:int,offset:int,total:int,hasMore:bool}
     */
    public function listJobs(
        ?int $organizationId,
        ?string $status,
        ?string $backupType,
        ?string $triggerSource,
        ?string $query,
        int $limit,
        int $offset,
    ): array {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        $where = [];
        $params = [];

        if ($organizationId !== null && $organizationId > 0) {
            $where[] = 'j.organization_id = ?';
            $params[] = $organizationId;
        }
        $status = $this->allowed($status, self::STATUSES);
        if ($status !== null) {
            $where[] = 'j.status = ?';
            $params[] = $status;
        }
        $backupType = $this->allowed($backupType, self::TYPES);
        if ($backupType !== null) {
            $where[] = 'j.backup_type = ?';
            $params[] = $backupType;
        }
        $triggerSource = $this->allowed($triggerSource, self::TRIGGERS);
        if ($triggerSource !== null) {
            $where[] = 'j.trigger_source = ?';
            $params[] = $triggerSource;
        }
        $query = trim((string)$query);
        if ($query !== '') {
            $where[] = '(LOWER(o.name) LIKE ? OR LOWER(COALESCE(j.artifact_name, \'\')) LIKE ? OR ' . $this->castText('j.id') . ' LIKE ?)';
            $needle = '%' . strtolower($query) . '%';
            $params[] = $needle;
            $params[] = $needle;
            $params[] = $needle;
        }

        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->db->prepare(
            'SELECT COUNT(*) AS total FROM *PREFIX*org_backup_jobs j'
            . ' INNER JOIN *PREFIX*organizations o ON o.id = j.organization_id'
            . $clause,
        );
        $count->execute($params);
        $total = (int)$count->fetchOne();

        $sql = 'SELECT j.id, j.organization_id, o.name AS organization_name,'
            . ' j.requested_by_uid, j.backup_type, j.trigger_source, j.baseline_job_id,'
            . ' j.base_full_job_id, j.status, j.attempt, j.error_message, j.artifact_name,'
            . ' j.artifact_size, j.created_at, j.updated_at, j.started_at, j.finished_at, j.expires_at'
            . ' FROM *PREFIX*org_backup_jobs j'
            . ' INNER JOIN *PREFIX*organizations o ON o.id = j.organization_id'
            . $clause
            . ' ORDER BY j.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        $jobs = [];
        while (($row = $statement->fetch()) !== false) {
            $jobs[] = [
                'jobId' => (int)$row['id'],
                'organizationId' => (int)$row['organization_id'],
                'organizationName' => (string)$row['organization_name'],
                'requestedByUid' => (string)$row['requested_by_uid'],
                'backupType' => (string)$row['backup_type'],
                'triggerSource' => (string)$row['trigger_source'],
                'baselineJobId' => $row['baseline_job_id'] !== null ? (int)$row['baseline_job_id'] : null,
                'baseFullJobId' => $row['base_full_job_id'] !== null ? (int)$row['base_full_job_id'] : null,
                'status' => (string)$row['status'],
                'attempt' => (int)($row['attempt'] ?? 1),
                'errorMessage' => $row['error_message'],
                'artifactName' => $row['artifact_name'],
                'artifactSize' => $row['artifact_size'] !== null ? (int)$row['artifact_size'] : null,
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at'],
                'startedAt' => $row['started_at'],
                'finishedAt' => $row['finished_at'],
                'expiresAt' => $row['expires_at'],
            ];
        }

        return [
            'jobs' => $jobs,
            'limit' => $limit,
            'offset' => $offset,
            'total' => $total,
            'hasMore' => $offset + count($jobs) < $total,
        ];
    }

    /** @param list<string> $allowed */
    private function allowed(?string $value, array $allowed): ?string {
        $value = strtolower(trim((string)$value));
        return in_array($value, $allowed, true) ? $value : null;
    }
}
