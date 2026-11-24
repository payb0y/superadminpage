<template>
  <section class="financials">
    <div v-if="loading" class="financials__state">
      <div class="iz-spinner iz-spinner--lg"></div>
      <p>{{ t("Loading financials…") }}</p>
    </div>

    <div v-else-if="error" class="financials__state financials__state--error">
      <p class="financials__error-msg">{{ error }}</p>
      <button type="button" class="iz-btn iz-btn--accent" @click="reload">{{ t("Try again") }}</button>
    </div>

    <template v-else-if="data">
      <div class="financials__strip">
        <div class="financials__stat">
          <span class="financials__stat-label">{{ t("MRR") }}</span>
          <span class="financials__stat-value">
            {{ money(summary.mrr) }}
            <span class="iz-badge" :class="deltaTone(summary.mrrDelta)">{{ deltaText(summary.mrrDelta) }}</span>
          </span>
        </div>
        <div class="financials__stat">
          <span class="financials__stat-label">{{ t("ARR") }}</span>
          <span class="financials__stat-value">{{ money(summary.arr) }}</span>
        </div>
        <div class="financials__stat">
          <span class="financials__stat-label">{{ t("Active") }}</span>
          <span class="financials__stat-value">{{ summary.activeSubs }} / {{ summary.totalOrgs }}</span>
        </div>
        <div class="financials__stat">
          <span class="financials__stat-label">{{ t("Up for renewal") }}</span>
          <span class="financials__stat-value">{{ money(summary.upForRenewal) }}</span>
        </div>
        <span class="financials__source">{{ t("contracted revenue · list prices, not payments") }}</span>
      </div>

      <p v-if="summary.mixedCurrency" class="financials__warn">
        {{ t("Plans on this platform are priced in more than one currency. Totals here add the raw numbers without converting them, so treat them as indicative.") }}
      </p>

      <div class="iz-card financials__panel">
        <div class="financials__panel-head">
          <h3 class="financials__panel-title">{{ t("Revenue by organization and month") }}</h3>
          <span class="financials__panel-meta">
            {{ n("%n organization", "%n organizations", orgs.length) }}
            · {{ windowLabel }}
          </span>
        </div>

        <p v-if="noRevenue" class="financials__empty">{{ emptyGridMessage }}</p>

        <p v-if="deletedPlans" class="financials__warn">
          {{ n("%n plan referenced here has since been deleted. Its prices are gone from the database, so revenue billed on it reads as {zero} rather than what was actually charged.", "%n plans referenced here have since been deleted. Their prices are gone from the database, so revenue billed on them reads as {zero} rather than what was actually charged.", deletedPlans, { zero: money(0) }) }}
        </p>

        <RevenueGrid
          :months="data.months"
          :orgs="orgs"
          :totals="data.totals"
          :now-index="data.nowIndex"
          :currency="summary.currency"
          @open-org="openOrg"
        />
      </div>

      <div class="financials__band">
        <div class="iz-card financials__panel">
          <div class="financials__panel-head">
            <h3 class="financials__panel-title">{{ t("What happened") }}</h3>
            <span class="financials__panel-meta">
              {{ n("%n change", "%n changes", data.events.length) }}
            </span>
          </div>
          <div v-if="data.events.length" class="financials__scroller">
            <div
              v-for="(event, i) in data.events"
              :key="'ev-' + i"
              class="financials__event"
            >
              <span class="financials__event-when">{{ shortDate(event.at) }}</span>
              <span class="financials__event-what">
                <button
                  type="button"
                  class="financials__org-btn"
                  :aria-label="t('Open {organization} in Organizations', { organization: event.orgName })"
                  @click="openOrg(event.orgId)"
                >{{ event.orgName }}</button>
                <span>{{ t("{from} → {to}", { from: event.from, to: event.to }) }}</span>
                <span
                  v-if="event.unreflected"
                  class="iz-badge iz-badge--warning"
                  :title="t('Recorded in the subscription audit trail, but this organization\'s current subscription does not match it — so the amount on this line never took effect. Only the most recent logged change can be checked this way.')"
                >{{ t("logged, never applied") }}</span>
                <span v-if="event.notes" class="financials__event-note">“{{ event.notes }}”</span>
              </span>
              <span class="financials__event-amount" :class="amountTone(event.amount)">
                {{ signedMoney(event.amount) }}
              </span>
            </div>
          </div>
          <p v-else class="financials__empty">{{ t("No subscription changes recorded yet.") }}</p>
        </div>

        <div class="iz-card financials__panel">
          <div class="financials__panel-head">
            <h3 class="financials__panel-title">{{ t("What lands next") }}</h3>
            <span class="financials__panel-meta">
              {{ n("%n renewal", "%n renewals", data.renewals.length) }}
            </span>
          </div>
          <div v-if="data.renewals.length" class="financials__scroller">
            <div
              v-for="(renewal, i) in data.renewals"
              :key="'rn-' + i"
              class="financials__event"
            >
              <span class="financials__event-when">{{ shortDate(renewal.at) }}</span>
              <span class="financials__event-what">
                <button
                  type="button"
                  class="financials__org-btn"
                  :aria-label="t('Open {organization} in Organizations', { organization: renewal.orgName })"
                  @click="openOrg(renewal.orgId)"
                >{{ renewal.orgName }}</button>
                <span>{{ t("{plan} renews", { plan: renewal.plan }) }}</span>
              </span>
              <span class="financials__event-amount">{{ moneyIn(renewal.price, renewal.currency) }}</span>
            </div>
          </div>
          <p v-else class="financials__empty">{{ t("No renewals scheduled.") }}</p>
        </div>
      </div>

      <div class="iz-card financials__panel">
        <div class="financials__panel-head">
          <h3 class="financials__panel-title">{{ t("Plans against usage bands") }}</h3>
          <span class="financials__panel-meta">
            {{ n("%n plan", "%n plans", plans.length) }}
            {{ t("· active subscriptions only") }}
          </span>
        </div>

        <PlanBandsPanel
          :plans="plans"
          :bands="bandKeys"
          :currency="summary.currency"
          :active-plan-id="bandSelection.planId"
          :active-band="bandSelection.band"
          @pick="onBandPick"
        />
      </div>

      <div ref="roster" class="iz-card financials__panel">
        <div class="financials__panel-head">
          <h3 class="financials__panel-title">{{ t("Organizations") }}</h3>
          <span class="financials__panel-meta">{{ rosterMeta }}</span>
        </div>

        <div class="financials__toolbar">
          <div class="financials__toolbar-row">
            <input
              v-model="search"
              class="iz-input financials__search"
              type="text"
              name="superadminpage-financials-search"
              autocomplete="off"
              data-1p-ignore
              data-bwignore
              data-lpignore="true"
:aria-label="t('Search organizations')"
              :placeholder="t('Search organizations…')"
            />

            <div class="financials__chips" role="group" :aria-label="t('Subscription status')">
              <button
                v-for="opt in statusOptions"
                :key="opt.value"
                type="button"
                class="iz-chip"
                :class="{ 'iz-chip--active': statusFilter === opt.value }"
                :aria-pressed="String(statusFilter === opt.value)"
                @click="statusFilter = opt.value"
              >
                {{ opt.label() }}
                <span class="financials__chip-count">{{ statusCount(opt.value) }}</span>
              </button>
            </div>
          </div>

          <div class="financials__toolbar-row">
            <div class="financials__field">
              <label :for="fieldId('plan')">{{ t("Plan") }}</label>
              <select :id="fieldId('plan')" v-model="planFilter" class="iz-select iz-select--sm">
                <option :value="null">{{ t("All plans") }}</option>
                <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                <option v-if="hasUnplanned" value="none">{{ t("No plan") }}</option>
              </select>
            </div>

            <div class="financials__field">
              <label :for="fieldId('usage')">{{ t("Of cap") }}</label>
              <select :id="fieldId('usage')" v-model="usageFilter" class="iz-select iz-select--sm">
                <option :value="null">{{ t("Any usage") }}</option>
                <option v-for="band in bandKeys" :key="band" :value="band">{{ bandLabel(band) }}</option>
              </select>
            </div>

            <div class="financials__field">
              <label :for="fieldId('revenue')">{{ t("Revenue") }}</label>
              <select :id="fieldId('revenue')" v-model="revenueFilter" class="iz-select iz-select--sm">
                <option value="all">{{ t("Any") }}</option>
                <option value="paying">{{ t("Paying") }}</option>
                <option value="free">{{ t("Free") }}</option>
              </select>
            </div>

            <div class="financials__field">
              <label :for="fieldId('renew')">{{ t("Renews") }}</label>
              <select :id="fieldId('renew')" v-model.number="renewFilter" class="iz-select iz-select--sm">
                <option :value="0">{{ t("Any time") }}</option>
                <option :value="30">{{ t("≤ 30 days") }}</option>
                <option :value="90">{{ t("≤ 90 days") }}</option>
                <option :value="180">{{ t("≤ 180 days") }}</option>
              </select>
            </div>

            <div class="financials__field">
              <label :for="fieldId('sort')">{{ t("Sort") }}</label>
              <select :id="fieldId('sort')" v-model="sortBy" class="iz-select iz-select--sm">
                <option value="revenue">{{ t("Revenue") }}</option>
                <option value="billed">{{ t("Billed") }}</option>
                <option value="usage">{{ t("Of cap") }}</option>
                <option value="renews">{{ t("Renewal date") }}</option>
                <option value="name">{{ t("Name") }}</option>
              </select>
            </div>

            <button
              type="button"
              class="iz-btn iz-btn--sm"
              :disabled="!anyFilter"
              @click="clearFilters"
            >{{ t("Clear") }}</button>
          </div>
        </div>

        <div v-if="!filteredOrgs.length" class="iz-empty">
          {{ t("No organizations match these filters.") }}
        </div>

        <div v-else class="iz-table-wrap">
          <table class="iz-table financials__table">
            <thead>
              <tr>
                <th scope="col">{{ t("Organization") }}</th>
                <th scope="col">{{ t("Plan") }}</th>
                <th scope="col" class="financials__num">{{ t("Price / mo") }}</th>
                <th scope="col" class="financials__num">
                  <span :title="billedTitle">{{ t("Billed, {n} mo", { n: recordedMonths }) }}</span>
                </th>
                <th scope="col">{{ t("Status") }}</th>
                <th scope="col">{{ t("Renews") }}</th>
                <th scope="col" class="financials__num">{{ t("Members") }}</th>
                <th scope="col" class="financials__num">{{ t("Projects") }}</th>
                <th scope="col" class="financials__num">{{ t("Of cap") }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="org in pagedOrgs" :key="org.id">
                <td>
                  <span class="financials__org">
                    <span class="financials__avatar">{{ initial(org.name) }}</span>
                    <button
                      type="button"
                      class="financials__org-btn"
                      :aria-label="t('Open {organization} in Organizations', { organization: org.name })"
                      @click="openOrg(org.id)"
                    >{{ org.name }}</button>
                  </span>
                </td>
                <td>{{ org.plan }}</td>
                <td class="financials__num financials__price">{{ moneyIn(org.price, org.currency) }}</td>
                <td class="financials__num financials__price">
                  <span :class="{ 'financials__muted': !org.billed }">
                    {{ org.billed ? moneyIn(org.billed, org.currency) : "—" }}
                  </span>
                </td>
                <td><span class="iz-badge" :class="statusTone(org.status)">{{ statusLabel(org.status) }}</span></td>
                <td :class="{ 'financials__muted': !renewsOn(org) }">{{ renewsOn(org) }}</td>
                <td class="financials__num">
                  <span :class="{ 'financials__at-cap': atCap(org.members) }">
                    {{ org.members.used }} / {{ org.members.cap }}
                  </span>
                </td>
                <td class="financials__num">
                  <span :class="{ 'financials__at-cap': atCap(org.projects) }">
                    {{ org.projects.used }} / {{ org.projects.cap }}
                  </span>
                </td>
                <td class="financials__num">
                  <span class="financials__usage" :title="usageTitle(org)">
                    <i class="financials__band-dot" :class="bandClass(org.usageBand)"></i>
                    {{ usageText(org) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="filteredOrgs.length" class="financials__pagination-bar">
          <div class="financials__page-summary">
            {{ t("Showing {start}–{end} of {total}", { start: rangeStart, end: rangeEnd, total: filteredOrgs.length }) }}
            <span v-if="filteredOrgs.length !== orgs.length">
              {{ t("filtered from {n}", { n: orgs.length }) }}
            </span>
          </div>

          <div v-if="totalPages > 1" class="iz-pagination financials__pagination">
            <button
              type="button"
              class="iz-btn iz-btn--sm financials__page-btn"
              :disabled="currentPage === 1"
              :aria-label="t('First page')"
              @click="currentPage = 1"
            >«</button>
            <button
              type="button"
              class="iz-btn iz-btn--sm financials__page-btn"
              :disabled="currentPage <= 1"
              :aria-label="t('Previous page')"
              @click="currentPage--"
            >‹</button>
            <template v-for="(p, i) in visiblePages">
              <span
                v-if="p === '…'"
                :key="'ellipsis-' + i"
                class="financials__page-ellipsis"
              >…</span>
              <button
                v-else
                :key="'page-' + p"
                type="button"
                class="financials__page-num"
                :class="{ 'financials__page-num--active': p === currentPage }"
                :aria-current="p === currentPage ? 'page' : null"
                @click="currentPage = p"
              >{{ p }}</button>
            </template>
            <button
              type="button"
              class="iz-btn iz-btn--sm financials__page-btn"
              :disabled="currentPage >= totalPages"
              :aria-label="t('Next page')"
              @click="currentPage++"
            >›</button>
            <button
              type="button"
              class="iz-btn iz-btn--sm financials__page-btn"
              :disabled="currentPage === totalPages"
              :aria-label="t('Last page')"
              @click="currentPage = totalPages"
            >»</button>
          </div>

          <div class="financials__page-size">
            <label :for="pageSizeId">{{ t("Per page") }}</label>
            <select
              :id="pageSizeId"
              v-model.number="pageSize"
              class="iz-select iz-select--sm"
            >
              <option v-for="size in pageSizeOptions" :key="size" :value="size">{{ size }}</option>
            </select>
          </div>
        </div>
      </div>
    </template>
  </section>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";
import RevenueGrid from "./RevenueGrid.vue";
import PlanBandsPanel from "./PlanBandsPanel.vue";
import { t, n, locale } from "../lib/l10n.mjs";

const PAGE_SIZE_OPTIONS = [10, 20, 50, 100];

// Filters work on the status code (value); label() is only what is shown.
const STATUS_OPTIONS = [
  { value: "all", label: () => t("All") },
  { value: "active", label: () => t("Active") },
  { value: "paused", label: () => t("Paused") },
  { value: "cancelled", label: () => t("Cancelled") },
  { value: "expired", label: () => t("Expired") },
  { value: "none", label: () => t("No plan") },
];

// Band keys come from the payload so the service owns the thresholds; only the
// wording lives here, as an explicit table with a fallback to the raw key.
const BAND_LABELS = {
  low: () => t("under 50%"),
  mid: () => t("50–79%"),
  high: () => t("80–99%"),
  cap: () => t("at or over cap"),
  none: () => t("not measured"),
};

const BAND_CLASSES = {
  low: "financials__band-dot--low",
  mid: "financials__band-dot--mid",
  high: "financials__band-dot--high",
  cap: "financials__band-dot--cap",
};

// Explicit tables, never a concatenated class name — an unexpected status falls
// back to a real class instead of resolving to nothing.
const STATUS_BADGES = {
  active: "iz-badge--success",
  paused: "iz-badge--warning",
  cancelled: "iz-badge--muted",
  expired: "iz-badge--danger",
  none: "iz-badge--muted",
};

const STATUS_LABELS = {
  active: () => t("Active"),
  paused: () => t("Paused"),
  cancelled: () => t("Cancelled"),
  expired: () => t("Expired"),
  none: () => t("No plan"),
};

export default {
  name: "FinancialsPanel",
  components: { RevenueGrid, PlanBandsPanel },
  data() {
    return {
      data: null,
      loading: true,
      error: null,
      currentPage: 1,
      pageSize: 10,
      pageSizeOptions: PAGE_SIZE_OPTIONS,
      statusOptions: STATUS_OPTIONS,
      search: "",
      statusFilter: "all",
      planFilter: null,
      usageFilter: null,
      revenueFilter: "all",
      renewFilter: 0,
      sortBy: "revenue",
    };
  },
  computed: {
    summary() {
      return (this.data && this.data.summary) || {};
    },
    orgs() {
      return (this.data && this.data.orgs) || [];
    },
    plans() {
      return (this.data && this.data.plans) || [];
    },
    bandKeys() {
      return (this.data && this.data.bands) || [];
    },
    hasUnplanned() {
      return this.orgs.some((org) => org.planId === null);
    },
    recordedMonths() {
      return (this.data ? this.data.nowIndex : 0) + 1;
    },
    billedTitle() {
      return t(
        "List price summed over the {n} recorded months of this window ({window} runs longer, but committed months are not billed). Anything before the window is not counted, so this is not lifetime value.",
        { n: this.recordedMonths, window: this.windowLabel },
      );
    },
    // A cell counts ACTIVE subscriptions, on one plan, in one band. So it may
    // only show as selected when the roster is showing exactly that population —
    // every facet, not just the two the cell names. Deriving the highlight from
    // plan and band alone lit a cell reading "1" above a table of 2, and because
    // pick() reads a lit cell as "already applied", clicking it then cleared the
    // filter instead of applying it.
    bandSelection() {
      const isPickState =
        this.usageFilter !== null &&
        this.statusFilter === "active" &&
        this.planFilter !== "none" &&
        this.search.trim() === "" &&
        this.revenueFilter === "all" &&
        this.renewFilter === 0;
      if (!isPickState) {
        return { planId: null, band: null };
      }
      return {
        planId: typeof this.planFilter === "number" ? this.planFilter : null,
        band: this.usageFilter,
      };
    },
    anyFilter() {
      return (
        this.search.trim() !== "" ||
        this.statusFilter !== "all" ||
        this.planFilter !== null ||
        this.usageFilter !== null ||
        this.revenueFilter !== "all" ||
        this.renewFilter !== 0
      );
    },
    // Everything except the status facet, so the count on each status chip says
    // how many rows that chip would actually produce from here — not a platform
    // total that contradicts the table next to it.
    baseFiltered() {
      const needle = this.search.trim().toLowerCase();
      return this.orgs.filter((org) => {
        if (needle && String(org.name).toLowerCase().indexOf(needle) === -1) return false;
        if (this.planFilter === "none") {
          if (org.planId !== null) return false;
        } else if (this.planFilter !== null && org.planId !== this.planFilter) {
          return false;
        }
        if (this.usageFilter !== null && org.usageBand !== this.usageFilter) return false;
        if (this.revenueFilter === "paying" && !(org.price > 0)) return false;
        if (this.revenueFilter === "free" && org.price > 0) return false;
        if (this.renewFilter) {
          // Only a running subscription has a renewal date — see renewsOn().
          if (org.status !== "active" || !org.endedAt) return false;
          const days = this.daysUntil(org.endedAt);
          if (days === null || days > this.renewFilter) return false;
        }
        return true;
      });
    },
    filteredOrgs() {
      const rows = this.baseFiltered.filter(
        (org) => this.statusFilter === "all" || org.status === this.statusFilter,
      );
      return this.sortRows(rows);
    },
    rosterMeta() {
      const parts = [];
      if (this.filteredOrgs.length === this.orgs.length) {
        parts.push(n("%n organization", "%n organizations", this.orgs.length));
      } else {
        parts.push(t("{filtered} of {total}", { filtered: this.filteredOrgs.length, total: this.orgs.length }));
      }
      if (this.planFilter !== null) {
        parts.push(this.planFilter === "none" ? t("no plan") : this.planName(this.planFilter));
      }
      if (this.usageFilter !== null) parts.push(this.bandLabel(this.usageFilter));
      return parts.join(" · ");
    },
    noRevenue() {
      return this.data !== null && this.data.totals.every((total) => total === 0);
    },
    dataQuality() {
      return (this.data && this.data.dataQuality) || {};
    },
    deletedPlans() {
      return this.dataQuality.deletedPlans || 0;
    },
    // An empty grid has several distinct causes and they are not interchangeable.
    // "Everything is priced zero" is a claim about oc_plans, so it is only made
    // when the backend actually says so — inferring it from an all-zero grid
    // would tell an admin their pricing is broken when the real story is that
    // every subscription has lapsed.
    emptyGridMessage() {
      const zero = this.money(0);
      if (!this.orgs.length) {
        return t("No organizations on this platform yet, so there is nothing to bill.");
      }
      if (this.dataQuality.planCount === 0) {
        return t("No plans have been created yet, so no organization can be billed.");
      }
      if (this.dataQuality.allPlansFree) {
        return t(
          "No revenue recorded. Every plan on this platform is currently priced {zero}, so the grid below has nothing to shade.",
          { zero },
        );
      }
      return t("No revenue recorded in this window. Priced plans exist, but no subscription was billing during these months.");
    },
    windowLabel() {
      const months = (this.data && this.data.months) || [];
      if (!months.length) return "";
      const first = months[0];
      const last = months[months.length - 1];
      // Month labels arrive localised from the backend.
      return t("{from} – {to}", {
        from: first.label + " " + first.year,
        to: last.label + " " + last.year,
      });
    },
    pageSizeId() {
      return "financials-page-size-" + this._uid;
    },
    filterSignature() {
      return [
        this.search.trim(),
        this.statusFilter,
        String(this.planFilter),
        String(this.usageFilter),
        this.revenueFilter,
        this.renewFilter,
      ].join("|");
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.filteredOrgs.length / this.pageSize));
    },
    pagedOrgs() {
      const start = (this.currentPage - 1) * this.pageSize;
      return this.filteredOrgs.slice(start, start + this.pageSize);
    },
    rangeStart() {
      return this.filteredOrgs.length === 0 ? 0 : (this.currentPage - 1) * this.pageSize + 1;
    },
    rangeEnd() {
      return Math.min(this.currentPage * this.pageSize, this.filteredOrgs.length);
    },
    visiblePages() {
      const total = this.totalPages;
      const current = this.currentPage;
      if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
      }
      const pages = [1];
      if (current > 3) pages.push("…");
      const start = Math.max(2, current - 1);
      const end = Math.min(total - 1, current + 1);
      for (let p = start; p <= end; p++) pages.push(p);
      if (current < total - 2) pages.push("…");
      pages.push(total);
      return pages;
    },
  },
  watch: {
    pageSize() {
      this.currentPage = 1;
    },
    totalPages(val) {
      if (this.currentPage > val) this.currentPage = val;
    },
    // Any change to the filter set puts the reader back on page one — staying on
    // page 4 of a result that just shrank to six rows shows an empty table.
    filterSignature() {
      this.currentPage = 1;
    },
  },
  mounted() {
    this.fetch();
  },
  methods: {
    async fetch() {
      try {
        const res = await axios.get(
          generateUrl("/apps/superadminpage/api/super/subscriptions"),
        );
        this.data = res.data || null;
      } catch (e) {
        console.error("Failed to load financials", e);
        this.error = this.friendlyError(e);
      } finally {
        this.loading = false;
      }
    },
    reload() {
      this.error = null;
      this.loading = true;
      this.fetch();
    },
    friendlyError(e) {
      const status = e && e.response && e.response.status;
      const serverMsg = e && e.response && e.response.data && e.response.data.message;
      if (status === 403) {
        return t("You don't have permission to view this data — super-admin access is required.");
      }
      if (serverMsg) return serverMsg;
      if (status && status >= 500) {
        return t("The server ran into a problem loading financials. Please try again in a moment.");
      }
      if (e && e.request && !e.response) {
        return t("Couldn't reach the server. Check your connection and try again.");
      }
      return t("Something went wrong loading financials. Please try again.");
    },
    // Platform-level totals: one currency, the dominant one, with the
    // mixedCurrency banner covering the case where that is a simplification.
    money(value) {
      return this.moneyIn(value, this.summary.currency);
    },
    // A single organization is always shown in ITS OWN currency — the payload
    // carries it per row. Formatting a JPY plan with the platform's euro symbol
    // would overstate that organization's price by roughly thirty times.
    moneyIn(value, currency) {
      try {
        return new Intl.NumberFormat(locale(), {
          style: "currency",
          currency: currency || this.summary.currency || "EUR",
        }).format(value || 0);
      } catch (e) {
        // Unknown or malformed ISO code from the plans table — show the number
        // rather than throwing inside a render.
        return (value || 0).toFixed(2);
      }
    },
    signedMoney(value) {
      if (!value) return "—";
      const amount = this.money(Math.abs(value));
      return value > 0 ? t("+{amount}", { amount }) : t("−{amount}", { amount });
    },
    deltaText(value) {
      if (!value) return t("±{amount}", { amount: this.money(0) });
      const amount = this.money(Math.abs(value));
      return value > 0 ? t("▲ {amount}", { amount }) : t("▼ {amount}", { amount });
    },
    deltaTone(value) {
      if (!value) return "iz-badge--muted";
      return value > 0 ? "iz-badge--success" : "iz-badge--danger";
    },
    amountTone(value) {
      if (!value) return "financials__event-amount--flat";
      return value > 0
        ? "financials__event-amount--up"
        : "financials__event-amount--down";
    },
    statusTone(status) {
      return STATUS_BADGES[status] || "iz-badge--muted";
    },
    statusLabel(status) {
      const label = STATUS_LABELS[status];
      return label ? label() : status;
    },
    atCap(usage) {
      return usage && usage.cap > 0 && usage.used >= usage.cap;
    },
    fieldId(name) {
      return "financials-" + name + "-" + this._uid;
    },
    bandLabel(band) {
      const label = BAND_LABELS[band];
      return label ? label() : band;
    },
    bandClass(band) {
      return BAND_CLASSES[band] || "financials__band-dot--none";
    },
    planName(planId) {
      const plan = this.plans.filter((p) => p.id === planId)[0];
      return plan ? plan.name : t("Unknown plan");
    },
    statusCount(value) {
      if (value === "all") return this.baseFiltered.length;
      return this.baseFiltered.filter((org) => org.status === value).length;
    },
    // Floored below the cap, so the printed percentage can never contradict the
    // band next to it: rounding 0.795 up to "80%" on a row the service banded as
    // 50–79% reads as a bug, and rounding 199/200 up to "100%" claims a cap the
    // organization has not actually reached.
    usageText(org) {
      if (org.usage === null || org.usage === undefined) return "—";
      const pct = org.usage < 1 ? Math.floor(org.usage * 100) : Math.round(org.usage * 100);
      return pct + "%";
    },
    usageTitle(org) {
      if (org.usage === null || org.usage === undefined) {
        return t("{organization} · this plan caps neither members nor projects", { organization: org.name });
      }
      const tighter =
        org.projects.cap > 0 && org.members.cap > 0
          ? org.projects.used / org.projects.cap > org.members.used / org.members.cap
            ? "projects"
            : "members"
          : org.members.cap > 0
            ? "members"
            : "projects";
      const vars = {
        organization: org.name,
        percentage: this.usageText(org),
        band: this.bandLabel(org.usageBand),
      };
      return tighter === "members"
        ? t("{organization} · {percentage} of its plan cap ({band}), set by members", vars)
        : t("{organization} · {percentage} of its plan cap ({band}), set by projects", vars);
    },
    // Sorts are stable on name so that equal values — every free plan at zero,
    // for instance — do not shuffle between renders.
    sortRows(rows) {
      const byName = (a, b) => String(a.name).localeCompare(String(b.name));
      const copy = rows.slice();
      if (this.sortBy === "name") return copy.sort(byName);
      if (this.sortBy === "billed") return copy.sort((a, b) => b.billed - a.billed || byName(a, b));
      if (this.sortBy === "usage") {
        return copy.sort((a, b) => {
          const av = a.usage === null || a.usage === undefined ? -1 : a.usage;
          const bv = b.usage === null || b.usage === undefined ? -1 : b.usage;
          return bv - av || byName(a, b);
        });
      }
      if (this.sortBy === "renews") {
        // Rows with no renewal date sort last rather than sorting as the
        // earliest — an empty string would put them at the top of an ascending
        // date sort and read as "renewing first".
        return copy.sort((a, b) => {
          const av = a.status === "active" && a.endedAt ? String(a.endedAt) : "";
          const bv = b.status === "active" && b.endedAt ? String(b.endedAt) : "";
          if (av === bv) return byName(a, b);
          if (!av) return 1;
          if (!bv) return -1;
          return av < bv ? -1 : 1;
        });
      }
      return copy.sort((a, b) => b.now - a.now || byName(a, b));
    },
    // Whole days from today to a 'YYYY-MM-DD …' date, negative if already past.
    // Built from UTC parts rather than new Date(string): Safari rejects the
    // space-separated form the API returns.
    daysUntil(value) {
      const m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
      if (!m) return null;
      const target = Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
      const today = new Date();
      const start = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
      return Math.round((target - start) / 86400000);
    },
    clearFilters() {
      this.search = "";
      this.statusFilter = "all";
      this.planFilter = null;
      this.usageFilter = null;
      this.revenueFilter = "all";
      this.renewFilter = 0;
    },
    // A pick is a complete statement of what the roster should show, not a
    // refinement of what it already shows. The cell prints a count, and the only
    // way the table underneath it can be that count is if nothing else is
    // narrowing the table too — so picking replaces the whole filter set (a
    // search left in the box would otherwise silently subtract from the number
    // the reader just clicked). Picking again clears it, which is what the hint
    // under the grid promises.
    onBandPick({ planId, band }) {
      this.clearFilters();
      if (band !== null) {
        this.planFilter = planId;
        this.usageFilter = band;
        this.statusFilter = "active";
      }
      this.$nextTick(() => {
        const el = this.$refs.roster;
        if (el && el.scrollIntoView) {
          el.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      });
    },
    // `ended_at` is the end of the current term, which is only a *renewal* date
    // while the subscription is still running. On a cancelled or expired row it
    // is the date the thing stopped, and printing that under a "Renews" heading
    // claims the opposite of what happened.
    renewsOn(org) {
      if (org.status !== "active" || !org.endedAt) return "—";
      return this.shortDate(org.endedAt);
    },
    initial(name) {
      return (name || "?").charAt(0).toUpperCase();
    },
    // Every mention of an organization on this tab — grid row, audit trail,
    // renewal schedule, roster — leads to the same place: that organization in
    // the Organizations tab, opened on its Subscription panel, which is the one
    // that answers the question the money view just raised. Reuses the drill-down
    // contract the alert cards already emit; Dashboard does the tab switch and
    // the scroll.
    openOrg(orgId) {
      if (!orgId) return;
      this.$emit("drill-down", { orgId: orgId, tab: "subscription" });
    },
    // 'YYYY-MM-DD HH:MM:SS' from the API. Parsed by hand rather than with
    // new Date(): Safari rejects the space-separated form outright.
    shortDate(value) {
      if (!value) return "—";
      const m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
      if (!m) return String(value);
      const date = new Date(Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3])));
      try {
        return date.toLocaleDateString(locale(), { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" });
      } catch (e) {
        return m[3] + "-" + m[2] + "-" + m[1];
      }
    },
  },
};
</script>

<style scoped>
.financials {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
  min-width: 0;
}

.financials__state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: var(--spacing-2xl);
  color: var(--color-text-secondary);
  font-size: var(--iz-fs-md);
}
.financials__state--error {
  color: var(--color-danger);
}
.financials__error-msg {
  max-width: 420px;
  text-align: center;
  margin: 0;
}

.financials__strip {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 26px;
  background: var(--bg-card);
  border-radius: var(--radius-card);
  box-shadow: var(--shadow-card);
  padding: 14px 20px;
}
.financials__stat {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.financials__stat-label {
  font-size: var(--iz-fs-micro);
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}
.financials__stat-value {
  display: flex;
  align-items: baseline;
  gap: 8px;
  font-family: 'Space Grotesk', system-ui, -apple-system, sans-serif;
  font-size: var(--iz-fs-xl);
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.financials__source {
  margin-left: auto;
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted);
}

.financials__warn {
  margin: 0;
  padding: 9px 13px;
  border-radius: var(--radius-el);
  background: var(--color-warning-bg);
  color: var(--color-warning-text);
  font-size: var(--iz-fs-sm);
}

.financials__panel {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 20px 22px;
  min-width: 0;
}
.financials__panel-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 6px;
}
.financials__panel-title {
  margin: 0;
  font-family: 'Space Grotesk', system-ui, -apple-system, sans-serif;
  font-size: var(--iz-fs-md);
  font-weight: 600;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
.financials__panel-meta {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
}

.financials__empty {
  margin: 0;
  padding: 10px 13px;
  border-radius: var(--radius-el);
  background: var(--bg-subtle);
  color: var(--color-text-secondary);
  font-size: var(--iz-fs-sm);
}

.financials__band {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--spacing-md);
}
@media (max-width: 900px) {
  .financials__band {
    grid-template-columns: 1fr;
  }
}

.financials__scroller {
  max-height: 296px;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.financials__event {
  display: grid;
  grid-template-columns: 96px 1fr auto;
  gap: 12px;
  align-items: baseline;
  padding: 9px 2px;
  border-bottom: 1px solid var(--color-border);
}
.financials__event:last-child {
  border-bottom: 0;
}
@media (max-width: 720px) {
  .financials__event {
    grid-template-columns: 1fr;
    gap: 3px;
  }
}
.financials__event-when {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.financials__event-what {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 7px;
  font-size: var(--iz-fs-md);
  min-width: 0;
}
.financials__event-note {
  color: var(--color-text-muted);
  font-style: italic;
  font-size: var(--iz-fs-sm);
}
/* .iz-badge capitalizes, which is right for the single status words it was
   built for and wrong for a phrase — without this, "logged, never applied" is
   rendered as "Logged, Never Applied". */
.financials__event-what .iz-badge {
  text-transform: none;
}
.financials__event-amount {
  font-family: 'Space Grotesk', system-ui, -apple-system, sans-serif;
  font-weight: 700;
  font-size: var(--iz-fs-sm);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.financials__event-amount--up {
  color: var(--color-success-text);
}
.financials__event-amount--down {
  color: var(--color-danger-text);
}
.financials__event-amount--flat {
  color: var(--color-text-muted);
}

/* Layout only — the field chrome is .iz-input / .iz-select / .iz-chip / .iz-btn. */
/* Two rows on purpose: what you type or click on the first, what you narrow with
   on the second. One wrapping row put the labelled selects at both ends of a
   break, which read as two unrelated groups. */
.financials__toolbar {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.financials__toolbar-row {
  display: flex;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 10px 14px;
}
.financials__search {
  flex: 1 1 200px;
  min-width: 160px;
  max-width: 280px;
}
.financials__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
/* No opacity here: dimming the count against the chip's own tinted background
   drops it below the contrast floor, and the count is data, not decoration. */
.financials__chip-count {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
}
.financials__field {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.financials__field label {
  font-size: var(--iz-fs-micro);
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.financials__table {
  width: 100%;
}
.financials__usage {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-variant-numeric: tabular-nums;
}
.financials__band-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
  background: var(--band-c, var(--color-text-muted));
}
/* Same band palette as the plan grid above, so a band reads as one thing across
   the tab. Scoped styles do not cross components, hence the second copy. */
.financials__band-dot--low { --band-c: var(--chart-3); }
.financials__band-dot--mid { --band-c: var(--chart-5); }
.financials__band-dot--high { --band-c: var(--chart-4); }
.financials__band-dot--cap { --band-c: var(--color-danger); }
.financials__band-dot--none { --band-c: var(--color-border-strong); }
.financials__num {
  text-align: right;
}
.financials__price {
  font-family: 'Space Grotesk', system-ui, -apple-system, sans-serif;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.financials__muted {
  color: var(--color-text-muted);
}
.financials__at-cap {
  color: var(--color-danger-text);
  font-weight: 600;
}
.financials__org {
  display: flex;
  align-items: center;
  gap: 9px;
  font-weight: 600;
}

/* Qualified on `button` and with the min-height reset, or Nextcloud core's bare
   button rules (padding + var(--default-clickable-area)) push these out of the
   text line they sit in. The name is the whole control — no chrome until hover
   or focus, same as the alert cards' drill-down tags. */
button.financials__org-btn {
  font: inherit;
  font-weight: 600;
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
button.financials__org-btn:hover {
  color: var(--accent);
  text-decoration: underline;
  background: transparent;
}
button.financials__org-btn:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
  border-radius: var(--radius-sm);
}
.financials__avatar {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: var(--accent);
  color: var(--color-primary-element-text, #fff);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--iz-fs-xs);
  font-weight: 700;
  flex-shrink: 0;
}

/* Same pagination bar as OrgListPanel — layout only; chrome is .iz-btn. */
.financials__pagination-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}
.financials__page-summary {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary);
  font-variant-numeric: tabular-nums;
}
.financials__page-summary strong {
  color: var(--color-text-primary);
  font-weight: 600;
}
.financials__pagination {
  display: flex;
  align-items: center;
  gap: 4px;
}
.financials__page-btn {
  min-width: 30px;
}
/* Qualified on `button` and with min-height reset: Nextcloud core styles bare
   buttons with `min-height: var(--default-clickable-area)` (34px), which beats a
   plain class selector and left the numbered pages 34px tall next to the 25px
   .iz-btn--sm arrows in the same row. */
button.financials__page-num {
  font: inherit;
  font-size: var(--iz-fs-sm);
  font-weight: 600;
  min-width: 30px;
  min-height: 0;
  height: 30px;
  padding: 0 8px;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--bg-card);
  color: var(--color-text-secondary);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-variant-numeric: tabular-nums;
}
button.financials__page-num:hover:not(.financials__page-num--active) {
  background: var(--accent-bg);
  color: var(--accent-on-bg);
  border-color: var(--accent);
}
button.financials__page-num--active {
  background: var(--accent);
  border-color: var(--accent);
  color: var(--color-primary-element-text, #fff);
}
button.financials__page-num:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}
.financials__page-ellipsis {
  padding: 0 4px;
  color: var(--color-text-muted);
  font-size: var(--iz-fs-sm);
}
.financials__page-size {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary);
}
</style>
