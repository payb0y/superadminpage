<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Service;

use OCP\IDBConnection;

/**
 * Financial view across all organizations: contracted revenue, the per-org
 * month-by-month grid, upcoming renewals and the subscription audit trail.
 *
 * SCOPE — list prices, never payments. The platform has no invoice, payment or
 * transaction table, so every figure here is `oc_plans.price` attached to a row
 * of `oc_subscriptions`. It is what customers are contracted to pay, not what
 * they have paid, and the UI labels it that way.
 *
 * WHY THE SERIES IS REBUILT IN PHP, NOT SQL
 * Nothing snapshots revenue over time, so the monthly grid has to be
 * reconstructed from `started_at`/`ended_at` plus the before/after snapshots in
 * `oc_subscriptions_history`. Expressing "what was this subscription's state at
 * month end" in portable SQL means date arithmetic and window functions in two
 * dialects; doing it in PHP keeps the queries to plain SELECTs with JOINs, which
 * is the part that has repeatedly broken across MariaDB and PostgreSQL. The row
 * counts here are small (one subscription per organization, a handful of history
 * rows each), so the cost is nil.
 */
class FinancialsService {

    /** Recorded months shown before the current one. */
    private const MONTHS_BACK = 7;

    /** Committed months shown after the current one. */
    private const MONTHS_FORWARD = 6;

    /** Cap on audit-trail rows returned; the panel scrolls, it does not page. */
    private const MAX_EVENTS = 100;

    private IDBConnection $db;

    /** Plan ids referenced by history that no longer exist in oc_plans. */
    private array $missingPlanIds = [];

    public function __construct(IDBConnection $db) {
        $this->db = $db;
    }

    public function getFinancials(): array {
        $months = $this->buildMonths();
        $plans = $this->fetchPlans();
        $subs = $this->fetchSubscriptions();
        $history = $this->fetchHistory();

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $orgs = [];
        foreach ($subs as $sub) {
            $orgs[] = $this->buildOrgRow($sub, $months, $plans, $history[$sub['subId']] ?? [], $now);
        }

        // Biggest current contribution first, so the rows that matter are visible
        // before the grid is scrolled; zero rows collect at the bottom.
        usort($orgs, static function (array $a, array $b): int {
            return ($b['now'] <=> $a['now']) ?: strcasecmp($a['name'], $b['name']);
        });

        $totals = [];
        foreach ($months as $i => $_) {
            $sum = 0.0;
            foreach ($orgs as $org) {
                $sum += $org['series'][$i];
            }
            $totals[] = round($sum, 2);
        }

        $nowIdx = self::MONTHS_BACK;
        $mrr = $totals[$nowIdx] ?? 0.0;
        $prev = $totals[$nowIdx - 1] ?? 0.0;

        $renewals = $this->buildRenewals($orgs);
        $events = $this->buildEvents($history, $plans, $subs);

        // Whether every plan really is free is a fact about oc_plans, not about
        // whether anything happens to be billing right now — the UI must not
        // infer one from the other.
        $priced = 0;
        foreach ($plans as $plan) {
            if ($plan['price'] > 0) {
                $priced++;
            }
        }

        return [
            'months'    => $months,
            'nowIndex'  => $nowIdx,
            'orgs'      => $orgs,
            'totals'    => $totals,
            'summary'   => $this->buildSummary($orgs, $mrr, $prev, $plans, $renewals),
            'renewals'  => $renewals,
            'events'    => $events,
            'dataQuality' => [
                'planCount'      => count($plans),
                'pricedPlans'    => $priced,
                'allPlansFree'   => count($plans) > 0 && $priced === 0,
                'deletedPlans'   => count($this->missingPlanIds),
            ],
        ];
    }

    // ─── window ──────────────────────────────────────────────────────────

    /**
     * The 14-month window, current month at index MONTHS_BACK. Each entry
     * carries both bounds, because the three kinds of month are evaluated at
     * different instants — see buildOrgRow().
     */
    private function buildMonths(): array {
        $cursor = (new \DateTimeImmutable('first day of this month'))
            ->setTime(0, 0)
            ->modify('-' . self::MONTHS_BACK . ' months');

        $out = [];
        $total = self::MONTHS_BACK + 1 + self::MONTHS_FORWARD;
        for ($i = 0; $i < $total; $i++) {
            $end = $cursor->modify('last day of this month')->setTime(23, 59, 59);
            $out[] = [
                'key'      => $cursor->format('Y-m'),
                'label'    => $cursor->format('M'),
                'year'     => (int)$cursor->format('Y'),
                'startsAt' => $cursor->format('Y-m-d H:i:s'),
                'endsAt'   => $end->format('Y-m-d H:i:s'),
                'isFuture' => $i > self::MONTHS_BACK,
            ];
            $cursor = $cursor->modify('+1 month');
        }
        return $out;
    }

    // ─── raw reads ───────────────────────────────────────────────────────

    /** @return array<int, array{name: string, price: float, currency: string}> */
    private function fetchPlans(): array {
        $sql = "SELECT id, name, price, currency FROM *PREFIX*plans";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            // price is NUMERIC(10,2): PDO hands it back as a *string*, and an
            // un-cast string silently poisons every sum downstream.
            $out[(int)$row['id']] = [
                'name'     => (string)($row['name'] ?? ''),
                'price'    => (float)($row['price'] ?? 0),
                'currency' => (string)($row['currency'] ?? 'EUR'),
            ];
        }
        return $out;
    }

    /**
     * One row per organization. An organization with no subscription still
     * appears — it is exactly the case the roster needs to show.
     */
    private function fetchSubscriptions(): array {
        $sql = "
            SELECT
                o.id            AS org_id,
                o.name          AS org_name,
                s.id            AS sub_id,
                s.status        AS sub_status,
                s.plan_id       AS plan_id,
                s.started_at    AS started_at,
                s.ended_at      AS ended_at,
                p.name          AS plan_name,
                p.price         AS plan_price,
                p.currency      AS currency,
                p.max_members   AS max_members,
                p.max_projects  AS max_projects,
                (SELECT COUNT(*) FROM *PREFIX*organization_members m
                  WHERE m.organization_id = o.id) AS member_count,
                (SELECT COUNT(*) FROM *PREFIX*custom_projects cp
                  WHERE cp.organization_id = o.id) AS project_count
            FROM *PREFIX*organizations o
            LEFT JOIN *PREFIX*subscriptions s ON s.organization_id = o.id
            LEFT JOIN *PREFIX*plans p ON p.id = s.plan_id
            ORDER BY o.id ASC, s.started_at DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        // Postgres folds unquoted aliases to lower case and MySQL preserves
        // them, so every key is read lower-case.
        $seen = [];
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $orgId = (int)$row['org_id'];
            if (isset($seen[$orgId])) {
                continue;   // ORDER BY put the newest subscription first
            }
            $seen[$orgId] = true;

            $out[] = [
                'orgId'       => $orgId,
                'orgName'     => (string)($row['org_name'] ?? ''),
                'subId'       => $row['sub_id'] !== null ? (int)$row['sub_id'] : null,
                'status'      => (string)($row['sub_status'] ?? 'none'),
                'planId'      => $row['plan_id'] !== null ? (int)$row['plan_id'] : null,
                'planName'    => (string)($row['plan_name'] ?? 'No plan'),
                'price'       => (float)($row['plan_price'] ?? 0),
                'currency'    => (string)($row['currency'] ?? 'EUR'),
                'startedAt'   => $row['started_at'] ?? null,
                'endedAt'     => $row['ended_at'] ?? null,
                'maxMembers'  => (int)($row['max_members'] ?? 0),
                'maxProjects' => (int)($row['max_projects'] ?? 0),
                'members'     => (int)($row['member_count'] ?? 0),
                'projects'    => (int)($row['project_count'] ?? 0),
            ];
        }
        return $out;
    }

    /** History rows grouped by subscription id, oldest first. */
    private function fetchHistory(): array {
        $sql = "
            SELECT
                h.subscription_id      AS subscription_id,
                h.change_timestamp     AS change_timestamp,
                h.changed_by_user_id   AS changed_by_user_id,
                h.notes                AS notes,
                h.previous_plan_id     AS previous_plan_id,
                h.previous_status      AS previous_status,
                h.previous_started_at  AS previous_started_at,
                h.previous_ended_at    AS previous_ended_at,
                h.new_plan_id          AS new_plan_id,
                h.new_status           AS new_status,
                h.new_started_at       AS new_started_at,
                h.new_ended_at         AS new_ended_at
            FROM *PREFIX*subscriptions_history h
            ORDER BY h.subscription_id ASC, h.change_timestamp ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $subId = (int)$row['subscription_id'];
            $out[$subId][] = [
                'at'         => (string)$row['change_timestamp'],
                'by'         => (string)($row['changed_by_user_id'] ?? ''),
                'notes'      => (string)($row['notes'] ?? ''),
                'prevPlanId' => $row['previous_plan_id'] !== null ? (int)$row['previous_plan_id'] : null,
                'prevStatus' => $row['previous_status'] !== null ? (string)$row['previous_status'] : null,
                'prevStart'  => $row['previous_started_at'] ?? null,
                'prevEnd'    => $row['previous_ended_at'] ?? null,
                'newPlanId'  => $row['new_plan_id'] !== null ? (int)$row['new_plan_id'] : null,
                'newStatus'  => (string)($row['new_status'] ?? ''),
                'newStart'   => $row['new_started_at'] ?? null,
                'newEnd'     => $row['new_ended_at'] ?? null,
            ];
        }
        return $out;
    }

    // ─── reconstruction ──────────────────────────────────────────────────

    private function buildOrgRow(array $sub, array $months, array $plans, array $history, string $now): array {
        $nowIdx = self::MONTHS_BACK;
        $series = [];
        $renewIdx = -1;

        // Three kinds of month, evaluated at three different instants.
        //
        //   past    — a month-end snapshot: what was billing when the month closed.
        //   current — evaluated at NOW, not at month end. Month end is a FUTURE
        //             instant, and using it reported any subscription whose term
        //             expires later this month as already gone: headline MRR
        //             under-counted and the delta badge showed churn that had not
        //             happened, while the same subscription was still being listed
        //             under "what lands next".
        //   future  — evaluated at the month's START: a term running to the 20th
        //             is still paid for that whole month, so it counts. Sampling
        //             those at month end dropped the final month of every term and
        //             left the grid's renewal marker unreachable.
        foreach ($months as $i => $month) {
            if ($i < $nowIdx) {
                $series[] = $this->recordedAt($sub, $history, $plans, $month['endsAt']);
            } elseif ($i === $nowIdx) {
                $series[] = $this->recordedAt($sub, $history, $plans, $now);
            } else {
                $series[] = $this->committedAt($sub, $month['startsAt']);
            }
        }

        // The month the renewal decision lands, for the marker in the grid.
        if ($sub['status'] === 'active' && $sub['endedAt'] !== null) {
            $endKey = substr((string)$sub['endedAt'], 0, 7);
            foreach ($months as $i => $month) {
                if ($month['key'] === $endKey) {
                    $renewIdx = $i;
                    break;
                }
            }
        }

        return [
            'id'          => $sub['orgId'],
            'name'        => $sub['orgName'],
            'plan'        => $sub['subId'] === null ? 'No plan' : $sub['planName'],
            'price'       => $sub['price'],
            'currency'    => $sub['currency'],
            'status'      => $sub['subId'] === null ? 'none' : $sub['status'],
            'startedAt'   => $sub['startedAt'],
            'endedAt'     => $sub['endedAt'],
            'renewIndex'  => $renewIdx,
            'members'     => ['used' => $sub['members'], 'cap' => $sub['maxMembers']],
            'projects'    => ['used' => $sub['projects'], 'cap' => $sub['maxProjects']],
            'series'      => $series,
            'now'         => $series[$nowIdx] ?? 0.0,
        ];
    }

    /**
     * What this subscription was billing at the end of a past month.
     *
     * State is walked forward through the audit trail: before the first logged
     * change the subscription looked like that row's `previous_*` snapshot;
     * after each change it looks like that row's `new_*`. With no history at
     * all, the current row has always applied.
     */
    private function recordedAt(array $sub, array $history, array $plans, string $at): float {
        if ($sub['subId'] === null) {
            return 0.0;
        }

        $status = $sub['status'];
        $planId = $sub['planId'];
        $start  = $sub['startedAt'];
        $end    = $sub['endedAt'];

        $last = $history === [] ? null : $history[count($history) - 1];
        $afterLastChange = $last === null || strcmp((string)$last['at'], $at) <= 0;

        // `oc_subscriptions` is the source of truth for the CURRENT state and the
        // history table is best-effort audit — they really can diverge. This
        // database has a logged reactivation that the live row never kept, and
        // replaying history over it would report a subscription as active that
        // is actually expired. So history only reconstructs the period it
        // covers; past its last entry the live row wins.
        if ($history !== [] && !$afterLastChange) {
            $first = $history[0];
            // Before anything was logged, the oldest "previous" snapshot held.
            $status = $first['prevStatus'] ?? $status;
            $planId = $first['prevPlanId'] ?? $planId;
            $start  = $first['prevStart'] ?? $start;
            $end    = $first['prevEnd'] ?? $end;

            foreach ($history as $h) {
                if (strcmp($h['at'], $at) > 0) {
                    break;      // change happened after the instant we are asking about
                }
                $status = $h['newStatus'];
                $planId = $h['newPlanId'];
                $start  = $h['newStart'];
                $end    = $h['newEnd'];
            }
        }

        if ($status !== 'active') {
            return 0.0;
        }
        if ($start !== null && strcmp((string)$start, $at) > 0) {
            return 0.0;   // had not started yet
        }
        if ($end !== null && strcmp((string)$end, $at) < 0) {
            return 0.0;   // term had already ended
        }
        if ($planId === null) {
            return 0.0;
        }
        if (!isset($plans[$planId])) {
            // The plan this month was billed on has since been deleted — the
            // organization app removes a non-public custom plan once nothing
            // references it. Its price is gone from the database and cannot be
            // recovered, so this month reads as 0. Record that it happened so
            // the UI can say the history is incomplete instead of implying the
            // organization paid nothing.
            $this->missingPlanIds[$planId] = true;
            return 0.0;
        }
        return $plans[$planId]['price'];
    }

    /**
     * What this subscription is contracted to bill at the end of a future
     * month. Not a forecast: it is the current term projected forward, and it
     * stops at `ended_at` unless the subscription is renewed.
     */
    private function committedAt(array $sub, string $at): float {
        if ($sub['subId'] === null || $sub['status'] !== 'active') {
            return 0.0;
        }
        if ($sub['startedAt'] !== null && strcmp((string)$sub['startedAt'], $at) > 0) {
            return 0.0;
        }
        if ($sub['endedAt'] !== null && strcmp((string)$sub['endedAt'], $at) < 0) {
            return 0.0;
        }
        return $sub['price'];
    }

    // ─── derived views ───────────────────────────────────────────────────

    private function buildSummary(array $orgs, float $mrr, float $prev, array $plans, array $renewals): array {
        $active = 0;
        $paid = 0;
        foreach ($orgs as $org) {
            if ($org['status'] === 'active') {
                $active++;
                if ($org['price'] > 0) {
                    $paid++;
                }
            }
        }

        // Naive cross-currency addition would be wrong, so say when the estate
        // is not single-currency rather than quietly summing euros and pounds.
        // Every organization that has a plan counts here, not just the active
        // paid ones: the roster prints a price for cancelled and expired rows
        // too, so a second currency hiding on one of those still needs the
        // warning.
        $currencies = [];
        foreach ($orgs as $org) {
            if ($org['status'] !== 'none') {
                $currencies[$org['currency']] = true;
            }
        }
        if ($currencies === []) {
            foreach ($plans as $plan) {
                $currencies[$plan['currency']] = true;
            }
        }
        $currencyList = array_keys($currencies);

        // Summed over exactly the rows the renewals list shows, so the figure in
        // the strip and the list underneath it can never disagree. A renewal
        // falling outside the grid's window still counts — it is still revenue
        // that has to be re-won.
        $upForRenewal = 0.0;
        foreach ($renewals as $renewal) {
            $upForRenewal += $renewal['price'];
        }

        return [
            'mrr'           => round($mrr, 2),
            'mrrDelta'      => round($mrr - $prev, 2),
            'arr'           => round($mrr * 12, 2),
            'currency'      => $currencyList[0] ?? 'EUR',
            'mixedCurrency' => count($currencyList) > 1,
            'activeSubs'    => $active,
            'paidSubs'      => $paid,
            'totalOrgs'     => count($orgs),
            'upForRenewal'  => round($upForRenewal, 2),
        ];
    }

    private function buildRenewals(array $orgs): array {
        $out = [];
        foreach ($orgs as $org) {
            if ($org['status'] !== 'active' || $org['endedAt'] === null) {
                continue;
            }
            $out[] = [
                'orgId'    => $org['id'],
                'orgName'  => $org['name'],
                'plan'     => $org['plan'],
                'price'    => $org['price'],
                'currency' => $org['currency'],
                'at'       => $org['endedAt'],
            ];
        }
        usort($out, static fn (array $a, array $b): int => strcmp((string)$a['at'], (string)$b['at']));
        return $out;
    }

    /**
     * The audit trail as revenue movements. Each row's euro impact is the
     * difference between what it was billing before the change and after, so a
     * pause and a cancellation both read as the loss they are.
     */
    private function buildEvents(array $history, array $plans, array $subs): array {
        $orgBySub = [];
        $liveBySub = [];
        foreach ($subs as $sub) {
            if ($sub['subId'] !== null) {
                $orgBySub[$sub['subId']] = ['id' => $sub['orgId'], 'name' => $sub['orgName']];
                $liveBySub[$sub['subId']] = ['status' => $sub['status'], 'planId' => $sub['planId']];
            }
        }

        $priceOf = function (?int $planId, ?string $status) use ($plans): float {
            if ($planId === null || $status !== 'active') {
                return 0.0;
            }
            if (!isset($plans[$planId])) {
                $this->missingPlanIds[$planId] = true;
                return 0.0;
            }
            return $plans[$planId]['price'];
        };

        // A null plan id genuinely means "no plan"; an id that is set but absent
        // from oc_plans means the plan was deleted after the fact, which is a
        // different thing and must not be labelled as though the organization
        // had never had one.
        $planLabel = function (?int $planId) use ($plans): string {
            if ($planId === null) {
                return 'No plan';
            }
            if (!isset($plans[$planId])) {
                $this->missingPlanIds[$planId] = true;
                return 'Deleted plan';
            }
            return $plans[$planId]['name'];
        };

        $out = [];
        foreach ($history as $subId => $rows) {
            $org = $orgBySub[$subId] ?? null;
            if ($org === null) {
                continue;   // subscription no longer exists; nothing to point at
            }
            $lastIdx = count($rows) - 1;
            foreach ($rows as $idx => $h) {
                $before = $priceOf($h['prevPlanId'], $h['prevStatus']);
                $after  = $priceOf($h['newPlanId'], $h['newStatus']);

                // The newest logged change should describe the row that is
                // actually in oc_subscriptions. When it does not, something
                // rewrote the subscription without logging it, and the euro
                // figure on this line never materialised. Say so rather than
                // presenting it as revenue that landed.
                //
                // LIMITATION: only the newest entry per subscription can be
                // checked, because it is the only one with a known end state to
                // compare against. An older change that was also overwritten is
                // indistinguishable from one that applied and was later
                // superseded, so it is left unflagged — the badge's tooltip says
                // as much rather than implying every such row is caught.
                $unreflected = false;
                if ($idx === $lastIdx && isset($liveBySub[$subId])) {
                    $live = $liveBySub[$subId];
                    $unreflected = $live['status'] !== $h['newStatus']
                        || $live['planId'] !== $h['newPlanId'];
                }

                $planChanged = $h['prevPlanId'] !== $h['newPlanId'];
                $fromLabel = $planChanged
                    ? $planLabel($h['prevPlanId'])
                    : (string)($h['prevStatus'] ?? 'none');
                $toLabel = $planChanged
                    ? $planLabel($h['newPlanId'])
                    : $h['newStatus'];

                $out[] = [
                    'orgId'       => $org['id'],
                    'orgName'     => $org['name'],
                    'at'          => $h['at'],
                    'by'          => $h['by'],
                    'notes'       => $h['notes'],
                    'from'        => $fromLabel,
                    'to'          => $toLabel,
                    'amount'      => round($after - $before, 2),
                    'unreflected' => $unreflected,
                ];
            }
        }

        usort($out, static fn (array $a, array $b): int => strcmp((string)$b['at'], (string)$a['at']));
        return array_slice($out, 0, self::MAX_EVENTS);
    }
}
