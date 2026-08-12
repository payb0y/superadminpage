<template>
  <section
    class="org-detail"
    :class="{ 'org-detail--embedded': embedded }"
  >
    <button
      v-if="!embedded"
      class="org-detail__back"
      @click="$emit('back')"
    >
      × Close
    </button>

    <header v-if="!embedded" class="org-detail__header">
      <span class="org-detail__avatar">{{ initial }}</span>
      <div class="org-detail__title-group">
        <h2 class="org-detail__name">{{ org.profile.name }}</h2>
        <div class="org-detail__pills">
          <span
            class="iz-pill"
            :class="'iz-pill--' + statusTone"
          >
            <span class="iz-dot"></span>
            {{ statusLabel }}
          </span>
          <span class="iz-pill iz-pill--accent">
            {{ org.subscription.planName }}
          </span>
          <span
            v-if="org.profile.contactEmail && org.profile.contactEmail !== '—'"
            class="org-detail__contact"
          >
            {{ org.profile.contactEmail }}
          </span>
        </div>
      </div>
    </header>

    <nav class="iz-tabs org-detail__tabs">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="iz-tab"
        :class="{ 'iz-tab--active': activeTab === tab.key }"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
        <span v-if="tab.count !== null" class="iz-tab__count">
          {{ tab.count }}
        </span>
      </button>
    </nav>

    <div class="org-detail__body">
      <div v-if="activeTab === 'overview'" class="org-detail__overview">
        <div class="iz-stat-grid">
          <KpiCard
            title="Projects"
            :metrics="[
              { value: org.usageSummary.projectCount, label: 'total' },
            ]"
          />
          <KpiCard
            title="Tasks"
            :metrics="[
              { value: org.usageSummary.totalTasks, label: 'total' },
              { value: org.usageSummary.doneTasks, label: 'done' },
            ]"
          />
          <KpiCard
            title="Resources"
            :metrics="[
              { value: org.usageSummary.memberCount, label: 'members' },
              {
                value: org.subscription.maxMembers || '∞',
                label: 'plan cap',
              },
            ]"
          />
          <KpiCard
            title="Storage"
            :metrics="[
              { value: storageUsedDisplay, label: 'used' },
              { value: storageCapacityDisplay, label: 'allocated' },
            ]"
          />
          <KpiCard
            title="Financial"
            :metrics="[
              { value: planPriceDisplay, label: 'plan price' },
              { value: arrDisplay, label: 'ARR' },
            ]"
          />
        </div>

        <div class="iz-card org-detail__profile-card">
          <h3 class="org-detail__section-title">Organization profile</h3>
          <div class="org-detail__profile-grid">
            <div class="org-detail__profile-item">
              <span class="org-detail__profile-label">Owner UID</span>
              <span class="org-detail__profile-value">
                {{ org.profile.adminUid }}
              </span>
            </div>
            <div class="org-detail__profile-item">
              <span class="org-detail__profile-label">Contact</span>
              <span class="org-detail__profile-value">
                {{ contactName }}
              </span>
            </div>
            <div class="org-detail__profile-item">
              <span class="org-detail__profile-label">Email</span>
              <span class="org-detail__profile-value">
                {{ org.profile.contactEmail }}
              </span>
            </div>
            <div
              v-if="org.profile.contactPhone"
              class="org-detail__profile-item"
            >
              <span class="org-detail__profile-label">Phone</span>
              <span class="org-detail__profile-value">
                {{ org.profile.contactPhone }}
              </span>
            </div>
            <div class="org-detail__profile-item">
              <span class="org-detail__profile-label">Subscription</span>
              <span class="org-detail__profile-value">
                {{ org.subscription.status }}
                <template v-if="org.subscription.startedAt">
                  (since {{ formatDate(org.subscription.startedAt) }})
                </template>
              </span>
            </div>
          </div>
        </div>
      </div>

      <MembersPanel
        v-else-if="activeTab === 'members'"
        :members="org.members"
        :org-id="org.profile.id"
        :owner-uid="org.profile.adminUid"
        :embedded="true"
        @reload="$emit('reload')"
      />

      <ProjectsPanel
        v-else-if="activeTab === 'projects'"
        :projects="org.projects"
        :org-members="org.members || []"
        @reload="$emit('reload')"
      />

      <SubscriptionPanel
        v-else-if="activeTab === 'subscription'"
        :org="org"
        @reload="$emit('reload')"
      />

      <HandoverPanel
        v-else-if="activeTab === 'handover'"
        :org="org"
        @reload="$emit('reload')"
      />

      <BackupsPanel
        v-else-if="activeTab === 'backups'"
        :jobs="org.backups || []"
        :embedded="true"
      />

      <ActivityFeed
        v-else-if="activeTab === 'activity'"
        :org-id="org.profile.id"
        :members="org.members || []"
        :projects="org.projects || []"
        :embedded="true"
      />
    </div>
  </section>
</template>

<script>
import KpiCard from "./KpiCard.vue";
import MembersPanel from "./MembersPanel.vue";
import ProjectsPanel from "./ProjectsPanel.vue";
import BackupsPanel from "./BackupsPanel.vue";
import ActivityFeed from "./ActivityFeed.vue";
import SubscriptionPanel from "./SubscriptionPanel.vue";
import HandoverPanel from "./HandoverPanel.vue";

// The seven keys the `tabs` computed renders. A tab key outside this set would
// leave every v-else-if in the body false and render an empty panel, so unknown
// values fall back to the overview.
const TAB_KEYS = [
  "overview",
  "members",
  "projects",
  "subscription",
  "handover",
  "backups",
  "activity",
];

function safeTab(key) {
  return TAB_KEYS.indexOf(key) === -1 ? "overview" : key;
}

export default {
  name: "OrgDetailView",
  components: { KpiCard, MembersPanel, ProjectsPanel, BackupsPanel, ActivityFeed, SubscriptionPanel, HandoverPanel },
  props: {
    org: {
      type: Object,
      required: true,
    },
    embedded: {
      type: Boolean,
      default: false,
    },
    initialTab: {
      type: String,
      default: null,
    },
  },
  data() {
    return {
      activeTab: safeTab(this.initialTab),
    };
  },
  watch: {
    // Today every drill-down lands on a freshly mounted instance: Dashboard
    // swaps its main tabs with v-if, so OrgListPanel and everything under it is
    // torn down on the way to System Health and rebuilt on the way back, which
    // means data() above does the work. This watcher covers re-pointing an
    // instance that stays mounted — a second tag aimed at an already-expanded
    // row — and would carry every drill-down if Dashboard ever moved to v-show
    // or <keep-alive> to stop refetching on each tab switch.
    initialTab(v) {
      if (v) this.activeTab = safeTab(v);
    },
  },
  computed: {
    tabs() {
      return [
        { key: "overview", label: "Overview", count: null },
        { key: "members", label: "Members", count: this.org.members.length },
        {
          key: "projects",
          label: "Projects",
          count: this.org.projects.length,
        },
        { key: "subscription", label: "Subscription", count: null },
        { key: "handover", label: "Handover", count: null },
        {
          key: "backups",
          label: "Backups",
          count: (this.org.backups || []).length,
        },
        { key: "activity", label: "Activity", count: null },
      ];
    },
    initial() {
      return (this.org.profile.name || "?").charAt(0).toUpperCase();
    },
    statusTone() {
      switch (this.org.subscription.status) {
        case "active":
          return "success";
        case "paused":
          return "warning";
        case "cancelled":
        case "expired":
          return "danger";
        default:
          return "muted";
      }
    },
    statusLabel() {
      const s = this.org.subscription.status;
      if (!s || s === "none") return "No subscription";
      return s.charAt(0).toUpperCase() + s.slice(1);
    },
    contactName() {
      const f = this.org.profile.contactFirstName || "";
      const l = this.org.profile.contactLastName || "";
      const full = (f + " " + l).trim();
      return full || "—";
    },
    planPriceDisplay() {
      const price = Number(this.org.subscription.price) || 0;
      return this.formatMoney(price, this.org.subscription.currency);
    },
    arrDisplay() {
      const price = Number(this.org.subscription.price) || 0;
      return this.formatMoney(price * 12, this.org.subscription.currency);
    },
    storage() {
      return (this.org.usageSummary && this.org.usageSummary.storage) || {};
    },
    storageUsedDisplay() {
      return this.formatBytes(this.storage.usedBytes);
    },
    storageCapacityDisplay() {
      return this.formatBytes(this.storage.capacityBytes);
    },
  },
  methods: {
    formatDate(d) {
      if (!d) return "—";
      const dt = new Date(d);
      if (isNaN(dt.getTime())) return d;
      return dt.toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      });
    },
    formatMoney(amount, currency) {
      const code = currency || "EUR";
      const sym = code === "EUR" ? "€" : code === "USD" ? "$" : code + " ";
      const value = Number(amount) || 0;
      return sym + value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },
    formatBytes(value) {
      if (value == null) return "—";
      const bytes = Number(value) || 0;
      if (bytes <= 0) return "0 B";
      const units = ["B", "KB", "MB", "GB", "TB"];
      const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
      const scaled = bytes / Math.pow(1024, index);
      return scaled.toFixed(index >= 3 && scaled < 10 ? 1 : 0) + " " + units[index];
    },
  },
};
</script>

<style scoped>
.org-detail {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md, 16px);
}

.org-detail__back {
  align-self: flex-end;
  background: none;
  border: none;
  color: var(--color-text-secondary, var(--color-text-secondary));
  font-size: var(--iz-fs-md);
  font-weight: 600;
  cursor: pointer;
  padding: 4px 8px;
}

.org-detail__back:hover {
  color: var(--color-text-primary, var(--color-text-primary));
}

.org-detail__header {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  padding: 20px 24px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.org-detail__avatar {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-card);
  background: linear-gradient(135deg, var(--accent), var(--accent));
  color: #fff;
  font-size: var(--iz-fs-xl);
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.org-detail__title-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.org-detail__name {
  font-size: var(--iz-fs-xl);
  font-weight: 700;
  color: var(--color-text-primary, var(--color-text-primary));
  margin: 0;
  line-height: 1.2;
}

.org-detail__pills {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}




.org-detail__contact {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-muted, var(--color-text-muted));
}
/* Chrome from .iz-tabs; the inset padding is local. */
.org-detail__tabs {
  padding: 0 4px;
}

.org-detail__body {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  padding: var(--spacing-lg, 24px);
}

.org-detail__overview {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg, 24px);
}

/* Chrome from .iz-card; this one is slightly roomier than the default. */
.org-detail__profile-card {
  padding: 16px 20px;
}

.org-detail__section-title {
  font-size: var(--iz-fs-md);
  font-weight: 600;
  color: var(--color-text-secondary, var(--color-text-secondary));
  text-transform: uppercase;
  letter-spacing: 0.4px;
  margin: 0 0 12px;
}

.org-detail__profile-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 24px;
}

.org-detail__profile-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid var(--bg-subtle);
}

.org-detail__profile-item:last-child {
  border-bottom: none;
}

.org-detail__profile-label {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary, var(--color-text-secondary));
}

.org-detail__profile-value {
  font-size: var(--iz-fs-sm);
  font-weight: 600;
  color: var(--color-text-primary, var(--color-text-primary));
  text-align: right;
  word-break: break-word;
}

.org-detail--embedded {
  gap: var(--spacing-sm, 8px);
}

.org-detail--embedded .org-detail__tabs {
  padding: 0;
}

.org-detail--embedded .org-detail__body {
  background: transparent;
  box-shadow: none;
  border-radius: 0;
  padding: var(--spacing-md, 16px) 0 0;
}


@media (max-width: 768px) {
  .org-detail__profile-grid {
    grid-template-columns: 1fr;
  }
}
</style>
