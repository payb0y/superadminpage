<template>
  <div class="superadmin-dashboard iz-app">
    <div v-if="loading" class="superadmin-dashboard__loading">
      <div class="iz-spinner iz-spinner--lg"></div>
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
      <div class="iz-tabs iz-tabs--display superadmin-dashboard__tabs" role="tablist">
        <button
          type="button"
          role="tab"
          class="iz-tab"
          :class="{
            'iz-tab--active': activeTab === 'health',
          }"
          :aria-selected="activeTab === 'health'"
          @click="setActiveTab('health')"
        >System Health</button>
        <button
          type="button"
          role="tab"
          class="iz-tab"
          :class="{
            'iz-tab--active': activeTab === 'orgs',
          }"
          :aria-selected="activeTab === 'orgs'"
          @click="setActiveTab('orgs')"
        >Organizations</button>
        <button
          type="button"
          role="tab"
          class="iz-tab"
          :class="{
            'iz-tab--active': activeTab === 'financials',
          }"
          :aria-selected="activeTab === 'financials'"
          @click="setActiveTab('financials')"
        >Financials</button>
      </div>

      <template v-if="activeTab === 'health'">
        <AlertsPanel
          v-if="platform"
          :alerts="platform.alerts"
          @drill-down="onDrillDown"
        />
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

      <template v-else-if="activeTab === 'financials'">
        <FinancialsPanel @drill-down="onDrillDown" />
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
import FinancialsPanel from "./FinancialsPanel.vue";

// The tab bar's vocabulary, in one place. Both the click handler and the
// localStorage rehydration validate against this, so adding a tab is one edit
// rather than three that can drift apart.
const TAB_KEYS = ["health", "orgs", "financials"];

export default {
  name: "Dashboard",
  components: {
    OrgListPanel,
    PlatformKpiStrip,
    AlertsPanel,
    SystemHealthPanel,
    FinancialsPanel,
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
        if (TAB_KEYS.indexOf(stored) !== -1) {
          this.activeTab = stored;
        }
      } catch (e) {
        // ignore — defaults to 'health'
      }
    },
    setActiveTab(tab) {
      if (TAB_KEYS.indexOf(tab) === -1) return;
      this.activeTab = tab;
    },
    onDrillDown(payload) {
      // Fired from three places now: the KPI strip (already in this tab), the
      // alert cards' offender tags on Health, and any mention of an
      // organization on Financials. The first two only ever need the scroll;
      // the others land here from a different tab, so switch first and let the
      // org list mount before applying the payload.
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
        this.$nextTick(this.handleContractReturn);
      }
    },
    handleContractReturn() {
      const params = new URLSearchParams(window.location.search);
      const orgId = Number(params.get("contractsOrg"));
      if (!orgId) return;
      this.onDrillDown({ orgId, tab: "contracts" });
      params.delete("contractsOrg");
      const query = params.toString();
      window.history.replaceState({}, "", window.location.pathname + (query ? "?" + query : ""));
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

/* Native form controls: the theme's `.iz-app input[type=...]` rule covers
   these now. */
</style>

<style scoped>
.superadmin-dashboard {
  /* Tokens come from the theme's `.iz-app` bridge (server.css §8), which this
     element opts into. The local copy that used to live here is gone: it was
     one of three, and the copies had drifted. Only layout stays in this file. */


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


.superadmin-dashboard__error {
  color: var(--color-danger);
}

.superadmin-dashboard__error-msg {
  max-width: 420px;
  text-align: center;
  margin: 0;
}
/* Chrome from .iz-tabs; only the pull-up against the page padding is local. */
.superadmin-dashboard__tabs {
  margin-bottom: calc(-1 * var(--spacing-lg) + 4px);
}
</style>
