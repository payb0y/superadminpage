<template>
  <div class="task-browser iz-stack iz-stack--tight">
    <div v-if="loading" class="iz-empty">Loading tasks…</div>
    <div v-else-if="error" class="iz-error">
      {{ error }}
    </div>

    <template v-else>
      <div class="task-browser__filters">
        <div class="task-browser__filter">
          <label class="iz-label">Search</label>
          <input
            v-model="filterName"
            type="text"
            class="iz-input"
            placeholder="Task name…"
          />
        </div>
        <div class="task-browser__filter">
          <label class="iz-label">Status</label>
          <select v-model="filterStatus" class="iz-select">
            <option value="">All</option>
            <option value="open">Open</option>
            <option value="done">Done</option>
            <option value="archived">Archived</option>
          </select>
        </div>
        <div class="task-browser__filter">
          <label class="iz-label">Stack</label>
          <select v-model="filterStack" class="iz-select">
            <option value="">All</option>
            <option v-for="s in stackOptions" :key="s" :value="s">
              {{ s }}
            </option>
          </select>
        </div>
        <div class="task-browser__filter">
          <label class="iz-label">Label</label>
          <select v-model="filterLabel" class="iz-select">
            <option value="">All</option>
            <option v-for="l in labelOptions" :key="l" :value="l">
              {{ l }}
            </option>
          </select>
        </div>
        <div class="task-browser__filter">
          <label class="iz-label">Assignee</label>
          <select v-model="filterAssignee" class="iz-select">
            <option value="">All</option>
            <option v-for="a in assigneeOptions" :key="a" :value="a">
              {{ a }}
            </option>
          </select>
        </div>
        <div class="task-browser__filter">
          <label class="iz-label">Due</label>
          <select v-model="filterDue" class="iz-select">
            <option value="">All</option>
            <option value="overdue">Overdue</option>
            <option value="today">Today</option>
            <option value="tomorrow">Tomorrow</option>
            <option value="nextSevenDays">Next 7 Days</option>
            <option value="later">Later</option>
            <option value="nodue">No Due Date</option>
          </select>
        </div>
      </div>

      <div class="task-browser__count">
        {{ filteredTasks.length }} of {{ tasks.length }} tasks
      </div>

      <div class="iz-table-wrap">
        <table class="iz-table">
          <thead>
            <tr>
              <th
                class="iz-table__sort"
                :class="{ 'iz-table__sort--active': sortKey === 'title' }"
                @click="toggleSort('title')"
              >
                Task
                <span class="task-browser__sort-arrow">{{ sortArrow('title') }}</span>
              </th>
              <th>Stack</th>
              <th
                class="iz-table__sort"
                :class="{ 'iz-table__sort--active': sortKey === 'status' }"
                @click="toggleSort('status')"
              >
                Status
                <span class="task-browser__sort-arrow">{{ sortArrow('status') }}</span>
              </th>
              <th>Labels</th>
              <th>Assignees</th>
              <th
                class="iz-table__sort"
                :class="{ 'iz-table__sort--active': sortKey === 'due' }"
                @click="toggleSort('due')"
              >
                Due Date
                <span class="task-browser__sort-arrow">{{ sortArrow('due') }}</span>
              </th>
              <th
                class="iz-table__sort"
                :class="{ 'iz-table__sort--active': sortKey === 'age' }"
                @click="toggleSort('age')"
              >
                Opened
                <span class="task-browser__sort-arrow">{{ sortArrow('age') }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredTasks.length === 0">
              <td colspan="7" class="task-browser__empty">
                No tasks match the current filters
              </td>
            </tr>
            <tr v-for="task in paginatedTasks" :key="'tb-' + task.id">
              <td class="task-browser__cell-title">{{ task.title }}</td>
              <td>
                <span class="iz-badge iz-badge--accent">{{ task.stack }}</span>
              </td>
              <td>
                <span class="iz-badge" :class="statusToneClass(task.status)">{{
                  task.status
                }}</span>
              </td>
              <td>
                <span
                  v-for="lbl in task.labels"
                  :key="lbl"
                  class="iz-badge iz-badge--cat-5 task-browser__label-badge"
                >{{ lbl }}</span>
                <span v-if="!task.labels.length" class="task-browser__muted">&mdash;</span>
              </td>
              <td>
                <span v-if="task.assignees.length">{{ task.assignees.join(', ') }}</span>
                <span v-else class="task-browser__muted">&mdash;</span>
              </td>
              <td>
                <span
                  v-if="task.due"
                  class="task-browser__due"
                  :class="'task-browser__due--' + task.dueBucket"
                >{{ formatDate(task.due) }}</span>
                <span v-else class="task-browser__muted">&mdash;</span>
              </td>
              <td>
                <span
                  v-if="task.createdAt"
                  class="task-browser__age"
                  :title="formatDate(task.createdAt)"
                >{{ taskAge(task.createdAt) }}</span>
                <span v-else class="task-browser__muted">&mdash;</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="totalPages > 1" class="iz-pagination task-browser__pagination">
        <button
          class="iz-btn iz-btn--sm"
          :disabled="page <= 1"
          @click="page--"
        >
          &lsaquo; Prev
        </button>
        <span class="task-browser__page-info">
          Page {{ page }} of {{ totalPages }}
        </span>
        <button
          class="iz-btn iz-btn--sm"
          :disabled="page >= totalPages"
          @click="page++"
        >
          Next &rsaquo;
        </button>
      </div>
    </template>
  </div>
</template>

<script>
export default {
  name: "ProjectTaskBrowser",
  props: {
    tasks: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
  },
  data() {
    return {
      filterName: "",
      filterStatus: "",
      filterStack: "",
      filterLabel: "",
      filterAssignee: "",
      filterDue: "",
      sortKey: "",
      sortDir: "asc",
      page: 1,
      pageSize: 15,
    };
  },
  watch: {
    filterName() { this.page = 1; },
    filterStatus() { this.page = 1; },
    filterStack() { this.page = 1; },
    filterLabel() { this.page = 1; },
    filterAssignee() { this.page = 1; },
    filterDue() { this.page = 1; },
    tasks() { this.page = 1; },
  },
  computed: {
    stackOptions() {
      const set = {};
      this.tasks.forEach((t) => { if (t.stack) set[t.stack] = true; });
      return Object.keys(set).sort();
    },
    labelOptions() {
      const set = {};
      this.tasks.forEach((t) => (t.labels || []).forEach((l) => { set[l] = true; }));
      return Object.keys(set).sort();
    },
    assigneeOptions() {
      const set = {};
      this.tasks.forEach((t) => (t.assignees || []).forEach((a) => { set[a] = true; }));
      return Object.keys(set).sort();
    },
    filteredTasks() {
      const name = this.filterName.toLowerCase();
      return this.tasks.filter((t) => {
        if (name && (t.title || "").toLowerCase().indexOf(name) === -1) return false;
        if (this.filterStatus && t.status !== this.filterStatus) return false;
        if (this.filterStack && t.stack !== this.filterStack) return false;
        if (this.filterLabel && (t.labels || []).indexOf(this.filterLabel) === -1) return false;
        if (this.filterAssignee && (t.assignees || []).indexOf(this.filterAssignee) === -1) return false;
        if (this.filterDue && t.dueBucket !== this.filterDue) return false;
        return true;
      });
    },
    sortedTasks() {
      if (!this.sortKey) return this.filteredTasks;
      const key = this.sortKey;
      const dir = this.sortDir === "asc" ? 1 : -1;
      const arr = this.filteredTasks.slice();
      arr.sort((a, b) => {
        let va, vb;
        if (key === "title") {
          va = (a.title || "").toLowerCase();
          vb = (b.title || "").toLowerCase();
        } else if (key === "status") {
          const order = { open: 1, done: 2, archived: 3 };
          va = order[a.status] || 4;
          vb = order[b.status] || 4;
        } else if (key === "due") {
          va = a.due ? new Date(a.due).getTime() : 9999999999999;
          vb = b.due ? new Date(b.due).getTime() : 9999999999999;
        } else if (key === "age") {
          va = a.createdAt ? new Date(a.createdAt).getTime() : 9999999999999;
          vb = b.createdAt ? new Date(b.createdAt).getTime() : 9999999999999;
        }
        if (va < vb) return -1 * dir;
        if (va > vb) return 1 * dir;
        return 0;
      });
      return arr;
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.sortedTasks.length / this.pageSize));
    },
    paginatedTasks() {
      const start = (this.page - 1) * this.pageSize;
      return this.sortedTasks.slice(start, start + this.pageSize);
    },
  },
  methods: {
    // Deck status -> In Zicht badge tone. Kept as a map (not string concat)
    // so an unknown status degrades to the neutral tone instead of emitting a
    // class that doesn't exist.
    statusToneClass(status) {
      return (
        {
          open: "iz-badge--success",
          done: "iz-badge--cat-5",
          archived: "iz-badge--muted",
        }[status] || "iz-badge--muted"
      );
    },
    toggleSort(key) {
      if (this.sortKey === key) {
        this.sortDir = this.sortDir === "asc" ? "desc" : "asc";
      } else {
        this.sortKey = key;
        this.sortDir = "asc";
      }
      this.page = 1;
    },
    sortArrow(key) {
      if (this.sortKey !== key) return "↕";
      return this.sortDir === "asc" ? "↑" : "↓";
    },
    formatDate(d) {
      if (!d) return "—";
      const date = new Date(d);
      if (isNaN(date.getTime())) return d;
      return date.toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      });
    },
    taskAge(createdAt) {
      if (!createdAt) return "—";
      const created = new Date(createdAt);
      if (isNaN(created.getTime())) return "—";
      const diffMs = Date.now() - created.getTime();
      const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
      if (days < 1) return "Today";
      if (days === 1) return "1 day";
      if (days < 7) return days + " days";
      const weeks = Math.floor(days / 7);
      if (weeks < 5) return weeks + (weeks === 1 ? " week" : " weeks");
      const months = Math.floor(days / 30);
      if (months < 12) return months + (months === 1 ? " month" : " months");
      const years = Math.floor(days / 365);
      return years + (years === 1 ? " year" : " years");
    },
  },
};
</script>

<style scoped>
/* .task-browser is .iz-stack--tight; loading/error states are .iz-empty/.iz-error. */

.task-browser__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

/* Layout only — the field chrome comes from .iz-label / .iz-input / .iz-select. */
.task-browser__filter {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 120px;
  max-width: 200px;
}
.task-browser__filter .iz-label {
  margin-bottom: 0;
}

.task-browser__count {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-muted);
}

/* Table chrome is .iz-table-wrap / .iz-table; only column behaviour is local. */
.task-browser__sort-arrow {
  font-size: var(--iz-fs-micro);
  margin-left: 2px;
}

.task-browser__cell-title {
  font-weight: 500;
  max-width: 260px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Labels sit several to a cell, so they need the gap the shared badge omits. */
.task-browser__label-badge {
  margin-right: 4px;
}

.task-browser__muted {
  color: var(--color-text-muted);
}

.task-browser__due--overdue {
  color: var(--color-danger-text);
  font-weight: 600;
}

.task-browser__due--today {
  color: var(--color-warning-text);
  font-weight: 600;
}

.task-browser__due--tomorrow {
  color: var(--color-warning-text);
}

.task-browser__empty {
  text-align: center;
  padding: 24px 12px;
  color: var(--color-text-muted);
  font-style: italic;
}

.task-browser__age {
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  color: var(--color-text-secondary);
  white-space: nowrap;
}

/* Centred rather than the shared space-between: this pager has no page list. */
.task-browser__pagination {
  justify-content: center;
  gap: 12px;
}

.task-browser__page-info {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-muted);
}
</style>
