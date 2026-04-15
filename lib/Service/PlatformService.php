<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Service;

use OCP\IDBConnection;

class PlatformService {

    private IDBConnection $db;

    public function __construct(IDBConnection $db) {
        $this->db = $db;
    }

    public function getOverview(): array {
        return [
            'kpis'   => $this->getKpis(),
            'alerts' => $this->getAlerts(),
        ];
    }

    private function getKpis(): array {
        // Orgs by subscription status
        $orgSql = "
            SELECT
                COUNT(DISTINCT o.id) AS total_orgs,
                SUM(CASE WHEN s.status = 'active'    THEN 1 ELSE 0 END) AS active_subs,
                SUM(CASE WHEN s.status = 'paused'    THEN 1 ELSE 0 END) AS paused_subs,
                SUM(CASE WHEN s.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_subs
            FROM *PREFIX*organizations o
            LEFT JOIN *PREFIX*subscriptions s
                   ON s.organization_id = o.id AND s.ended_at IS NULL
        ";
        $stmt = $this->db->prepare($orgSql);
        $stmt->execute();
        $orgRow = $stmt->fetch() ?: [];

        // MRR: sum of plan prices for active subscriptions (currency-agnostic sum for v1)
        $mrrSql = "
            SELECT COALESCE(SUM(p.price), 0) AS mrr,
                   COALESCE(p.currency, 'EUR') AS currency,
                   COUNT(DISTINCT p.currency) AS currency_count
            FROM *PREFIX*subscriptions s
            INNER JOIN *PREFIX*plans p ON p.id = s.plan_id
            WHERE s.ended_at IS NULL AND s.status = 'active'
            GROUP BY p.currency
            ORDER BY mrr DESC
            LIMIT 1
        ";
        $stmt = $this->db->prepare($mrrSql);
        $stmt->execute();
        $mrrRow = $stmt->fetch() ?: ['mrr' => 0, 'currency' => 'EUR', 'currency_count' => 0];

        // Total members across platform
        $memSql = "SELECT COUNT(*) AS cnt FROM *PREFIX*organization_members";
        $stmt = $this->db->prepare($memSql);
        $stmt->execute();
        $memRow = $stmt->fetch() ?: ['cnt' => 0];

        // Total projects (non-archived)
        $projSql = "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN archived_at IS NULL THEN 1 ELSE 0 END) AS active
            FROM *PREFIX*custom_projects
        ";
        $stmt = $this->db->prepare($projSql);
        $stmt->execute();
        $projRow = $stmt->fetch() ?: ['total' => 0, 'active' => 0];

        // Cross-platform task counts (Deck cards joined through custom_projects)
        $taskSql = "
            SELECT
                COUNT(c.id) AS total_tasks,
                SUM(CASE WHEN s.title = 'Approved/Done' AND c.deleted_at = 0 THEN 1 ELSE 0 END) AS done_tasks,
                SUM(CASE
                    WHEN c.duedate IS NOT NULL
                     AND c.duedate < NOW()
                     AND c.deleted_at = 0
                     AND s.title <> 'Approved/Done'
                    THEN 1 ELSE 0
                END) AS overdue_tasks
            FROM *PREFIX*custom_projects cp
            INNER JOIN *PREFIX*deck_stacks s
                    ON s.board_id = CAST(cp.board_id AS UNSIGNED)
            INNER JOIN *PREFIX*deck_cards c
                    ON c.stack_id = s.id
                   AND c.deleted_at = 0
                   AND c.archived = 0
        ";
        $stmt = $this->db->prepare($taskSql);
        $stmt->execute();
        $taskRow = $stmt->fetch() ?: ['total_tasks' => 0, 'done_tasks' => 0, 'overdue_tasks' => 0];

        return [
            'orgs' => [
                'total'     => (int)($orgRow['total_orgs'] ?? 0),
                'active'    => (int)($orgRow['active_subs'] ?? 0),
                'paused'    => (int)($orgRow['paused_subs'] ?? 0),
                'cancelled' => (int)($orgRow['cancelled_subs'] ?? 0),
            ],
            'mrr' => [
                'value'         => (float)$mrrRow['mrr'],
                'currency'      => $mrrRow['currency'],
                'multiCurrency' => ((int)$mrrRow['currency_count']) > 1,
            ],
            'members' => [
                'total' => (int)$memRow['cnt'],
            ],
            'projects' => [
                'total'  => (int)$projRow['total'],
                'active' => (int)$projRow['active'],
            ],
            'tasks' => [
                'total'   => (int)$taskRow['total_tasks'],
                'done'    => (int)$taskRow['done_tasks'],
                'overdue' => (int)$taskRow['overdue_tasks'],
            ],
        ];
    }

    private function getAlerts(): array {
        return [
            'failedBackups7d'  => $this->countFailedBackups7d(),
            'stuckAhoJobs'     => $this->countStuckAhoJobs(),
            'staleProjects30d' => $this->countStaleProjects(30),
            'orgsNoSub'        => $this->countOrgsWithoutActiveSubscription(),
        ];
    }

    private function countFailedBackups7d(): array {
        $sql = "
            SELECT COUNT(*) AS cnt
            FROM *PREFIX*org_backup_jobs
            WHERE status = 'failed'
              AND created_at >= UNIX_TIMESTAMP(NOW()) - 7 * 86400
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $cnt = (int)($stmt->fetch()['cnt'] ?? 0);
        } catch (\Throwable $e) {
            $cnt = 0;
        }
        return [
            'count'   => $cnt,
            'label'   => 'Failed backups (7d)',
            'tone'    => $cnt > 0 ? 'danger' : 'success',
        ];
    }

    private function countStuckAhoJobs(): array {
        $sql = "
            SELECT COUNT(*) AS cnt
            FROM *PREFIX*org_aho_jobs
            WHERE status IN ('pending', 'failed')
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $cnt = (int)($stmt->fetch()['cnt'] ?? 0);
        } catch (\Throwable $e) {
            $cnt = 0;
        }
        return [
            'count' => $cnt,
            'label' => 'AHO jobs pending/failed',
            'tone'  => $cnt > 0 ? 'warning' : 'success',
        ];
    }

    private function countStaleProjects(int $days): array {
        $sql = "
            SELECT COUNT(*) AS cnt
            FROM *PREFIX*custom_projects
            WHERE archived_at IS NULL
              AND (last_deck_move_at IS NULL
                   OR last_deck_move_at < UNIX_TIMESTAMP(NOW()) - ? * 86400)
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$days]);
            $cnt = (int)($stmt->fetch()['cnt'] ?? 0);
        } catch (\Throwable $e) {
            $cnt = 0;
        }
        return [
            'count' => $cnt,
            'label' => "Stale projects (>{$days}d)",
            'tone'  => $cnt > 0 ? 'warning' : 'success',
        ];
    }

    private function countOrgsWithoutActiveSubscription(): array {
        $sql = "
            SELECT COUNT(*) AS cnt
            FROM *PREFIX*organizations o
            LEFT JOIN *PREFIX*subscriptions s
                   ON s.organization_id = o.id
                  AND s.ended_at IS NULL
                  AND s.status = 'active'
            WHERE s.id IS NULL
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $cnt = (int)($stmt->fetch()['cnt'] ?? 0);
        } catch (\Throwable $e) {
            $cnt = 0;
        }
        return [
            'count' => $cnt,
            'label' => 'Orgs without active plan',
            'tone'  => $cnt > 0 ? 'warning' : 'success',
        ];
    }
}
