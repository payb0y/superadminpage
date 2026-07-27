<template>
  <div class="platform-kpi-strip">
    <KpiCard title="Organizations" icon-color="var(--accent)">
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
    <KpiCard title="Human Resources" icon-color="var(--chart-5)">
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
    <KpiCard title="Projects" icon-color="var(--color-warning-text)">
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

        <div class="projects-kpi__chips">
          <button
            type="button"
            class="projects-kpi__chip"
            @click="$emit('drill-down', { sortBy: 'doneDesc' })"
          >
            <span class="iz-badge iz-badge--success"><strong>{{ kpis.tasks.done }}</strong> done</span>
          </button>
          <button
            type="button"
            class="projects-kpi__chip"
            @click="$emit('drill-down', { sortBy: 'overdueDesc' })"
          >
            <span class="iz-badge iz-badge--danger"><strong>{{ kpis.tasks.overdue }}</strong> overdue</span>
          </button>
          <button
            type="button"
            class="projects-kpi__chip"
            @click="$emit('drill-down', { sortBy: 'openDesc' })"
          >
            <span class="iz-badge iz-badge--accent"><strong>{{ openTasks }}</strong> open</span>
          </button>
        </div>
      </div>
    </KpiCard>
    <KpiCard
      title="Financial"
      icon-color="var(--color-success)"
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
  font-size: var(--iz-fs-xl);
  font-weight: 700;
  color: var(--color-text-primary, var(--color-text-primary));
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
}

.projects-kpi__hero-label {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted, var(--color-text-muted));
}

.projects-kpi__hero-sep {
  color: var(--color-border-strong);
  font-size: var(--iz-fs-md);
  user-select: none;
}

.projects-kpi__hero-tasks {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary, var(--color-text-secondary));
  font-variant-numeric: tabular-nums;
}

.projects-kpi__hero-tasks strong {
  color: var(--color-text-primary, var(--color-text-primary));
  font-weight: 700;
}

/* Status chips: chrome (tint + text) comes from the theme's .iz-badge--*
   primitive. The chip is a bare drill-down button that never changes on
   hover/focus/active — the badge is the whole visual — so clicking one leaves
   it looking exactly as it did. Only a keyboard focus ring remains, for a11y
   (mouse clicks don't trigger :focus-visible). Selectors are qualified on
   `button` to beat NC core's bare-element button styling. */
.projects-kpi__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

button.projects-kpi__chip {
  padding: 0;
  margin: 0;
  border: 0;
  background: transparent;
  border-radius: var(--iz-radius-pill);
  cursor: pointer;
  font: inherit;
  -webkit-appearance: none;
  appearance: none;
}

button.projects-kpi__chip:hover,
button.projects-kpi__chip:focus,
button.projects-kpi__chip:active {
  background: transparent;
  box-shadow: none;
  outline: none;
}

button.projects-kpi__chip:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}

.projects-kpi__chip .iz-badge {
  font-variant-numeric: tabular-nums;
  transition: transform 0.12s ease, filter 0.12s ease;
}

/* Hover feedback lives on the badge and only while the pointer is over it, so
   it clears the moment you leave — nothing persists after a click. */
button.projects-kpi__chip:hover .iz-badge {
  transform: translateY(-1px);
  filter: brightness(0.96);
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
  border-radius: var(--radius-el);
  font: inherit;
  color: inherit;
  text-align: left;
  transition: background 0.15s ease;
}

.kpi-link:hover {
  background: var(--iz-accent-bg);
}

.kpi-link:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}

.kpi-link.kpi-card__metric {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 14px;
  margin: 0;
  border-left: 1px solid var(--color-border, var(--color-border));
  border-radius: 0;
}

.kpi-link.kpi-card__metric:hover {
  background: var(--iz-accent-bg);
}

.kpi-card__metrics > .kpi-link.kpi-card__metric:first-child {
  padding-left: 0;
  border-left: none;
}
</style>
