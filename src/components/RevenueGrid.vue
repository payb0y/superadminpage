<template>
  <div class="revenue-grid">
    <div
      class="revenue-grid__scroll"
      tabindex="0"
      role="region"
      aria-label="Revenue by organization and month, scrollable"
    >
      <table class="revenue-grid__table">
        <thead>
          <tr>
            <th class="revenue-grid__rowh" scope="col">Organization</th>
            <template v-for="(month, i) in months">
              <th
                :key="'h-' + month.key"
                scope="col"
                :class="{ 'revenue-grid__future-h': month.isFuture }"
              >{{ monthLabel(month) }}</th>
              <th
                v-if="i === nowIndex && hasFuture"
                :key="'hd-' + month.key"
                class="revenue-grid__divider-h"
                scope="col"
              ><span>NOW</span></th>
            </template>
          </tr>
        </thead>

        <tbody>
          <tr v-for="org in orgs" :key="org.id">
            <th class="revenue-grid__rowh" scope="row">
              <span class="revenue-grid__name">
                <button
                  type="button"
                  class="revenue-grid__name-btn"
                  :aria-label="'Open ' + org.name + ' in Organizations'"
                  @click="$emit('open-org', org.id)"
                >{{ org.name }}</button>
                <span class="iz-badge" :class="statusTone(org.status)">{{ statusLabel(org.status) }}</span>
              </span>
            </th>
            <template v-for="(month, i) in months">
              <td :key="org.id + '-' + month.key">
                <div
                  class="revenue-grid__cell"
                  :class="cellClass(org, i)"
                  :style="cellStyle(org, i)"
                  :title="cellTitle(org, month, i)"
                >{{ cellText(org, i) }}</div>
              </td>
              <td
                v-if="i === nowIndex && hasFuture"
                :key="org.id + '-d-' + month.key"
              ><div class="revenue-grid__divider-c"><i></i></div></td>
            </template>
          </tr>
        </tbody>

        <tfoot>
          <tr>
            <th class="revenue-grid__rowh" scope="row">{{ hasFuture ? "Recorded / committed" : "Monthly MRR" }}</th>
            <template v-for="(month, i) in months">
              <th
                :key="'f-' + month.key"
                :class="{
                  'revenue-grid__now-col': i === nowIndex,
                  'revenue-grid__future-h': month.isFuture,
                }"
              >{{ moneyShort(totals[i]) }}</th>
              <th
                v-if="i === nowIndex && hasFuture"
                :key="'fd-' + month.key"
                class="revenue-grid__divider-h"
              ><span>NOW</span></th>
            </template>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="revenue-grid__legend">
      <span class="revenue-grid__legend-item">
        <span>{{ moneyShort(0) }}</span>
        <span class="revenue-grid__scale"></span>
        <span>{{ moneyShort(maxValue) }}</span>
      </span>
      <span class="revenue-grid__legend-item">
        <span class="revenue-grid__swatch revenue-grid__swatch--recorded"></span>recorded
      </span>
      <span v-if="hasFuture" class="revenue-grid__legend-item">
        <span class="revenue-grid__swatch revenue-grid__swatch--future"></span>committed if renewed
      </span>
      <span v-if="hasFuture" class="revenue-grid__legend-item">
        <span class="revenue-grid__swatch revenue-grid__swatch--renew"></span>renewal lands this month
      </span>
      <span class="revenue-grid__legend-item">
        <span class="revenue-grid__swatch revenue-grid__swatch--zero"></span>no revenue
      </span>
    </div>
  </div>
</template>

<script>
// Explicit status → badge table. A table keeps an unexpected status falling back
// to a real class instead of resolving to nothing, same as badgeTone() in
// AlertsPanel — never build the class name by concatenation.
const STATUS_BADGES = {
  active: "iz-badge--success",
  paused: "iz-badge--warning",
  cancelled: "iz-badge--muted",
  expired: "iz-badge--danger",
  none: "iz-badge--muted",
};

const STATUS_LABELS = {
  active: "Active",
  paused: "Paused",
  cancelled: "Cancelled",
  expired: "Expired",
  none: "No plan",
};

export default {
  name: "RevenueGrid",
  props: {
    months: { type: Array, required: true },
    orgs: { type: Array, required: true },
    totals: { type: Array, required: true },
    nowIndex: { type: Number, required: true },
    currency: { type: String, default: "EUR" },
  },
  computed: {
    hasFuture() {
      return this.months.some((m) => m.isFuture);
    },
    // The tint scale is per-view, not per-row: a cell's darkness has to mean the
    // same thing everywhere in the grid or the picture lies.
    maxValue() {
      let max = 0;
      this.orgs.forEach((org) => {
        org.series.forEach((v) => {
          if (v > max) max = v;
        });
      });
      return max;
    },
    baseYear() {
      return this.months.length ? this.months[0].year : 0;
    },
  },
  methods: {
    monthLabel(month) {
      return month.year === this.baseYear
        ? month.label
        : month.label + " '" + String(month.year).slice(2);
    },
    statusTone(status) {
      return STATUS_BADGES[status] || "iz-badge--muted";
    },
    statusLabel(status) {
      return STATUS_LABELS[status] || status;
    },
    money(value) {
      try {
        return new Intl.NumberFormat(undefined, {
          style: "currency",
          currency: this.currency || "EUR",
        }).format(value || 0);
      } catch (e) {
        // Unknown ISO code from the plans table — show the number, not a crash.
        return (value || 0).toFixed(2);
      }
    },
    moneyShort(value) {
      try {
        return new Intl.NumberFormat(undefined, {
          style: "currency",
          currency: this.currency || "EUR",
          maximumFractionDigits: 0,
        }).format(value || 0);
      } catch (e) {
        return String(Math.round(value || 0));
      }
    },
    cellClass(org, i) {
      return {
        "revenue-grid__cell--zero": org.series[i] === 0,
        "revenue-grid__cell--future": this.months[i].isFuture && org.series[i] > 0,
        "revenue-grid__cell--renew": org.renewIndex === i && org.series[i] > 0,
      };
    },
    // Tint via color-mix against the theme accent, so the heatmap follows light
    // and dark with no JavaScript and no raw rgba() anywhere.
    cellStyle(org, i) {
      const value = org.series[i];
      if (value === 0 || this.maxValue === 0) return {};
      let pct = Math.round((value / this.maxValue) * 92) + 8;
      const future = this.months[i].isFuture;
      if (future) pct = Math.round(pct * 0.45);
      const style = {
        background:
          "color-mix(in oklab, var(--accent) " + pct + "%, var(--bg-card))",
      };
      if (!future && pct > 55) {
        style.color = "var(--color-primary-element-text, #fff)";
      }
      return style;
    },
    cellText(org, i) {
      return org.series[i] === 0 ? "—" : this.moneyShort(org.series[i]);
    },
    cellTitle(org, month, i) {
      const value = org.series[i];
      const when = month.label + " " + month.year;
      // The renewal note belongs on the cell whether or not it carries revenue —
      // a free plan renews too, and gating it on value hid it entirely.
      const renews = org.renewIndex === i ? " · renews this month" : "";
      if (value === 0) {
        return org.name + " · " + when + " · no revenue" + renews;
      }
      const kind = month.isFuture ? " committed" : " recorded";
      return org.name + " · " + when + " · " + this.money(value) + kind + renews;
    },
  },
};
</script>

<style scoped>
.revenue-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-width: 0;
}

/* Bounded box: the grid scrolls in both axes rather than growing the page by a
   row per organization. */
.revenue-grid__scroll {
  max-height: 392px;
  overflow: auto;
  overscroll-behavior: contain;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-el);
}
.revenue-grid__scroll:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}

/* collapse, not separate: border-spacing leaves transparent gutters that a
   sticky header cannot cover, so rows show through it while scrolling. The gaps
   are td padding over an opaque cell background instead. */
.revenue-grid__table {
  border-collapse: collapse;
  min-width: 100%;
}

.revenue-grid__table th,
.revenue-grid__table td {
  padding: 2px;
  background: var(--bg-card);
}

.revenue-grid__table thead th {
  position: sticky;
  top: 0;
  z-index: 5;
  padding: 7px 6px;
  text-align: center;
  white-space: nowrap;
  font-size: var(--iz-fs-micro);
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
  box-shadow: inset 0 -1px 0 var(--color-border);
}

.revenue-grid__table tfoot th,
.revenue-grid__table tfoot td {
  position: sticky;
  bottom: 0;
  z-index: 5;
  padding: 7px 6px;
  text-align: center;
  white-space: nowrap;
  font-size: var(--iz-fs-xs);
  color: var(--color-text-secondary);
  font-variant-numeric: tabular-nums;
  box-shadow: inset 0 1px 0 var(--color-border);
}

/* Month headers are labels and may be uppercased; a row header carries an
   organization's real name and has to read exactly as it was entered — the same
   reason .iz-badge gets text-transform: none where it holds an org name. */
.revenue-grid__rowh {
  position: sticky;
  left: 0;
  z-index: 4;
  min-width: 190px;
  width: 190px;
  padding-left: 10px !important;
  text-align: left;
  text-transform: none;
  letter-spacing: normal;
  font-size: var(--iz-fs-md);
  font-weight: 600;
  color: var(--color-text-primary);
  box-shadow: 1px 0 0 var(--color-border);
}
.revenue-grid__table thead .revenue-grid__rowh {
  z-index: 6;
  box-shadow: 1px 0 0 var(--color-border), inset 0 -1px 0 var(--color-border);
}
.revenue-grid__table tfoot .revenue-grid__rowh {
  z-index: 6;
  box-shadow: 1px 0 0 var(--color-border), inset 0 1px 0 var(--color-border);
}

/* Qualified on the table: the bare class loses a specificity contest to this
   component's own `.revenue-grid__table thead th` rule above, which sets colour
   — so unqualified, both of these silently never applied. */
.revenue-grid__table thead th.revenue-grid__future-h,
.revenue-grid__table tfoot th.revenue-grid__future-h {
  color: color-mix(in oklab, var(--color-text-muted) 80%, var(--accent));
}
.revenue-grid__table tfoot th.revenue-grid__now-col {
  color: var(--accent);
  font-weight: 700;
}

.revenue-grid__name {
  display: flex;
  align-items: center;
  gap: 7px;
  min-width: 0;
}
/* Qualified on `button` with the min-height reset: Nextcloud core styles bare
   buttons with padding and `min-height: var(--default-clickable-area)`, which
   would blow the frozen column's row height out. The name is the whole control
   — no chrome until hover or focus. */
button.revenue-grid__name-btn {
  font: inherit;
  color: inherit;
  background: transparent;
  border: 0;
  margin: 0;
  padding: 0;
  min-width: 0;
  min-height: 0;
  max-width: 100%;
  text-align: left;
  cursor: pointer;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  -webkit-appearance: none;
  appearance: none;
}
button.revenue-grid__name-btn:hover {
  color: var(--accent);
  text-decoration: underline;
  background: transparent;
}
button.revenue-grid__name-btn:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
  border-radius: var(--radius-sm);
}
.revenue-grid__name .iz-badge {
  flex-shrink: 0;
  /* .iz-badge capitalizes, which is right for the status words it was built for
     and irrelevant here — but keep the sizing tight so the frozen column stays
     narrow enough to leave room for months. */
  font-size: var(--iz-fs-micro);
  padding: 1px 6px;
}

.revenue-grid__table tbody tr:hover th,
.revenue-grid__table tbody tr:hover td {
  background: var(--bg-subtle);
}

.revenue-grid__cell {
  min-width: 52px;
  height: 32px;
  border-radius: var(--radius-sm);
  border: 1px solid transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  transition: transform 0.12s ease;
}
.revenue-grid__cell:hover {
  transform: scale(1.08);
}

/* recorded is a solid tint; committed is the same hue, dashed and lighter,
   because it has not happened yet */
.revenue-grid__cell--future {
  border-style: dashed;
  border-color: color-mix(in oklab, var(--accent) 40%, var(--bg-card));
  color: var(--color-text-secondary);
  font-weight: 500;
}
.revenue-grid__cell--zero {
  background: var(--bg-inset);
  color: var(--color-text-muted);
  opacity: 0.5;
}
.revenue-grid__cell--renew {
  box-shadow: inset 0 -3px 0 var(--chart-4);
}

.revenue-grid__divider-h {
  padding: 0 4px !important;
}
.revenue-grid__divider-h span {
  display: block;
  font-size: var(--iz-fs-micro);
  font-weight: 700;
  letter-spacing: 0.06em;
  color: var(--chart-4);
  white-space: nowrap;
}
.revenue-grid__divider-c {
  width: 16px;
  min-width: 16px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.revenue-grid__divider-c i {
  display: block;
  width: 0;
  height: 100%;
  border-left: 1px dashed var(--color-border-strong);
}

.revenue-grid__legend {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px 20px;
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted);
}
.revenue-grid__legend-item {
  display: inline-flex;
  align-items: center;
  gap: 7px;
}
.revenue-grid__scale {
  height: 10px;
  width: 120px;
  border-radius: var(--radius-pill);
  background: linear-gradient(
    to right,
    color-mix(in oklab, var(--accent) 8%, var(--bg-card)),
    var(--accent)
  );
}
.revenue-grid__swatch {
  width: 22px;
  height: 15px;
  border-radius: var(--radius-sm);
  flex-shrink: 0;
}
.revenue-grid__swatch--recorded {
  background: color-mix(in oklab, var(--accent) 60%, var(--bg-card));
}
.revenue-grid__swatch--future {
  background: color-mix(in oklab, var(--accent) 26%, var(--bg-card));
  border: 1px dashed color-mix(in oklab, var(--accent) 45%, var(--bg-card));
}
.revenue-grid__swatch--renew {
  background: color-mix(in oklab, var(--accent) 40%, var(--bg-card));
  box-shadow: inset 0 -3px 0 var(--chart-4);
}
.revenue-grid__swatch--zero {
  background: var(--bg-inset);
  opacity: 0.6;
}

@media (prefers-reduced-motion: reduce) {
  .revenue-grid__cell {
    transition: none;
  }
  .revenue-grid__cell:hover {
    transform: none;
  }
}
</style>
