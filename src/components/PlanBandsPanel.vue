<template>
  <div class="plan-bands">
    <div v-if="!plans.length" class="iz-empty">
      {{ t('No plans have been created yet, so there is nothing to band.') }}
    </div>

    <template v-else>
      <div class="iz-table-wrap">
        <table class="iz-table plan-bands__table">
          <thead>
            <tr>
              <th scope="col">{{ t('Plan') }}</th>
              <th
                v-for="band in bands"
                :key="'h-' + band"
                scope="col"
                class="plan-bands__band-h"
              >
                <span class="plan-bands__sw" :class="bandClass(band)"></span>
                <span>{{ bandLabel(band) }}</span>
              </th>
              <th scope="col" class="plan-bands__num">{{ t('MRR') }}</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="plan in plans" :key="plan.id">
              <th scope="row" class="plan-bands__plan">
                <span class="plan-bands__plan-name">{{ plan.name }}</span>
                <span class="plan-bands__plan-caps">{{ planCaps(plan) }}</span>
              </th>
              <td
                v-for="band in bands"
                :key="plan.id + '-' + band"
                class="plan-bands__cell-td"
              >
                <button
                  type="button"
                  class="plan-bands__cell"
                  :class="cellClass(plan, band)"
                  :style="cellStyle(plan, band)"
                  :disabled="!plan.bands[band]"
                  :aria-pressed="ariaPressed(plan.id, band)"
                  :aria-label="cellLabel(plan, band)"
                  :title="cellLabel(plan, band)"
                  @click="pick(plan.id, band)"
                >{{ plan.bands[band] }}</button>
              </td>
              <td class="plan-bands__num plan-bands__mrr">
                <span :class="{ 'plan-bands__muted': !plan.mrr }">
                  {{ plan.mrr ? moneyIn(plan.mrr, plan.currency) : "—" }}
                </span>
              </td>
            </tr>
          </tbody>

          <tfoot>
            <tr>
              <th scope="row" class="plan-bands__plan">
                <span class="plan-bands__plan-name">{{ t('All plans') }}</span>
                <span class="plan-bands__plan-caps">{{ t('{n} active', { n: totalActive }) }}</span>
              </th>
              <td
                v-for="band in bands"
                :key="'t-' + band"
                class="plan-bands__cell-td"
              >
                <button
                  type="button"
                  class="plan-bands__cell plan-bands__cell--total"
                  :class="cellClass(null, band)"
                  :disabled="!bandTotal(band)"
                  :aria-pressed="ariaPressed(null, band)"
                  :aria-label="totalLabel(band)"
                  :title="totalLabel(band)"
                  @click="pick(null, band)"
                >{{ bandTotal(band) }}</button>
              </td>
              <td class="plan-bands__num plan-bands__mrr">{{ money(totalMrr) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <p class="plan-bands__hint">
        {{ t('Counts are active subscriptions. Pick a cell to filter the organizations below; pick it again to clear.') }}
      </p>

      <p v-if="unmeasured" class="plan-bands__note">
        {{ n('%n active subscription is on a plan that caps neither members nor projects, so it has no usage to band and is not counted above.', '%n active subscriptions are on a plan that caps neither members nor projects, so they have no usage to band and are not counted above.', unmeasured) }}
      </p>
    </template>
  </div>
</template>

<script>
import { t, n, locale } from "../lib/l10n.mjs";

// Explicit tables, never a class or label assembled from the band key — an
// unexpected key falls back to something real instead of resolving to nothing.
// Labels are built at call time so they follow the user's language.
const BAND_LABELS = {
  low: () => t("under 50%"),
  mid: () => t("50–79%"),
  high: () => t("80–99%"),
  cap: () => t("at or over cap"),
};

const BAND_CLASSES = {
  low: "plan-bands__band--low",
  mid: "plan-bands__band--mid",
  high: "plan-bands__band--high",
  cap: "plan-bands__band--cap",
};

export default {
  name: "PlanBandsPanel",
  props: {
    plans: { type: Array, required: true },
    bands: { type: Array, required: true },
    currency: { type: String, default: "EUR" },
    /** Currently filtered plan, or null for "every plan". */
    activePlanId: { type: Number, default: null },
    /** Currently filtered band, or null for "no band filter". */
    activeBand: { type: String, default: null },
  },
  computed: {
    totalActive() {
      return this.plans.reduce((sum, plan) => sum + plan.active, 0);
    },
    totalMrr() {
      return this.plans.reduce((sum, plan) => sum + plan.mrr, 0);
    },
    unmeasured() {
      return this.plans.reduce((sum, plan) => sum + (plan.active - plan.measured), 0);
    },
    // The tint scale is per-row on purpose: it says where a plan's own
    // subscriptions sit, so the darkest cell in a row is that plan's centre of
    // mass. Colours are therefore NOT comparable between rows, which is why the
    // count stays printed in every cell.
    rowTotals() {
      const out = {};
      this.plans.forEach((plan) => {
        out[plan.id] = this.bands.reduce((sum, band) => sum + (plan.bands[band] || 0), 0);
      });
      return out;
    },
  },
  methods: {
    bandLabel(band) {
      return BAND_LABELS[band] ? BAND_LABELS[band]() : band;
    },
    bandClass(band) {
      return BAND_CLASSES[band] || "plan-bands__band--neutral";
    },
    bandTotal(band) {
      return this.plans.reduce((sum, plan) => sum + (plan.bands[band] || 0), 0);
    },
    isSelected(planId, band) {
      return this.activeBand === band && this.activePlanId === planId;
    },
    // Vue 2 drops an attribute bound to boolean false, which would leave an
    // unselected toggle with no aria-pressed at all — a string keeps the "off"
    // half of the state exposed.
    ariaPressed(planId, band) {
      return String(this.isSelected(planId, band));
    },
    cellClass(plan, band) {
      const count = plan ? plan.bands[band] : this.bandTotal(band);
      return [
        this.bandClass(band),
        {
          "plan-bands__cell--zero": !count,
          "plan-bands__cell--on": this.isSelected(plan ? plan.id : null, band),
        },
      ];
    },
    // Tint via color-mix against the band colour, so the grid follows light and
    // dark with no JavaScript and no raw rgba().
    cellStyle(plan, band) {
      const total = this.rowTotals[plan.id] || 0;
      const count = plan.bands[band] || 0;
      if (!count || !total) return {};
      const pct = Math.round((count / total) * 55);
      return {
        background: "color-mix(in oklab, var(--band-c) " + pct + "%, var(--bg-card))",
      };
    },
    // A cap of 0 is "this plan sets no limit", which is what the note about
    // unmeasured subscriptions says too. Printing it as "0 members" claims the
    // opposite — a plan that allows nobody.
    planCaps(plan) {
      return [
        t("{price} / mo", { price: this.moneyIn(plan.price, plan.currency) }),
        plan.maxMembers > 0 ? n("%n member", "%n members", plan.maxMembers) : t("no member limit"),
        plan.maxProjects > 0 ? n("%n project", "%n projects", plan.maxProjects) : t("no project limit"),
      ].join(" · ");
    },
    cellLabel(plan, band) {
      const count = plan.bands[band] || 0;
      const vars = { plan: plan.name, band: this.bandLabel(band) };
      if (!count) {
        return t("No {plan} subscription is {band}", vars);
      }
      if (this.isSelected(plan.id, band)) {
        return t("{label} — clear this filter", {
          label: n("%n {plan} subscription {band}", "%n {plan} subscriptions {band}", count, vars),
        });
      }
      return n(
        "%n {plan} subscription {band} — filter the table to these",
        "%n {plan} subscriptions {band} — filter the table to these",
        count,
        vars
      );
    },
    totalLabel(band) {
      const count = this.bandTotal(band);
      const vars = { band: this.bandLabel(band) };
      if (!count) {
        return t("No subscription on any plan is {band}", vars);
      }
      if (this.isSelected(null, band)) {
        return t("{label} — clear this filter", {
          label: n("%n subscription {band}, across every plan", "%n subscriptions {band}, across every plan", count, vars),
        });
      }
      return n(
        "%n subscription {band}, across every plan — filter the table to these",
        "%n subscriptions {band}, across every plan — filter the table to these",
        count,
        vars
      );
    },
    // Picking the cell that is already picked clears the filter, so the grid is
    // its own way back out rather than sending the reader to the Clear button.
    pick(planId, band) {
      if (this.isSelected(planId, band)) {
        this.$emit("pick", { planId: null, band: null });
        return;
      }
      this.$emit("pick", { planId: planId, band: band });
    },
    money(value) {
      return this.moneyIn(value, this.currency);
    },
    moneyIn(value, currency) {
      try {
        return new Intl.NumberFormat(locale(), {
          style: "currency",
          currency: currency || this.currency || "EUR",
        }).format(value || 0);
      } catch (e) {
        // Unknown ISO code from the plans table — show the number, not a crash.
        return (value || 0).toFixed(2);
      }
    },
  },
};
</script>

<style scoped>
.plan-bands {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
}

.plan-bands__table {
  width: 100%;
  min-width: 620px;
}

.plan-bands__band-h {
  text-align: center;
  white-space: nowrap;
}
.plan-bands__sw {
  display: block;
  width: 22px;
  height: 4px;
  margin: 0 auto 3px;
  border-radius: var(--radius-pill);
  background: var(--band-c, var(--color-text-muted));
}

.plan-bands__num {
  text-align: right;
}

/* .iz-table styles every th as an uppercase column label, which is right for the
   band headings and wrong for a row header carrying a plan's own name. */
.plan-bands__plan {
  display: table-cell;
  text-align: left;
  white-space: nowrap;
  text-transform: none;
  letter-spacing: normal;
}
/* .iz-table th sets --iz-fs-micro for column labels; without resetting it here
   the plan's own name renders a point SMALLER than the caption beneath it. */
.plan-bands__plan-name {
  display: block;
  font-size: var(--iz-fs-md);
  font-weight: 700;
  font-family: 'Space Grotesk', system-ui, -apple-system, sans-serif;
  color: var(--color-text-primary);
}
.plan-bands__plan-caps {
  display: block;
  font-size: var(--iz-fs-xs);
  font-weight: 400;
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
}

.plan-bands__mrr {
  font-family: 'Space Grotesk', system-ui, -apple-system, sans-serif;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.plan-bands__muted {
  color: var(--color-text-muted);
  font-weight: 400;
}

/* The cell is the control, so it fills its td rather than sitting inside one. */
.plan-bands__cell-td {
  padding: 4px;
  text-align: center;
}

/* Qualified on `button` and with min-height reset, or Nextcloud core's bare
   button rules (padding + min-height: var(--default-clickable-area)) fight the
   grid's own sizing. */
button.plan-bands__cell {
  font: inherit;
  font-family: 'Space Grotesk', system-ui, -apple-system, sans-serif;
  font-size: var(--iz-fs-lg);
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-width: 54px;
  min-height: 0;
  height: 42px;
  margin: 0;
  padding: 0 6px;
  border: 1.5px solid transparent;
  border-radius: var(--radius-sm);
  background: var(--bg-card);
  color: var(--color-text-primary);
  cursor: pointer;
  transition: border-color 0.15s ease, transform 0.15s ease;
}
button.plan-bands__cell:hover:not(:disabled) {
  border-color: var(--band-c, var(--accent));
  transform: translateY(-1px);
}
button.plan-bands__cell:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}
button.plan-bands__cell--on {
  border-color: var(--band-c, var(--accent));
  box-shadow: inset 0 0 0 1px var(--band-c, var(--accent));
}
button.plan-bands__cell--zero {
  color: var(--color-text-muted);
  font-weight: 400;
  background: transparent;
  cursor: default;
}
button.plan-bands__cell--total {
  background: var(--bg-subtle);
}
button.plan-bands__cell--total.plan-bands__cell--zero {
  background: transparent;
}

/* Band colours come from the shared categorical palette, so a band keeps the
   same colour wherever it appears on this tab. */
.plan-bands__band--low { --band-c: var(--chart-3); }
.plan-bands__band--mid { --band-c: var(--chart-5); }
.plan-bands__band--high { --band-c: var(--chart-4); }
.plan-bands__band--cap { --band-c: var(--color-danger); }
.plan-bands__band--neutral { --band-c: var(--color-text-muted); }

.plan-bands__hint,
.plan-bands__note {
  margin: 0;
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted);
}
.plan-bands__note {
  padding: 8px 12px;
  border-radius: var(--radius-el);
  background: var(--bg-subtle);
  color: var(--color-text-secondary);
  font-size: var(--iz-fs-sm);
}

@media (prefers-reduced-motion: reduce) {
  button.plan-bands__cell {
    transition: none;
  }
  button.plan-bands__cell:hover:not(:disabled) {
    transform: none;
  }
}
</style>
