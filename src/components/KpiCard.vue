<template>
  <div class="kpi-card">
    <div class="kpi-card__header">
      <div class="kpi-card__icon" :style="{ backgroundColor: iconBgColor }">
        <svg
          v-if="title === 'Projects'"
          class="kpi-card__icon-svg"
          :style="{ color: iconColor }"
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path
            d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"
          />
        </svg>
        <svg
          v-else-if="title === 'Tasks'"
          class="kpi-card__icon-svg"
          :style="{ color: iconColor }"
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M9 11l3 3L22 4" />
          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
        </svg>
        <svg
          v-else-if="title === 'Resources'"
          class="kpi-card__icon-svg"
          :style="{ color: iconColor }"
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
          <line x1="8" y1="21" x2="16" y2="21" />
          <line x1="12" y1="17" x2="12" y2="21" />
        </svg>
        <svg
          v-else-if="title === 'Financial'"
          class="kpi-card__icon-svg"
          :style="{ color: iconColor }"
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="16" />
          <line x1="8" y1="12" x2="16" y2="12" />
        </svg>
        <svg
          v-else-if="title === 'Timeline'"
          class="kpi-card__icon-svg"
          :style="{ color: iconColor }"
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <circle cx="12" cy="12" r="10" />
          <polyline points="12 6 12 12 16 14" />
        </svg>
        <svg
          v-else
          class="kpi-card__icon-svg"
          :style="{ color: iconColor }"
          xmlns="http://www.w3.org/2000/svg"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path
            d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
          />
          <polyline points="22,6 12,13 2,6" />
        </svg>
      </div>
      <span class="kpi-card__title">{{ title }}</span>
    </div>
    <div class="kpi-card__metrics">
      <div
        v-for="(metric, index) in metrics"
        :key="index"
        class="kpi-card__metric"
      >
        <span class="kpi-card__metric-value">{{ metric.value }}</span>
        <span class="kpi-card__metric-label">{{ metric.label }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "KpiCard",
  props: {
    title: {
      type: String,
      required: true,
    },
    icon: {
      type: String,
      default: "",
    },
    iconColor: {
      type: String,
      default: "#4A90D9",
    },
    metrics: {
      type: Array,
      required: true,
    },
  },
  computed: {
    iconBgColor() {
      // Lighten the icon color for the background circle
      const hex = this.iconColor.replace("#", "");
      const r = parseInt(hex.substr(0, 2), 16);
      const g = parseInt(hex.substr(2, 2), 16);
      const b = parseInt(hex.substr(4, 2), 16);
      return `rgba(${r}, ${g}, ${b}, 0.1)`;
    },
  },
  methods: {
    isLongValue(val) {
      return typeof val === "string" && val.length > 8 && isNaN(Number(val));
    },
  },
};
</script>

<style scoped>
.kpi-card {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  padding: 20px 24px;
  transition: box-shadow 0.2s ease;
}

.kpi-card:hover {
  box-shadow: var(--shadow-card-hover, 0 4px 12px rgba(0, 0, 0, 0.1));
}

.kpi-card__header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.kpi-card__icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.kpi-card__icon-svg {
  width: 18px;
  height: 18px;
}

.kpi-card__title {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.kpi-card__metrics {
  display: flex;
  flex-wrap: wrap;
  gap: 0;
}

.kpi-card__metric {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 14px;
  position: relative;
  border-left: 1px solid var(--color-border, #e5e7eb);
}

.kpi-card__metric:first-child {
  padding-left: 0;
  border-left: none;
}

.kpi-card__metric-value {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  line-height: 1.1;
  white-space: nowrap;
}

.kpi-card__metric-label {
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
  line-height: 1.3;
  font-weight: 400;
  white-space: nowrap;
}
</style>
