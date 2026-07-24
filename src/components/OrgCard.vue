<template>
  <div
    class="org-card"
    :class="{ 'org-card--selected': selected }"
    @click="$emit('click')"
  >
    <div class="org-card__header">
      <span class="iz-identity__avatar iz-identity__avatar--lg">{{ initial }}</span>
      <div class="org-card__title-group">
        <span class="org-card__name">{{ org.name }}</span>
        <div class="org-card__pills">
          <span
            class="iz-pill"
            :class="'iz-pill--' + statusTone"
          >
            <span class="iz-dot"></span>
            {{ statusLabel }}
          </span>
          <span class="iz-pill iz-pill--accent">
            {{ org.planName || "No plan" }}
          </span>
        </div>
      </div>
    </div>

    <div class="org-card__metrics">
      <div class="org-card__metric">
        <span
          class="org-card__metric-value"
          :class="{ 'iz-mark': metricHighlight === 'members' }"
        >{{ org.memberCount }}</span>
        <span class="org-card__metric-label">members</span>
      </div>
      <div class="org-card__metric">
        <span
          class="org-card__metric-value"
          :class="{ 'iz-mark': metricHighlight === 'projects' }"
        >{{ org.projectCount }}</span>
        <span class="org-card__metric-label">projects</span>
      </div>
      <div class="org-card__metric">
        <span class="org-card__metric-value">{{ storageDisplay }}</span>
        <span class="org-card__metric-label">storage</span>
      </div>
    </div>

    <div class="org-card__tasks">
      <span
        class="org-card__task"
        :class="{ 'iz-mark': metricHighlight === 'tasks' }"
      >
        <strong>{{ org.totalTasks || 0 }}</strong> tasks
      </span>
      <span class="org-card__task-sep">·</span>
      <span
        class="org-card__task"
        :class="{ 'iz-mark': metricHighlight === 'done' }"
      >
        <strong>{{ org.doneTasks || 0 }}</strong> done
      </span>
      <span class="org-card__task-sep">·</span>
      <span
        class="org-card__task"
        :class="{ 'iz-mark': metricHighlight === 'overdue' }"
      >
        <strong>{{ org.overdueTasks || 0 }}</strong> overdue
      </span>
      <span class="org-card__task-sep">·</span>
      <span
        class="org-card__task"
        :class="{ 'iz-mark': metricHighlight === 'open' }"
      >
        <strong>{{ openTasks }}</strong> open
      </span>
    </div>
  </div>
</template>

<script>
export default {
  name: "OrgCard",
  props: {
    org: {
      type: Object,
      required: true,
    },
    selected: {
      type: Boolean,
      default: false,
    },
    metricHighlight: {
      type: String,
      default: null,
    },
  },
  computed: {
    openTasks() {
      const total = Number(this.org.totalTasks) || 0;
      const done = Number(this.org.doneTasks) || 0;
      return Math.max(0, total - done);
    },
    initial() {
      return (this.org.name || "?").charAt(0).toUpperCase();
    },
    statusTone() {
      switch (this.org.subscriptionStatus) {
        case "active":
          return "success";
        case "paused":
          return "warning";
        case "cancelled":
        case "expired":
          return "danger";
        default:
          return "muted";
      }
    },
    statusLabel() {
      const s = this.org.subscriptionStatus;
      if (!s || s === "none") return "No subscription";
      return s.charAt(0).toUpperCase() + s.slice(1);
    },
    storageDisplay() {
      const bytes = this.org.storageBytes || 0;
      if (bytes <= 0) return "—";
      if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + " KB";
      if (bytes < 1024 * 1024 * 1024)
        return Math.round(bytes / (1024 * 1024)) + " MB";
      return (bytes / (1024 * 1024 * 1024)).toFixed(1) + " GB";
    },
  },
};
</script>

<style scoped>
.org-card {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  padding: 20px 24px;
  cursor: pointer;
  transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1),
    box-shadow 0.3s cubic-bezier(0.22, 1, 0.36, 1);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.org-card:hover {
  box-shadow: var(--shadow-card-hover, 0 4px 12px rgba(0, 0, 0, 0.1));
  transform: translateY(-4px);
}

.org-card--selected {
  box-shadow: 0 0 0 2px var(--accent), var(--shadow-card-hover, 0 4px 12px rgba(0, 0, 0, 0.1));
  background: var(--accent-bg);
}

.org-card--selected:hover {
  box-shadow: 0 0 0 2px var(--accent), var(--shadow-card-hover, 0 4px 12px rgba(0, 0, 0, 0.1));
}

.org-card__header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.org-card__title-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.org-card__name {
  font-size: var(--iz-fs-lg);
  font-weight: 700;
  color: var(--color-text-primary, var(--color-text-primary));
  line-height: 1.2;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.org-card__pills {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}




.org-card__metrics {
  display: flex;
  border-top: 1px solid var(--color-border, var(--color-border));
  padding-top: 12px;
}

.org-card__metric {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-left: 14px;
  border-left: 1px solid var(--color-border, var(--color-border));
}

.org-card__metric:first-child {
  padding-left: 0;
  border-left: none;
}

.org-card__metric-value {
  font-size: var(--iz-fs-xl);
  font-weight: 700;
  color: var(--color-text-primary, var(--color-text-primary));
  line-height: 1.1;
}

.org-card__metric-label {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted, var(--color-text-muted));
}

.org-card__tasks {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 6px;
  margin-top: 10px;
  font-size: var(--iz-fs-xs);
  color: var(--color-text-secondary, var(--color-text-secondary));
  font-variant-numeric: tabular-nums;
}

.org-card__task {
  display: inline-flex;
  align-items: center;
}

.org-card__task strong {
  color: var(--color-text-primary, var(--color-text-primary));
  font-weight: 700;
  margin-right: 4px;
}

.org-card__task-sep {
  color: var(--color-border-strong);
  user-select: none;
}
</style>
