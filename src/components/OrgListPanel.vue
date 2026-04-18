<template>
  <section class="org-list">
    <header class="org-list__header">
      <h2 class="org-list__title">
        Organizations
        <span class="org-list__count">{{ orgs.length }}</span>
      </h2>
      <div
        class="org-list__view-toggle"
        role="group"
        aria-label="Organization view mode"
      >
        <button
          type="button"
          class="org-list__view-btn"
          :class="{ 'org-list__view-btn--active': viewMode === 'grid' }"
          :aria-pressed="viewMode === 'grid'"
          title="Grid view"
          @click="viewMode = 'grid'"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <rect x="3" y="3" width="7" height="7" />
            <rect x="14" y="3" width="7" height="7" />
            <rect x="3" y="14" width="7" height="7" />
            <rect x="14" y="14" width="7" height="7" />
          </svg>
          Grid
        </button>
        <button
          type="button"
          class="org-list__view-btn"
          :class="{ 'org-list__view-btn--active': viewMode === 'table' }"
          :aria-pressed="viewMode === 'table'"
          title="Table view"
          @click="viewMode = 'table'"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <line x1="3" y1="6" x2="21" y2="6" />
            <line x1="3" y1="12" x2="21" y2="12" />
            <line x1="3" y1="18" x2="21" y2="18" />
          </svg>
          Table
        </button>
      </div>
    </header>

    <div class="org-list__filters">
      <input
        v-model="searchQuery"
        class="org-list__search"
        type="text"
        placeholder="Search organizations…"
      />
      <div class="org-list__filter-group">
        <span
          v-for="opt in statusOptions"
          :key="opt.value"
          class="org-list__filter-badge"
          :class="{
            'org-list__filter-badge--active': statusFilter === opt.value,
          }"
          @click="
            statusFilter = opt.value;
            currentPage = 1;
          "
        >
          {{ opt.label }}
        </span>
      </div>
    </div>

    <div v-if="filteredOrgs.length === 0" class="org-list__empty">
      No organizations match your filters.
    </div>

    <div v-else-if="viewMode === 'grid'" class="org-list__grid">
      <OrgCard
        v-for="org in paginatedOrgs"
        :key="'org-' + org.id"
        :org="org"
        :selected="org.id === selectedOrgId"
        @click="$emit('select-org', org.id)"
      />
    </div>

    <div v-else class="org-list__table-wrap">
      <table class="org-list__table">
        <thead>
          <tr>
            <th class="org-list__th org-list__th--expand" aria-label="Expand"></th>
            <th class="org-list__th">Name</th>
            <th class="org-list__th">Status</th>
            <th class="org-list__th org-list__th--plan">Plan</th>
            <th class="org-list__th org-list__th--num">Members</th>
            <th class="org-list__th org-list__th--num">Projects</th>
            <th class="org-list__th org-list__th--num org-list__th--storage">
              Storage
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-for="org in paginatedOrgs">
            <tr
              :key="'row-' + org.id"
              class="org-list__row"
              :class="{
                'org-list__row--selected': org.id === selectedOrgId,
                'org-list__row--expanded': !!expanded[org.id],
              }"
              @click="$emit('select-org', org.id)"
            >
              <td class="org-list__cell org-list__cell--expand">
                <button
                  type="button"
                  class="org-list__expand-btn"
                  :class="{
                    'org-list__expand-btn--open': !!expanded[org.id],
                  }"
                  :aria-expanded="!!expanded[org.id]"
                  aria-label="Toggle organization details"
                  @click.stop="toggleExpand(org)"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <polyline points="9 18 15 12 9 6" />
                  </svg>
                </button>
              </td>
              <td class="org-list__cell org-list__cell--name">
                <span class="org-list__avatar">{{ initial(org) }}</span>
                <span class="org-list__name">{{ org.name }}</span>
              </td>
              <td class="org-list__cell">
                <span
                  class="org-list__pill"
                  :class="'org-list__pill--' + statusTone(org)"
                >
                  <span class="org-list__dot"></span>
                  {{ statusLabel(org) }}
                </span>
              </td>
              <td class="org-list__cell org-list__cell--plan">
                <span class="org-list__pill org-list__pill--plan">
                  {{ org.planName || "No plan" }}
                </span>
              </td>
              <td class="org-list__cell org-list__cell--num">
                {{ org.memberCount }}
              </td>
              <td class="org-list__cell org-list__cell--num">
                {{ org.projectCount }}
              </td>
              <td
                class="org-list__cell org-list__cell--num org-list__cell--storage"
              >
                {{ storageDisplay(org) }}
              </td>
            </tr>
            <tr
              v-if="expanded[org.id]"
              :key="'detail-' + org.id"
              class="org-list__detail-row"
              @click.stop
            >
              <td class="org-list__detail-cell" :colspan="7">
                <div
                  v-if="detailLoading[org.id]"
                  class="org-list__detail-state"
                >
                  <div class="org-list__spinner"></div>
                  <span>Loading organization…</span>
                </div>
                <div
                  v-else-if="detailError[org.id]"
                  class="org-list__detail-state org-list__detail-state--error"
                >
                  <span>{{ detailError[org.id] }}</span>
                  <button
                    type="button"
                    class="org-list__retry-btn"
                    @click="loadDetail(org.id)"
                  >
                    Retry
                  </button>
                </div>
                <div
                  v-else-if="detailCache[org.id]"
                  class="org-list__detail-body"
                >
                  <div class="org-list__detail-header">
                    <div class="org-list__detail-tabs">
                      <button
                        v-for="tab in detailTabs(detailCache[org.id])"
                        :key="tab.key"
                        type="button"
                        class="org-list__detail-tab"
                        :class="{
                          'org-list__detail-tab--active':
                            currentTab(org.id) === tab.key,
                        }"
                        @click="setDetailTab(org.id, tab.key)"
                      >
                        {{ tab.label }}
                        <span
                          v-if="tab.count !== null"
                          class="org-list__detail-tab-count"
                        >
                          {{ tab.count }}
                        </span>
                      </button>
                    </div>
                    <button
                      type="button"
                      class="org-list__open-full"
                      @click="$emit('select-org', org.id)"
                    >
                      Open full details →
                    </button>
                  </div>

                  <div class="org-list__detail-content">
                    <div
                      v-if="currentTab(org.id) === 'overview'"
                      class="org-list__overview"
                    >
                      <div class="org-list__kpi-strip">
                        <KpiCard
                          title="Projects"
                          :metrics="[
                            {
                              value:
                                detailCache[org.id].usageSummary.projectCount,
                              label: 'total',
                            },
                          ]"
                        />
                        <KpiCard
                          title="Tasks"
                          :metrics="[
                            {
                              value:
                                detailCache[org.id].usageSummary.totalTasks,
                              label: 'total',
                            },
                            {
                              value:
                                detailCache[org.id].usageSummary.doneTasks,
                              label: 'done',
                            },
                          ]"
                        />
                        <KpiCard
                          title="Resources"
                          :metrics="[
                            {
                              value:
                                detailCache[org.id].usageSummary.memberCount,
                              label: 'members',
                            },
                            {
                              value:
                                detailCache[org.id].subscription.maxMembers ||
                                '∞',
                              label: 'plan cap',
                            },
                          ]"
                        />
                        <KpiCard
                          title="Financial"
                          :metrics="[
                            {
                              value:
                                detailCache[org.id].subscription.price +
                                ' ' +
                                detailCache[org.id].subscription.currency,
                              label: 'plan price',
                            },
                          ]"
                        />
                      </div>
                      <div class="org-list__profile-card">
                        <h4 class="org-list__section-title">
                          Organization profile
                        </h4>
                        <div class="org-list__profile-grid">
                          <div class="org-list__profile-item">
                            <span class="org-list__profile-label">
                              Owner UID
                            </span>
                            <span class="org-list__profile-value">
                              {{ detailCache[org.id].profile.adminUid }}
                            </span>
                          </div>
                          <div class="org-list__profile-item">
                            <span class="org-list__profile-label">
                              Contact
                            </span>
                            <span class="org-list__profile-value">
                              {{ contactName(detailCache[org.id]) }}
                            </span>
                          </div>
                          <div class="org-list__profile-item">
                            <span class="org-list__profile-label">Email</span>
                            <span class="org-list__profile-value">
                              {{ detailCache[org.id].profile.contactEmail }}
                            </span>
                          </div>
                          <div
                            v-if="detailCache[org.id].profile.contactPhone"
                            class="org-list__profile-item"
                          >
                            <span class="org-list__profile-label">Phone</span>
                            <span class="org-list__profile-value">
                              {{ detailCache[org.id].profile.contactPhone }}
                            </span>
                          </div>
                          <div class="org-list__profile-item">
                            <span class="org-list__profile-label">
                              Subscription
                            </span>
                            <span class="org-list__profile-value">
                              {{ detailCache[org.id].subscription.status }}
                              <template
                                v-if="
                                  detailCache[org.id].subscription.startedAt
                                "
                              >
                                (since
                                {{
                                  formatDate(
                                    detailCache[org.id].subscription.startedAt,
                                  )
                                }})
                              </template>
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <MembersPanel
                      v-else-if="currentTab(org.id) === 'members'"
                      :members="detailCache[org.id].members"
                      :embedded="true"
                    />

                    <ProjectsPanel
                      v-else-if="currentTab(org.id) === 'projects'"
                      :projects="detailCache[org.id].projects"
                    />

                    <BackupsPanel
                      v-else-if="currentTab(org.id) === 'backups'"
                      :jobs="detailCache[org.id].backups || []"
                      :embedded="true"
                    />
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div v-if="totalPages > 1" class="org-list__pagination">
      <button
        class="org-list__page-btn"
        :disabled="currentPage <= 1"
        @click="currentPage--"
      >
        ‹
      </button>
      <span class="org-list__page-info">
        {{ currentPage }} / {{ totalPages }}
      </span>
      <button
        class="org-list__page-btn"
        :disabled="currentPage >= totalPages"
        @click="currentPage++"
      >
        ›
      </button>
    </div>
  </section>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";
import OrgCard from "./OrgCard.vue";
import KpiCard from "./KpiCard.vue";
import MembersPanel from "./MembersPanel.vue";
import ProjectsPanel from "./ProjectsPanel.vue";
import BackupsPanel from "./BackupsPanel.vue";

const VIEW_MODE_STORAGE_KEY = "superadminpage.orgListView";

export default {
  name: "OrgListPanel",
  components: { OrgCard, KpiCard, MembersPanel, ProjectsPanel, BackupsPanel },
  props: {
    orgs: {
      type: Array,
      default: () => [],
    },
    selectedOrgId: {
      type: [Number, String, null],
      default: null,
    },
  },
  data() {
    return {
      searchQuery: "",
      statusFilter: "all",
      currentPage: 1,
      statusOptions: [
        { value: "all", label: "All" },
        { value: "active", label: "Active" },
        { value: "paused", label: "Paused" },
        { value: "cancelled", label: "Cancelled" },
        { value: "none", label: "No plan" },
      ],
      viewMode: this.readViewMode(),
      expanded: {},
      detailCache: {},
      detailLoading: {},
      detailError: {},
      activeDetailTab: {},
    };
  },
  computed: {
    pageSize() {
      return this.viewMode === "table" ? 20 : 9;
    },
    filteredOrgs() {
      const q = (this.searchQuery || "").toLowerCase();
      const filter = this.statusFilter;
      return this.orgs.filter((o) => {
        if (filter !== "all") {
          const status = o.subscriptionStatus || "none";
          if (status !== filter) return false;
        }
        if (q && (o.name || "").toLowerCase().indexOf(q) === -1) return false;
        return true;
      });
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.filteredOrgs.length / this.pageSize));
    },
    paginatedOrgs() {
      const start = (this.currentPage - 1) * this.pageSize;
      return this.filteredOrgs.slice(start, start + this.pageSize);
    },
  },
  watch: {
    searchQuery() {
      this.currentPage = 1;
    },
    filteredOrgs() {
      if (this.currentPage > this.totalPages) {
        this.currentPage = this.totalPages;
      }
    },
    viewMode(v) {
      try {
        window.localStorage.setItem(VIEW_MODE_STORAGE_KEY, v);
      } catch (_) {
        // ignore storage errors (private mode, etc.)
      }
      this.currentPage = 1;
    },
  },
  methods: {
    readViewMode() {
      try {
        const v = window.localStorage.getItem(VIEW_MODE_STORAGE_KEY);
        return v === "table" || v === "grid" ? v : "grid";
      } catch (_) {
        return "grid";
      }
    },
    initial(org) {
      return (org.name || "?").charAt(0).toUpperCase();
    },
    statusTone(org) {
      switch (org.subscriptionStatus) {
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
    statusLabel(org) {
      const s = org.subscriptionStatus;
      if (!s || s === "none") return "No subscription";
      return s.charAt(0).toUpperCase() + s.slice(1);
    },
    storageDisplay(org) {
      const bytes = org.storageBytes || 0;
      if (bytes <= 0) return "—";
      if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + " KB";
      if (bytes < 1024 * 1024 * 1024)
        return Math.round(bytes / (1024 * 1024)) + " MB";
      return (bytes / (1024 * 1024 * 1024)).toFixed(1) + " GB";
    },
    toggleExpand(org) {
      const isOpen = !!this.expanded[org.id];
      this.$set(this.expanded, org.id, !isOpen);
      if (!isOpen) {
        if (!this.activeDetailTab[org.id]) {
          this.$set(this.activeDetailTab, org.id, "overview");
        }
        if (!this.detailCache[org.id] && !this.detailLoading[org.id]) {
          this.loadDetail(org.id);
        }
      }
    },
    currentTab(orgId) {
      return this.activeDetailTab[orgId] || "overview";
    },
    setDetailTab(orgId, tabKey) {
      this.$set(this.activeDetailTab, orgId, tabKey);
    },
    async loadDetail(orgId) {
      this.$set(this.detailLoading, orgId, true);
      this.$set(this.detailError, orgId, null);
      try {
        const res = await axios.get(
          generateUrl("/apps/superadminpage/api/super/orgs/" + orgId),
        );
        this.$set(this.detailCache, orgId, res.data);
      } catch (e) {
        this.$set(
          this.detailError,
          orgId,
          (e && e.message) || "Failed to load organization",
        );
      } finally {
        this.$set(this.detailLoading, orgId, false);
      }
    },
    detailTabs(detail) {
      return [
        { key: "overview", label: "Overview", count: null },
        {
          key: "members",
          label: "Members",
          count: (detail.members || []).length,
        },
        {
          key: "projects",
          label: "Projects",
          count: (detail.projects || []).length,
        },
        {
          key: "backups",
          label: "Backups",
          count: (detail.backups || []).length,
        },
      ];
    },
    contactName(detail) {
      const p = detail.profile || {};
      const full = ((p.contactFirstName || "") + " " + (p.contactLastName || ""))
        .trim();
      return full || "—";
    },
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
.org-list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md, 16px);
}

.org-list__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.org-list__title {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.org-list__count {
  font-size: 12px;
  font-weight: 600;
  background: #e8f0fe;
  color: #1e4a8a;
  padding: 2px 10px;
  border-radius: 8px;
}

.org-list__view-toggle {
  display: inline-flex;
  background: #f0f1f5;
  border-radius: 999px;
  padding: 3px;
}

.org-list__view-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border: none;
  background: transparent;
  color: #6b7280;
  font-size: 12px;
  font-weight: 600;
  border-radius: 999px;
  cursor: pointer;
  transition: background 0.15s, color 0.15s, box-shadow 0.15s;
}

.org-list__view-btn:hover {
  color: var(--color-text-primary, #1a1a2e);
}

.org-list__view-btn--active {
  background: #ffffff;
  color: #1e4a8a;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

.org-list__filters {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.org-list__search {
  flex: 1;
  min-width: 200px;
  padding: 8px 14px;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 8px;
  font-size: 13px;
  color: var(--color-text-primary, #1a1a2e);
  background: #fff;
  outline: none;
  transition: border-color 0.15s;
}

.org-list__search:focus {
  border-color: #4a90d9;
}

.org-list__filter-group {
  display: flex;
  gap: 6px;
}

.org-list__filter-badge {
  font-size: 11px;
  font-weight: 500;
  padding: 4px 12px;
  border-radius: 999px;
  cursor: pointer;
  background: #f0f1f5;
  color: #6b7280;
  transition: all 0.15s ease;
  user-select: none;
  border: 1.5px solid transparent;
}

.org-list__filter-badge:hover {
  background: #e5e7eb;
}

.org-list__filter-badge--active {
  font-weight: 600;
  background: #e8f0fe;
  color: #1e4a8a;
  border-color: currentColor;
}

.org-list__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--spacing-md, 16px);
}

.org-list__empty {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  padding: var(--spacing-2xl, 40px);
  text-align: center;
  color: var(--color-text-muted, #9ca3af);
  font-size: 14px;
}

/* ───────── Table view ───────── */

.org-list__table-wrap {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  overflow: hidden;
}

.org-list__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.org-list__th {
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  padding: 12px 14px;
  background: #fafbfd;
  border-bottom: 1px solid var(--color-border, #e5e7eb);
}

.org-list__th--expand {
  width: 36px;
  padding: 12px 4px;
}

.org-list__th--num {
  text-align: right;
}

.org-list__row {
  cursor: pointer;
  transition: background 0.15s;
  border-bottom: 1px solid #eef1f5;
}

.org-list__row:hover {
  background: #f7faff;
}

.org-list__row--selected {
  background: #f5faff;
  box-shadow: inset 3px 0 0 #4a90d9;
}

.org-list__row--selected:hover {
  background: #eef6ff;
}

.org-list__row--expanded {
  background: #f7faff;
}

.org-list__cell {
  padding: 12px 14px;
  color: var(--color-text-primary, #1a1a2e);
  vertical-align: middle;
}

.org-list__cell--num {
  text-align: right;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
}

.org-list__cell--expand {
  padding: 12px 4px;
  width: 36px;
}

.org-list__cell--name {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.org-list__avatar {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: linear-gradient(135deg, #4a90d9, #6cb0f0);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.org-list__name {
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.org-list__pill {
  font-size: 10px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  text-transform: capitalize;
  white-space: nowrap;
}

.org-list__pill--success {
  background: var(--color-badge-success-bg, #d4edda);
  color: var(--color-badge-success-text, #166534);
}
.org-list__pill--warning {
  background: var(--color-badge-warning-bg, #fef3cd);
  color: var(--color-badge-warning-text, #92400e);
}
.org-list__pill--danger {
  background: var(--color-badge-danger-bg, #fde8e8);
  color: var(--color-badge-danger-text, #b91c1c);
}
.org-list__pill--muted {
  background: #f0f1f5;
  color: #6b7280;
}
.org-list__pill--plan {
  background: #e8f0fe;
  color: #1e4a8a;
}

.org-list__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  display: inline-block;
}

.org-list__expand-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border: none;
  background: transparent;
  color: var(--color-text-secondary, #6b7280);
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.15s, transform 0.2s, color 0.15s;
}

.org-list__expand-btn:hover {
  background: #e8f0fe;
  color: #1e4a8a;
}

.org-list__expand-btn--open {
  transform: rotate(90deg);
  color: #1e4a8a;
}

/* ───────── Expanded detail row ───────── */

.org-list__detail-row {
  background: #fafbfd;
  border-bottom: 1px solid #eef1f5;
}

.org-list__detail-cell {
  padding: 0;
}

.org-list__detail-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: var(--spacing-lg, 24px);
  color: var(--color-text-secondary, #6b7280);
  font-size: 13px;
}

.org-list__detail-state--error {
  color: var(--color-danger, #d94040);
  flex-wrap: wrap;
}

.org-list__retry-btn {
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 600;
  color: #4a90d9;
  background: #fff;
  border: 1px solid #4a90d9;
  border-radius: 6px;
  cursor: pointer;
}

.org-list__retry-btn:hover {
  background: #e8f0fe;
}

.org-list__spinner {
  width: 18px;
  height: 18px;
  border: 2px solid #e5e7eb;
  border-top-color: #4a90d9;
  border-radius: 50%;
  animation: org-list-spin 0.8s linear infinite;
}

@keyframes org-list-spin {
  to {
    transform: rotate(360deg);
  }
}

.org-list__detail-body {
  padding: var(--spacing-md, 16px) var(--spacing-lg, 24px) var(--spacing-lg, 24px);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md, 16px);
}

.org-list__detail-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  border-bottom: 1px solid var(--color-border, #e5e7eb);
  padding-bottom: 4px;
}

.org-list__detail-tabs {
  display: flex;
  gap: 2px;
  flex-wrap: wrap;
}

.org-list__detail-tab {
  background: none;
  border: none;
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: color 0.15s, border-color 0.15s;
}

.org-list__detail-tab:hover {
  color: var(--color-text-primary, #1a1a2e);
}

.org-list__detail-tab--active {
  color: #4a90d9;
  border-bottom-color: #4a90d9;
}

.org-list__detail-tab-count {
  font-size: 10px;
  font-weight: 600;
  background: #f0f1f5;
  color: #6b7280;
  padding: 1px 6px;
  border-radius: 8px;
}

.org-list__detail-tab--active .org-list__detail-tab-count {
  background: #e8f0fe;
  color: #1e4a8a;
}

.org-list__open-full {
  padding: 5px 12px;
  font-size: 12px;
  font-weight: 600;
  color: #4a90d9;
  background: transparent;
  border: 1px solid #4a90d9;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.15s;
}

.org-list__open-full:hover {
  background: #e8f0fe;
}

.org-list__detail-content {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md, 16px);
}

.org-list__overview {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md, 16px);
}

.org-list__kpi-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--spacing-md, 16px);
}

.org-list__profile-card {
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  padding: 14px 18px;
  background: #ffffff;
}

.org-list__section-title {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  margin: 0 0 10px;
}

.org-list__profile-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 24px;
}

.org-list__profile-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
  border-bottom: 1px solid #eef1f5;
}

.org-list__profile-item:last-child {
  border-bottom: none;
}

.org-list__profile-label {
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
}

.org-list__profile-value {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
  text-align: right;
  word-break: break-word;
}

/* ───────── Pagination ───────── */

.org-list__pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  margin-top: 8px;
}

.org-list__page-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid var(--color-border, #e5e7eb);
  background: #fff;
  font-size: 18px;
  font-weight: 600;
  color: #4a90d9;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
}

.org-list__page-btn:hover:not(:disabled) {
  background: #e8f0fe;
  border-color: #4a90d9;
}

.org-list__page-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.org-list__page-info {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
}

/* ───────── Responsive ───────── */

@media (max-width: 1200px) {
  .org-list__grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .org-list__kpi-strip {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 900px) {
  .org-list__th--storage,
  .org-list__cell--storage {
    display: none;
  }
}

@media (max-width: 768px) {
  .org-list__grid {
    grid-template-columns: 1fr;
  }
  .org-list__kpi-strip {
    grid-template-columns: 1fr;
  }
  .org-list__profile-grid {
    grid-template-columns: 1fr;
  }
  .org-list__th--plan,
  .org-list__cell--plan {
    display: none;
  }
}
</style>
