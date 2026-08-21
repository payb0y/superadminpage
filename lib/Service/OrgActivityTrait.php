<?php

declare(strict_types=1);

namespace OCA\SuperAdminPage\Service;

/**
 * When each organization was last signed into.
 *
 * Shared because two surfaces have to agree on it: the Retention card counts
 * dormant organizations, and the roster filters to exactly those rows when the
 * count is clicked. A count and a filter derived from separate queries would
 * eventually disagree, which is the failure this trait exists to prevent — the
 * same reasoning as FinancialsService::USAGE_BANDS and PlatformService::STALE_DAYS.
 *
 * WHY THE AGGREGATION IS IN PHP
 * `oc_preferences.configvalue` is a text column holding an epoch, so taking a
 * MAX() of it means casting text to an integer in two dialects — and Postgres
 * errors outright on an empty string where MySQL quietly yields 0. Reading the
 * raw pairs and folding them here avoids the cast entirely. The result set is
 * one row per membership, which is small by construction.
 */
trait OrgActivityTrait {

    /**
     * Newest login epoch per organization.
     *
     * Organizations with no members, or whose members have never signed in, map
     * to null — deliberately distinct from 0, because "never used" and "used
     * long ago" are different states and the card reports them separately.
     *
     * @return array<int, int|null>
     */
    private function lastActivityByOrg(): array {
        $sql = "
            SELECT
                o.id           AS org_id,
                p.configvalue  AS last_login
            FROM *PREFIX*organizations o
            LEFT JOIN *PREFIX*organization_members m
                   ON m.organization_id = o.id
            LEFT JOIN *PREFIX*preferences p
                   ON p.userid = m.user_uid
                  AND p.appid = 'login'
                  AND p.configkey = 'lastLogin'
        ";

        $out = [];
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $orgId = (int)$row['org_id'];
                if (!array_key_exists($orgId, $out)) {
                    $out[$orgId] = null;
                }
                $raw = $row['last_login'];
                // Anything not a plain integer string is treated as "no login":
                // a blank or malformed preference must not read as 1970.
                if ($raw === null || !ctype_digit((string)$raw)) {
                    continue;
                }
                $epoch = (int)$raw;
                if ($epoch > 0 && ($out[$orgId] === null || $epoch > $out[$orgId])) {
                    $out[$orgId] = $epoch;
                }
            }
        } catch (\Throwable $e) {
            return $out;
        }
        return $out;
    }
}
