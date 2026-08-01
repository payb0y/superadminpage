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

**This app does not define its own look.** Colours, radii, shadows, type scale and
component chrome come from the **In Zicht Nextcloud theme**
(`github.com/payb0y/inzicht-nextcloud-theme`, section 8 of
`themes/inzicht/core/css/server.css`) as a set of opt-in `.iz-*` classes and
`--iz-*` tokens.

Earlier docs described a blue palette (`#4A90D9`) and per-component BEM tokens.
That is gone — the brand is the In Zicht pink/navy, and the values live in the
theme, not here.

### Why the CSS lives in the theme

Nextcloud appends `themes/<active>/core/css/server.css` on every page **with a
`?v=` cache-buster**. This app's webpack bundle gets no such buster. So a styling
fix shipped in the theme reaches users with `git pull` + deploy — no rebuild, no
stale-bundle debugging — and the same fix lands in `adminpage` and
`employee_dashboard` at the same time.

### The rule

- **Chrome → primitive. Layout → local.** Surface, border, radius, shadow, type
  scale, hover and focus come from `.iz-*`. Grid tracks, column widths and gaps
  between regions stay in the component.
- **Never hardcode a colour or a font size.** Use an `--iz-*` token. The app has
  zero raw brand/status `rgba()` and zero hardcoded `font-size` — keep it that way.
- **Never put a layout property in a shared primitive.** `flex-grow` on
  `.iz-meter` meant "fill the width" in a row and "stretch the height" in a
  column; it shipped a collapsed bar in one place and a fat circle in another.
  Chrome generalises, layout does not.
- **Badges pair a tint background with a solid text colour.** Every status and
  categorical colour has both (`--iz-success` / `--iz-success-bg` /
  `--iz-success-text`; `--iz-cat-5` / `--iz-cat-5-bg` / `--iz-cat-5-text`). Using
  one token for both renders invisible text — shipped twice already.
- **Don't build a class name from data.** `'prefix--' + row.status` silently emits
  a class that may not exist. Map through an explicit table with a neutral
  fallback, as `badgeTone()` / `statusToneClass()` do. About 8 concatenation sites
  remain (activity icons, alert tones, handover status, task due-buckets); a DOM
  sweep confirmed every class they currently emit is styled, so they are latent
  rather than broken — convert them when you touch them, and don't add more.
- **Anything built on `<button>` or `<input>` needs a qualified selector.** NC core
  styles bare elements at specificity 0,1,1 and beats a plain class. Primitives use
  `.iz-app .iz-btn, button.iz-btn`. If you qualify a base, **qualify its modifiers
  too** — otherwise the base outranks them.

### What's available

Layout `.iz-stack` `.iz-panel` `.iz-stat-grid` · Surfaces `.iz-card` (+`--flat`)
`.iz-inset` · Lists `.iz-row` / `.iz-row--card` / `--expandable` (+`__header`
`__actions` `__chevron` `__detail`) `.iz-table` · Controls `.iz-btn` `.iz-input`
`.iz-select` `.iz-segment` `.iz-close` `.iz-tabs`/`.iz-tab` `.iz-pagination` ·
Status `.iz-pill` `.iz-badge` `.iz-chip` `.iz-dot` `.iz-mark` `.iz-meter`
`.iz-state` · Composites `.iz-modal` `.iz-user-picker` `.iz-reveal` `.iz-identity`
`.iz-spinner` `.iz-empty` `.iz-error`.

Read section 8 of `server.css` before adding a class — the comments there record
why each exists.

### Expandable rows have one design

Chevron **on the right**, last element in the row, chevron-down rotating 180°,
muted → accent on hover and while open. The summary tints on hover and the card
border firms up; expandable rows deliberately do **not** take the −4px lift (that
reads as "navigates away"; these open in place). Members, Projects and the org
list all follow this — don't invent a fourth.

### Changing a shared style

1. Edit `server.css` in the theme repo.
2. Sync into the dev instance:
   `cp …/inzicht-nextcloud-theme/themes/inzicht/core/css/server.css …/nextcloud-docker-dev/workspace/server/themes/inzicht/core/css/server.css`
3. Verify in a browser in **light and dark** and at **two viewport widths** — the
   meter bug was invisible at the one width that got tested.
4. Bust the cache: Playwright CDP `Network.clearBrowserCache` (the app bundle has
   no `?v=`), or a hard refresh.
5. Deploy the **theme before or with the app** — the bundle emits `.iz-*` classes
   that only exist in the new `server.css`.

## Components to Copy From `../adminpage/src/components/`

> **Copy the structure, not the styles.** `adminpage` has **not** been migrated to
> the In Zicht theme yet — it still carries the old blue palette and its own
> per-component tokens. Anything lifted from there must have its chrome replaced
> with `.iz-*` before it lands here.

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

- `../adminpage` — single-org analytics dashboard (architectural reference).
- `../employee_dashboard` — per-employee task view; same DB.

Both are **still on the old blue styling** — this app is the only one migrated to
the In Zicht theme so far. They are the intended next consumers of the `.iz-*`
layer, which is why it lives in the theme rather than here. Treat them as a
reference for structure and data flow, not for looks.
