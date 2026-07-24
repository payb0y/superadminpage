<template>
  <section class="alerts-panel iz-panel">
    <header class="alerts-panel__header">
      <h3 class="alerts-panel__title">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
          <line x1="12" y1="9" x2="12" y2="13" />
          <line x1="12" y1="17" x2="12.01" y2="17" />
        </svg>
        Platform Alerts
      </h3>
      <span class="alerts-panel__summary">
        {{ totalIssues }} {{ totalIssues === 1 ? "issue" : "issues" }}
      </span>
    </header>

    <div class="alerts-panel__grid">
      <div
        v-for="(alert, key) in alerts"
        :key="key"
        class="alerts-panel__card"
        :class="'alerts-panel__card--' + alert.tone"
      >
        <div class="alerts-panel__card-value">{{ alert.count }}</div>
        <div class="alerts-panel__card-label">{{ alert.label }}</div>
      </div>
    </div>
  </section>
</template>

<script>
export default {
  name: "AlertsPanel",
  props: {
    alerts: {
      type: Object,
      required: true,
    },
  },
  computed: {
    totalIssues() {
      let total = 0;
      for (const key in this.alerts) {
        const a = this.alerts[key];
        if (a.tone !== "success" && a.count > 0) total += a.count;
      }
      return total;
    },
  },
};
</script>

<style scoped>

.alerts-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--spacing-md, 16px);
}

.alerts-panel__title {
  font-size: var(--iz-fs-md);
  font-weight: 600;
  color: var(--color-text-secondary, var(--color-text-secondary));
  text-transform: uppercase;
  letter-spacing: 0.4px;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.alerts-panel__title svg {
  color: var(--color-warning-text);
}

.alerts-panel__summary {
  font-size: var(--iz-fs-sm);
  font-weight: 600;
  color: var(--color-text-muted, var(--color-text-muted));
}

.alerts-panel__grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--spacing-md, 16px);
}

.alerts-panel__card {
  border-radius: var(--radius-lg);
  padding: 14px 16px;
  border: 1px solid transparent;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.alerts-panel__card--success {
  background: var(--color-badge-success-bg, var(--color-success-bg));
  border-color: var(--color-success-bg);
  color: var(--color-badge-success-text, var(--color-success-text));
}

.alerts-panel__card--warning {
  background: var(--color-badge-warning-bg, var(--color-warning-bg));
  border-color: var(--color-warning-bg);
  color: var(--color-badge-warning-text, var(--color-warning-text));
}

.alerts-panel__card--danger {
  background: var(--color-badge-danger-bg, var(--color-danger-bg));
  border-color: var(--color-danger-bg);
  color: var(--color-badge-danger-text, var(--color-danger-text));
}

.alerts-panel__card-value {
  font-size: var(--iz-fs-2xl);
  font-weight: 700;
  line-height: 1.1;
}

.alerts-panel__card-label {
  font-size: var(--iz-fs-xs);
  font-weight: 500;
  opacity: 0.9;
}

@media (max-width: 1200px) {
  .alerts-panel__grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 600px) {
  .alerts-panel__grid {
    grid-template-columns: 1fr;
  }
}
</style>
