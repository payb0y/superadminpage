<template>
  <section class="org-list" :data-build="buildMarker">
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
        @click="revealOrgInTable(org)"
      />
    </div>

    <div v-else class="org-list__grid-table" role="table">
      <div class="org-list__header-row" role="row">
        <div class="org-list__th org-list__th--expand" role="columnheader" aria-label="Expand"></div>
        <div class="org-list__th" role="columnheader">Name</div>
        <div class="org-list__th" role="columnheader">Status</div>
        <div class="org-list__th org-list__th--plan" role="columnheader">Plan</div>
        <div class="org-list__th org-list__th--num" role="columnheader">Members</div>
        <div class="org-list__th org-list__th--num" role="columnheader">Projects</div>
        <div class="org-list__th org-list__th--num org-list__th--storage" role="columnheader">Storage</div>
      </div>

      <div
        v-for="org in paginatedOrgs"
        :key="'org-' + org.id"
        class="org-list__row"
        :class="{
          'org-list__row--expanded': !!expanded[org.id],
        }"
        role="row"
        :data-org-id="org.id"
      >
        <div
          class="org-list__row-summary"
          @click="toggleExpand(org)"
        >
          <div class="org-list__cell org-list__cell--expand" role="cell">
            <button
              type="button"
              class="org-list__expand-btn"
              :class="{ 'org-list__expand-btn--open': !!expanded[org.id] }"
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
          </div>
          <div class="org-list__cell org-list__cell--name" role="cell">
            <span class="org-list__avatar">{{ initial(org) }}</span>
            <span class="org-list__name">{{ org.name }}</span>
          </div>
          <div class="org-list__cell" role="cell">
            <span
              class="org-list__pill"
              :class="'org-list__pill--' + statusTone(org)"
            >
              <span class="org-list__dot"></span>
              {{ statusLabel(org) }}
            </span>
          </div>
          <div class="org-list__cell org-list__cell--plan" role="cell">
            <span class="org-list__pill org-list__pill--plan">
              {{ org.planName || "No plan" }}
            </span>
          </div>
          <div class="org-list__cell org-list__cell--num" role="cell">
            {{ org.memberCount }}
          </div>
          <div class="org-list__cell org-list__cell--num" role="cell">
            {{ org.projectCount }}
          </div>
          <div
            class="org-list__cell org-list__cell--num org-list__cell--storage"
            role="cell"
          >
            {{ storageDisplay(org) }}
          </div>
        </div>

        <div
          v-if="expanded[org.id]"
          class="org-list__row-detail"
          @click.stop
        >
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

          <OrgDetailView
            v-else-if="detailCache[org.id]"
            :org="detailCache[org.id]"
            :embedded="true"
          />
        </div>
      </div>
    </div>

    <div
      v-if="filteredOrgs.length > 0"
      class="org-list__pagination-bar"
    >
      <div class="org-list__page-summary">
        Showing
        <strong>{{ rangeStart }}</strong>–<strong>{{ rangeEnd }}</strong>
        of <strong>{{ filteredOrgs.length }}</strong>
      </div>

      <div v-if="totalPages > 1" class="org-list__pagination">
        <button
          type="button"
          class="org-list__page-btn"
          :disabled="currentPage === 1"
          aria-label="First page"
          @click="currentPage = 1"
        >
          «
        </button>
        <button
          type="button"
          class="org-list__page-btn"
          :disabled="currentPage <= 1"
          aria-label="Previous page"
          @click="currentPage--"
        >
          ‹
        </button>
        <template v-for="(p, i) in visiblePages">
          <span
            v-if="p === '…'"
            :key="'ellipsis-' + i"
            class="org-list__page-ellipsis"
          >
            …
          </span>
          <button
            v-else
            :key="'page-' + p"
            type="button"
            class="org-list__page-num"
            :class="{ 'org-list__page-num--active': p === currentPage }"
            :aria-current="p === currentPage ? 'page' : null"
            @click="currentPage = p"
          >
            {{ p }}
          </button>
        </template>
        <button
          type="button"
          class="org-list__page-btn"
          :disabled="currentPage >= totalPages"
          aria-label="Next page"
          @click="currentPage++"
        >
          ›
        </button>
        <button
          type="button"
          class="org-list__page-btn"
          :disabled="currentPage === totalPages"
          aria-label="Last page"
          @click="currentPage = totalPages"
        >
          »
        </button>
      </div>

      <div class="org-list__page-size">
        <label :for="pageSizeId">Per page</label>
        <select
          :id="pageSizeId"
          v-model.number="pageSize"
          class="org-list__page-size-select"
        >
          <option
            v-for="n in pageSizeOptions"
            :key="n"
            :value="n"
          >
            {{ n }}
          </option>
        </select>
      </div>
    </div>
  </section>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";
import OrgCard from "./OrgCard.vue";
import OrgDetailView from "./OrgDetailView.vue";

const VIEW_MODE_STORAGE_KEY = "superadminpage.orgListView";
const ORG_LIST_BUILD_MARKER = "v5-pagination";

const PAGE_SIZE_OPTIONS = {
  grid: [9, 18, 36, 72],
  table: [10, 20, 50, 100],
};

function defaultPageSize(viewMode) {
  return viewMode === "table" ? 20 : 9;
}

export default {
  name: "OrgListPanel",
  components: { OrgCard, OrgDetailView },
  props: {
    orgs: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    const viewMode = this.readViewMode();
    return {
      searchQuery: "",
      statusFilter: "all",
      currentPage: 1,
      pageSize: defaultPageSize(viewMode),
      statusOptions: [
        { value: "all", label: "All" },
        { value: "active", label: "Active" },
        { value: "paused", label: "Paused" },
        { value: "cancelled", label: "Cancelled" },
        { value: "none", label: "No plan" },
      ],
      viewMode,
      expanded: {},
      detailCache: {},
      detailLoading: {},
      detailError: {},
    };
  },
  computed: {
    buildMarker() {
      return ORG_LIST_BUILD_MARKER;
    },
    pageSizeId() {
      return "org-list-page-size-" + this._uid;
    },
    pageSizeOptions() {
      return PAGE_SIZE_OPTIONS[this.viewMode] || PAGE_SIZE_OPTIONS.grid;
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
    rangeStart() {
      if (this.filteredOrgs.length === 0) return 0;
      return (this.currentPage - 1) * this.pageSize + 1;
    },
    rangeEnd() {
      return Math.min(
        this.currentPage * this.pageSize,
        this.filteredOrgs.length,
      );
    },
    visiblePages() {
      const total = this.totalPages;
      const current = this.currentPage;
      if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
      }
      const pages = [1];
      if (current > 3) pages.push("…");
      const start = Math.max(2, current - 1);
      const end = Math.min(total - 1, current + 1);
      for (let p = start; p <= end; p++) pages.push(p);
      if (current < total - 2) pages.push("…");
      pages.push(total);
      return pages;
    },
  },
  watch: {
    searchQuery() {
      this.currentPage = 1;
    },
    statusFilter() {
      this.currentPage = 1;
    },
    pageSize() {
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
      this.pageSize = defaultPageSize(v);
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
      if (!isOpen && !this.detailCache[org.id] && !this.detailLoading[org.id]) {
        this.loadDetail(org.id);
      }
    },
    revealOrgInTable(org) {
      this.viewMode = "table";
      this.$nextTick(() => {
        const idx = this.filteredOrgs.findIndex((o) => o.id === org.id);
        if (idx === -1) return;
        this.currentPage = Math.floor(idx / this.pageSize) + 1;
        this.$set(this.expanded, org.id, true);
        if (
          !this.detailCache[org.id] &&
          !this.detailLoading[org.id]
        ) {
          this.loadDetail(org.id);
        }
        this.$nextTick(() => {
          const el = this.$el.querySelector(
            '[data-org-id="' + org.id + '"]',
          );
          if (el) {
            el.scrollIntoView({ behavior: "smooth", block: "start" });
          }
        });
      });
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

/* ───────── Grid-table (DataGridPremium-style) ───────── */

.org-list__grid-table {
  display: block;
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  overflow: hidden;
  width: 100%;
}

.org-list__header-row,
.org-list__row-summary {
  display: grid !important;
  grid-template-columns:
    36px
    minmax(0, 2fr)
    minmax(0, 1fr)
    minmax(0, 1fr)
    88px
    96px
    104px;
  align-items: center;
  gap: 0;
  padding: 10px 14px;
  width: 100%;
  box-sizing: border-box;
}

.org-list__header-row {
  background: #fafbfd;
  border-bottom: 1px solid var(--color-border, #e5e7eb);
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.org-list__th {
  min-width: 0;
  padding: 0 4px;
}

.org-list__th--num {
  text-align: right;
}

.org-list__th--expand {
  padding: 0;
}

.org-list__row {
  display: block !important;
  position: relative;
  border-bottom: 1px solid #eef1f5;
  transition: background 0.15s;
  width: 100%;
  box-sizing: border-box;
}

.org-list__row:last-child {
  border-bottom: none;
}

.org-list__row:hover .org-list__row-summary {
  background: #f7faff;
}

.org-list__row--expanded {
  background: #fafbfd;
}

.org-list__row--expanded .org-list__row-summary {
  background: #f7faff;
  border-bottom: 1px dashed #dfe5ee;
}

.org-list__row-summary {
  cursor: pointer;
  font-size: 13px;
  color: var(--color-text-primary, #1a1a2e);
}

.org-list__cell {
  min-width: 0;
  padding: 0 4px;
}

.org-list__cell--num {
  text-align: right;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.org-list__cell--expand {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}

.org-list__cell--name {
  display: flex;
  align-items: center;
  gap: 10px;
  overflow: hidden;
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

/* ───────── In-place detail (inside the same row container) ───────── */

.org-list__row-detail {
  display: flex !important;
  flex-direction: column;
  gap: 14px;
  padding: 14px 18px 18px;
  width: 100%;
  box-sizing: border-box;
}

.org-list__detail-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 12px;
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

/* ───────── Pagination ───────── */

.org-list__pagination-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-md, 16px);
  margin-top: var(--spacing-sm, 8px);
  flex-wrap: wrap;
}

.org-list__page-summary {
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
  font-variant-numeric: tabular-nums;
}

.org-list__page-summary strong {
  color: var(--color-text-primary, #1a1a2e);
  font-weight: 700;
}

.org-list__pagination {
  display: flex;
  align-items: center;
  gap: 4px;
}

.org-list__page-btn,
.org-list__page-num {
  min-width: 32px;
  height: 32px;
  padding: 0 6px;
  border-radius: 8px;
  border: 1px solid var(--color-border, #e5e7eb);
  background: #fff;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
  font-variant-numeric: tabular-nums;
}

.org-list__page-btn {
  font-size: 16px;
  color: #4a90d9;
}

.org-list__page-btn:hover:not(:disabled),
.org-list__page-num:hover:not(.org-list__page-num--active) {
  background: #e8f0fe;
  border-color: #4a90d9;
  color: #1e4a8a;
}

.org-list__page-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.org-list__page-num--active {
  background: #4a90d9;
  border-color: #4a90d9;
  color: #fff;
  cursor: default;
}

.org-list__page-ellipsis {
  min-width: 20px;
  text-align: center;
  color: var(--color-text-muted, #9ca3af);
  font-size: 13px;
  user-select: none;
}

.org-list__page-size {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
}

.org-list__page-size label {
  font-weight: 500;
}

.org-list__page-size-select {
  height: 32px;
  padding: 0 8px;
  border-radius: 8px;
  border: 1px solid var(--color-border, #e5e7eb);
  background: #fff;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
  cursor: pointer;
  font-variant-numeric: tabular-nums;
}

.org-list__page-size-select:hover,
.org-list__page-size-select:focus {
  border-color: #4a90d9;
  outline: none;
}

/* ───────── Responsive ───────── */

@media (max-width: 1200px) {
  .org-list__grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 900px) {
  .org-list__header-row,
  .org-list__row-summary {
    grid-template-columns:
      36px
      minmax(0, 2fr)
      minmax(0, 1fr)
      minmax(0, 1fr)
      88px
      96px;
  }
  .org-list__th--storage,
  .org-list__cell--storage {
    display: none;
  }
}

@media (max-width: 768px) {
  .org-list__grid {
    grid-template-columns: 1fr;
  }
  .org-list__header-row,
  .org-list__row-summary {
    grid-template-columns: 36px minmax(0, 2fr) minmax(0, 1fr) 88px 96px;
  }
  .org-list__th--plan,
  .org-list__cell--plan {
    display: none;
  }
}
</style>
