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
- **No external CSS frameworks.** Chrome comes from the In Zicht theme's `.iz-*` primitives (see "Styling" below); what stays in a component is layout only.
- **The app follows Nextcloud light/dark.** `Dashboard.vue`'s unscoped block sets `#app-content { background: var(--image-background); }`. It used to force `background-color: #f0f1f5 !important` to stop dark mode "bleeding through" — that is exactly what broke dark mode, and it must not come back.
- **PHP namespace:** `OCA\SuperAdminPage` (PSR-4 from `lib/`). Mirror `OCA\AdminPage`.
- **Translation stub:** `t(app, text) => text` global Vue mixin (no full l10n).

## Styling — the In Zicht theme owns it

**Read `/home/payboy/src/inzicht-nextcloud-theme/USING-THE-THEME.md` before
touching any style.** It is the canonical guide, shared with `adminpage` and
`employee_dashboard`, and it is the file to edit when a rule changes. In short:
this app defines no look of its own — chrome comes from the theme's `.iz-*`
primitives, layout stays in the component, and the root carries `iz-app` to
receive the tokens.

Earlier docs here described a blue palette (`#4A90D9`) and per-component BEM
tokens. That is long gone.

### Specific to this app

- **Zero raw brand/status `rgba()` and zero hardcoded `font-size`** — this app
  got there first and is the reference. Keep it that way.
- **Class names built from data.** About 8 concatenation sites remain (activity
  icons, alert tones, handover status, task due-buckets) where a class is
  assembled as `'prefix--' + value`. A DOM sweep confirmed every class they
  currently emit is styled, so they are latent rather than broken — convert them
  to an explicit map with a neutral fallback when you touch them, as
  `badgeTone()` / `statusToneClass()` do, and don't add more.
- This app draws **no canvas charts** (`TimelineChart` is DOM and CSS), so
  `src/lib/izChart.js` is deliberately not vendored here.

## Components to Copy From `../adminpage/src/components/`

> **`adminpage` is now on the In Zicht theme too**, so its components are a good
> reference rather than a cautionary one — its KPI cards are on `.iz-kpi`, its
> dialogs on `.iz-modal`, its tables on `.iz-table-wrap`. Still check what you
> lift: a handful of documented local overrides remain (alert tints on footers,
> toolbar widths), and those are app-specific, not patterns to copy.

KPI cards: `ProjectsKpiCard.vue`, `TasksKpiCard.vue`, `ResourcesKpiCard.vue`, `TimelineKpiCard.vue` — all on `.iz-kpi`
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

**The app runs on PostgreSQL, in the `nc_pg` container.** Confirm before
trusting any of this: `docker exec master-nextcloud-1 grep -E "'dbtype'|'dbhost'" /var/www/html/config/config.php`.

```bash
docker start nc_pg master-nextcloud-1                                 # containers may be stopped
docker exec -it nc_pg psql -U nextcloud -d nextcloud                  # interactive
docker exec -i nc_pg psql -U nextcloud -d nextcloud -c "..."          # one-shot
```

A MariaDB container `nc_db` also exists and holds a **different, larger, stale
dataset** (11 orgs vs 2, 77 backup jobs vs 14). It is not what the app reads.
Querying it and believing the numbers has already produced one wrong spec —
if you want to know what the dashboard will show, query `nc_pg`.

Because prod and this dev instance are both Postgres, dialect bugs are not
theoretical: Postgres refuses comparisons MySQL silently coerces. Route
anything involving dates or casts through `SqlDialectTrait`, and alias result
columns in lowercase — Postgres folds unquoted identifiers, MySQL preserves
them, so `AS orgId` lands under a different key per engine.

Compose file: `/home/payboy/src/nextcloud-docker-dev/docker-compose.yml`.

### Reaching the app over HTTP

`nextcloud.local` only resolves when the `master-proxy-1` container is up. When
it is not, use the container directly — add its current IP to
`trusted_domains` first, or Nextcloud answers 400:

```bash
docker inspect master-nextcloud-1 --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}'
docker exec -u www-data master-nextcloud-1 php occ config:system:set trusted_domains 5 --value=<ip>
```

This instance has **no pretty-URL rewrite**: API paths need `index.php`, e.g.
`/index.php/apps/superadminpage/api/super/data`. Admin endpoints accept basic
auth (`-u admin:admin`), so `curl` works for checking payloads.

Two startup traps: `.env` sets `SQL=mysql` while the instance is installed
against pgsql, so `bootstrap.sh` waits for a MySQL that never appears, dies
before removing `/etc/apache2/conf-enabled/install.conf`, and every URL serves
the "Installing container" holding page while `occ` keeps working. Finish the
bootstrap by hand:

```bash
docker exec master-nextcloud-1 sh -c \
  'rm -f /etc/apache2/conf-enabled/install.conf /var/www/html/installing.html && pkill -USR1 apache2'
```

And the JS bundle is served with no cache-busting query, so a rebuild will not
show up until a cache-bypassing reload (`Ctrl+Shift+R`).

## When Changing Data Shape

If you change a service's return shape in PHP, update the corresponding Vue component props/usage in the same change. `Dashboard.vue` destructures a single `data` prop into sub-props for child components.

## Sibling Apps

- `../adminpage` — single-org analytics dashboard (architectural reference).
- `../employee_dashboard` — per-employee task view; same DB.

Both are **still on the old blue styling** — this app is the only one migrated to
the In Zicht theme so far. They are the intended next consumers of the `.iz-*`
layer, which is why it lives in the theme rather than here. Treat them as a
reference for structure and data flow, not for looks.
