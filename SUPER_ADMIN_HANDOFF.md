# SUPER ADMIN DASHBOARD — HANDOFF

A handoff for an agent building a **super admin dashboard** that mirrors the look/feel of `adminpage` but operates **across all organizations** (not scoped to one).

This is a sibling project. Treat the existing `adminpage` app at `/home/payboy/src/nextcloud-docker-dev/data/apps-extra/adminpage` as the visual + architectural reference, but do **not** assume tenant scoping — the super admin sees everything.

---

## 1. Visual Language (must match `adminpage`)

All values come from `src/components/Dashboard.vue` (CSS custom properties block, lines 165–200). Reuse them verbatim.

### 1.1 Colors

| Token                               | Value                 | Use                                                                         |
| ----------------------------------- | --------------------- | --------------------------------------------------------------------------- |
| `--bg-page`                         | `#f0f1f5`             | Page background (forced via unscoped style to override Nextcloud dark mode) |
| `--bg-card`                         | `#ffffff`             | Card surface                                                                |
| `--color-text-primary`              | `#1a1a2e`             | Headings, KPI values                                                        |
| `--color-text-secondary`            | `#6b7280`             | Card titles, labels                                                         |
| `--color-text-muted`                | `#9ca3af`             | Metric sub-labels                                                           |
| `--color-border`                    | `#e5e7eb`             | Card dividers, table rows                                                   |
| `--color-danger`                    | `#d94040`             | Error / destructive                                                         |
| `--color-warning`                   | `#b8860b`             | Warning text                                                                |
| `--color-success`                   | `#2e7d32`             | Success text                                                                |
| `--color-badge-danger-bg` / `text`  | `#fde8e8` / `#b91c1c` | Danger pill                                                                 |
| `--color-badge-warning-bg` / `text` | `#fef3cd` / `#92400e` | Warning pill                                                                |
| `--color-badge-success-bg` / `text` | `#d4edda` / `#166534` | Success pill                                                                |

**Accent / brand blue:** `#4A90D9` (default `iconColor` in `KpiCard.vue`). Icon backgrounds are derived as `rgba(r,g,b,0.1)` of the icon color (see `KpiCard.iconBgColor` computed).

**Empty-state icon tint:** background `#e8f0fe`, foreground `#4a90d9`.

### 1.2 Spacing scale

| Token           | Value  |
| --------------- | ------ |
| `--spacing-xs`  | `4px`  |
| `--spacing-sm`  | `8px`  |
| `--spacing-md`  | `16px` |
| `--spacing-lg`  | `24px` |
| `--spacing-xl`  | `32px` |
| `--spacing-2xl` | `40px` |

Page wrapper: `max-width: 1200px; margin: 0 auto; padding: var(--spacing-lg);`.

### 1.3 Radii

| Where                   | Value               |
| ----------------------- | ------------------- |
| Cards (`--radius-card`) | `12px`              |
| KPI icon tile           | `8px`               |
| Empty-state icon tile   | `20px`              |
| Pills/badges            | `999px` (full pill) |
| Buttons / inputs        | `6–8px`             |

### 1.4 Shadows

| Token                 | Value                        |
| --------------------- | ---------------------------- |
| `--shadow-card`       | `0 1px 3px rgba(0,0,0,0.08)` |
| `--shadow-card-hover` | `0 4px 12px rgba(0,0,0,0.1)` |

Hover transition: `transition: box-shadow 0.2s ease;`.

### 1.5 Typography

- Font stack: `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", Arial, sans-serif`
- KPI value: `22px / 700`
- KPI label: `11px / 400`, color `--color-text-muted`
- Card title: `13px / 600`, `text-transform: uppercase; letter-spacing: 0.4px;`
- Empty-state title: `22px / 700`
- Empty-state body: `14px`, color `--color-text-secondary`

### 1.6 Layout primitives

- KPI strip: `display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--spacing-md);`
  - `≤1200px` → 2 cols
  - `≤768px` → 1 col
- KPI card padding: `20px 24px`
- Metric divider: `border-left: 1px solid var(--color-border)`, first child has no border + no left padding

### 1.7 Required Nextcloud override

`Dashboard.vue` ships an **unscoped** style block forcing `#app-content` background to `#f0f1f5` so dark mode does not bleed through. Replicate this in the super-admin entry component or the page renders incorrectly under dark themes.

```css
/* unscoped — do not nest in `scoped` */
#app-content {
  background-color: #f0f1f5 !important;
  min-height: 100vh;
}
```

### 1.8 Reusable components to copy/extend

From `src/components/`:

- `KpiCard.vue`, `ProjectsKpiCard.vue`, `TasksKpiCard.vue`, `ResourcesKpiCard.vue`, `TimelineKpiCard.vue`
- `AreaChart.vue`, `DonutChart.vue`, `BarChart.vue` (Chart.js 4 wrappers)
- `AlertCard.vue`, `AlertsPanel.vue`
- `OrgOverviewPanel.vue`, `MembersPanel.vue`, `SubscriptionPanel.vue`
- `BackupsPanel.vue` (filter-pill UX is the established pattern for list filters)

---

## 2. Architectural Constraints (carry over)

- **Vue 2.7 only.** No Vue 3, no Composition API. NC32 compatibility.
- **No external CSS frameworks.** All styles scoped BEM-style per component; design tokens declared on the root dashboard component and inherited.
- **PHP namespace pattern:** `OCA\YourAppName` (PSR-4 from `lib/`). Mirror `OCA\AdminPage`.
- **Controller annotations:** `@NoCSRFRequired` on API routes. **Do NOT use** `@NoAdminRequired` on the super-admin endpoints — these MUST require admin. Use Nextcloud's group-admin check (`IGroupManager::isAdmin($uid)`) and reject otherwise.
- **Webpack entry rule:** entry key in `webpack.config.js` MUST stay literally `main` (and `public` for the public entry). `@nextcloud/webpack-vue-config` prefixes output with the app id → `js/<appid>-main.js`. Renaming entry keys produces double-prefixed filenames and blank pages.
- **Translation stub:** `t(app, text) => text` global mixin (no full l10n).
- **Build:** `npm run build` after every `src/` change, then hard-refresh `Ctrl+Shift+R`.

---

## 3. Database Access (dev environment)

The app reads via Nextcloud's `IDBConnection`. For ad-hoc inspection:

```bash
# Containers may be stopped — start MariaDB first
docker start nc_db

# Interactive shell
docker exec -it nc_db mariadb -uroot -prootpass

# One-shot query (database name is `nextcloud`, NOT `nc_db`)
docker exec -i nc_db mariadb -uroot -prootpass -D nextcloud -e "SHOW TABLES;"
```

Compose file lives at `/home/payboy/src/nextcloud-docker-dev/docker-compose.yml`.

### Table-name conventions

- All app tables use Nextcloud's `*PREFIX*` macro in PHP (resolves to `oc_`).
- `oc_custom_projects.board_id` is **VARCHAR**. When joining to `oc_deck_boards.id` (INT), `CAST(cp.board_id AS UNSIGNED)`.
- "Done" stack identified by hardcoded title `'Approved/Done'` (used across `KpiService`, `DeckService`, `OrgOverviewService`).
- Soft-delete: always filter `deleted_at = 0` on boards and cards.
- Task "done" = `c.done IS NOT NULL` OR card is in the `'Approved/Done'` stack.

---

## 4. Tables Linked to an Organization

These are the tables that have a **direct `organization_id` FK** or are reachable via a one-hop join through a project/user owned by the org. The super-admin view needs to be able to aggregate or list rows across ALL `organization_id` values (no scoping).

### 4.1 Organization core

| Table                     | Key columns                                  | Purpose                                                 |
| ------------------------- | -------------------------------------------- | ------------------------------------------------------- |
| `oc_organizations`        | `id` PK, `name`, `admin_uid`, contact\_\*    | Org master record. `admin_uid` = owner UID              |
| `oc_organization_members` | `organization_id` FK, `user_uid` UNI, `role` | Org membership (roles: `member`, `admin`, ...)          |
| `oc_users`                | `uid` PK, `displayname`, `organization_id`   | Nextcloud user. Has direct `organization_id` column too |

### 4.2 Plans & subscriptions

| Table                      | Key columns                                                                                                                                | Purpose                               |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------- |
| `oc_plans`                 | `id` PK, `name`, `max_projects`, `max_members`, `shared_storage_per_project`, `private_storage_per_user`, `price`, `currency`, `is_public` | Plan catalogue                        |
| `oc_subscriptions`         | `organization_id` FK, `plan_id` FK, `status`, `started_at`, `ended_at`, `paused_at`, `cancelled_at`                                        | Active/paused/cancelled subscriptions |
| `oc_subscriptions_history` | `subscription_id` FK, full before/after snapshot, `changed_by_user_id`, `change_timestamp`, `notes`                                        | Audit trail of subscription changes   |

### 4.3 Projects (org-owned)

| Table                        | Key columns                                                                                                                                                                        | Purpose                                             |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------- |
| `oc_custom_projects`         | `id`, `organization_id` FK, `owner_id`, `board_id` (VARCHAR → cast), `folder_id`, `project_group_gid`, `status`, `archived_at`, `last_deck_move_at`, full client/loc/cv\_\* fields | Project master record. ~30+ business-domain columns |
| `oc_proj_private_folders`    | `project_id` FK, `user_id`, `folder_id`                                                                                                                                            | Per-user private folder mapping inside a project    |
| `oc_project_timeline_items`  | `project_id` FK, `label`, `start_date`, `end_date`, `color`, `item_type`                                                                                                           | Gantt/timeline phases and items                     |
| `oc_project_notes`           | `project_id` FK, `user_id`, `title`, `content`, `visibility`                                                                                                                       | Project notes                                       |
| `oc_project_activity_events` | `project_id` FK, `actor_uid`, `event_type`, `payload_json`, `occurred_at`                                                                                                          | Activity log                                        |
| `oc_project_deck_done_sync`  | `project_id` FK, `card_id` FK, `managed_done`                                                                                                                                      | Tracks Deck cards moved to Approved/Done            |
| `oc_project_digest_cursors`  | `project_id` FK, `user_uid`, `last_event_id`, `last_sent_at`                                                                                                                       | Per-user digest read cursor                         |
| `oc_project_file_processing` | `project_id` FK, `organization_id` FK, `file_id`, `document_type_id`, `ocr_status`, `extracted_json`                                                                               | OCR / file pipeline                                 |
| `oc_project_ocr_doc_types`   | `organization_id` FK, `name`, `fields_json`, `is_active`                                                                                                                           | Per-org OCR templates                               |

### 4.4 Backups (per org)

| Table                      | Key columns                                                                                                                                                                    | Purpose                  |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------ |
| `oc_org_backup_jobs`       | `organization_id` FK, `requested_by_uid`, `status`, `backup_type` (`full`/etc), `trigger_source` (`manual`/...), `artifact_name`, `artifact_size`, timing fields, `expires_at` | Backup job header        |
| `oc_org_backup_steps`      | `job_id` FK, `step_key`, `status`, `attempt`, `retriable`, `result_json`, `error_message`, timing                                                                              | Per-step state           |
| `oc_org_backup_events`     | `job_id` FK, `sequence_no`, `step_key`, `level`, `message`, `payload_json`                                                                                                     | Streaming log entries    |
| `oc_org_backup_file_index` | `organization_id` FK, `project_id`, `file_id`, `path`, `etag`, `mtime`, `size`, `last_backup_job_id`                                                                           | Incremental backup index |

### 4.5 Account hand-off ("AHO" — user-to-user transfer)

| Table               | Key columns                                                                                                              | Purpose             |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------ | ------------------- |
| `oc_org_aho_jobs`   | `organization_id` FK, `source_user_uid`, `target_user_uid`, `status`, `dry_run`, `remap_deck_content`, `idempotency_key` | Hand-off job header |
| `oc_org_aho_steps`  | `job_id` FK, `step_key`, `status`, `attempt`, `retriable`, timing                                                        | Per-step state      |
| `oc_org_aho_events` | `job_id` FK, `sequence_no`, `step_key`, `level`, `message`, `payload_json`                                               | Log events          |

### 4.6 Public dashboard links

| Table                       | Key columns                                                                            | Purpose                                               |
| --------------------------- | -------------------------------------------------------------------------------------- | ----------------------------------------------------- |
| `oc_adminpage_public_links` | `org_id` FK, `token` UNI, `label`, `enabled`, `expires_at`, `created_by`, `created_at` | Tokens for the public-share view of the org dashboard |

### 4.7 Deck (joined via project board_id)

Not org-scoped at the schema level, but reached through `oc_custom_projects.board_id`:

- `oc_deck_boards` — `id`, `title`, `owner`, `color`, `archived`, `deleted_at`
- `oc_deck_stacks` — stack rows (look up `'Approved/Done'`)
- `oc_deck_cards` — card rows (`done` IS NOT NULL means done)
- `oc_deck_assigned_users`, `oc_deck_assigned_labels`, `oc_deck_labels`, `oc_deck_attachment`
- `oc_deck_board_acl`, `oc_deck_board_policy_*`, `oc_deck_card_policy*`, `oc_deck_role_*`
- `oc_private_card_notes` — `user_id`, `card_id`, `content` (per-user private notes on a card)

### 4.8 Storage (joined via project folder_id / user uid)

- `oc_group_folders` — `folder_id`, `mount_point`, `quota`, `root_id`, `storage_id` (project shared folders)
- `oc_group_folders_groups`, `_acl`, `_manage`, `_trash`, `_versions`
- `oc_filecache` — `fileid`, `storage`, `path`, `size`, `mtime` (use for storage usage rollups)
- `oc_storages` — `numeric_id`, `id`, `available`

---

## 5. Cross-Org Aggregation Patterns (super-admin specific)

The existing `OrgOverviewService::resolveOrgId($uid)` returns ONE org. The super admin should **never** call it. Instead, queries should:

1. **Iterate all orgs** with `SELECT id, name FROM *PREFIX*organizations ORDER BY name;`
2. **Group by `organization_id`** for KPIs (project count, member count, storage, MRR).
3. **For tables WITHOUT `organization_id`** (e.g., `oc_deck_*`, `oc_filecache`, `oc_group_folders`), join through `oc_custom_projects` (which has `organization_id`, `board_id`, `folder_id`, `project_group_gid`).

Suggested global KPIs:

- Total orgs / active subscriptions / paused / cancelled
- Total MRR (sum of `oc_plans.price` for active subscriptions, with currency normalization)
- Total projects / total active members / total storage used
- Backup job success rate across orgs (last 7/30 days)
- AHO job pipeline state (pending/failed)
- Stale projects (`last_deck_move_at` > N days, `archived_at IS NULL`) per org

Per-org drill-down should reuse the existing `KpiService` / `DeckService` / `OrgOverviewService` shape but accept an `orgId` parameter from the super-admin route instead of resolving from the session uid.

---

## 6. Suggested API Surface

Mirror the `adminpage` route style (`appinfo/routes.php`):

| Route                          | Purpose                                      |
| ------------------------------ | -------------------------------------------- |
| `GET /api/super/data`          | Global KPIs (all orgs aggregated)            |
| `GET /api/super/orgs`          | List of all orgs with summary stats          |
| `GET /api/super/orgs/{orgId}`  | Per-org drill-down (reuse existing services) |
| `GET /api/super/backups`       | Recent backup jobs across orgs               |
| `GET /api/super/aho`           | Recent hand-off jobs across orgs             |
| `GET /api/super/subscriptions` | Subscription roster + history                |

All routes must verify the caller is a Nextcloud admin (`IGroupManager::isAdmin($uid)`) before executing.

---

## 7. Sibling Apps (for context)

- `../adminpage` — single-org analytics dashboard (this app is the visual reference).
- `../employee-dashboard` — per-employee task view. Same visual language, same DB.

When in doubt, copy the `adminpage` component, then strip the org-scoping and add an `orgId` selector / global aggregator on top.
