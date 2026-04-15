<template>
  <div class="org-card" @click="$emit('click')">
    <div class="org-card__header">
      <span class="org-card__avatar">{{ initial }}</span>
      <div class="org-card__title-group">
        <span class="org-card__name">{{ org.name }}</span>
        <div class="org-card__pills">
          <span
            class="org-card__pill"
            :class="'org-card__pill--' + statusTone"
          >
            <span class="org-card__dot"></span>
            {{ statusLabel }}
          </span>
          <span class="org-card__pill org-card__pill--plan">
            {{ org.planName || "No plan" }}
          </span>
        </div>
      </div>
    </div>

    <div class="org-card__metrics">
      <div class="org-card__metric">
        <span class="org-card__metric-value">{{ org.memberCount }}</span>
        <span class="org-card__metric-label">members</span>
      </div>
      <div class="org-card__metric">
        <span class="org-card__metric-value">{{ org.projectCount }}</span>
        <span class="org-card__metric-label">projects</span>
      </div>
      <div class="org-card__metric">
        <span class="org-card__metric-value">{{ storageDisplay }}</span>
        <span class="org-card__metric-label">storage</span>
      </div>
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
  },
  computed: {
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
  transition: box-shadow 0.2s ease, transform 0.2s ease;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.org-card:hover {
  box-shadow: var(--shadow-card-hover, 0 4px 12px rgba(0, 0, 0, 0.1));
  transform: translateY(-1px);
}

.org-card__header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.org-card__avatar {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, #4a90d9, #6cb0f0);
  color: #fff;
  font-size: 17px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.org-card__title-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.org-card__name {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
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

.org-card__pill {
  font-size: 10px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  text-transform: capitalize;
}

.org-card__pill--success {
  background: var(--color-badge-success-bg, #d4edda);
  color: var(--color-badge-success-text, #166534);
}
.org-card__pill--warning {
  background: var(--color-badge-warning-bg, #fef3cd);
  color: var(--color-badge-warning-text, #92400e);
}
.org-card__pill--danger {
  background: var(--color-badge-danger-bg, #fde8e8);
  color: var(--color-badge-danger-text, #b91c1c);
}
.org-card__pill--muted {
  background: #f0f1f5;
  color: #6b7280;
}
.org-card__pill--plan {
  background: #e8f0fe;
  color: #1e4a8a;
}

.org-card__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  display: inline-block;
}

.org-card__metrics {
  display: flex;
  border-top: 1px solid var(--color-border, #e5e7eb);
  padding-top: 12px;
}

.org-card__metric {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-left: 14px;
  border-left: 1px solid var(--color-border, #e5e7eb);
}

.org-card__metric:first-child {
  padding-left: 0;
  border-left: none;
}

.org-card__metric-value {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  line-height: 1.1;
}

.org-card__metric-label {
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
}
</style>
