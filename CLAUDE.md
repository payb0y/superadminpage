# CLAUDE.md

Guidance for Claude Code when working in this repository.

## What This Is

`superadminpage` is a Nextcloud app that renders a SaaS-style **super-admin** analytics dashboard across **all organizations** on the platform. It is the cross-org sibling of `../adminpage` (single-org scoped). PHP backend (Nextcloud App Framework) + Vue 2.7 frontend bundled with webpack.

Authoritative design/architecture spec: `SUPER_ADMIN_HANDOFF.md` in this directory. Read it before non-trivial changes.

Visual reference app: `../adminpage` (copy components, strip org-scoping, add a cross-org aggregator or `orgId` selector).

## Build Commands

```bash
npm run build          # Production build (required after any src/ change)
npm run dev            # Development build
npm run watch          # Development build with file watching
```

Hard-refresh the browser with `Ctrl+Shift+R` after each build.

## Architecture

Two webpack entries expected: `main` (authenticated super-admin dashboard) and `public` (if/when public surface is needed).

```
src/main.js → mounts Dashboard.vue on #superadminpage-root
```

**Data flow:** Browser → `PageController` renders template → Vue fetches `GET /apps/superadminpage/api/super/data` → controller verifies caller is a Nextcloud admin → delegates to service classes that aggregate across `oc_organizations` → returns one JSON payload consumed as `data` prop by `Dashboard.vue`.

**No `OrgOverviewService::resolveOrgId($uid)` calls at the top level.** That helper returns a single org and is only used inside the per-org drill-down path (`GET /api/super/orgs/{orgId}`). Global KPIs iterate all orgs or `GROUP BY organization_id`.

### Suggested API surface

Mirror `adminpage`'s `appinfo/routes.php` style. All routes require admin.

| Route                          | Purpose                                                         |
| ------------------------------ | --------------------------------------------------------------- |
| `GET /api/super/data`          | Global KPIs (all orgs aggregated)                               |
| `GET /api/super/orgs`          | List of all orgs with summary stats                             |
| `GET /api/super/orgs/{orgId}`  | Per-org drill-down (reuse existing services with `orgId` param) |
| `GET /api/super/backups`       | Recent backup jobs across orgs                                  |
| `GET /api/super/aho`           | Recent account-hand-off jobs across orgs                        |
| `GET /api/super/subscriptions` | Subscription roster + history                                   |

## Critical Rules

- **Admin-gated, not `@NoAdminRequired`.** Super-admin endpoints must reject non-admins. Use `@NoCSRFRequired` + an explicit `IGroupManager::isAdmin($uid)` check in every controller action and return 403 otherwise.
- **Webpack entry keys stay literally `main` (and `public`).** `@nextcloud/webpack-vue-config` prefixes output with the app id → `js/superadminpage-main.js`. This must match the `Util::addScript('superadminpage', 'superadminpage-main')` call in the page controller. Renaming entry keys produces double-prefixed filenames and a blank page.
- **Vue 2.7 only** — no Vue 3, no Composition API (NC32 compatibility).
- **No external CSS frameworks.** Styles are scoped BEM-style per component; design tokens are declared once on the root dashboard component and inherited.
- **Unscoped `#app-content` override.** `Dashboard.vue` must ship an **unscoped** `<style>` block forcing `#app-content { background-color: #f0f1f5 !important; min-height: 100vh; }` so Nextcloud dark mode does not bleed through.
- **PHP namespace:** `OCA\SuperAdminPage` (PSR-4 from `lib/`). Mirror `OCA\AdminPage`.
- **Translation stub:** `t(app, text) => text` global Vue mixin (no full l10n).

## Design Tokens

All tokens (colors, spacing, radii, shadows, typography, layout primitives) are specified in `SUPER_ADMIN_HANDOFF.md` §1 and must match the values in `adminpage/src/components/Dashboard.vue` (CSS custom properties block, lines 165–200). Brand blue: `#4A90D9`. Card radius: `12px`. Shadow: `0 1px 3px rgba(0,0,0,0.08)`.

## Components to Copy From `../adminpage/src/components/`

KPI cards: `KpiCard.vue`, `ProjectsKpiCard.vue`, `TasksKpiCard.vue`, `ResourcesKpiCard.vue`, `TimelineKpiCard.vue`
Charts (Chart.js 4): `AreaChart.vue`, `DonutChart.vue`, `BarChart.vue`
Panels: `AlertCard.vue`, `AlertsPanel.vue`, `OrgOverviewPanel.vue`, `MembersPanel.vue`, `SubscriptionPanel.vue`, `BackupsPanel.vue` (filter-pill pattern).

## Database Conventions

- All tables use Nextcloud's `*PREFIX*` macro (resolves to `oc_`).
- `oc_custom_projects.board_id` is VARCHAR — cast with `CAST(cp.board_id AS UNSIGNED)` when joining to `oc_deck_boards.id` (INT).
- Done stack is identified by the hardcoded title `'Approved/Done'`.
- Soft-delete: always filter `deleted_at = 0` on boards and cards.
- A task is "done" when `c.done IS NOT NULL` OR the card is in the `'Approved/Done'` stack.
- For tables without `organization_id` (Deck, `oc_filecache`, `oc_group_folders`), join through `oc_custom_projects` which carries `organization_id`, `board_id`, `folder_id`, and `project_group_gid`.

See `SUPER_ADMIN_HANDOFF.md` §4 for the complete list of org-linked tables.

## Database Access (Dev Environment)

```bash
docker start nc_db                                                    # containers may be stopped
docker exec -it nc_db mariadb -uroot -prootpass                       # interactive
docker exec -i nc_db mariadb -uroot -prootpass -D nextcloud -e "..."  # one-shot
```

Database name is `nextcloud` (not `nc_db`). Compose file: `/home/payboy/src/nextcloud-docker-dev/docker-compose.yml`.

## When Changing Data Shape

If you change a service's return shape in PHP, update the corresponding Vue component props/usage in the same change. `Dashboard.vue` destructures a single `data` prop into sub-props for child components.

## Sibling Apps

- `../adminpage` — single-org analytics dashboard (visual + architectural reference).
- `../employee-dashboard` — per-employee task view; same visual language, same DB.
