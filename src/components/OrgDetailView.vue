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
            class="org-detail__pill"
            :class="'org-detail__pill--' + statusTone"
          >
            <span class="org-detail__dot"></span>
            {{ statusLabel }}
          </span>
          <span class="org-detail__pill org-detail__pill--plan">
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

    <nav class="org-detail__tabs">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="org-detail__tab"
        :class="{ 'org-detail__tab--active': activeTab === tab.key }"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
        <span v-if="tab.count !== null" class="org-detail__tab-count">
          {{ tab.count }}
        </span>
      </button>
    </nav>

    <div class="org-detail__body">
      <div v-if="activeTab === 'overview'" class="org-detail__overview">
        <div class="org-detail__kpi-strip">
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
            title="Financial"
            :metrics="[
              {
                value:
                  org.subscription.price + ' ' + org.subscription.currency,
                label: 'plan price',
              },
            ]"
          />
        </div>

        <div class="org-detail__profile-card">
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
        :embedded="true"
      />

      <ProjectsPanel
        v-else-if="activeTab === 'projects'"
        :projects="org.projects"
      />

      <BackupsPanel
        v-else-if="activeTab === 'backups'"
        :jobs="org.backups || []"
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

export default {
  name: "OrgDetailView",
  components: { KpiCard, MembersPanel, ProjectsPanel, BackupsPanel },
  props: {
    org: {
      type: Object,
      required: true,
    },
    embedded: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      activeTab: "overview",
    };
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
        {
          key: "backups",
          label: "Backups",
          count: (this.org.backups || []).length,
        },
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
  color: var(--color-text-secondary, #6b7280);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 4px 8px;
}

.org-detail__back:hover {
  color: var(--color-text-primary, #1a1a2e);
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
  border-radius: 12px;
  background: linear-gradient(135deg, #4a90d9, #6cb0f0);
  color: #fff;
  font-size: 20px;
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
  font-size: 22px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  margin: 0;
  line-height: 1.2;
}

.org-detail__pills {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.org-detail__pill {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-transform: capitalize;
}

.org-detail__pill--success {
  background: var(--color-badge-success-bg, #d4edda);
  color: var(--color-badge-success-text, #166534);
}
.org-detail__pill--warning {
  background: var(--color-badge-warning-bg, #fef3cd);
  color: var(--color-badge-warning-text, #92400e);
}
.org-detail__pill--danger {
  background: var(--color-badge-danger-bg, #fde8e8);
  color: var(--color-badge-danger-text, #b91c1c);
}
.org-detail__pill--muted {
  background: #f0f1f5;
  color: #6b7280;
}
.org-detail__pill--plan {
  background: #e8f0fe;
  color: #1e4a8a;
}

.org-detail__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.org-detail__contact {
  font-size: 12px;
  color: var(--color-text-muted, #9ca3af);
}

.org-detail__tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--color-border, #e5e7eb);
  padding: 0 4px;
}

.org-detail__tab {
  background: none;
  border: none;
  padding: 10px 16px;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: color 0.15s, border-color 0.15s;
}

.org-detail__tab:hover {
  color: var(--color-text-primary, #1a1a2e);
}

.org-detail__tab--active {
  color: #4a90d9;
  border-bottom-color: #4a90d9;
}

.org-detail__tab-count {
  font-size: 11px;
  font-weight: 600;
  background: #f0f1f5;
  color: #6b7280;
  padding: 1px 7px;
  border-radius: 8px;
}

.org-detail__tab--active .org-detail__tab-count {
  background: #e8f0fe;
  color: #1e4a8a;
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

.org-detail__kpi-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--spacing-md, 16px);
}

.org-detail__profile-card {
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  padding: 16px 20px;
}

.org-detail__section-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
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
  border-bottom: 1px solid #eef1f5;
}

.org-detail__profile-item:last-child {
  border-bottom: none;
}

.org-detail__profile-label {
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
}

.org-detail__profile-value {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
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

@media (max-width: 1200px) {
  .org-detail__kpi-strip {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .org-detail__kpi-strip {
    grid-template-columns: 1fr;
  }
  .org-detail__profile-grid {
    grid-template-columns: 1fr;
  }
}
</style>
