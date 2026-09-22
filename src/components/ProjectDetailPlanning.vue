<template>
  <div class="detail-planning">
    <!-- ── Top Header Bar ── -->
    <header class="detail-planning__header">
      <div class="detail-planning__title-group">
        <div class="detail-planning__brand-row">
          <span class="detail-planning__brand">In Zicht</span>
          <h2 class="detail-planning__title">Project Timeline – {{ project.name }}</h2>
        </div>
        <p class="detail-planning__subtitle">Initiation Phase · From intake to Handover 1</p>
      </div>

      <div class="detail-planning__header-actions">
        <button
          type="button"
          class="iz-btn iz-btn--secondary detail-planning__back-btn"
          @click="$emit('back')"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="detail-planning__btn-icon">
            <polyline points="15 18 9 12 15 6" />
          </svg>
          Back to Planning Overview
        </button>
        <span class="detail-planning__last-updated">
          Last updated {{ lastUpdatedText }}
        </span>
      </div>
    </header>

    <!-- ── Project Overview & KPI Summary Card ── -->
    <section class="iz-card detail-planning__summary-card">
      <div class="detail-planning__identity-col">
        <div class="detail-planning__thumb-avatar">
          <span>{{ projectInitials }}</span>
        </div>
        <div class="detail-planning__identity-info">
          <h3 class="detail-planning__project-name">{{ project.name }}</h3>
          <p class="detail-planning__project-city">{{ projectCity }}</p>
          <div class="detail-planning__badges">
            <span class="iz-badge iz-badge--accent">Initiation Phase</span>
            <span class="iz-badge" :class="statusBadgeClass">{{ statusBadgeText }}</span>
          </div>
        </div>
      </div>

      <div class="detail-planning__kpis-strip">
        <!-- 1. Readiness -->
        <div class="detail-planning__kpi-tile">
          <span class="detail-planning__kpi-label">Readiness</span>
          <strong class="detail-planning__kpi-val">{{ project.completionPct || 0 }}%</strong>
          <span class="iz-badge iz-badge--sm" :class="bucketBadgeClass">{{ bucketBadgeLabel }}</span>
        </div>

        <!-- 2. Expected 100% -->
        <div class="detail-planning__kpi-tile">
          <span class="detail-planning__kpi-label">Expected 100%</span>
          <strong class="detail-planning__kpi-val">{{ formatWeekShort(project.expected100Week) || '—' }}</strong>
          <small class="detail-planning__kpi-sub">{{ project.expected100Countdown || 'On schedule' }}</small>
        </div>

        <!-- 3. Start Work Preparation -->
        <div class="detail-planning__kpi-tile">
          <span class="detail-planning__kpi-label">Start Work Prep</span>
          <strong class="detail-planning__kpi-val">{{ formatWeekShort(project.startPrepWeek) || '—' }}</strong>
          <small class="detail-planning__kpi-sub">{{ project.startPrepCountdown || '—' }}</small>
        </div>

        <!-- 4. Min. Execution Start -->
        <div class="detail-planning__kpi-tile">
          <span class="detail-planning__kpi-label">Min. Execution Start</span>
          <strong class="detail-planning__kpi-val">{{ formatWeekShort(project.minExecutionStartWeek) || '—' }}</strong>
          <small class="detail-planning__kpi-sub">{{ project.minExecutionStartCountdown || '—' }}</small>
        </div>

        <!-- 5. Desired Week (Client) -->
        <div class="detail-planning__kpi-tile">
          <span class="detail-planning__kpi-label">Desired Week (Client)</span>
          <strong class="detail-planning__kpi-val">{{ formatWeekShort(project.desiredStartWeek) || '—' }}</strong>
          <small class="detail-planning__kpi-sub">{{ project.desiredCountdown || '—' }}</small>
        </div>

        <!-- 6. Actual Start Week -->
        <div class="detail-planning__kpi-tile">
          <span class="detail-planning__kpi-label">Actual Start</span>
          <strong class="detail-planning__kpi-val">{{ formatWeekShort(project.actualStartWeek) || '—' }}</strong>
          <small class="detail-planning__kpi-sub">{{ formatActualDateSub(project.actualStartDate) }}</small>
        </div>

        <!-- 7. Planning Gap -->
        <div class="detail-planning__kpi-tile detail-planning__kpi-tile--gap" :class="{ 'detail-planning__kpi-tile--has-gap': hasGap }">
          <span class="detail-planning__kpi-label">Planning Gap</span>
          <strong class="detail-planning__kpi-val detail-planning__kpi-val--gap">
            {{ gapSummaryText }}
          </strong>
        </div>

        <!-- Actions button -->
        <div class="detail-planning__actions-tile">
          <button
            type="button"
            class="iz-btn iz-btn--secondary iz-btn--sm"
            @click="actionsMenuOpen = !actionsMenuOpen"
          >
            Actions
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="detail-planning__btn-chevron">
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </button>
          <div v-if="actionsMenuOpen" class="detail-planning__actions-menu iz-card" @click="actionsMenuOpen = false">
            <button v-if="project.boardId" type="button" @click="openDeckBoard">
              Open Deck Board ↗
            </button>
            <button type="button" @click="openProjectsApp">
              Open in Projects App ↗
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Live Project Timeline (Gantt Chart from projectcreatoraio) ── -->
    <section class="detail-planning__timeline-wrapper">
      <GanttChart
        :project-id="Number(project.id)"
        :is-admin="isAdmin"
      />
    </section>
  </div>
</template>

<script>
import { generateUrl } from "@nextcloud/router";
import GanttChart from "./ProjectTimeline/GanttChart.vue";

export default {
  name: "ProjectDetailPlanning",
  components: {
    GanttChart,
  },
  props: {
    project: {
      type: Object,
      required: true,
    },
    organizationId: {
      type: Number,
      default: null,
    },
    isAdmin: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      actionsMenuOpen: false,
      lastUpdated: new Date(),
    };
  },
  computed: {
    lastUpdatedText() {
      var h = String(this.lastUpdated.getHours()).padStart(2, "0");
      var m = String(this.lastUpdated.getMinutes()).padStart(2, "0");
      return "today at " + h + ":" + m;
    },
    projectInitials() {
      return String(this.project.name || "P")
        .split(/\s+/)
        .slice(0, 2)
        .map(function (part) {
          return part.charAt(0);
        })
        .join("")
        .toUpperCase();
    },
    projectCity() {
      return this.project.city || this.project.address || "Location specified in project";
    },
    statusBadgeText() {
      if (this.project.isCompleted) return "100% Ready";
      if (this.project.bucket === "75-99") return "Upcoming";
      return this.project.bucketLabel || "In Progress";
    },
    statusBadgeClass() {
      if (this.project.isCompleted) return "iz-badge--success";
      if (this.project.bucket === "75-99") return "iz-badge--warning";
      return "";
    },
    bucketBadgeClass() {
      if (this.project.completionPct >= 100) return "iz-badge--success";
      if (this.project.completionPct >= 75) return "iz-badge--warning";
      return "";
    },
    bucketBadgeLabel() {
      if (this.project.completionPct >= 100) return "100% Handover ready";
      if (this.project.completionPct >= 75) return "75-99% Upcoming";
      return this.project.bucketLabel || "In progress";
    },
    hasGap() {
      return !!(this.project.planningGap && this.project.planningGap.hasGap)
        || (this.project.gapWeeks && this.project.gapWeeks > 0);
    },
    gapSummaryText() {
      if (this.project.planningGapDisplay && this.project.planningGapDisplay !== "None") {
        return this.project.planningGapDisplay;
      }
      if (this.hasGap) {
        var w = this.project.gapWeeks || 2;
        var s = this.project.gapSpan || "W32-W33";
        return w + (w === 1 ? " week · " : " weeks · ") + s;
      }
      return "No planning gap";
    },
  },
  methods: {
    formatWeekShort(weekStr) {
      if (!weekStr || weekStr === "—") return "";
      var match = String(weekStr).match(/W(\d+)/i);
      return match ? "W" + match[1] : weekStr;
    },
    formatActualDateSub(dateStr) {
      if (!dateStr || dateStr === "—") return "Not set";
      try {
        var d = new Date(dateStr);
        if (!isNaN(d.getTime())) {
          return d.toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" });
        }
      } catch (e) {}
      return String(dateStr);
    },
    openDeckBoard() {
      if (!this.project.boardId) return;
      window.open(generateUrl("/apps/deck/#/board/" + this.project.boardId), "_blank");
    },
    openProjectsApp() {
      window.open(generateUrl("/apps/projectcreatoraio/"), "_blank");
    },
  },
};
</script>

<style scoped>
.detail-planning {
  display: grid;
  gap: var(--iz-gap);
  padding: 0;
  margin-bottom: var(--iz-gap);
  min-width: 0;
  max-width: 100%;
}

/* ── Top Header ── */
.detail-planning__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--iz-gap);
  flex-wrap: wrap;
}
.detail-planning__title-group { display: grid; gap: 4px; }
.detail-planning__brand-row { display: flex; align-items: baseline; gap: var(--iz-gap-tight); }
.detail-planning__brand {
  font-size: var(--iz-fs-lg);
  font-weight: 800;
  color: var(--iz-accent);
  letter-spacing: -0.02em;
}
.detail-planning__title {
  margin: 0;
  font-size: var(--iz-fs-xl);
  font-weight: 700;
  color: var(--iz-text);
}
.detail-planning__subtitle {
  margin: 0;
  color: var(--iz-text-muted);
  font-size: var(--iz-fs-sm);
}
.detail-planning__header-actions {
  display: flex;
  align-items: center;
  gap: var(--iz-gap);
  flex-wrap: wrap;
}
.detail-planning__last-updated {
  font-size: var(--iz-fs-xs);
  color: var(--iz-text-muted);
}
.detail-planning__btn-icon { width: 16px; height: 16px; }

/* ── Top Summary Card ── */
.detail-planning__summary-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--iz-gap);
  padding: var(--iz-pad-card);
  flex-wrap: wrap;
}
.detail-planning__identity-col {
  display: flex;
  align-items: center;
  gap: var(--iz-gap);
  min-width: 240px;
}
.detail-planning__thumb-avatar {
  width: 52px;
  height: 52px;
  border-radius: var(--iz-radius);
  background: var(--iz-accent-bg, rgba(59, 130, 246, 0.12));
  color: var(--iz-accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--iz-fs-lg);
  font-weight: 700;
  flex-shrink: 0;
  border: 1px solid var(--iz-border);
}
.detail-planning__identity-info { display: grid; gap: 2px; }
.detail-planning__project-name { margin: 0; font-size: var(--iz-fs-lg); font-weight: 700; color: var(--iz-text); }
.detail-planning__project-city { margin: 0; font-size: var(--iz-fs-sm); color: var(--iz-text-muted); }
.detail-planning__badges { display: flex; gap: 6px; margin-top: 4px; }

/* ── KPIs Strip ── */
.detail-planning__kpis-strip {
  display: flex;
  align-items: center;
  gap: var(--iz-gap);
  flex-wrap: wrap;
}
.detail-planning__kpi-tile {
  display: grid;
  gap: 2px;
  min-width: 100px;
  padding: 6px 10px;
  border-radius: var(--iz-radius);
  background: var(--iz-surface-subtle);
  border: 1px solid var(--iz-border);
}
.detail-planning__kpi-label {
  font-size: var(--iz-fs-xs);
  color: var(--iz-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.detail-planning__kpi-val { font-size: var(--iz-fs-lg); font-weight: 700; color: var(--iz-text); }
.detail-planning__kpi-sub { font-size: var(--iz-fs-xs); color: var(--iz-text-muted); }
.detail-planning__kpi-tile--gap { min-width: 140px; }
.detail-planning__kpi-tile--has-gap {
  background: rgba(239, 68, 68, 0.08);
  border-color: rgba(239, 68, 68, 0.3);
}
.detail-planning__kpi-val--gap { color: #dc2626; font-size: var(--iz-fs-md); }
.detail-planning__actions-tile { position: relative; }
.detail-planning__actions-menu {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  z-index: 100;
  display: grid;
  gap: 2px;
  padding: 6px;
  min-width: 170px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  background: var(--iz-surface);
  border: 1px solid var(--iz-border);
  border-radius: var(--iz-radius);
}
.detail-planning__actions-menu button {
  text-align: left;
  background: transparent;
  border: 0;
  padding: 8px 10px;
  border-radius: var(--iz-radius);
  cursor: pointer;
  font-size: var(--iz-fs-sm);
  color: var(--iz-text);
}
.detail-planning__actions-menu button:hover { background: var(--iz-surface-subtle); }
.detail-planning__btn-chevron { width: 14px; height: 14px; }

/* ── Live Gantt Chart Wrapper ── */
.detail-planning__timeline-wrapper {
  padding: 0;
  overflow: hidden;
  background: transparent;
  border: none;
  border-radius: 0;
  min-width: 0;
  max-width: 100%;
}
</style>
