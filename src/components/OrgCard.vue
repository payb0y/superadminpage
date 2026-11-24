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
            {{ org.planName || t("No plan") }}
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
        <span class="org-card__metric-label">{{ t("members") }}</span>
      </div>
      <div class="org-card__metric">
        <span
          class="org-card__metric-value"
          :class="{ 'iz-mark': metricHighlight === 'projects' }"
        >{{ org.projectCount }}</span>
        <span class="org-card__metric-label">{{ t("projects") }}</span>
      </div>
      <div class="org-card__metric">
        <span class="org-card__metric-value">{{ storageDisplay }}</span>
        <span class="org-card__metric-label">{{ t("storage") }}</span>
      </div>
    </div>

    <div class="org-card__storage">
      <div class="org-card__storage-line">
        <span>{{ storageCapacityDisplay }}</span>
        <span>{{ storagePercentageDisplay }}</span>
      </div>
      <div class="iz-meter iz-meter--thin">
        <div class="iz-meter__fill" :style="{ width: storageMeterWidth + '%' }"></div>
      </div>
    </div>

    <div class="org-card__tasks">
      <span
        class="org-card__task"
        :class="{ 'iz-mark': metricHighlight === 'tasks' }"
      >
        {{ taskText.tasks[0] }}<strong>{{ org.totalTasks || 0 }}</strong>{{ taskText.tasks[1] }}
      </span>
      <span class="org-card__task-sep">·</span>
      <span
        class="org-card__task"
        :class="{ 'iz-mark': metricHighlight === 'done' }"
      >
        {{ taskText.done[0] }}<strong>{{ org.doneTasks || 0 }}</strong>{{ taskText.done[1] }}
      </span>
      <span class="org-card__task-sep">·</span>
      <span
        class="org-card__task"
        :class="{ 'iz-mark': metricHighlight === 'overdue' }"
      >
        {{ taskText.overdue[0] }}<strong>{{ org.overdueTasks || 0 }}</strong>{{ taskText.overdue[1] }}
      </span>
      <span class="org-card__task-sep">·</span>
      <span
        class="org-card__task"
        :class="{ 'iz-mark': metricHighlight === 'open' }"
      >
        {{ taskText.open[0] }}<strong>{{ openTasks }}</strong>{{ taskText.open[1] }}
      </span>
    </div>
  </div>
</template>

<script>
import { t, formatNumber } from "../lib/l10n.mjs";

// Subscription status code → label, made at display time.
const STATUS_LABELS = {
  active: () => t("Active"),
  paused: () => t("Paused"),
  cancelled: () => t("Cancelled"),
  expired: () => t("Expired"),
};

// A translated sentence split around its count, so the count can stay bold
// without v-html: "Aantal taken: {n}" → ["Aantal taken: ", ""].
const COUNT_MARK = "\u0001";
function aroundCount(text) {
  const i = text.indexOf(COUNT_MARK);
  return i === -1 ? [text + " ", ""] : [text.slice(0, i), text.slice(i + COUNT_MARK.length)];
}

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
    taskText() {
      return {
        tasks: aroundCount(t("{n} tasks", { n: COUNT_MARK })),
        done: aroundCount(t("{n} done", { n: COUNT_MARK })),
        overdue: aroundCount(t("{n} overdue", { n: COUNT_MARK })),
        open: aroundCount(t("{n} open", { n: COUNT_MARK })),
      };
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
      if (!s || s === "none") return t("No subscription");
      return STATUS_LABELS[s] ? STATUS_LABELS[s]() : s;
    },
    storageDisplay() {
      return this.formatBytes(this.storage.usedBytes);
    },
    storage() {
      return this.org.storage || {};
    },
    storageCapacityDisplay() {
      if (this.storage.capacityBytes == null) return t("No plan capacity");
      return t("{used} of {capacity}", {
        used: this.formatBytes(this.storage.usedBytes),
        capacity: this.formatBytes(this.storage.capacityBytes),
      });
    },
    storageMeterWidth() {
      return Math.min(100, Math.max(0, Number(this.storage.percentage) || 0));
    },
    storagePercentageDisplay() {
      const incomplete = this.storage.complete === false;
      if (this.storage.percentage == null) {
        return incomplete ? t("{percentage}% · incomplete", { percentage: "—" }).replace("—%", "—") : "—";
      }
      const percentage = formatNumber(this.storage.percentage);
      return incomplete
        ? t("{percentage}% · incomplete", { percentage })
        : percentage + "%";
    },
  },
  methods: {
    formatBytes(value) {
      const [B, KB, MB, GB] = t("B / KB / MB / GB").split(" / ");
      const bytes = Number(value) || 0;
      if (bytes <= 0) return "0 " + B;
      if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + " " + KB;
      if (bytes < 1024 * 1024 * 1024)
        return Math.round(bytes / (1024 * 1024)) + " " + MB;
      return formatNumber(bytes / (1024 * 1024 * 1024)) + " " + GB;
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

.org-card__storage {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.org-card__storage-line {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  color: var(--color-text-muted);
  font-size: var(--iz-fs-xs);
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
