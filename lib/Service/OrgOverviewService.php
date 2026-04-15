<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Service;

use OCP\IDBConnection;

class OrgOverviewService {

    private IDBConnection $db;

    public function __construct(IDBConnection $db) {
        $this->db = $db;
    }

    /**
     * Cross-org summary for the org list grid.
     * One row per org with counts + plan + subscription status.
     */
    public function listOrgs(): array {
        $sql = "
            SELECT
                o.id,
                o.name,
                COUNT(DISTINCT m.user_uid) AS member_count,
                COUNT(DISTINCT cp.id)      AS project_count,
                p.name                     AS plan_name,
                s.status                   AS subscription_status
            FROM *PREFIX*organizations o
            LEFT JOIN *PREFIX*organization_members m
                   ON m.organization_id = o.id
            LEFT JOIN *PREFIX*custom_projects cp
                   ON cp.organization_id = o.id
            LEFT JOIN *PREFIX*subscriptions s
                   ON s.organization_id = o.id AND s.ended_at IS NULL
            LEFT JOIN *PREFIX*plans p
                   ON p.id = s.plan_id
            GROUP BY o.id, o.name, p.name, s.status
            ORDER BY o.name
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [
                'id'                 => (int)$r['id'],
                'name'               => $r['name'],
                'memberCount'        => (int)$r['member_count'],
                'projectCount'       => (int)$r['project_count'],
                'planName'           => $r['plan_name'] ?? 'No plan',
                'subscriptionStatus' => $r['subscription_status'] ?? 'none',
                // TODO: storage rollup via oc_filecache + oc_group_folders (expensive, deferred)
                'storageBytes'       => 0,
            ];
        }
        return $rows;
    }

    /**
     * Full payload for the org detail view: profile, subscription, members, projects, usage.
     */
    public function getOrgOverview(int $orgId): ?array {
        if (!$this->orgExists($orgId)) {
            return null;
        }
        return [
            'profile'      => $this->getProfile($orgId),
            'subscription' => $this->getSubscription($orgId),
            'members'      => $this->getMembers($orgId),
            'projects'     => $this->getProjects($orgId),
            'backups'      => $this->getBackups($orgId),
            'usageSummary' => $this->getUsageSummary($orgId),
        ];
    }

    private function getBackups(int $orgId): array {
        $sql = "
            SELECT id, status, backup_type, trigger_source,
                   artifact_name, artifact_size,
                   created_at, started_at, finished_at, expires_at
              FROM *PREFIX*org_backup_jobs
             WHERE organization_id = ?
             ORDER BY created_at DESC
             LIMIT 100
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$orgId]);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(function ($r) {
            return [
                'jobId'         => (string)$r['id'],
                'status'        => $r['status'],
                'backupType'    => $r['backup_type'],
                'triggerSource' => $r['trigger_source'],
                'artifactName'  => $r['artifact_name'],
                'artifactSize'  => $r['artifact_size'] !== null ? (int)$r['artifact_size'] : null,
                'createdAt'     => $r['created_at'],
                'startedAt'     => $r['started_at'],
                'finishedAt'    => $r['finished_at'],
                'expiresAt'     => $r['expires_at'],
            ];
        }, $rows);
    }

    private function orgExists(int $orgId): bool {
        $sql = "SELECT 1 FROM *PREFIX*organizations WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orgId]);
        return (bool)$stmt->fetch();
    }

    private function getProfile(int $orgId): array {
        $sql = "SELECT id, name, contact_first_name, contact_last_name, contact_email, contact_phone, admin_uid FROM *PREFIX*organizations WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orgId]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['id' => $orgId, 'name' => 'Unknown', 'contactFirstName' => '', 'contactLastName' => '', 'contactEmail' => '—', 'contactPhone' => '', 'adminUid' => '—'];
        }

        return [
            'id'               => (int)$row['id'],
            'name'             => $row['name'],
            'contactFirstName' => $row['contact_first_name'] ?? '',
            'contactLastName'  => $row['contact_last_name'] ?? '',
            'contactEmail'     => $row['contact_email'] ?? '—',
            'contactPhone'     => $row['contact_phone'] ?? '',
            'adminUid'         => $row['admin_uid'] ?? '—',
        ];
    }

    private function getSubscription(int $orgId): array {
        $sql = "
            SELECT
                sub.id          AS sub_id,
                sub.status,
                sub.started_at,
                sub.ended_at,
                p.id            AS plan_id,
                p.name          AS plan_name,
                p.price,
                p.currency,
                p.max_projects,
                p.max_members,
                p.is_public,
                ROUND(p.shared_storage_per_project / 1073741824, 2) AS shared_storage_gb,
                ROUND(p.private_storage_per_user   / 1073741824, 2) AS private_storage_gb
            FROM *PREFIX*subscriptions sub
            INNER JOIN *PREFIX*plans p ON p.id = sub.plan_id
            WHERE sub.organization_id = ?
            ORDER BY sub.started_at DESC
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orgId]);
        $row = $stmt->fetch();

        if (!$row) {
            return [
                'status'           => 'none',
                'planName'         => 'No plan',
                'price'            => 0.0,
                'currency'         => 'EUR',
                'maxProjects'      => 0,
                'maxMembers'       => 0,
                'sharedStorageGb'  => 0.0,
                'privateStorageGb' => 0.0,
                'startedAt'        => null,
                'endedAt'          => null,
                'isPublic'         => false,
            ];
        }

        return [
            'status'           => $row['status'],
            'planName'         => $row['plan_name'],
            'price'            => (float)$row['price'],
            'currency'         => $row['currency'] ?? 'EUR',
            'maxProjects'      => (int)$row['max_projects'],
            'maxMembers'       => (int)$row['max_members'],
            'sharedStorageGb'  => (float)$row['shared_storage_gb'],
            'privateStorageGb' => (float)$row['private_storage_gb'],
            'startedAt'        => $row['started_at'],
            'endedAt'          => $row['ended_at'],
            'isPublic'         => (bool)$row['is_public'],
        ];
    }

    private function getMembers(int $orgId): array {
        $sql = "
            SELECT
                om.user_uid,
                om.role,
                om.created_at   AS joined_at,
                u.displayname,
                a.data          AS account_data
            FROM *PREFIX*organization_members om
            LEFT JOIN *PREFIX*users u    ON u.uid = om.user_uid
            LEFT JOIN *PREFIX*accounts a ON a.uid = om.user_uid
            WHERE om.organization_id = ?
            ORDER BY om.role DESC, om.user_uid ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orgId]);
        $memberRows = $stmt->fetchAll();

        if (empty($memberRows)) {
            return [];
        }

        $taskSql = "
            SELECT
                au.participant                      AS user_uid,
                COUNT(DISTINCT c.id)                AS assigned_tasks,
                SUM(CASE WHEN s.title = 'Approved/Done' AND c.deleted_at = 0 THEN 1 ELSE 0 END) AS done_tasks,
                SUM(CASE
                    WHEN c.duedate IS NOT NULL
                     AND c.duedate < NOW()
                     AND c.deleted_at = 0
                     AND s.title <> 'Approved/Done'
                    THEN 1 ELSE 0
                END) AS overdue_tasks
            FROM *PREFIX*deck_assigned_users au
            JOIN *PREFIX*deck_cards  c  ON c.id = au.card_id AND c.deleted_at = 0
            JOIN *PREFIX*deck_stacks s  ON s.id = c.stack_id
            JOIN *PREFIX*deck_boards b  ON b.id = s.board_id AND b.deleted_at = 0
            JOIN *PREFIX*custom_projects cp ON cp.board_id = CAST(b.id AS CHAR)
                AND cp.organization_id = ?
            WHERE au.type = 0
            GROUP BY au.participant
        ";
        $stmt2 = $this->db->prepare($taskSql);
        $stmt2->execute([$orgId]);
        $taskStats = [];
        foreach ($stmt2->fetchAll() as $r) {
            $taskStats[$r['user_uid']] = [
                'assignedTasks' => (int)$r['assigned_tasks'],
                'doneTasks'     => (int)$r['done_tasks'],
                'overdueTasks'  => (int)$r['overdue_tasks'],
            ];
        }

        $actSql = "
            SELECT
                au.participant AS user_uid,
                MAX(c.last_modified) AS last_active_ts
            FROM *PREFIX*deck_assigned_users au
            JOIN *PREFIX*deck_cards  c  ON c.id = au.card_id
            JOIN *PREFIX*deck_stacks s  ON s.id = c.stack_id
            JOIN *PREFIX*deck_boards b  ON b.id = s.board_id AND b.deleted_at = 0
            JOIN *PREFIX*custom_projects cp ON cp.board_id = CAST(b.id AS CHAR)
                AND cp.organization_id = ?
            WHERE au.type = 0
            GROUP BY au.participant
        ";
        $stmt3 = $this->db->prepare($actSql);
        $stmt3->execute([$orgId]);
        $lastActive = [];
        foreach ($stmt3->fetchAll() as $r) {
            if ($r['last_active_ts']) {
                $lastActive[$r['user_uid']] = (new \DateTime())
                    ->setTimestamp((int)$r['last_active_ts'])
                    ->format('Y-m-d H:i');
            }
        }

        return array_map(function ($row) use ($taskStats, $lastActive) {
            $uid = $row['user_uid'];
            $account = [];
            if (!empty($row['account_data'])) {
                $decoded = json_decode($row['account_data'], true);
                if (is_array($decoded)) {
                    $account = $decoded;
                }
            }

            $stats = $taskStats[$uid] ?? ['assignedTasks' => 0, 'doneTasks' => 0, 'overdueTasks' => 0];

            return [
                'userId'        => $uid,
                'displayName'   => $account['displayname']['value'] ?? $row['displayname'] ?? $uid,
                'email'         => $account['email']['value'] ?? '',
                'phone'         => $account['phone']['value'] ?? '',
                'organisation'  => $account['organisation']['value'] ?? '',
                'title'         => $account['role']['value'] ?? '',
                'role'          => $row['role'] ?? 'member',
                'joinedAt'      => $row['joined_at'],
                'lastActive'    => $lastActive[$uid] ?? null,
                'assignedTasks' => $stats['assignedTasks'],
                'doneTasks'     => $stats['doneTasks'],
                'overdueTasks'  => $stats['overdueTasks'],
            ];
        }, $memberRows);
    }

    private function getProjects(int $orgId): array {
        $sql = "
            SELECT cp.id, cp.name, cp.board_id
            FROM *PREFIX*custom_projects cp
            WHERE cp.organization_id = ?
            ORDER BY cp.name
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orgId]);
        $projects = $stmt->fetchAll();

        if (empty($projects)) {
            return [];
        }

        $boardIds = array_filter(array_column($projects, 'board_id'));
        if (empty($boardIds)) {
            return array_map(fn ($p) => $this->emptyProject($p), $projects);
        }

        $placeholders = implode(',', array_fill(0, count($boardIds), '?'));

        $totalSql = "
            SELECT s.board_id, COUNT(c.id) AS total
            FROM *PREFIX*deck_stacks s
            INNER JOIN *PREFIX*deck_cards c ON c.stack_id = s.id
            WHERE s.board_id IN ($placeholders)
              AND c.deleted_at = 0 AND c.archived = 0
            GROUP BY s.board_id
        ";
        $stmt = $this->db->prepare($totalSql);
        $stmt->execute($boardIds);
        $totalByBoard = [];
        foreach ($stmt->fetchAll() as $r) {
            $totalByBoard[(int)$r['board_id']] = (int)$r['total'];
        }

        $doneSql = "
            SELECT s.board_id, COUNT(c.id) AS done
            FROM *PREFIX*deck_stacks s
            INNER JOIN *PREFIX*deck_cards c ON c.stack_id = s.id
            WHERE s.board_id IN ($placeholders)
              AND s.title = 'Approved/Done'
              AND c.deleted_at = 0
            GROUP BY s.board_id
        ";
        $stmt2 = $this->db->prepare($doneSql);
        $stmt2->execute($boardIds);
        $doneByBoard = [];
        foreach ($stmt2->fetchAll() as $r) {
            $doneByBoard[(int)$r['board_id']] = (int)$r['done'];
        }

        $now = time();
        $overdueSql = "
            SELECT s.board_id, COUNT(c.id) AS overdue
            FROM *PREFIX*deck_stacks s
            INNER JOIN *PREFIX*deck_cards c ON c.stack_id = s.id
            WHERE s.board_id IN ($placeholders)
              AND s.title != 'Approved/Done'
              AND c.duedate IS NOT NULL
              AND UNIX_TIMESTAMP(c.duedate) < ?
              AND c.deleted_at = 0 AND c.archived = 0
            GROUP BY s.board_id
        ";
        $stmt3 = $this->db->prepare($overdueSql);
        $stmt3->execute(array_merge($boardIds, [$now]));
        $overdueByBoard = [];
        foreach ($stmt3->fetchAll() as $r) {
            $overdueByBoard[(int)$r['board_id']] = (int)$r['overdue'];
        }

        $stacksSql = "
            SELECT s.board_id, s.title AS stack_title, s.order AS stack_order, COUNT(c.id) AS card_count
            FROM *PREFIX*deck_stacks s
            LEFT JOIN *PREFIX*deck_cards c
                ON c.stack_id = s.id AND c.deleted_at = 0 AND c.archived = 0
            WHERE s.board_id IN ($placeholders)
            GROUP BY s.board_id, s.id, s.title, s.order
            ORDER BY s.board_id, s.order
        ";
        $stmt4 = $this->db->prepare($stacksSql);
        $stmt4->execute($boardIds);
        $stacksByBoard = [];
        foreach ($stmt4->fetchAll() as $r) {
            $bid = (int)$r['board_id'];
            $stacksByBoard[$bid][] = [
                'title' => $r['stack_title'],
                'count' => (int)$r['card_count'],
            ];
        }

        return array_map(function ($p) use ($totalByBoard, $doneByBoard, $overdueByBoard, $stacksByBoard) {
            $bid      = (int)$p['board_id'];
            $total    = $totalByBoard[$bid]  ?? 0;
            $done     = $doneByBoard[$bid]   ?? 0;
            $overdue  = $overdueByBoard[$bid] ?? 0;
            $progress = $total > 0 ? (int)round(($done / $total) * 100) : 0;

            return [
                'id'       => (int)$p['id'],
                'name'     => $p['name'],
                'boardId'  => $bid,
                'total'    => $total,
                'done'     => $done,
                'overdue'  => $overdue,
                'progress' => $progress,
                'stacks'   => $stacksByBoard[$bid] ?? [],
            ];
        }, $projects);
    }

    private function emptyProject(array $p): array {
        return [
            'id'       => (int)$p['id'],
            'name'     => $p['name'],
            'boardId'  => (int)($p['board_id'] ?? 0),
            'total'    => 0,
            'done'     => 0,
            'overdue'  => 0,
            'progress' => 0,
            'stacks'   => [],
        ];
    }

    private function getUsageSummary(int $orgId): array {
        $sql = "SELECT COUNT(*) AS cnt FROM *PREFIX*organization_members WHERE organization_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orgId]);
        $memberCount = (int)($stmt->fetch()['cnt'] ?? 0);

        $sql2 = "SELECT COUNT(*) AS cnt FROM *PREFIX*custom_projects WHERE organization_id = ?";
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->execute([$orgId]);
        $projectCount = (int)($stmt2->fetch()['cnt'] ?? 0);

        $taskSql = "
            SELECT COUNT(c.id) AS total_tasks,
                   SUM(CASE WHEN s.title = 'Approved/Done' AND c.deleted_at = 0 THEN 1 ELSE 0 END) AS done_tasks
            FROM *PREFIX*custom_projects cp
            INNER JOIN *PREFIX*deck_stacks s  ON s.board_id = CAST(cp.board_id AS UNSIGNED)
            INNER JOIN *PREFIX*deck_cards  c  ON c.stack_id = s.id AND c.deleted_at = 0 AND c.archived = 0
            WHERE cp.organization_id = ?
        ";
        $stmt3 = $this->db->prepare($taskSql);
        $stmt3->execute([$orgId]);
        $r3 = $stmt3->fetch();

        return [
            'memberCount'  => $memberCount,
            'projectCount' => $projectCount,
            'totalTasks'   => (int)($r3['total_tasks'] ?? 0),
            'doneTasks'    => (int)($r3['done_tasks']  ?? 0),
        ];
    }
}
