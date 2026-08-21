<template>
  <div class="platform-kpi-strip">
    <KpiCard
      v-for="card in cards"
      :key="card.key"
      :title="card.title"
      :icon-color="card.color"
    >
      <template #icon>
        <svg
          class="platform-kpi-strip__icon"
          :style="{ color: card.color }"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <template v-if="card.key === 'governance'">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
          </template>
          <template v-else-if="card.key === 'adoption'">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
          </template>
          <template v-else-if="card.key === 'capacity'">
            <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
          </template>
          <template v-else>
            <path d="M3 12a9 9 0 1 0 9-9 9 9 0 0 0-6.36 2.64L3 8" />
            <polyline points="3 3 3 8 8 8" />
          </template>
        </svg>
      </template>

      <div class="platform-kpi-strip__metrics">
        <button
          v-for="metric in card.metrics"
          :key="metric.label"
          type="button"
          class="kpi-link platform-kpi-strip__metric"
          :class="metricClass(metric)"
          :disabled="!metric.value || !metric.filter"
          :title="metric.hint"
          @click="pick(metric)"
        >
          <span class="platform-kpi-strip__value">{{ metric.value }}</span>
          <span class="platform-kpi-strip__label">{{ metric.label }}</span>
        </button>
      </div>

      <span class="platform-kpi-strip__foot">{{ card.foot }}</span>
    </KpiCard>
  </div>
</template>

<script>
import KpiCard from "./KpiCard.vue";

// Tone per card, as an explicit table rather than a class assembled from data.
const TONE = {
  clear: "var(--color-success)",
  warn: "var(--color-warning-text)",
  bad: "var(--color-danger)",
};

export default {
  name: "PlatformKpiStrip",
  components: { KpiCard },
  props: {
    kpis: {
      type: Object,
      required: true,
    },
    /** Counts of organizations in a state somebody has to resolve. */
    attention: {
      type: Object,
      default: () => ({}),
    },
    /** The platform alert set, for the two figures that already live there. */
    alerts: {
      type: Object,
      default: () => ({}),
    },
    /**
     * The roster rows. Storage is counted here rather than server-side so the
     * figure is derived from exactly the rows the filter will select — the
     * usage percentages come from StorageUsageService per row, and a second
     * server-side count could drift from them.
     */
    orgs: {
      type: Array,
      default: () => [],
    },
  },
  computed: {
    capacity() {
      return this.attention.capacity || { atCap: 0, nearCap: 0 };
    },
    capacityColor() {
      if (this.capacity.atCap) return TONE.bad;
      return this.capacity.nearCap || this.storageHigh ? TONE.warn : TONE.clear;
    },
    noPlan() {
      return (this.alerts.orgsNoSub && this.alerts.orgsNoSub.count) || 0;
    },
    // Organizations owning a stale project — NOT the count of stale projects
    // the health alert reports. Every figure on this strip filters the roster
    // to exactly that many rows, so it has to count the same things the roster
    // lists.
    staleOrgs() {
      return this.attention.staleOrgs || 0;
    },
    retention() {
      return this.attention.retention || { dormant: 0, never: 0 };
    },
    storageHigh() {
      return this.orgs.filter(
        (o) => o.storage && o.storage.percentage >= 80
      ).length;
    },
    // Every figure here is a count of things to go and fix, and every one of
    // them filters the roster below — which is the whole reason the strip earns
    // its height. Totals that only restate the list live in the list.
    cards() {
      return [
        {
          key: "governance",
          title: "Governance",
          color: this.attention.noAdmin || this.noPlan ? TONE.bad : TONE.clear,
          foot: "nobody can administer these",
          metrics: [
            {
              value: this.attention.noAdmin || 0,
              label: "no admin",
              filter: "noAdmin",
              tone: "bad",
              hint: "Organizations with no member holding the admin role",
            },
            {
              value: this.noPlan,
              label: "no plan",
              filter: "noPlan",
              tone: "bad",
              hint: "Organizations without an active subscription",
            },
          ],
        },
        {
          key: "adoption",
          title: "Adoption",
          color: this.attention.noProjects || this.staleOrgs ? TONE.warn : TONE.clear,
          foot: "onboarded but not started",
          metrics: [
            {
              value: this.attention.noProjects || 0,
              label: "no projects",
              filter: "noProjects",
              tone: "warn",
              hint: "Organizations that have never created a project",
            },
            {
              value: this.staleOrgs,
              label: "stale >30d",
              filter: "staleProjects",
              tone: "warn",
              hint: "Organizations with a project idle for more than 30 days",
            },
          ],
        },
        {
          key: "capacity",
          title: "Capacity",
          color: this.capacityColor,
          foot: "members, projects and storage",
          metrics: [
            {
              value: this.capacity.atCap,
              label: "at cap",
              filter: "atCap",
              tone: "bad",
              hint: "Active organizations at or over a limit their plan sells",
            },
            {
              value: this.capacity.nearCap,
              label: "80–99%",
              filter: "nearCap",
              tone: "warn",
              hint: "Active organizations approaching a member or project limit",
            },
            {
              value: this.storageHigh,
              label: "storage 80%+",
              filter: "storageHigh",
              tone: "warn",
              hint: "Organizations using more than 80% of their storage entitlement",
            },
          ],
        },
        {
          key: "retention",
          title: "Retention",
          color: this.retention.never ? TONE.bad : this.retention.dormant ? TONE.warn : TONE.clear,
          foot: "last login by any member",
          metrics: [
            {
              value: this.retention.dormant,
              label: "dormant >30d",
              filter: "dormant",
              tone: "warn",
              hint: "Organizations whose most recent member login was over 30 days ago",
            },
            {
              value: this.retention.never,
              label: "never used",
              filter: "neverUsed",
              tone: "bad",
              hint: "Organizations no member has ever signed into",
            },
          ],
        },
      ];
    },
  },
  methods: {
    metricClass(metric) {
      return {
        "platform-kpi-strip__metric--bad": metric.value > 0 && metric.tone === "bad",
        "platform-kpi-strip__metric--warn": metric.value > 0 && metric.tone === "warn",
        "platform-kpi-strip__metric--inert": !metric.filter,
      };
    },
    // A zero is not worth a click — there is nothing to show — and a metric with
    // no filter (an aggregate the roster cannot select on) is a readout only.
    pick(metric) {
      if (!metric.value || !metric.filter) return;
      this.$emit("drill-down", { statusFilter: "all", attentionFilter: metric.filter });
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
@media (max-width: 1100px) {
  .platform-kpi-strip {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 620px) {
  .platform-kpi-strip {
    grid-template-columns: 1fr;
  }
}

.platform-kpi-strip__icon {
  width: 18px;
  height: 18px;
}

.platform-kpi-strip__metrics {
  display: flex;
  gap: 18px;
  flex-wrap: wrap;
}

/* Qualified on `button` with min-height reset: Nextcloud core styles bare
   buttons with padding and min-height: var(--default-clickable-area), which
   would push these out of the card. */
button.platform-kpi-strip__metric {
  font: inherit;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0;
  margin: 0;
  padding: 2px 4px;
  min-height: 0;
  border: 0;
  border-radius: var(--radius-sm);
  background: transparent;
  color: inherit;
  cursor: pointer;
  text-align: left;
}
button.platform-kpi-strip__metric:hover:not(:disabled) {
  background: var(--accent-bg);
}
button.platform-kpi-strip__metric:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}
/* A zero has nothing to filter to, and an aggregate has nothing to filter on:
   both stay readable but stop looking clickable. */
button.platform-kpi-strip__metric:disabled,
button.platform-kpi-strip__metric--inert {
  cursor: default;
  background: transparent;
}

.platform-kpi-strip__value {
  font-family: 'Space Grotesk', system-ui, -apple-system, sans-serif;
  font-size: var(--iz-fs-xl);
  font-weight: 700;
  line-height: 1.15;
  font-variant-numeric: tabular-nums;
  color: var(--color-text-primary);
}
.platform-kpi-strip__metric--bad .platform-kpi-strip__value {
  color: var(--color-danger-text);
}
.platform-kpi-strip__metric--warn .platform-kpi-strip__value {
  color: var(--color-warning-text);
}

.platform-kpi-strip__label {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted);
  white-space: nowrap;
}

.platform-kpi-strip__foot {
  margin-top: auto;
  padding-top: 8px;
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted);
}
</style>
