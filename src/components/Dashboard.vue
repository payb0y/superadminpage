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

      <PlatformKpiStrip v-if="platform" :kpis="platform.kpis" />

      <AlertsPanel v-if="platform" :alerts="platform.alerts" />

      <OrgListPanel :orgs="orgs" @select-org="onSelectOrg" />

      <section
        v-if="currentView === 'orgDetail'"
        ref="detailSection"
        class="superadmin-dashboard__detail"
      >
        <div v-if="detailLoading" class="superadmin-dashboard__loading">
          <div class="superadmin-dashboard__spinner"></div>
          <p>Loading organization…</p>
        </div>
        <OrgDetailView
          v-else-if="orgDetail"
          :org="orgDetail"
          @back="onBack"
        />
      </section>
    </template>
  </div>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";
import OrgListPanel from "./OrgListPanel.vue";
import OrgDetailView from "./OrgDetailView.vue";
import PlatformKpiStrip from "./PlatformKpiStrip.vue";
import AlertsPanel from "./AlertsPanel.vue";

export default {
  name: "Dashboard",
  components: {
    OrgListPanel,
    OrgDetailView,
    PlatformKpiStrip,
    AlertsPanel,
  },
  data() {
    return {
      currentView: "orgList",
      orgs: [],
      platform: null,
      selectedOrgId: null,
      orgDetail: null,
      loading: true,
      detailLoading: false,
      error: null,
    };
  },
  mounted() {
    this.fetchAll();
  },
  methods: {
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
    async onSelectOrg(orgId) {
      this.selectedOrgId = orgId;
      this.currentView = "orgDetail";
      this.detailLoading = true;
      this.orgDetail = null;
      this.$nextTick(() => {
        if (this.$refs.detailSection) {
          this.$refs.detailSection.scrollIntoView({
            behavior: "smooth",
            block: "start",
          });
        }
      });
      try {
        const url = generateUrl(
          "/apps/superadminpage/api/super/orgs/" + orgId,
        );
        const res = await axios.get(url);
        this.orgDetail = res.data;
      } catch (e) {
        console.error("Failed to load organization detail", e);
        this.error = e.message || "Failed to load organization";
        this.currentView = "orgList";
      } finally {
        this.detailLoading = false;
      }
    },
    onBack() {
      this.currentView = "orgList";
      this.selectedOrgId = null;
      this.orgDetail = null;
    },
  },
};
</script>

<style>
/* unscoped — overrides Nextcloud dark mode */
#app-content {
  background-color: #f0f1f5 !important;
  min-height: 100vh;
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

.superadmin-dashboard__detail {
  scroll-margin-top: var(--spacing-md, 16px);
}
</style>
