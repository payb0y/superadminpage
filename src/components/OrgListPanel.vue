<template>
  <section class="org-list">
    <header class="org-list__header">
      <h2 class="org-list__title">
        Organizations
        <span class="org-list__count">{{ orgs.length }}</span>
      </h2>
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

    <div v-else class="org-list__grid">
      <OrgCard
        v-for="org in paginatedOrgs"
        :key="'org-' + org.id"
        :org="org"
        :selected="org.id === selectedOrgId"
        @click="$emit('select-org', org.id)"
      />
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
import OrgCard from "./OrgCard.vue";

export default {
  name: "OrgListPanel",
  components: { OrgCard },
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
      pageSize: 9,
      statusOptions: [
        { value: "all", label: "All" },
        { value: "active", label: "Active" },
        { value: "paused", label: "Paused" },
        { value: "cancelled", label: "Cancelled" },
        { value: "none", label: "No plan" },
      ],
    };
  },
  computed: {
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

@media (max-width: 1200px) {
  .org-list__grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .org-list__grid {
    grid-template-columns: 1fr;
  }
}
</style>
