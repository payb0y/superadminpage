<template>
  <div class="superadmin-dashboard iz-app">
    <div v-if="loading" class="superadmin-dashboard__loading">
      <div class="superadmin-dashboard__spinner"></div>
      <p>Loading super-admin dashboard…</p>
    </div>

    <div v-else-if="error" class="superadmin-dashboard__error">
      <p class="superadmin-dashboard__error-msg">{{ error }}</p>
      <button
        type="button"
        class="iz-btn iz-btn--accent"
        @click="retry"
      >Try again</button>
    </div>

    <template v-else>
      <header class="superadmin-dashboard__page-header">
        <h1 class="superadmin-dashboard__page-title">Super Admin</h1>
        <p class="superadmin-dashboard__page-sub">
          Platform-wide overview across all organizations
        </p>
      </header>

      <div class="superadmin-dashboard__tabs" role="tablist">
        <button
          type="button"
          role="tab"
          class="superadmin-dashboard__tab"
          :class="{
            'superadmin-dashboard__tab--active': activeTab === 'health',
          }"
          :aria-selected="activeTab === 'health'"
          @click="setActiveTab('health')"
        >System Health</button>
        <button
          type="button"
          role="tab"
          class="superadmin-dashboard__tab"
          :class="{
            'superadmin-dashboard__tab--active': activeTab === 'orgs',
          }"
          :aria-selected="activeTab === 'orgs'"
          @click="setActiveTab('orgs')"
        >Organizations</button>
      </div>

      <template v-if="activeTab === 'health'">
        <AlertsPanel v-if="platform" :alerts="platform.alerts" />
        <SystemHealthPanel />
      </template>

      <template v-else-if="activeTab === 'orgs'">
        <PlatformKpiStrip
          v-if="platform"
          :kpis="platform.kpis"
          @drill-down="onDrillDown"
        />
        <OrgListPanel
          ref="orgList"
          :orgs="orgs"
          @list-stale="refreshOrgs"
        />
      </template>
    </template>
  </div>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";
import OrgListPanel from "./OrgListPanel.vue";
import PlatformKpiStrip from "./PlatformKpiStrip.vue";
import AlertsPanel from "./AlertsPanel.vue";
import SystemHealthPanel from "./SystemHealthPanel.vue";

export default {
  name: "Dashboard",
  components: {
    OrgListPanel,
    PlatformKpiStrip,
    AlertsPanel,
    SystemHealthPanel,
  },
  data() {
    return {
      orgs: [],
      platform: null,
      loading: true,
      error: null,
      activeTab: "health",
    };
  },
  mounted() {
    this.hydrateActiveTab();
    this.fetchAll();
  },
  watch: {
    activeTab(val) {
      try {
        window.localStorage.setItem("superadminpage:activeTab", val);
      } catch (e) {
        // localStorage may be unavailable (private mode, security policy);
        // tab choice just won't persist across reloads in that case.
      }
    },
  },
  methods: {
    hydrateActiveTab() {
      try {
        const stored = window.localStorage.getItem(
          "superadminpage:activeTab",
        );
        if (stored === "health" || stored === "orgs") {
          this.activeTab = stored;
        }
      } catch (e) {
        // ignore — defaults to 'health'
      }
    },
    setActiveTab(tab) {
      if (tab !== "health" && tab !== "orgs") return;
      this.activeTab = tab;
    },
    onDrillDown(payload) {
      // Drill-down originates from the KPI strip which lives in the Orgs
      // tab — but if a future trigger fires it while we're on Health,
      // switch over first so the org list is mounted before we scroll.
      if (this.activeTab !== "orgs") {
        this.activeTab = "orgs";
      }
      this.$nextTick(() => {
        if (!this.$refs.orgList) return;
        this.$refs.orgList.applyDrillDown(payload);
        const el = this.$refs.orgList.$el;
        if (el && typeof el.scrollIntoView === "function") {
          el.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      });
    },
    async fetchAll() {
      try {
        const [platformRes, orgsRes] = await Promise.all([
          axios.get(generateUrl("/apps/superadminpage/api/super/data")),
          axios.get(generateUrl("/apps/superadminpage/api/super/orgs")),
        ]);
        this.platform = platformRes.data || null;
        this.orgs = (orgsRes.data && orgsRes.data.orgs) || [];
      } catch (e) {
        console.error("Failed to load dashboard", e);
        this.error = this.friendlyError(e);
      } finally {
        this.loading = false;
      }
    },
    retry() {
      this.error = null;
      this.loading = true;
      this.fetchAll();
    },
    // Turn an axios error into a message a human can act on, never the raw
    // "Request failed with status code 500". Prefers a message the server
    // deliberately sent (controller error boundary), then falls back to
    // status-based copy.
    friendlyError(e) {
      const status = e && e.response && e.response.status;
      const serverMsg =
        e && e.response && e.response.data && e.response.data.message;
      if (status === 403) {
        return "You don't have permission to view this dashboard — super-admin access is required.";
      }
      if (serverMsg) {
        return serverMsg;
      }
      if (status && status >= 500) {
        return "The server ran into a problem loading the dashboard. Please try again in a moment.";
      }
      if (e && e.request && !e.response) {
        return "Couldn't reach the server. Check your connection and try again.";
      }
      return "Something went wrong loading the dashboard. Please try again.";
    },
    async refreshOrgs() {
      // Triggered by OrgListPanel after any child reload (member add/
      // remove, project member change, subscription edit). Refetches just
      // the orgs list so row summary cells stay in sync with the now-
      // refreshed detail.
      try {
        const res = await axios.get(
          generateUrl("/apps/superadminpage/api/super/orgs"),
        );
        this.orgs = (res.data && res.data.orgs) || this.orgs;
      } catch (e) {
        // Silent — the detail panel is the authoritative view; the row
        // summary will catch up on the next user-triggered refresh.
        console.warn("Failed to refresh orgs list", e);
      }
    },
  },
};
</script>

<style>
/* unscoped — page backdrop follows the In Zicht theme (light + dark) */
#app-content:has(.superadmin-dashboard) {
  background: var(--image-background);
}

/* ---- Cross-cutting In Zicht behaviours (unscoped so they reach every child
   component's elements regardless of scoped data-v attributes) ---- */

/* Space Grotesk on titles + big value numerals across all panels */
.superadmin-dashboard [class*="__title"],
.superadmin-dashboard [class*="__metric-value"],
.superadmin-dashboard [class*="__value"],
.superadmin-dashboard [class*="__count"],
.superadmin-dashboard [class*="__figure"],
.superadmin-dashboard [class*="__number"],
.superadmin-dashboard [class*="__amount"],
.superadmin-dashboard [class*="__headline"],
.superadmin-dashboard [class*="__kpi-stat-value"] {
  font-family: 'Space Grotesk', system-ui, -apple-system, sans-serif;
}

/* Buttons — In Zicht motif: smooth transition + pink focus-visible ring on all,
   hover lift on primary/create actions. Colors already themed via tokens. */
.superadmin-dashboard [class*="btn"] {
  transition: background-color 0.2s ease, border-color 0.2s ease,
    box-shadow 0.2s ease, transform 0.2s ease;
}
.superadmin-dashboard [class*="btn"]:focus-visible {
  outline: none;
  box-shadow: 0 0 0 2px var(--bg-card), 0 0 0 4px var(--accent);
}
.superadmin-dashboard [class*="btn--primary"]:hover:not(:disabled),
.superadmin-dashboard [class*="__create-btn"]:hover:not(:disabled),
.superadmin-dashboard [class*="__add-btn"]:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: var(--iz-shadow-accent);
}

/* Native form controls follow the In Zicht accent (checkboxes, radios, etc.) */
.superadmin-dashboard input[type="checkbox"],
.superadmin-dashboard input[type="radio"],
.superadmin-dashboard input[type="range"],
.superadmin-dashboard progress {
  accent-color: var(--accent);
}
</style>

<style scoped>
.superadmin-dashboard {
  /* ---- In Zicht wiring: tokens consume the theme's NC variables ---- */
  /* surfaces */
  /* ── App tokens are now ALIASES of the In Zicht theme's .iz-* primitives ──
     The real definitions live in the theme (server.css §8), which is shared
     with the other In Zicht apps and — unlike this bundle — gets Nextcloud's
     ?v= cache-buster. These aliases exist so components not yet converted to
     .iz-* classes still resolve to the same values; as a component is
     converted, its uses of these names go away. Do not re-add a literal value
     here — change it in the theme. */

  /* surfaces */
  --bg-page: var(--image-background, linear-gradient(135deg, #f7e9f2, #fdf9fc)); /* gradient → use with `background:` */
  --bg-card: var(--iz-surface, var(--color-main-background, #fff));
  --bg-subtle: var(--iz-surface-subtle, var(--color-background-hover, #faf6fa));
  --bg-inset: var(--iz-surface-inset, var(--color-background-dark, #f3ecf3));

  /* text */
  --color-text-primary: var(--iz-text, var(--color-main-text, #24172e));
  --color-text-secondary: var(--iz-text-secondary, var(--color-text-maxcontrast, #6a6472));
  --color-text-muted: var(--iz-text-muted, color-mix(in oklab, var(--color-text-maxcontrast, #6a6472) 70%, var(--color-main-background, #fff)));

  /* borders — --color-border is inherited from the theme (do NOT redefine: cycle) */
  --color-border-strong: var(--iz-border-strong, var(--color-border-dark, #e6d8e6));

  /* accent (pink) */
  --accent: var(--iz-accent, var(--color-primary-element, #cc3d94));
  --accent-hover: var(--iz-accent-hover, var(--color-primary-element-hover, #bd3487));
  --accent-strong: var(--iz-cat-2, var(--color-primary, #3a2350));
  --accent-bg: var(--iz-accent-bg, var(--color-primary-element-light, #f6e4f0));
  --accent-on-bg: var(--iz-accent-bg-text, var(--color-primary-element-light-text, #8a2b6b));

  /* radii */
  --radius-card: var(--iz-radius-card, var(--border-radius-container, 14px));
  --radius-el: var(--iz-radius, var(--border-radius-element, 8px));
  --radius-sm: var(--iz-radius-sm, var(--border-radius-small, 6px));
  --radius-lg: var(--iz-radius-lg, var(--border-radius-large, 10px));
  --radius-pill: var(--iz-radius-pill, var(--border-radius-pill, 9999px));

  /* shadows — In Zicht pink glow */
  --shadow-card: var(--iz-shadow, 0 1px 3px rgba(0, 0, 0, 0.08));
  --shadow-card-hover: var(--iz-shadow-lift, 0 12px 32px -8px rgba(204, 61, 148, 0.15), 0 4px 12px -4px rgba(0, 0, 0, 0.08));

  /* status — semantic; warning is renamed off the theme var to avoid a cycle */
  --color-danger: var(--iz-danger, var(--color-error, #c9314a));
  --color-danger-text: var(--iz-danger-text, var(--color-error, #b42318));
  --color-danger-bg: var(--iz-danger-bg, color-mix(in oklab, var(--color-error, #c9314a) 14%, var(--color-main-background, #fff)));
  --color-warning-text: var(--iz-warning-text, #a86a12);
  --color-warning-bg: var(--iz-warning-bg, color-mix(in oklab, var(--color-warning, #ecc980) 30%, var(--color-main-background, #fff)));
  --color-success: var(--iz-success, #1f7a3e);
  --color-success-text: var(--iz-success-text, #166534);
  --color-success-bg: var(--iz-success-bg, color-mix(in oklab, #1f7a3e 14%, var(--color-main-background, #fff)));

  /* legacy badge token names (children reference them) → point at the ramps */
  --color-badge-danger-bg: var(--iz-danger-bg, color-mix(in oklab, var(--color-error, #c9314a) 14%, var(--color-main-background, #fff)));
  --color-badge-danger-text: var(--iz-danger-text, var(--color-error, #b42318));
  --color-badge-warning-bg: var(--iz-warning-bg, color-mix(in oklab, var(--color-warning, #ecc980) 30%, var(--color-main-background, #fff)));
  --color-badge-warning-text: var(--iz-warning-text, #a86a12);
  --color-badge-success-bg: var(--iz-success-bg, color-mix(in oklab, #1f7a3e 14%, var(--color-main-background, #fff)));
  --color-badge-success-text: var(--iz-success-text, #166534);

  /* chart palette — series 1 = pink, reharmonized for light + dark */
  --chart-1: var(--iz-cat-1, var(--color-primary-element, #cc3d94));
  --chart-2: var(--iz-cat-2, var(--color-primary, #3a2350));
  --chart-3: var(--iz-cat-3, #2f9e8f);
  --chart-4: var(--iz-cat-4, #d98a2b);
  --chart-5: var(--iz-cat-5, #7c5cbf);
  --chart-5-bg: var(--iz-cat-5-bg, color-mix(in oklab, #7c5cbf 16%, var(--color-main-background, #fff)));

  /* spacing — unchanged */
  --spacing-xs: 4px;
  --spacing-sm: 8px;
  --spacing-md: 16px;
  --spacing-lg: 24px;
  --spacing-xl: 32px;
  --spacing-2xl: 40px;

  background: var(--bg-page);
  max-width: 1200px;
  margin: 0 auto;
  padding: var(--spacing-lg);
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  color: var(--color-text-primary);
  min-height: calc(100vh - 50px);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
}

.superadmin-dashboard__page-header {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.superadmin-dashboard__page-title {
  font-family: 'Space Grotesk', system-ui, -apple-system, sans-serif;
  font-size: var(--iz-fs-2xl);
  font-weight: 700;
  color: var(--color-text-primary);
  margin: 0;
  line-height: 1.2;
}

.superadmin-dashboard__page-sub {
  font-size: var(--iz-fs-md);
  color: var(--color-text-secondary);
  margin: 0;
}

.superadmin-dashboard__loading,
.superadmin-dashboard__error {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: var(--spacing-2xl);
  color: var(--color-text-secondary);
  font-size: var(--iz-fs-md);
}

.superadmin-dashboard__spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--color-border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: superadmin-spin 0.8s linear infinite;
}

@keyframes superadmin-spin {
  to {
    transform: rotate(360deg);
  }
}

.superadmin-dashboard__error {
  color: var(--color-danger);
}

.superadmin-dashboard__error-msg {
  max-width: 420px;
  text-align: center;
  margin: 0;
}

.superadmin-dashboard__tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--color-border);
  margin-bottom: calc(-1 * var(--spacing-lg) + 4px);
}

.superadmin-dashboard__tab {
  font-family: 'Space Grotesk', system-ui, -apple-system, sans-serif;
  background: transparent;
  border: 0;
  border-bottom: 2px solid transparent;
  color: var(--color-text-muted);
  padding: 10px 16px;
  font-size: var(--iz-fs-md);
  font-weight: 600;
  cursor: pointer;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  transition: color 0.15s, border-color 0.15s;
  margin-bottom: -1px;
}

/* NC core styles bare <button> with `button:not(.button-vue, [class^="vs__"]):hover`
   (specificity 0,2,1), which beats a scoped single-class rule (0,2,0) and paints
   these tabs navy-on-pink on hover. Match its specificity so the tab keeps the
   In Zicht treatment: quiet tint, accent text, no pill background. */
.superadmin-dashboard__tabs .superadmin-dashboard__tab:hover {
  color: var(--color-text-primary);
  background: transparent;
}

.superadmin-dashboard__tabs .superadmin-dashboard__tab--active,
.superadmin-dashboard__tabs .superadmin-dashboard__tab--active:hover {
  color: var(--accent);
  border-bottom-color: var(--accent);
  background: transparent;
}

.superadmin-dashboard__tab:focus-visible {
  outline: none;
  box-shadow: 0 0 0 2px var(--bg-card), 0 0 0 4px var(--accent);
  border-radius: var(--radius-sm);
}
</style>
