<template>
  <div class="task-browser">
    <div v-if="loading" class="task-browser__state">Loading tasks…</div>
    <div v-else-if="error" class="task-browser__state task-browser__state--error">
      {{ error }}
    </div>

    <template v-else>
      <div class="task-browser__filters">
        <div class="task-browser__filter">
          <label class="task-browser__label">Search</label>
          <input
            v-model="filterName"
            type="text"
            class="task-browser__input"
            placeholder="Task name…"
          />
        </div>
        <div class="task-browser__filter">
          <label class="task-browser__label">Status</label>
          <select v-model="filterStatus" class="task-browser__select">
            <option value="">All</option>
            <option value="open">Open</option>
            <option value="done">Done</option>
            <option value="archived">Archived</option>
          </select>
        </div>
        <div class="task-browser__filter">
          <label class="task-browser__label">Stack</label>
          <select v-model="filterStack" class="task-browser__select">
            <option value="">All</option>
            <option v-for="s in stackOptions" :key="s" :value="s">
              {{ s }}
            </option>
          </select>
        </div>
        <div class="task-browser__filter">
          <label class="task-browser__label">Label</label>
          <select v-model="filterLabel" class="task-browser__select">
            <option value="">All</option>
            <option v-for="l in labelOptions" :key="l" :value="l">
              {{ l }}
            </option>
          </select>
        </div>
        <div class="task-browser__filter">
          <label class="task-browser__label">Assignee</label>
          <select v-model="filterAssignee" class="task-browser__select">
            <option value="">All</option>
            <option v-for="a in assigneeOptions" :key="a" :value="a">
              {{ a }}
            </option>
          </select>
        </div>
        <div class="task-browser__filter">
          <label class="task-browser__label">Due</label>
          <select v-model="filterDue" class="task-browser__select">
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

      <div class="task-browser__table-wrap">
        <table class="task-browser__table">
          <thead>
            <tr>
              <th
                class="task-browser__th-sort"
                :class="{ 'task-browser__th-sort--active': sortKey === 'title' }"
                @click="toggleSort('title')"
              >
                Task
                <span class="task-browser__sort-arrow">{{ sortArrow('title') }}</span>
              </th>
              <th>Stack</th>
              <th
                class="task-browser__th-sort"
                :class="{ 'task-browser__th-sort--active': sortKey === 'status' }"
                @click="toggleSort('status')"
              >
                Status
                <span class="task-browser__sort-arrow">{{ sortArrow('status') }}</span>
              </th>
              <th>Labels</th>
              <th>Assignees</th>
              <th
                class="task-browser__th-sort"
                :class="{ 'task-browser__th-sort--active': sortKey === 'due' }"
                @click="toggleSort('due')"
              >
                Due Date
                <span class="task-browser__sort-arrow">{{ sortArrow('due') }}</span>
              </th>
              <th
                class="task-browser__th-sort"
                :class="{ 'task-browser__th-sort--active': sortKey === 'age' }"
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
                <span class="task-browser__stack-badge">{{ task.stack }}</span>
              </td>
              <td>
                <span
                  class="task-browser__status"
                  :class="'task-browser__status--' + task.status"
                >{{ task.status }}</span>
              </td>
              <td>
                <span
                  v-for="lbl in task.labels"
                  :key="lbl"
                  class="task-browser__label-badge"
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

      <div v-if="totalPages > 1" class="task-browser__pagination">
        <button
          class="task-browser__page-btn"
          :disabled="page <= 1"
          @click="page--"
        >
          &lsaquo; Prev
        </button>
        <span class="task-browser__page-info">
          Page {{ page }} of {{ totalPages }}
        </span>
        <button
          class="task-browser__page-btn"
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
.task-browser {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.task-browser__state {
  padding: 20px;
  text-align: center;
  color: var(--color-text-muted, #9ca3af);
  font-size: 13px;
}

.task-browser__state--error {
  color: #d94040;
}

.task-browser__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.task-browser__filter {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 120px;
  max-width: 200px;
}

.task-browser__label {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-muted, #9ca3af);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.task-browser__input,
.task-browser__select {
  padding: 7px 10px;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 8px;
  font-size: 13px;
  color: var(--color-text-primary, #1a1a2e);
  background: #fff;
  outline: none;
  transition: border-color 0.15s;
}

.task-browser__input:focus,
.task-browser__select:focus {
  border-color: #4a90d9;
}

.task-browser__count {
  font-size: 12px;
  color: var(--color-text-muted, #9ca3af);
}

.task-browser__table-wrap {
  overflow-x: auto;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 8px;
  background: #fff;
}

.task-browser__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.task-browser__table th,
.task-browser__table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid var(--color-border, #eef1f5);
  color: var(--color-text-primary, #1a1a2e);
}

.task-browser__table th {
  background: #fafbfd;
  white-space: nowrap;
  font-weight: 600;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: var(--color-text-muted, #6b7280);
}

.task-browser__table tbody tr:last-child td {
  border-bottom: none;
}

.task-browser__table tbody tr:hover {
  background: #fafbfd;
}

.task-browser__th-sort {
  cursor: pointer;
  user-select: none;
}

.task-browser__th-sort--active {
  color: #4a90d9;
}

.task-browser__sort-arrow {
  font-size: 10px;
  margin-left: 2px;
}

.task-browser__cell-title {
  font-weight: 500;
  max-width: 260px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-browser__stack-badge {
  display: inline-block;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
  background: #f0f4ff;
  color: #4a90d9;
  white-space: nowrap;
}

.task-browser__status {
  display: inline-block;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
  text-transform: capitalize;
}

.task-browser__status--open {
  background: #d4edda;
  color: #166534;
}

.task-browser__status--done {
  background: #e0e7ff;
  color: #3730a3;
}

.task-browser__status--archived {
  background: #f3f4f6;
  color: #6b7280;
}

.task-browser__label-badge {
  display: inline-block;
  font-size: 10px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
  background: #ede9fe;
  color: #6d28d9;
  margin-right: 4px;
  white-space: nowrap;
}

.task-browser__muted {
  color: var(--color-text-muted, #9ca3af);
}

.task-browser__due--overdue {
  color: #d94040;
  font-weight: 600;
}

.task-browser__due--today {
  color: #b8860b;
  font-weight: 600;
}

.task-browser__due--tomorrow {
  color: #e67e5a;
}

.task-browser__empty {
  text-align: center;
  padding: 24px 12px;
  color: #9ca3af;
  font-style: italic;
}

.task-browser__age {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  white-space: nowrap;
}

.task-browser__pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding-top: 6px;
}

.task-browser__page-btn {
  padding: 5px 14px;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 6px;
  background: #fff;
  font-size: 13px;
  color: var(--color-text-primary, #1a1a2e);
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}

.task-browser__page-btn:hover:not(:disabled) {
  background: #fafbfd;
  border-color: #4a90d9;
}

.task-browser__page-btn:disabled {
  opacity: 0.4;
  cursor: default;
}

.task-browser__page-info {
  font-size: 12px;
  color: var(--color-text-muted, #9ca3af);
}
</style>
