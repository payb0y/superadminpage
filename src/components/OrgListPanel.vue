<template>
  <section class="org-list" :data-build="buildMarker">
    <header class="org-list__header iz-panel__header">
      <h2 class="org-list__title">
        Organizations
        <span class="org-list__count">{{ orgs.length }}</span>
      </h2>
      <div class="org-list__header-controls">
        <button
          type="button"
          class="iz-btn iz-btn--primary org-list__create-btn"
          @click="openCreate"
        >+ Create organization</button>
        <div class="org-list__sort">
          <label class="org-list__sort-label" :for="sortSelectId">Sort</label>
          <select
            :id="sortSelectId"
            v-model="sortBy"
            class="iz-select iz-select--sm org-list__sort-select"
          >
            <option value="planDesc">Plan: high → low</option>
            <option value="planAsc">Plan: low → high</option>
            <option value="membersDesc">Members ↓</option>
            <option value="projectsDesc">Projects ↓</option>
            <option value="tasksDesc">Tasks total ↓</option>
            <option value="doneDesc">Tasks done ↓</option>
            <option value="overdueDesc">Tasks overdue ↓</option>
            <option value="openDesc">Tasks open ↓</option>
          </select>
        </div>
        <div
          class="iz-segment org-list__view-toggle"
          role="group"
          aria-label="Organization view mode"
        >
          <button
            type="button"
            class="iz-btn iz-btn--sm"
            :class="{ 'iz-btn--active': viewMode === 'grid' }"
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
          class="iz-btn iz-btn--sm"
          :class="{ 'iz-btn--active': viewMode === 'table' }"
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
      </div>
    </header>

    <div class="org-list__filters">
      <input
        v-model="searchQuery"
        class="iz-input org-list__search"
        type="text"
        name="superadminpage-org-search"
        autocomplete="off"
        data-1p-ignore
        data-bwignore
        data-lpignore="true"
        placeholder="Search organizations…"
      />
      <div class="org-list__filter-group">
        <span
          v-for="opt in statusOptions"
          :key="opt.value"
          class="iz-chip"
          :class="{ 'iz-chip--active': statusFilter === opt.value }"
          @click="
            statusFilter = opt.value;
            currentPage = 1;
          "
        >
          {{ opt.label }}
        </span>
      </div>
    </div>

    <div v-if="filteredOrgs.length === 0" class="iz-empty">
      No organizations match your filters.
    </div>

    <div v-else-if="viewMode === 'grid'" class="org-list__grid">
      <OrgCard
        v-for="org in paginatedOrgs"
        :key="'org-' + org.id"
        :org="org"
        :metric-highlight="metricHighlight"
        @click="revealOrgInTable(org)"
      />
    </div>

    <div v-else class="org-list__grid-table" role="table">
      <div class="org-list__header-row" role="row">
        <div class="org-list__th" role="columnheader">Name</div>
        <div class="org-list__th" role="columnheader">Status</div>
        <div class="org-list__th org-list__th--plan" role="columnheader">Plan</div>
        <div class="org-list__th org-list__th--num" role="columnheader">Members</div>
        <div class="org-list__th org-list__th--num" role="columnheader">Projects</div>
        <div class="org-list__th org-list__th--num" role="columnheader">Tasks</div>
        <div class="org-list__th org-list__th--num" role="columnheader">Done</div>
        <div class="org-list__th org-list__th--num" role="columnheader">Overdue</div>
        <div class="org-list__th org-list__th--num" role="columnheader">Open</div>
        <div class="org-list__th org-list__th--num org-list__th--storage" role="columnheader">Storage</div>
        <div class="org-list__th org-list__th--expand" role="columnheader" aria-label="Expand"></div>
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
          <div class="org-list__cell org-list__cell--name" role="cell">
            <span class="org-list__avatar">{{ initial(org) }}</span>
            <span class="org-list__name">{{ org.name }}</span>
          </div>
          <div class="org-list__cell" role="cell">
            <span
              class="iz-pill"
              :class="'iz-pill--' + statusTone(org)"
            >
              <span class="org-list__dot"></span>
              {{ statusLabel(org) }}
            </span>
          </div>
          <div class="org-list__cell org-list__cell--plan" role="cell">
            <span class="iz-pill iz-pill--accent">
              {{ org.planName || "No plan" }}
            </span>
          </div>
          <div class="org-list__cell org-list__cell--num" role="cell">
            <span
              class="org-list__metric"
              :class="{ 'iz-mark': metricHighlight === 'members' }"
            >{{ org.memberCount }}</span>
          </div>
          <div class="org-list__cell org-list__cell--num" role="cell">
            <span
              class="org-list__metric"
              :class="{ 'iz-mark': metricHighlight === 'projects' }"
            >{{ org.projectCount }}</span>
          </div>
          <div class="org-list__cell org-list__cell--num" role="cell">
            <span
              class="org-list__metric"
              :class="{ 'iz-mark': metricHighlight === 'tasks' }"
            >{{ org.totalTasks || 0 }}</span>
          </div>
          <div class="org-list__cell org-list__cell--num" role="cell">
            <span
              class="org-list__metric"
              :class="{ 'iz-mark': metricHighlight === 'done' }"
            >{{ org.doneTasks || 0 }}</span>
          </div>
          <div class="org-list__cell org-list__cell--num" role="cell">
            <span
              class="org-list__metric"
              :class="{ 'iz-mark': metricHighlight === 'overdue' }"
            >{{ org.overdueTasks || 0 }}</span>
          </div>
          <div class="org-list__cell org-list__cell--num" role="cell">
            <span
              class="org-list__metric"
              :class="{ 'iz-mark': metricHighlight === 'open' }"
            >{{ Math.max(0, (Number(org.totalTasks) || 0) - (Number(org.doneTasks) || 0)) }}</span>
          </div>
          <div
            class="org-list__cell org-list__cell--num org-list__cell--storage"
            role="cell"
          >
            {{ storageDisplay(org) }}
          </div>
          <div class="org-list__cell org-list__cell--expand" role="cell">
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
              class="iz-row__chevron"
              :class="{ 'iz-row__chevron--open': !!expanded[org.id] }"
              aria-hidden="true"
            >
              <polyline points="6 9 12 15 18 9" />
            </svg>
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
            <div class="iz-spinner"></div>
            <span>Loading organization…</span>
          </div>

          <div
            v-else-if="detailError[org.id]"
            class="org-list__detail-state org-list__detail-state--error"
          >
            <span>{{ detailError[org.id] }}</span>
            <button
              type="button"
              class="iz-btn iz-btn--sm"
              @click="loadDetail(org.id)"
            >
              Retry
            </button>
          </div>

          <OrgDetailView
            v-else-if="detailCache[org.id]"
            :org="detailCache[org.id]"
            :embedded="true"
            @reload="reloadOrgDetail(org.id)"
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

      <div v-if="totalPages > 1" class="iz-pagination org-list__pagination">
        <button
          type="button"
          class="iz-btn iz-btn--sm org-list__page-btn"
          :disabled="currentPage === 1"
          aria-label="First page"
          @click="currentPage = 1"
        >
          «
        </button>
        <button
          type="button"
          class="iz-btn iz-btn--sm org-list__page-btn"
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
          class="iz-btn iz-btn--sm org-list__page-btn"
          :disabled="currentPage >= totalPages"
          aria-label="Next page"
          @click="currentPage++"
        >
          ›
        </button>
        <button
          type="button"
          class="iz-btn iz-btn--sm org-list__page-btn"
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
          class="iz-select iz-select--sm"
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

    <CreateOrgModal
      v-if="createOpen"
      @close="closeCreate"
      @created="onCreated"
    />
  </section>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";
import OrgCard from "./OrgCard.vue";
import OrgDetailView from "./OrgDetailView.vue";
import CreateOrgModal from "./CreateOrgModal.vue";

const VIEW_MODE_STORAGE_KEY = "superadminpage.orgListView";
const SORT_STORAGE_KEY = "superadminpage.orgListSort";
const ORG_LIST_BUILD_MARKER = "v5-pagination";

const PAGE_SIZE_OPTIONS = {
  grid: [9, 18, 36, 72],
  table: [10, 20, 50, 100],
};

const PLAN_TIER_RANK = {
  Enterprise: 0,
  Custom: 1,
  Pro: 2,
  Free: 3,
};
const NO_PLAN_RANK = 4;
const STANDARD_PLANS = ["Free", "Pro", "Enterprise"];

function defaultPageSize(viewMode) {
  return viewMode === "table" ? 20 : 9;
}

function planBucket(planName) {
  if (!planName || planName === "No plan") return "No plan";
  return STANDARD_PLANS.indexOf(planName) === -1 ? "Custom" : planName;
}

function planRank(planName) {
  const bucket = planBucket(planName);
  if (bucket === "No plan") return NO_PLAN_RANK;
  return PLAN_TIER_RANK[bucket];
}

export default {
  name: "OrgListPanel",
  components: { OrgCard, OrgDetailView, CreateOrgModal },
  emits: ["list-stale"],
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
      sortBy: this.readSortBy(),
      viewMode,
      expanded: {},
      detailCache: {},
      detailLoading: {},
      detailError: {},
      createOpen: false,
    };
  },
  computed: {
    buildMarker() {
      return ORG_LIST_BUILD_MARKER;
    },
    pageSizeId() {
      return "org-list-page-size-" + this._uid;
    },
    sortSelectId() {
      return "org-list-sort-" + this._uid;
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
    sortedOrgs() {
      const arr = this.filteredOrgs.slice();
      const sb = this.sortBy;
      const tieBreak = (a, b) => planRank(a.planName) - planRank(b.planName);
      const openOf = (o) => Math.max(0, (Number(o.totalTasks) || 0) - (Number(o.doneTasks) || 0));
      const byDesc = (key) => arr.sort(
        (a, b) => ((Number(b[key]) || 0) - (Number(a[key]) || 0)) || tieBreak(a, b)
      );
      switch (sb) {
        case "membersDesc":  return byDesc("memberCount");
        case "projectsDesc": return byDesc("projectCount");
        case "tasksDesc":    return byDesc("totalTasks");
        case "doneDesc":     return byDesc("doneTasks");
        case "overdueDesc":  return byDesc("overdueTasks");
        case "openDesc":     return arr.sort((a, b) => (openOf(b) - openOf(a)) || tieBreak(a, b));
        case "planAsc":
        case "planDesc": {
          const dir = sb === "planAsc" ? 1 : -1;
          return arr.sort((a, b) => {
            const ra = planRank(a.planName);
            const rb = planRank(b.planName);
            if (ra === rb) return 0;
            return (ra - rb) * dir * -1;
          });
        }
        default:
          return arr;
      }
    },
    metricHighlight() {
      switch (this.sortBy) {
        case "membersDesc":  return "members";
        case "projectsDesc": return "projects";
        case "tasksDesc":    return "tasks";
        case "doneDesc":     return "done";
        case "overdueDesc":  return "overdue";
        case "openDesc":     return "open";
        default:             return null;
      }
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.sortedOrgs.length / this.pageSize));
    },
    paginatedOrgs() {
      const start = (this.currentPage - 1) * this.pageSize;
      return this.sortedOrgs.slice(start, start + this.pageSize);
    },
    rangeStart() {
      if (this.sortedOrgs.length === 0) return 0;
      return (this.currentPage - 1) * this.pageSize + 1;
    },
    rangeEnd() {
      return Math.min(
        this.currentPage * this.pageSize,
        this.sortedOrgs.length,
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
    sortBy(v) {
      try {
        window.localStorage.setItem(SORT_STORAGE_KEY, v);
      } catch (_) {
        // ignore storage errors (private mode, etc.)
      }
      this.currentPage = 1;
    },
    sortedOrgs() {
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
    applyDrillDown({ statusFilter, sortBy } = {}) {
      if (statusFilter !== undefined) this.statusFilter = statusFilter;
      if (sortBy !== undefined) this.sortBy = sortBy;
      this.currentPage = 1;
    },
    reloadOrgDetail(orgId) {
      // Do NOT $delete(detailCache, orgId) here — that flips
      // OrgDetailView's v-else-if guard to false, unmounting it. The
      // component's internal activeTab would then reset to "overview" on
      // remount, kicking the admin out of the Members tab right after they
      // added/removed someone. loadDetail() overwrites detailCache[orgId]
      // atomically when the fetch resolves, so the prop just updates in
      // place and Vue diffs the rendered tree.
      this.loadDetail(orgId);
      // The detail cache update keeps the expanded panel in sync, but
      // the row's summary cells (planName, subscriptionStatus, member
      // count, etc.) are bound to the parent `orgs` prop owned by
      // Dashboard. Ask the parent to refetch the list so those stay in
      // sync after add-member / subscription edit / etc.
      this.$emit("list-stale");
    },
    openCreate() {
      this.createOpen = true;
    },
    closeCreate() {
      this.createOpen = false;
    },
    onCreated() {
      // CreateOrgModal fires this after success (or after the admin
      // clicks Done on the credentials reveal). Close the modal and
      // bubble up the staleness signal so Dashboard refetches the orgs
      // list and the new row shows up.
      this.createOpen = false;
      this.$emit("list-stale");
    },
    readViewMode() {
      try {
        const v = window.localStorage.getItem(VIEW_MODE_STORAGE_KEY);
        return v === "table" || v === "grid" ? v : "grid";
      } catch (_) {
        return "grid";
      }
    },
    readSortBy() {
      try {
        const v = window.localStorage.getItem(SORT_STORAGE_KEY);
        return v === "planAsc" || v === "planDesc" ? v : "planDesc";
      } catch (_) {
        return "planDesc";
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
        const idx = this.sortedOrgs.findIndex((o) => o.id === org.id);
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
      // Only flip detailLoading on the first load. On reload the cache
      // already exists, so we fetch silently and let Vue diff the props
      // in place — flipping detailLoading=true mid-reload would knock
      // OrgDetailView out of the v-else-if branch, unmount it, and reset
      // its internal activeTab back to "overview" on remount.
      const isFirstLoad = !this.detailCache[orgId];
      if (isFirstLoad) {
        this.$set(this.detailLoading, orgId, true);
      }
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
        if (isFirstLoad) {
          this.$set(this.detailLoading, orgId, false);
        }
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
  font-size: var(--iz-fs-xl);
  font-weight: 700;
  color: var(--color-text-primary, var(--color-text-primary));
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.org-list__count {
  font-size: var(--iz-fs-sm);
  font-weight: 600;
  background: var(--accent-bg);
  color: var(--accent-strong);
  padding: 2px 10px;
  border-radius: var(--radius-el);
}

.org-list__header-controls {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm, 8px);
  flex-wrap: wrap;
}


.org-list__create-btn:hover {
  background: var(--accent-hover);
  border-color: var(--accent-hover);
}

.org-list__sort {
  display: flex;
  align-items: center;
  gap: 6px;
}

.org-list__sort-label {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary, var(--color-text-secondary));
  font-weight: 500;
}
/* Chrome from .iz-segment. */

.org-list__filters {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}



.org-list__filter-group {
  display: flex;
  gap: 6px;
}




.org-list__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--spacing-md, 16px);
}


/* ───────── Grid-table (DataGridPremium-style) ───────── */

.org-list__grid-table {
  display: block;
  background: var(--iz-surface);
  border-radius: var(--iz-radius-card);
  box-shadow: var(--iz-shadow);
  overflow: hidden;
  width: 100%;
}

.org-list__header-row,
.org-list__row-summary {
  display: grid !important;
  grid-template-columns:
    minmax(0, 2fr)
    minmax(0, 1fr)
    minmax(0, 1fr)
    72px   /* Members */
    72px   /* Projects */
    64px   /* Tasks */
    64px   /* Done */
    72px   /* Overdue */
    64px   /* Open */
    104px  /* Storage */
    36px;  /* Expand — last, matching the standard expandable row */
  align-items: center;
  gap: 0;
  padding: 10px 14px;
  width: 100%;
  box-sizing: border-box;
}

/* Matches .iz-table th — this is a role="table" div grid (11 columns don't
   lay out in a real table), so it can't use .iz-table, but it must read the
   same as the Backups and Tasks tables. */
.org-list__header-row {
  background: var(--iz-surface-subtle);
  border-bottom: 1px solid var(--iz-border);
  font-size: var(--iz-fs-micro);
  font-weight: 600;
  color: var(--iz-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
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
  border-bottom: 1px solid var(--iz-border);
  transition: background var(--iz-transition);
  width: 100%;
  box-sizing: border-box;
}

.org-list__row:last-child {
  border-bottom: none;
}

/* Hover matches .iz-row--clickable; the accent tint is reserved for the
   expanded (active) row, like .iz-row--active. */
.org-list__row:hover .org-list__row-summary {
  background: var(--iz-surface-subtle);
}
.org-list__row:hover .iz-row__chevron {
  color: var(--iz-accent);
}

.org-list__row--expanded {
  background: var(--iz-surface-subtle);
}

.org-list__row--expanded .org-list__row-summary {
  background: var(--iz-accent-bg);
  border-bottom: 1px dashed var(--iz-border);
}

.org-list__row-summary {
  cursor: pointer;
  font-size: var(--iz-fs-md);
  color: var(--iz-text);
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

.org-list__metric {
  display: inline-block;
  font-variant-numeric: tabular-nums;
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
  border-radius: var(--radius-el);
  background: linear-gradient(135deg, var(--accent), var(--accent));
  color: #fff;
  font-size: var(--iz-fs-sm);
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



.org-list__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  display: inline-block;
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
  color: var(--color-text-secondary, var(--color-text-secondary));
  font-size: var(--iz-fs-md);
}

.org-list__detail-state--error {
  color: var(--color-danger, var(--color-danger-text));
  flex-wrap: wrap;
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
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary, var(--color-text-secondary));
  font-variant-numeric: tabular-nums;
}

.org-list__page-summary strong {
  color: var(--color-text-primary, var(--color-text-primary));
  font-weight: 700;
}


.org-list__page-btn,
.org-list__page-num {
  min-width: 32px;
  height: 32px;
  padding: 0 6px;
  border-radius: var(--radius-el);
  border: 1px solid var(--color-border, var(--color-border));
  background: var(--bg-card);
  font-size: var(--iz-fs-md);
  font-weight: 600;
  color: var(--color-text-secondary, var(--color-text-secondary));
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
  font-variant-numeric: tabular-nums;
}

.org-list__page-btn {
  font-size: var(--iz-fs-lg);
  color: var(--accent);
}

.org-list__page-btn:hover:not(:disabled),
.org-list__page-num:hover:not(.org-list__page-num--active) {
  background: var(--accent-bg);
  border-color: var(--accent);
  color: var(--accent-strong);
}

.org-list__page-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.org-list__page-num--active {
  background: var(--accent);
  border-color: var(--accent);
  color: #fff;
  cursor: default;
}

.org-list__page-ellipsis {
  min-width: 20px;
  text-align: center;
  color: var(--color-text-muted, var(--color-text-muted));
  font-size: var(--iz-fs-md);
  user-select: none;
}

.org-list__page-size {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary, var(--color-text-secondary));
}

.org-list__page-size label {
  font-weight: 500;
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
