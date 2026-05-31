<template>
  <div class="platform-kpi-strip">
    <KpiCard title="Organizations" icon-color="#4a90d9">
      <div class="kpi-card__metrics">
        <button
          type="button"
          class="kpi-link kpi-card__metric"
          @click="$emit('drill-down', { statusFilter: 'all' })"
        >
          <span class="kpi-card__metric-value">{{ kpis.orgs.total }}</span>
          <span class="kpi-card__metric-label">total</span>
        </button>
        <button
          type="button"
          class="kpi-link kpi-card__metric"
          @click="$emit('drill-down', { statusFilter: 'active' })"
        >
          <span class="kpi-card__metric-value">{{ kpis.orgs.active }}</span>
          <span class="kpi-card__metric-label">active</span>
        </button>
        <button
          type="button"
          class="kpi-link kpi-card__metric"
          @click="$emit('drill-down', { statusFilter: 'paused' })"
        >
          <span class="kpi-card__metric-value">{{ kpis.orgs.paused }}</span>
          <span class="kpi-card__metric-label">paused</span>
        </button>
      </div>
    </KpiCard>
    <KpiCard title="Human Resources" icon-color="#8b5cf6">
      <div class="kpi-card__metrics">
        <button
          type="button"
          class="kpi-link kpi-card__metric"
          @click="$emit('drill-down', { sortBy: 'membersDesc' })"
        >
          <span class="kpi-card__metric-value">{{ kpis.members.admins }}</span>
          <span class="kpi-card__metric-label">admins</span>
        </button>
        <button
          type="button"
          class="kpi-link kpi-card__metric"
          @click="$emit('drill-down', { sortBy: 'membersDesc' })"
        >
          <span class="kpi-card__metric-value">{{ kpis.members.members }}</span>
          <span class="kpi-card__metric-label">members</span>
        </button>
        <button
          type="button"
          class="kpi-link kpi-card__metric"
          @click="$emit('drill-down', { sortBy: 'projectsDesc' })"
        >
          <span class="kpi-card__metric-value">{{ kpis.projects.active }}</span>
          <span class="kpi-card__metric-label">projects</span>
        </button>
      </div>
    </KpiCard>
    <KpiCard title="Projects" icon-color="#f59e0b">
      <div class="projects-kpi">
        <div class="projects-kpi__hero">
          <button
            type="button"
            class="kpi-link"
            @click="$emit('drill-down', { sortBy: 'projectsDesc' })"
          >
            <span class="projects-kpi__hero-value">{{ kpis.projects.total }}</span>
            <span class="projects-kpi__hero-label">projects</span>
          </button>
          <span class="projects-kpi__hero-sep">·</span>
          <button
            type="button"
            class="kpi-link projects-kpi__hero-tasks"
            @click="$emit('drill-down', { sortBy: 'tasksDesc' })"
          >
            <strong>{{ kpis.tasks.total }}</strong> tasks
          </button>
        </div>

        <div
          class="projects-kpi__bar"
          role="img"
          :aria-label="barAriaLabel"
        >
          <div
            v-if="kpis.tasks.done > 0"
            class="projects-kpi__seg projects-kpi__seg--done"
            :style="{ flex: kpis.tasks.done }"
            :title="kpis.tasks.done + ' done'"
          ></div>
          <div
            v-if="kpis.tasks.overdue > 0"
            class="projects-kpi__seg projects-kpi__seg--overdue"
            :style="{ flex: kpis.tasks.overdue }"
            :title="kpis.tasks.overdue + ' overdue'"
          ></div>
          <div
            v-if="openTasks > 0"
            class="projects-kpi__seg projects-kpi__seg--open"
            :style="{ flex: openTasks }"
            :title="openTasks + ' open'"
          ></div>
        </div>

        <div class="projects-kpi__legend">
          <button
            type="button"
            class="kpi-link projects-kpi__legend-item"
            @click="$emit('drill-down', { sortBy: 'doneDesc' })"
          >
            <span class="projects-kpi__dot projects-kpi__dot--done"></span>
            <strong>{{ kpis.tasks.done }}</strong> done
          </button>
          <button
            type="button"
            class="kpi-link projects-kpi__legend-item"
            @click="$emit('drill-down', { sortBy: 'overdueDesc' })"
          >
            <span class="projects-kpi__dot projects-kpi__dot--overdue"></span>
            <strong>{{ kpis.tasks.overdue }}</strong> overdue
          </button>
          <button
            type="button"
            class="kpi-link projects-kpi__legend-item"
            @click="$emit('drill-down', { sortBy: 'openDesc' })"
          >
            <span class="projects-kpi__dot projects-kpi__dot--open"></span>
            <strong>{{ openTasks }}</strong> open
          </button>
        </div>
      </div>
    </KpiCard>
    <KpiCard
      title="Financial"
      icon-color="#2e9e5a"
      :metrics="[
        { value: mrrDisplay, label: 'MRR' },
        { value: arrDisplay, label: 'ARR' },
        { value: kpis.orgs.active, label: 'paying' },
      ]"
    />
  </div>
</template>

<script>
import KpiCard from "./KpiCard.vue";

export default {
  name: "PlatformKpiStrip",
  components: { KpiCard },
  props: {
    kpis: {
      type: Object,
      required: true,
    },
  },
  computed: {
    openTasks() {
      const total = this.kpis.tasks.total || 0;
      const done = this.kpis.tasks.done || 0;
      const overdue = this.kpis.tasks.overdue || 0;
      return Math.max(0, total - done - overdue);
    },
    barAriaLabel() {
      const d = this.kpis.tasks.done || 0;
      const o = this.kpis.tasks.overdue || 0;
      return `${d} done, ${o} overdue, ${this.openTasks} open`;
    },
    mrrDisplay() {
      return this.formatMoney(this.kpis.mrr.value);
    },
    arrDisplay() {
      return this.formatMoney((this.kpis.mrr.value || 0) * 12);
    },
  },
  methods: {
    formatMoney(amount) {
      const v = Math.round(Number(amount) || 0);
      const sym =
        this.kpis.mrr.currency === "EUR"
          ? "€"
          : this.kpis.mrr.currency === "USD"
            ? "$"
            : this.kpis.mrr.currency + " ";
      const prefix = this.kpis.mrr.multiCurrency ? "~" : "";
      return prefix + sym + v.toLocaleString();
    },
  },
};
</script>

<style scoped>
.platform-kpi-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--spacing-md, 16px);
}

.projects-kpi {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.projects-kpi__hero {
  display: flex;
  align-items: baseline;
  gap: 6px;
  flex-wrap: wrap;
}

.projects-kpi__hero-value {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
}

.projects-kpi__hero-label {
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
}

.projects-kpi__hero-sep {
  color: #cfd6e0;
  font-size: 13px;
  user-select: none;
}

.projects-kpi__hero-tasks {
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
  font-variant-numeric: tabular-nums;
}

.projects-kpi__hero-tasks strong {
  color: var(--color-text-primary, #1a1a2e);
  font-weight: 700;
}

.projects-kpi__bar {
  display: flex;
  height: 8px;
  border-radius: 4px;
  background: #f0f1f5;
  overflow: hidden;
}

.projects-kpi__seg {
  height: 100%;
  min-width: 2px;
  transition: flex-grow 0.3s ease;
}

.projects-kpi__seg--done {
  background: #10b981;
}

.projects-kpi__seg--overdue {
  background: #ef4444;
}

.projects-kpi__seg--open {
  background: #4a90d9;
}

.projects-kpi__legend {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  font-size: 11px;
  color: var(--color-text-secondary, #6b7280);
  font-variant-numeric: tabular-nums;
}

.projects-kpi__legend-item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.projects-kpi__legend-item strong {
  color: var(--color-text-primary, #1a1a2e);
  font-weight: 700;
}

.projects-kpi__dot {
  width: 8px;
  height: 8px;
  border-radius: 2px;
  flex-shrink: 0;
}

.projects-kpi__dot--done {
  background: #10b981;
}

.projects-kpi__dot--overdue {
  background: #ef4444;
}

.projects-kpi__dot--open {
  background: #4a90d9;
}

@media (max-width: 1200px) {
  .platform-kpi-strip {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .platform-kpi-strip {
    grid-template-columns: 1fr;
  }
}

.kpi-link {
  background: transparent;
  border: 0;
  padding: 4px 8px;
  margin: -4px -8px;
  cursor: pointer;
  border-radius: 8px;
  font: inherit;
  color: inherit;
  text-align: left;
  transition: background 0.15s ease;
}

.kpi-link:hover {
  background: rgba(74, 144, 217, 0.08);
}

.kpi-link:focus-visible {
  outline: 2px solid #4a90d9;
  outline-offset: 2px;
}

.kpi-link.kpi-card__metric {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 14px;
  margin: 0;
  border-left: 1px solid var(--color-border, #e5e7eb);
  border-radius: 0;
}

.kpi-link.kpi-card__metric:hover {
  background: rgba(74, 144, 217, 0.08);
}

.kpi-card__metrics > .kpi-link.kpi-card__metric:first-child {
  padding-left: 0;
  border-left: none;
}
</style>
