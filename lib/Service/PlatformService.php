<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Service;

use OCP\IDBConnection;

class PlatformService {

    use SqlDialectTrait;

    /** Tags shown per alert card before the remainder collapses to "+N more". */
    private const OFFENDER_LIMIT = 5;

    /** Idle days after which a project counts as stale. */
    private const STALE_DAYS = 30;

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
                   ON s.organization_id = o.id
                  AND (s.ended_at IS NULL OR s.ended_at > NOW())
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
            WHERE s.status = 'active'
              AND (s.ended_at IS NULL OR s.ended_at > NOW())
            GROUP BY p.currency
            ORDER BY mrr DESC
            LIMIT 1
        ";
        $stmt = $this->db->prepare($mrrSql);
        $stmt->execute();
        $mrrRow = $stmt->fetch() ?: ['mrr' => 0, 'currency' => 'EUR', 'currency_count' => 0];

        // Members across platform, split by role
        $memSql = "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN role = 'admin'  THEN 1 ELSE 0 END) AS admins,
                SUM(CASE WHEN role = 'member' THEN 1 ELSE 0 END) AS members
            FROM *PREFIX*organization_members
        ";
        $stmt = $this->db->prepare($memSql);
        $stmt->execute();
        $memRow = $stmt->fetch() ?: ['total' => 0, 'admins' => 0, 'members' => 0];

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
                    ON s.board_id = {$this->castInt('cp.board_id')}
            INNER JOIN *PREFIX*deck_cards c
                    ON c.stack_id = s.id
                   AND c.deleted_at = 0
                   AND c.archived = false
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
                'total'   => (int)$memRow['total'],
                'admins'  => (int)$memRow['admins'],
                'members' => (int)$memRow['members'],
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
            'failedBackups7d'  => array_merge(
                $this->countFailedBackups7d(),
                $this->failedBackupOffenders()
            ),
            'stuckAhoJobs'     => array_merge(
                $this->countStuckAhoJobs(),
                $this->stuckAhoOffenders()
            ),
            'staleProjects30d' => array_merge(
                $this->countStaleProjects(self::STALE_DAYS),
                $this->staleProjectOffenders(self::STALE_DAYS)
            ),
            'orgsNoSub'        => array_merge(
                $this->countOrgsWithoutActiveSubscription(),
                $this->orgsNoSubOffenders()
            ),
        ];
    }

    private function countFailedBackups7d(): array {
        $sql = "
            SELECT COUNT(*) AS cnt
            FROM *PREFIX*org_backup_jobs
            WHERE status = 'failed'
              AND {$this->toEpoch('created_at')} >= {$this->nowEpoch()} - 7 * 86400
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
                   OR {$this->toEpoch('last_deck_move_at')} < {$this->nowEpoch()} - ? * 86400)
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
                  AND s.status = 'active'
                  AND (s.ended_at IS NULL OR s.ended_at > NOW())
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

    /**
     * Runs an offender roll-up and shapes it for the alert payload.
     *
     * The query must select lowercase `orgid`, `orgname` and `cnt` columns —
     * Postgres folds unquoted identifiers to lower case while MySQL preserves
     * them, so a camelCase alias would land under a different key per engine.
     * One row per organization, already ordered biggest-contributor-first.
     *
     * Deliberately unbounded: the roll-up returns at most one row per org, so
     * fetching all of them and slicing here yields an exact remainder from a
     * single round-trip, where a SQL LIMIT would need a second COUNT(DISTINCT)
     * just to learn how many rows were dropped.
     *
     * @param list<mixed> $params
     * @return array{offenders: list<array{orgId:int,orgName:string,count:int}>, offendersRemaining: int}
     */
    private function rollUpOffenders(string $sql, array $params = []): array {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            // Same degradation as the count queries above: a missing table or a
            // dialect surprise costs the tags, never the dashboard.
            return ['offenders' => [], 'offendersRemaining' => 0];
        }

        $all = [];
        foreach ($rows as $row) {
            $all[] = [
                'orgId'   => (int)$row['orgid'],
                'orgName' => (string)$row['orgname'],
                'count'   => (int)$row['cnt'],
            ];
        }

        $offenders = array_slice($all, 0, self::OFFENDER_LIMIT);
        return [
            'offenders'          => $offenders,
            'offendersRemaining' => max(0, count($all) - count($offenders)),
        ];
    }

    private function failedBackupOffenders(): array {
        return $this->rollUpOffenders("
            SELECT j.organization_id AS orgid, o.name AS orgname, COUNT(*) AS cnt
            FROM *PREFIX*org_backup_jobs j
            JOIN *PREFIX*organizations o ON o.id = j.organization_id
            WHERE j.status = 'failed'
              AND {$this->toEpoch('j.created_at')} >= {$this->nowEpoch()} - 7 * 86400
            GROUP BY j.organization_id, o.name
            ORDER BY cnt DESC, o.name ASC
        ");
    }

    private function stuckAhoOffenders(): array {
        return $this->rollUpOffenders("
            SELECT j.organization_id AS orgid, o.name AS orgname, COUNT(*) AS cnt
            FROM *PREFIX*org_aho_jobs j
            JOIN *PREFIX*organizations o ON o.id = j.organization_id
            WHERE j.status IN ('pending', 'failed')
            GROUP BY j.organization_id, o.name
            ORDER BY cnt DESC, o.name ASC
        ");
    }

    private function staleProjectOffenders(int $days): array {
        return $this->rollUpOffenders("
            SELECT p.organization_id AS orgid, o.name AS orgname, COUNT(*) AS cnt
            FROM *PREFIX*custom_projects p
            JOIN *PREFIX*organizations o ON o.id = p.organization_id
            WHERE p.archived_at IS NULL
              AND (p.last_deck_move_at IS NULL
                   OR {$this->toEpoch('p.last_deck_move_at')} < {$this->nowEpoch()} - ? * 86400)
            GROUP BY p.organization_id, o.name
            ORDER BY cnt DESC, o.name ASC
        ", [$days]);
    }

    /**
     * The degenerate case: the organizations *are* the offenders, one each, so
     * there is nothing to group and every count is 1.
     */
    private function orgsNoSubOffenders(): array {
        return $this->rollUpOffenders("
            SELECT o.id AS orgid, o.name AS orgname, 1 AS cnt
            FROM *PREFIX*organizations o
            LEFT JOIN *PREFIX*subscriptions s
                   ON s.organization_id = o.id
                  AND s.status = 'active'
                  AND (s.ended_at IS NULL OR s.ended_at > NOW())
            WHERE s.id IS NULL
            ORDER BY o.name ASC
        ");
    }
}
