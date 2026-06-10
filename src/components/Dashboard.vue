<template>
  <div class="superadmin-dashboard">
    <div v-if="loading" class="superadmin-dashboard__loading">
      <div class="superadmin-dashboard__spinner"></div>
      <p>Loading super-admin dashboard…</p>
    </div>

    <div v-else-if="error" class="superadmin-dashboard__error">
      <p>{{ error }}</p>
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
        this.error = e.message || "Failed to load dashboard";
      } finally {
        this.loading = false;
      }
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
/* unscoped — overrides Nextcloud dark mode */
#app-content:has(.superadmin-dashboard) {
  background-color: #f0f1f5 !important;
}
</style>

<style scoped>
.superadmin-dashboard {
  --bg-page: #f0f1f5;
  --bg-card: #ffffff;
  --shadow-card: 0 1px 3px rgba(0, 0, 0, 0.08);
  --shadow-card-hover: 0 4px 12px rgba(0, 0, 0, 0.1);
  --radius-card: 12px;
  --color-text-primary: #1a1a2e;
  --color-text-secondary: #6b7280;
  --color-text-muted: #9ca3af;
  --color-danger: #d94040;
  --color-warning: #b8860b;
  --color-success: #2e7d32;
  --color-badge-danger-bg: #fde8e8;
  --color-badge-danger-text: #b91c1c;
  --color-badge-warning-bg: #fef3cd;
  --color-badge-warning-text: #92400e;
  --color-badge-success-bg: #d4edda;
  --color-badge-success-text: #166534;
  --color-border: #e5e7eb;
  --spacing-xs: 4px;
  --spacing-sm: 8px;
  --spacing-md: 16px;
  --spacing-lg: 24px;
  --spacing-xl: 32px;
  --spacing-2xl: 40px;

  background-color: var(--bg-page);
  max-width: 1200px;
  margin: 0 auto;
  padding: var(--spacing-lg);
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen,
    Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", Arial,
    sans-serif;
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
  font-size: 26px;
  font-weight: 700;
  color: var(--color-text-primary);
  margin: 0;
  line-height: 1.2;
}

.superadmin-dashboard__page-sub {
  font-size: 13px;
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
  font-size: 14px;
}

.superadmin-dashboard__spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top-color: #4a90d9;
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

.superadmin-dashboard__tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid #eaecf0;
  margin-bottom: calc(-1 * var(--spacing-lg) + 4px);
}

.superadmin-dashboard__tab {
  background: transparent;
  border: 0;
  border-bottom: 2px solid transparent;
  color: var(--color-text-muted);
  padding: 10px 16px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  transition: color 0.15s, border-color 0.15s;
  margin-bottom: -1px;
}

.superadmin-dashboard__tab:hover {
  color: var(--color-text-primary);
}

.superadmin-dashboard__tab--active {
  color: #4a90d9;
  border-bottom-color: #4a90d9;
}
</style>
