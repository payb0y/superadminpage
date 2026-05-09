<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Service;

use OCP\IDBConnection;

/**
 * Read-only feed of member/operational activity for an org or project.
 *
 * Unions multiple existing source tables into one normalized row shape:
 *   [ ts, actor_uid, organization_id, project_id, source, action,
 *     target_type, target_id, summary, meta ]
 *
 * Each source is implemented as a private from*() method that returns at most
 * $limit rows ordered by ts DESC. The public list*() methods merge the per-source
 * results, sort, and slice — keeping each subquery bounded so the planner has an
 * easy job and the merge stays cheap.
 */
class ActivityService {

    public const SOURCES_ALL = [
        'deck', 'files', 'talk', 'calendar',
        'subscription', 'backup', 'aho',
        'member', 'project', 'share', 'auth',
    ];

    public const SOURCES_PROJECT_ANCHORED = ['deck', 'talk', 'files', 'project'];

    private IDBConnection $db;

    public function __construct(IDBConnection $db) {
        $this->db = $db;
    }

    /**
     * Org-level feed: every source, attributed to the given org.
     *
     * Page-based pagination over UNION-merged sources. Each source is asked for
     * its top page*size rows by ts DESC (correctness: the global top-(page*size)
     * is always a subset of the union of each source's top-(page*size)).
     *
     * $filters keys: fromTs (int), toTs (int), actor (string), q (string).
     * fromTs/toTs/actor push into per-source SQL; q filters post-merge against
     * summary + actor_uid (substring, case-insensitive).
     */
    public function listForOrg(int $orgId, int $page, int $size, array $sources = [], array $filters = []): array {
        $sources = $this->resolveSources($sources, self::SOURCES_ALL);
        $page = max(1, $page);
        $size = $this->clampLimit($size);
        $q = $filters['q'] ?? null;
        $perSource = $this->perSourceLimit($page, $size, $q);

        $rows = [];
        foreach ($sources as $src) {
            $method = 'from' . ucfirst($src);
            if (!method_exists($this, $method)) {
                continue;
            }
            try {
                $rows = array_merge($rows, $this->$method($orgId, null, null, $perSource, $filters));
            } catch (\Throwable $e) {
                // graceful: a missing source table on a given install shouldn't 500 the feed
            }
        }

        return $this->mergeAndPage($rows, $page, $size, $q);
    }

    /**
     * Project view, "In this project" stream — only sources with a real project anchor.
     */
    public function listForProject(int $orgId, int $projectId, int $page, int $size, array $sources = [], array $filters = []): array {
        $sources = $this->resolveSources($sources, self::SOURCES_PROJECT_ANCHORED);
        $sources = array_values(array_intersect($sources, self::SOURCES_PROJECT_ANCHORED));
        $page = max(1, $page);
        $size = $this->clampLimit($size);
        $q = $filters['q'] ?? null;
        $perSource = $this->perSourceLimit($page, $size, $q);

        $rows = [];
        foreach ($sources as $src) {
            $method = 'from' . ucfirst($src);
            if (!method_exists($this, $method)) {
                continue;
            }
            try {
                $rows = array_merge($rows, $this->$method($orgId, $projectId, null, $perSource, $filters));
            } catch (\Throwable $e) {
                // see comment in listForOrg
            }
        }

        return $this->mergeAndPage($rows, $page, $size, $q);
    }

    /**
     * Project view, "Org-wide" stream — events that affect the org but aren't project-anchored.
     */
    public function listOrgWideForProjectView(int $orgId, int $page, int $size, array $sources = [], array $filters = []): array {
        $orgWideOnly = array_values(array_diff(self::SOURCES_ALL, self::SOURCES_PROJECT_ANCHORED));
        $sources = $this->resolveSources($sources, $orgWideOnly);
        $sources = array_values(array_intersect($sources, $orgWideOnly));

        $page = max(1, $page);
        $size = $this->clampLimit($size);
        $q = $filters['q'] ?? null;
        $perSource = $this->perSourceLimit($page, $size, $q);

        $rows = [];
        foreach ($sources as $src) {
            $method = 'from' . ucfirst($src);
            if (!method_exists($this, $method)) {
                continue;
            }
            try {
                $rows = array_merge($rows, $this->$method($orgId, null, null, $perSource, $filters));
            } catch (\Throwable $e) {
                // see comment in listForOrg
            }
        }

        return $this->mergeAndPage($rows, $page, $size, $q);
    }

    // -----------------------------------------------------------------------
    // oc_activity-backed sources
    // -----------------------------------------------------------------------

    /**
     * Deck activity — cards/boards/comments. Project-resolvable via card → board.
     */
    private function fromDeck(int $orgId, ?int $projectId, ?int $sinceTs, int $limit, array $filters = []): array {
        // oc_activity.object_id for deck rows is a card id (when type='deck_card_*') or
        // a board id (when type='deck'). We try to resolve to a project via the card path,
        // falling back to board-id matching when the row is board-level.
        $extra = $this->buildFilterClauses($filters, 'a.timestamp', 'a.user');
        $sql = "
            SELECT
                a.activity_id, a.timestamp, a.user, a.affecteduser,
                a.type, a.subject, a.object_type, a.object_id,
                COALESCE(cp_card.id, cp_board.id) AS project_id
              FROM *PREFIX*activity a
              JOIN *PREFIX*organization_members om
                ON om.user_uid = a.user
               AND om.organization_id = :orgId
         LEFT JOIN *PREFIX*deck_cards     dc
                ON dc.id = a.object_id
         LEFT JOIN *PREFIX*deck_stacks    ds
                ON ds.id = dc.stack_id
         LEFT JOIN *PREFIX*custom_projects cp_card
                ON CAST(cp_card.board_id AS UNSIGNED) = ds.board_id
         LEFT JOIN *PREFIX*custom_projects cp_board
                ON CAST(cp_board.board_id AS UNSIGNED) = a.object_id
             WHERE a.app = 'deck'
               AND a.user <> ''
               " . ($sinceTs !== null ? " AND a.timestamp < :sinceTs " : '') . "
               " . ($projectId !== null ? " AND COALESCE(cp_card.id, cp_board.id) = :projectId " : '') . "
               " . $extra['sql'] . "
             ORDER BY a.timestamp DESC
             LIMIT " . (int)$limit;

        $params = [':orgId' => $orgId] + $extra['params'];
        if ($sinceTs !== null) { $params[':sinceTs'] = $sinceTs; }
        if ($projectId !== null) { $params[':projectId'] = $projectId; }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = $this->normalize([
                'ts'              => (int)$r['timestamp'],
                'actor_uid'       => $r['user'] ?: null,
                'organization_id' => $orgId,
                'project_id'      => $r['project_id'] !== null ? (int)$r['project_id'] : null,
                'source'          => 'deck',
                'action'          => 'deck.' . $r['type'] . ($r['subject'] ? '.' . $r['subject'] : ''),
                'target_type'     => $r['object_type'] ?: null,
                'target_id'       => $r['object_id'] !== null ? (string)$r['object_id'] : null,
                'summary'         => $this->humanize($r['type'], $r['subject'], $r['user']),
            ]);
        }
        return $out;
    }

    /**
     * Files activity — file_created/changed/deleted, public_links_upload, etc.
     * Project resolution via group folder path is expensive; v1 leaves project_id null
     * for files, so file activity only appears in the Org-wide stream of the project view.
     */
    private function fromFiles(int $orgId, ?int $projectId, ?int $sinceTs, int $limit, array $filters = []): array {
        if ($projectId !== null) {
            return [];
        }

        $extra = $this->buildFilterClauses($filters, 'a.timestamp', 'a.user');
        $sql = "
            SELECT
                a.activity_id, a.timestamp, a.user, a.affecteduser,
                a.type, a.subject, a.object_type, a.object_id, a.file
              FROM *PREFIX*activity a
              JOIN *PREFIX*organization_members om
                ON om.user_uid = a.user
               AND om.organization_id = :orgId
             WHERE a.app = 'files'
               AND a.user <> ''
               " . ($sinceTs !== null ? " AND a.timestamp < :sinceTs " : '') . "
               " . $extra['sql'] . "
             ORDER BY a.timestamp DESC
             LIMIT " . (int)$limit;

        $params = [':orgId' => $orgId] + $extra['params'];
        if ($sinceTs !== null) { $params[':sinceTs'] = $sinceTs; }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = $this->normalize([
                'ts'              => (int)$r['timestamp'],
                'actor_uid'       => $r['user'] ?: null,
                'organization_id' => $orgId,
                'project_id'      => null,
                'source'          => 'files',
                'action'          => 'files.' . $r['type'],
                'target_type'     => $r['object_type'] ?: 'file',
                'target_id'       => $r['object_id'] !== null ? (string)$r['object_id'] : null,
                'summary'         => $this->humanize($r['type'], $r['subject'], $r['user'], $r['file']),
            ]);
        }
        return $out;
    }

    /**
     * Talk activity — calls and chat. Project-resolvable when the room token matches
     * a project's talk_conversation_token.
     */
    private function fromTalk(int $orgId, ?int $projectId, ?int $sinceTs, int $limit, array $filters = []): array {
        $extra = $this->buildFilterClauses($filters, 'a.timestamp', 'a.user');
        $sql = "
            SELECT
                a.activity_id, a.timestamp, a.user, a.affecteduser,
                a.type, a.subject, a.object_type, a.object_id,
                tr.token, cp.id AS project_id
              FROM *PREFIX*activity a
              JOIN *PREFIX*organization_members om
                ON om.user_uid = a.user
               AND om.organization_id = :orgId
         LEFT JOIN *PREFIX*talk_rooms        tr
                ON tr.id = a.object_id
         LEFT JOIN *PREFIX*custom_projects   cp
                ON cp.talk_conversation_token = tr.token
             WHERE a.app = 'spreed'
               AND a.user <> ''
               " . ($sinceTs !== null ? " AND a.timestamp < :sinceTs " : '') . "
               " . ($projectId !== null ? " AND cp.id = :projectId " : '') . "
               " . $extra['sql'] . "
             ORDER BY a.timestamp DESC
             LIMIT " . (int)$limit;

        $params = [':orgId' => $orgId] + $extra['params'];
        if ($sinceTs !== null) { $params[':sinceTs'] = $sinceTs; }
        if ($projectId !== null) { $params[':projectId'] = $projectId; }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = $this->normalize([
                'ts'              => (int)$r['timestamp'],
                'actor_uid'       => $r['user'] ?: null,
                'organization_id' => $orgId,
                'project_id'      => $r['project_id'] !== null ? (int)$r['project_id'] : null,
                'source'          => 'talk',
                'action'          => 'talk.' . $r['type'],
                'target_type'     => $r['object_type'] ?: 'room',
                'target_id'       => $r['object_id'] !== null ? (string)$r['object_id'] : null,
                'summary'         => $this->humanize($r['type'], $r['subject'], $r['user']),
            ]);
        }
        return $out;
    }

    /**
     * Calendar/contacts (dav) activity — never project-anchored.
     */
    private function fromCalendar(int $orgId, ?int $projectId, ?int $sinceTs, int $limit, array $filters = []): array {
        if ($projectId !== null) {
            return [];
        }

        $extra = $this->buildFilterClauses($filters, 'a.timestamp', 'a.user');
        $sql = "
            SELECT a.activity_id, a.timestamp, a.user, a.type, a.subject, a.object_type, a.object_id
              FROM *PREFIX*activity a
              JOIN *PREFIX*organization_members om
                ON om.user_uid = a.user
               AND om.organization_id = :orgId
             WHERE a.app = 'dav'
               AND a.user <> ''
               " . ($sinceTs !== null ? " AND a.timestamp < :sinceTs " : '') . "
               " . $extra['sql'] . "
             ORDER BY a.timestamp DESC
             LIMIT " . (int)$limit;

        $params = [':orgId' => $orgId] + $extra['params'];
        if ($sinceTs !== null) { $params[':sinceTs'] = $sinceTs; }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = $this->normalize([
                'ts'              => (int)$r['timestamp'],
                'actor_uid'       => $r['user'] ?: null,
                'organization_id' => $orgId,
                'project_id'      => null,
                'source'          => 'calendar',
                'action'          => 'calendar.' . $r['type'],
                'target_type'     => $r['object_type'] ?: null,
                'target_id'       => $r['object_id'] !== null ? (string)$r['object_id'] : null,
                'summary'         => $this->humanize($r['type'], $r['subject'], $r['user']),
            ]);
        }
        return $out;
    }

    // -----------------------------------------------------------------------
    // Org-level event sources (dedicated tables)
    // -----------------------------------------------------------------------

    /**
     * Subscription history — plan changes, status transitions.
     */
    private function fromSubscription(int $orgId, ?int $projectId, ?int $sinceTs, int $limit, array $filters = []): array {
        if ($projectId !== null) { return []; }

        $extra = $this->buildFilterClauses($filters, 'UNIX_TIMESTAMP(h.change_timestamp)', 'h.changed_by_user_id');
        $sql = "
            SELECT h.id, h.subscription_id, h.changed_by_user_id,
                   h.change_timestamp, h.previous_status, h.new_status,
                   h.previous_plan_id, h.new_plan_id,
                   pp.name AS prev_plan_name, np.name AS new_plan_name
              FROM *PREFIX*subscriptions_history h
              JOIN *PREFIX*subscriptions s ON s.id = h.subscription_id
         LEFT JOIN *PREFIX*plans pp ON pp.id = h.previous_plan_id
         LEFT JOIN *PREFIX*plans np ON np.id = h.new_plan_id
             WHERE s.organization_id = :orgId
               " . ($sinceTs !== null ? " AND UNIX_TIMESTAMP(h.change_timestamp) < :sinceTs " : '') . "
               " . $extra['sql'] . "
             ORDER BY h.change_timestamp DESC
             LIMIT " . (int)$limit;

        $params = [':orgId' => $orgId] + $extra['params'];
        if ($sinceTs !== null) { $params[':sinceTs'] = $sinceTs; }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $action = $r['previous_status'] !== $r['new_status']
                ? 'subscription.status_changed'
                : ($r['previous_plan_id'] !== $r['new_plan_id'] ? 'subscription.plan_changed' : 'subscription.updated');

            $summary = $r['previous_status'] !== $r['new_status']
                ? "Subscription {$r['previous_status']} → {$r['new_status']}"
                : ($r['previous_plan_id'] !== $r['new_plan_id']
                    ? "Plan changed: " . ($r['prev_plan_name'] ?? '—') . ' → ' . ($r['new_plan_name'] ?? '—')
                    : 'Subscription updated');

            $out[] = $this->normalize([
                'ts'              => strtotime($r['change_timestamp']) ?: 0,
                'actor_uid'       => $r['changed_by_user_id'] ?: null,
                'organization_id' => $orgId,
                'project_id'      => null,
                'source'          => 'subscription',
                'action'          => $action,
                'target_type'     => 'subscription',
                'target_id'       => (string)$r['subscription_id'],
                'summary'         => $summary,
                'meta'            => [
                    'prev_status' => $r['previous_status'],
                    'new_status'  => $r['new_status'],
                    'prev_plan'   => $r['prev_plan_name'],
                    'new_plan'    => $r['new_plan_name'],
                ],
            ]);
        }
        return $out;
    }

    /**
     * Backup jobs — one row per status transition (created → started → finished/expired).
     * We surface a single row per job at the most recent meaningful timestamp.
     */
    private function fromBackup(int $orgId, ?int $projectId, ?int $sinceTs, int $limit, array $filters = []): array {
        if ($projectId !== null) { return []; }

        $tsExpr = 'UNIX_TIMESTAMP(COALESCE(finished_at, started_at, created_at))';
        $extra = $this->buildFilterClauses($filters, $tsExpr, 'requested_by_uid');
        $sql = "
            SELECT id, requested_by_uid, status, backup_type, trigger_source,
                   created_at, started_at, finished_at,
                   COALESCE(finished_at, started_at, created_at) AS effective_at
              FROM *PREFIX*org_backup_jobs
             WHERE organization_id = :orgId
               " . ($sinceTs !== null ? " AND $tsExpr < :sinceTs " : '') . "
               " . $extra['sql'] . "
             ORDER BY effective_at DESC
             LIMIT " . (int)$limit;

        $params = [':orgId' => $orgId] + $extra['params'];
        if ($sinceTs !== null) { $params[':sinceTs'] = $sinceTs; }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = $this->normalize([
                'ts'              => strtotime($r['effective_at']) ?: 0,
                'actor_uid'       => $r['requested_by_uid'] ?: null,
                'organization_id' => $orgId,
                'project_id'      => null,
                'source'          => 'backup',
                'action'          => 'backup.' . $r['status'],
                'target_type'     => 'backup_job',
                'target_id'       => (string)$r['id'],
                'summary'         => "Backup #{$r['id']} ({$r['backup_type']}, {$r['trigger_source']}) — {$r['status']}",
                'meta'            => [
                    'backup_type'    => $r['backup_type'],
                    'trigger_source' => $r['trigger_source'],
                ],
            ]);
        }
        return $out;
    }

    /**
     * Account hand-off jobs.
     */
    private function fromAho(int $orgId, ?int $projectId, ?int $sinceTs, int $limit, array $filters = []): array {
        if ($projectId !== null) { return []; }

        $tsExpr = 'UNIX_TIMESTAMP(COALESCE(finished_at, started_at, created_at))';
        $extra = $this->buildFilterClauses($filters, $tsExpr, 'requested_by_uid');
        $sql = "
            SELECT id, requested_by_uid, source_user_uid, target_user_uid, status,
                   created_at, started_at, finished_at,
                   COALESCE(finished_at, started_at, created_at) AS effective_at
              FROM *PREFIX*org_aho_jobs
             WHERE organization_id = :orgId
               " . ($sinceTs !== null ? " AND $tsExpr < :sinceTs " : '') . "
               " . $extra['sql'] . "
             ORDER BY effective_at DESC
             LIMIT " . (int)$limit;

        $params = [':orgId' => $orgId] + $extra['params'];
        if ($sinceTs !== null) { $params[':sinceTs'] = $sinceTs; }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = $this->normalize([
                'ts'              => strtotime($r['effective_at']) ?: 0,
                'actor_uid'       => $r['requested_by_uid'] ?: null,
                'organization_id' => $orgId,
                'project_id'      => null,
                'source'          => 'aho',
                'action'          => 'aho.' . $r['status'],
                'target_type'     => 'aho_job',
                'target_id'       => (string)$r['id'],
                'summary'         => "Account hand-off {$r['source_user_uid']} → {$r['target_user_uid']} — {$r['status']}",
                'meta'            => [
                    'source_user' => $r['source_user_uid'],
                    'target_user' => $r['target_user_uid'],
                ],
            ]);
        }
        return $out;
    }

    /**
     * Member joined (current rows in oc_organization_members.created_at).
     * Removals are not tracked (hard delete) — deferred to v2.
     */
    private function fromMember(int $orgId, ?int $projectId, ?int $sinceTs, int $limit, array $filters = []): array {
        if ($projectId !== null) { return []; }

        $extra = $this->buildFilterClauses($filters, 'UNIX_TIMESTAMP(created_at)', 'user_uid');
        $sql = "
            SELECT id, user_uid, role, created_at
              FROM *PREFIX*organization_members
             WHERE organization_id = :orgId
               " . ($sinceTs !== null ? " AND UNIX_TIMESTAMP(created_at) < :sinceTs " : '') . "
               " . $extra['sql'] . "
             ORDER BY created_at DESC
             LIMIT " . (int)$limit;

        $params = [':orgId' => $orgId] + $extra['params'];
        if ($sinceTs !== null) { $params[':sinceTs'] = $sinceTs; }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = $this->normalize([
                'ts'              => strtotime($r['created_at']) ?: 0,
                'actor_uid'       => $r['user_uid'],
                'organization_id' => $orgId,
                'project_id'      => null,
                'source'          => 'member',
                'action'          => 'member.joined',
                'target_type'     => 'user',
                'target_id'       => $r['user_uid'],
                'summary'         => "{$r['user_uid']} joined as {$r['role']}",
                'meta'            => ['role' => $r['role']],
            ]);
        }
        return $out;
    }

    /**
     * Project lifecycle: created / archived / deleted.
     * Each project can produce up to 3 rows (one per timestamp column).
     */
    private function fromProject(int $orgId, ?int $projectId, ?int $sinceTs, int $limit, array $filters = []): array {
        $where = "WHERE cp.organization_id = :orgId";
        $params = [':orgId' => $orgId];
        if ($projectId !== null) {
            $where .= " AND cp.id = :projectId";
            $params[':projectId'] = $projectId;
        }
        $actor = !empty($filters['actor']) ? (string)$filters['actor'] : null;
        if ($actor !== null) {
            $where .= " AND cp.owner_id = :actor";
            $params[':actor'] = $actor;
        }

        $sql = "
            SELECT cp.id, cp.name, cp.owner_id, cp.created_at, cp.archived_at, cp.last_deck_move_at
              FROM *PREFIX*custom_projects cp
              $where
             ORDER BY GREATEST(
                 COALESCE(UNIX_TIMESTAMP(cp.created_at), 0),
                 COALESCE(UNIX_TIMESTAMP(cp.archived_at), 0)
             ) DESC
             LIMIT " . (int)$limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $fromTs = !empty($filters['fromTs']) ? (int)$filters['fromTs'] : null;
        $toTs   = !empty($filters['toTs'])   ? (int)$filters['toTs']   : null;
        $tsAllowed = function (int $ts) use ($sinceTs, $fromTs, $toTs): bool {
            if ($sinceTs !== null && $ts >= $sinceTs) { return false; }
            if ($fromTs !== null && $ts < $fromTs)    { return false; }
            if ($toTs !== null && $ts > $toTs)        { return false; }
            return true;
        };

        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            if ($r['created_at']) {
                $ts = strtotime($r['created_at']) ?: 0;
                if ($tsAllowed($ts)) {
                    $out[] = $this->normalize([
                        'ts'              => $ts,
                        'actor_uid'       => $r['owner_id'] ?: null,
                        'organization_id' => $orgId,
                        'project_id'      => (int)$r['id'],
                        'source'          => 'project',
                        'action'          => 'project.created',
                        'target_type'     => 'project',
                        'target_id'       => (string)$r['id'],
                        'summary'         => "Project \"{$r['name']}\" created",
                    ]);
                }
            }
            if ($r['archived_at']) {
                $ts = strtotime($r['archived_at']) ?: 0;
                if ($tsAllowed($ts)) {
                    $out[] = $this->normalize([
                        'ts'              => $ts,
                        'actor_uid'       => $r['owner_id'] ?: null,
                        'organization_id' => $orgId,
                        'project_id'      => (int)$r['id'],
                        'source'          => 'project',
                        'action'          => 'project.archived',
                        'target_type'     => 'project',
                        'target_id'       => (string)$r['id'],
                        'summary'         => "Project \"{$r['name']}\" archived",
                    ]);
                }
            }
        }
        return $out;
    }

    /**
     * Public link shares created by org members. share_type=3 is a public link.
     */
    private function fromShare(int $orgId, ?int $projectId, ?int $sinceTs, int $limit, array $filters = []): array {
        if ($projectId !== null) { return []; }

        $extra = $this->buildFilterClauses($filters, 's.stime', 's.uid_initiator');
        $sql = "
            SELECT s.id, s.uid_initiator, s.share_type, s.stime, s.expiration, s.token, s.item_type, s.file_target
              FROM *PREFIX*share s
              JOIN *PREFIX*organization_members om
                ON om.user_uid = s.uid_initiator
               AND om.organization_id = :orgId
             WHERE s.share_type = 3
               " . ($sinceTs !== null ? " AND s.stime < :sinceTs " : '') . "
               " . $extra['sql'] . "
             ORDER BY s.stime DESC
             LIMIT " . (int)$limit;

        $params = [':orgId' => $orgId] + $extra['params'];
        if ($sinceTs !== null) { $params[':sinceTs'] = $sinceTs; }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = $this->normalize([
                'ts'              => (int)$r['stime'],
                'actor_uid'       => $r['uid_initiator'] ?: null,
                'organization_id' => $orgId,
                'project_id'      => null,
                'source'          => 'share',
                'action'          => 'share.public_link_created',
                'target_type'     => $r['item_type'] ?: 'file',
                'target_id'       => (string)$r['id'],
                'summary'         => "Public link created for " . ($r['file_target'] ?: 'file'),
                'meta'            => [
                    'has_expiry' => $r['expiration'] !== null,
                ],
            ]);
        }
        return $out;
    }

    /**
     * Auth: most-recent session activity per user, scoped to org members.
     * Lossy by design — `oc_authtoken` only retains active tokens.
     */
    private function fromAuth(int $orgId, ?int $projectId, ?int $sinceTs, int $limit, array $filters = []): array {
        if ($projectId !== null) { return []; }

        $extra = $this->buildFilterClauses($filters, 't.last_activity', 't.uid');
        $sql = "
            SELECT t.uid, t.name, t.last_activity
              FROM *PREFIX*authtoken t
              JOIN *PREFIX*organization_members om
                ON om.user_uid = t.uid
               AND om.organization_id = :orgId
             WHERE t.last_activity > 0
               " . ($sinceTs !== null ? " AND t.last_activity < :sinceTs " : '') . "
               " . $extra['sql'] . "
             ORDER BY t.last_activity DESC
             LIMIT " . (int)$limit;

        $params = [':orgId' => $orgId] + $extra['params'];
        if ($sinceTs !== null) { $params[':sinceTs'] = $sinceTs; }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = $this->normalize([
                'ts'              => (int)$r['last_activity'],
                'actor_uid'       => $r['uid'],
                'organization_id' => $orgId,
                'project_id'      => null,
                'source'          => 'auth',
                'action'          => 'auth.session_active',
                'target_type'     => 'session',
                'target_id'       => null,
                'summary'         => "{$r['uid']} active in session " . ($r['name'] ?: ''),
            ]);
        }
        return $out;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function resolveSources(array $requested, array $defaults): array {
        if (empty($requested)) {
            return $defaults;
        }
        return array_values(array_intersect($requested, self::SOURCES_ALL));
    }

    /**
     * Build the SQL fragment + bind params for the date-range and actor filters.
     * The `q` (free-text) filter is applied post-merge, not here.
     *
     * @return array{sql: string, params: array<string, mixed>}
     */
    private function buildFilterClauses(array $filters, string $tsExpr, string $actorCol): array {
        $sql = '';
        $params = [];
        if (!empty($filters['fromTs'])) {
            $sql   .= " AND $tsExpr >= :flt_from ";
            $params[':flt_from'] = (int)$filters['fromTs'];
        }
        if (!empty($filters['toTs'])) {
            $sql   .= " AND $tsExpr <= :flt_to ";
            $params[':flt_to'] = (int)$filters['toTs'];
        }
        if (!empty($filters['actor'])) {
            $sql   .= " AND $actorCol = :flt_actor ";
            $params[':flt_actor'] = (string)$filters['actor'];
        }
        return ['sql' => $sql, 'params' => $params];
    }

    private function clampLimit(int $limit): int {
        return max(1, min($limit, 200));
    }

    private function normalize(array $row): array {
        return array_merge([
            'ts'              => 0,
            'actor_uid'       => null,
            'organization_id' => 0,
            'project_id'      => null,
            'source'          => '',
            'action'          => '',
            'target_type'     => null,
            'target_id'       => null,
            'summary'         => '',
            'meta'            => null,
        ], $row);
    }

    /**
     * Per-source LIMIT for a given (page, size). The +1 acts as a probe so
     * mergeAndPage can detect hasNext when a single source has more rows than
     * the current page boundary. Over-fetches when q is set because the search
     * filter is applied post-merge — without over-fetching, deep search pages
     * would be sparse.
     */
    private function perSourceLimit(int $page, int $size, ?string $q): int {
        $base = $page * $size + 1;
        return ($q !== null && $q !== '') ? $base * 3 : $base;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{rows: array<int, array<string, mixed>>, page: int, size: int, hasNext: bool}
     */
    private function mergeAndPage(array $rows, int $page, int $size, ?string $q = null): array {
        usort($rows, static fn ($a, $b) => $b['ts'] <=> $a['ts']);
        if ($q !== null && $q !== '') {
            $needle = mb_strtolower($q);
            $rows = array_values(array_filter($rows, function ($r) use ($needle) {
                $hay = mb_strtolower((string)$r['summary']);
                if ($hay !== '' && mb_strpos($hay, $needle) !== false) { return true; }
                $actor = $r['actor_uid'] ?? null;
                if ($actor !== null && mb_strpos(mb_strtolower((string)$actor), $needle) !== false) { return true; }
                return false;
            }));
        }
        $offset = ($page - 1) * $size;
        $pageRows = array_slice($rows, $offset, $size);
        $hasNext = count($rows) > $offset + $size;
        return ['rows' => $pageRows, 'page' => $page, 'size' => $size, 'hasNext' => $hasNext];
    }

    private function humanize(string $type, string $subject, ?string $user, ?string $file = null): string {
        $actor = $user ?: 'System';
        $file = $file ? " on {$file}" : '';
        // Subject strings come from NC apps and aren't 1:1 actions, but they're
        // good enough as a free-form fragment — we prefix with the actor and
        // join with the activity type for context.
        $sub = $subject ? " ({$subject})" : '';
        return "{$actor}: {$type}{$sub}{$file}";
    }
}
