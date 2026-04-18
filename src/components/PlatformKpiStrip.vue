<template>
  <div class="platform-kpi-strip">
    <KpiCard
      title="Organizations"
      icon-color="#4a90d9"
      :metrics="[
        { value: kpis.orgs.total, label: 'total' },
        { value: kpis.orgs.active, label: 'active' },
        { value: kpis.orgs.paused, label: 'paused' },
      ]"
    />
    <KpiCard
      title="Financial"
      icon-color="#2e9e5a"
      :metrics="[
        { value: mrrDisplay, label: 'MRR' },
        { value: kpis.orgs.active, label: 'paying' },
      ]"
    />
    <KpiCard
      title="Resources"
      icon-color="#8b5cf6"
      :metrics="[
        { value: kpis.members.total, label: 'members' },
        { value: kpis.projects.active, label: 'projects' },
      ]"
    />
    <KpiCard title="Projects" icon-color="#f59e0b">
      <div class="projects-kpi">
        <div class="projects-kpi__hero">
          <span class="projects-kpi__hero-value">
            {{ kpis.projects.total }}
          </span>
          <span class="projects-kpi__hero-label">projects</span>
          <span class="projects-kpi__hero-sep">·</span>
          <span class="projects-kpi__hero-tasks">
            <strong>{{ kpis.tasks.total }}</strong> tasks
          </span>
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
          <span class="projects-kpi__legend-item">
            <span class="projects-kpi__dot projects-kpi__dot--done"></span>
            <strong>{{ kpis.tasks.done }}</strong> done
          </span>
          <span class="projects-kpi__legend-item">
            <span class="projects-kpi__dot projects-kpi__dot--overdue"></span>
            <strong>{{ kpis.tasks.overdue }}</strong> overdue
          </span>
          <span class="projects-kpi__legend-item">
            <span class="projects-kpi__dot projects-kpi__dot--open"></span>
            <strong>{{ openTasks }}</strong> open
          </span>
        </div>
      </div>
    </KpiCard>
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
      const v = Math.round(this.kpis.mrr.value || 0);
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
</style>
