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
            <template v-if="key === 'backgroundJobs'">
              <circle cx="12" cy="12" r="10" />
              <polyline points="12 6 12 12 16 14" />
            </template>
            <template v-else-if="key === 'failedBackups7d'">
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

        <!-- Some alerts name the thing that is wrong. A count on its own tells
             an admin something is broken without saying what, which is one more
             click before they can act. -->
        <p v-if="alert.detail" class="alerts-panel__detail">{{ alert.detail }}</p>

        <div v-if="offendersOf(alert).length" class="alerts-panel__tags">
          <button
            v-for="offender in offendersOf(alert)"
            :key="offender.orgId"
            type="button"
            class="alerts-panel__tag"
            :aria-label="tagAria(alert, key, offender)"
            @click="$emit('drill-down', { orgId: offender.orgId, tab: tabFor(key) })"
          >
            <span class="iz-badge" :class="badgeTone(alert.tone)">
              {{ tagLabel(key, offender) }}
            </span>
          </button>
          <span
            v-if="remainingOf(alert)"
            class="alerts-panel__more"
          >+{{ remainingOf(alert) }} more</span>
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

// Badge variant per alert tone. Same reason as TONE_COLORS: a table keeps an
// unexpected tone falling back to a real class instead of resolving to nothing.
const TONE_BADGES = {
  success: "iz-badge--success",
  warning: "iz-badge--warning",
  danger: "iz-badge--danger",
};

// Which org-detail sub-tab shows the failure each alert counts.
const ALERT_TABS = {
  failedBackups7d: "backups",
  stuckAhoJobs: "handover",
  staleProjects30d: "projects",
  orgsNoSub: "subscription",
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
    badgeTone(tone) {
      return TONE_BADGES[tone] || "iz-badge--muted";
    },
    tabFor(key) {
      return ALERT_TABS[key] || "overview";
    },
    offendersOf(alert) {
      return Array.isArray(alert.offenders) ? alert.offenders : [];
    },
    remainingOf(alert) {
      return Number(alert.offendersRemaining) || 0;
    },
    // orgsNoSub counts organizations, so its offenders are always one each and
    // a "×1" suffix would be noise.
    tagLabel(key, offender) {
      return key === "orgsNoSub"
        ? offender.orgName
        : offender.orgName + " ×" + offender.count;
    },
    // The same organization can appear on several cards at once, so the visible
    // label alone ("Test ×3") is not a unique accessible name — two buttons
    // would announce identically while going to different tabs. Leading with
    // the alert says which one you are on, and the count is spelled out because
    // "×" is read inconsistently across screen readers.
    tagAria(alert, key, offender) {
      const who = key === "orgsNoSub"
        ? offender.orgName
        : offender.orgName + ", " + offender.count;
      return alert.label + ": " + who + " — open this organization";
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

/* Tag row: chrome (tint + text) comes from the theme's .iz-badge--* primitive.
   The tag is a bare drill-down button that never changes on hover/focus/active
   — the badge is the whole visual — so clicking one leaves it looking exactly
   as it did. Only a keyboard focus ring remains, for a11y. Selectors are
   qualified on `button` to beat NC core's bare-element button styling. Mirrors
   the chip pattern in PlatformKpiStrip. */
.alerts-panel__detail {
  margin: 0;
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
}

.alerts-panel__tags {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}

button.alerts-panel__tag {
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

button.alerts-panel__tag:hover,
button.alerts-panel__tag:focus,
button.alerts-panel__tag:active {
  background: transparent;
  box-shadow: none;
  outline: none;
}

button.alerts-panel__tag:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}

.alerts-panel__tag .iz-badge {
  font-variant-numeric: tabular-nums;
  transition: transform 0.12s ease, filter 0.12s ease;
  /* .iz-badge capitalizes, which is right for the status words it was built
     for ("active", "paused") and wrong here: these badges carry an
     organization's actual name, which has to read exactly as it was entered.
     Without this, an org called "testorg" renders as "Testorg". */
  text-transform: none;
}

/* Hover feedback lives on the badge and only while the pointer is over it, so
   it clears the moment you leave — nothing persists after a click. */
button.alerts-panel__tag:hover .iz-badge {
  transform: translateY(-1px);
  filter: brightness(0.96);
}

.alerts-panel__more {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted);
  padding: 3px 2px;
  font-variant-numeric: tabular-nums;
}
</style>
