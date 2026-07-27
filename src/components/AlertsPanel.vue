<template>
  <section class="alerts-panel">
    <div class="iz-stat-grid">
      <KpiCard
        v-for="(alert, key) in alerts"
        :key="key"
        :title="alert.label"
        :icon-color="toneColor(alert.tone)"
      >
        <template #icon>
          <svg
            class="alerts-panel__icon"
            :style="{ color: toneColor(alert.tone) }"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <template v-if="key === 'failedBackups7d'">
              <line x1="22" y1="12" x2="2" y2="12" />
              <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
            </template>
            <template v-else-if="key === 'stuckAhoJobs'">
              <polyline points="17 1 21 5 17 9" />
              <path d="M3 11V9a4 4 0 0 1 4-4h14" />
              <polyline points="7 23 3 19 7 15" />
              <path d="M21 13v2a4 4 0 0 1-4 4H3" />
            </template>
            <template v-else-if="key === 'staleProjects30d'">
              <circle cx="12" cy="12" r="10" />
              <polyline points="12 6 12 12 16 14" />
            </template>
            <template v-else-if="key === 'orgsNoSub'">
              <rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
              <line x1="1" y1="10" x2="23" y2="10" />
            </template>
            <template v-else>
              <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
              <line x1="12" y1="9" x2="12" y2="13" />
              <line x1="12" y1="17" x2="12.01" y2="17" />
            </template>
          </svg>
        </template>

        <div class="alerts-panel__metrics">
          <div class="alerts-panel__metric">
            <span class="alerts-panel__metric-value">{{ alert.count }}</span>
            <span class="alerts-panel__metric-label">{{ alert.tone === "success" ? "all clear" : "to review" }}</span>
          </div>
        </div>
      </KpiCard>
    </div>
  </section>
</template>

<script>
import KpiCard from "./KpiCard.vue";

// Explicit tone → colour table. Mapping through a table (rather than building
// a var name from `alert.tone`) keeps an unexpected tone falling back to the
// accent instead of resolving to nothing.
const TONE_COLORS = {
  success: "var(--color-success)",
  warning: "var(--color-warning-text)",
  danger: "var(--color-danger)",
};

export default {
  name: "AlertsPanel",
  components: { KpiCard },
  props: {
    alerts: {
      type: Object,
      required: true,
    },
  },
  methods: {
    toneColor(tone) {
      return TONE_COLORS[tone] || "var(--accent)";
    },
  },
};
</script>

<style scoped>
/* The cards themselves are KpiCard — same surface, padding, header and hover
   lift as the Organization KPI strip, so the two read as one system. Only the
   slotted icon and value need sizing here, since slot content is compiled in
   this component's scope and so cannot pick up KpiCard's scoped rules. */
.alerts-panel__icon {
  width: 18px;
  height: 18px;
}

/* Alert labels are sentences, so some wrap to two lines. Make the card a
   column and push the value to the bottom, otherwise the numbers sit at
   different heights across the row. (A child component's root element carries
   the parent's scope id, so this reaches KpiCard's root.) */
.kpi-card {
  display: flex;
  flex-direction: column;
}

.alerts-panel__metrics {
  display: flex;
  margin-top: auto;
}

.alerts-panel__metric {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.alerts-panel__metric-value {
  font-size: var(--iz-fs-xl);
  font-weight: 700;
  color: var(--color-text-primary, var(--color-text-primary));
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
}

.alerts-panel__metric-label {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted, var(--color-text-muted));
  line-height: 1.3;
}
</style>
