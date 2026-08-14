<template>
  <section class="financials">
    <div v-if="loading" class="financials__state">
      <div class="iz-spinner iz-spinner--lg"></div>
      <p>Loading financials…</p>
    </div>

    <div v-else-if="error" class="financials__state financials__state--error">
      <p class="financials__error-msg">{{ error }}</p>
      <button type="button" class="iz-btn iz-btn--accent" @click="reload">Try again</button>
    </div>

    <template v-else-if="data">
      <div class="financials__strip">
        <div class="financials__stat">
          <span class="financials__stat-label">MRR</span>
          <span class="financials__stat-value">
            {{ money(summary.mrr) }}
            <span class="iz-badge" :class="deltaTone(summary.mrrDelta)">{{ deltaText(summary.mrrDelta) }}</span>
          </span>
        </div>
        <div class="financials__stat">
          <span class="financials__stat-label">ARR</span>
          <span class="financials__stat-value">{{ money(summary.arr) }}</span>
        </div>
        <div class="financials__stat">
          <span class="financials__stat-label">Active</span>
          <span class="financials__stat-value">{{ summary.activeSubs }} / {{ summary.totalOrgs }}</span>
        </div>
        <div class="financials__stat">
          <span class="financials__stat-label">Up for renewal</span>
          <span class="financials__stat-value">{{ money(summary.upForRenewal) }}</span>
        </div>
        <span class="financials__source">contracted revenue · list prices, not payments</span>
      </div>

      <p v-if="summary.mixedCurrency" class="financials__warn">
        Plans on this platform are priced in more than one currency. Totals here add the
        raw numbers without converting them, so treat them as indicative.
      </p>

      <div class="iz-card financials__panel">
        <div class="financials__panel-head">
          <h3 class="financials__panel-title">Revenue by organization and month</h3>
          <span class="financials__panel-meta">
            {{ orgs.length }} {{ orgs.length === 1 ? "organization" : "organizations" }}
            · {{ windowLabel }}
          </span>
        </div>

        <p v-if="noRevenue" class="financials__empty">{{ emptyGridMessage }}</p>

        <p v-if="deletedPlans" class="financials__warn">
          {{ deletedPlans }} {{ deletedPlans === 1 ? "plan referenced here has" : "plans referenced here have" }}
          since been deleted. Their prices are gone from the database, so revenue billed on
          {{ deletedPlans === 1 ? "it" : "them" }} reads as {{ money(0) }} rather than what was actually charged.
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
            <h3 class="financials__panel-title">What happened</h3>
            <span class="financials__panel-meta">
              {{ data.events.length }} {{ data.events.length === 1 ? "change" : "changes" }}
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
                  :aria-label="'Open ' + event.orgName + ' in Organizations'"
                  @click="openOrg(event.orgId)"
                >{{ event.orgName }}</button>
                <span>{{ event.from }} → {{ event.to }}</span>
                <span
                  v-if="event.unreflected"
                  class="iz-badge iz-badge--warning"
                  title="Recorded in the subscription audit trail, but this organization's current subscription does not match it — so the amount on this line never took effect. Only the most recent logged change can be checked this way."
                >logged, never applied</span>
                <span v-if="event.notes" class="financials__event-note">“{{ event.notes }}”</span>
              </span>
              <span class="financials__event-amount" :class="amountTone(event.amount)">
                {{ signedMoney(event.amount) }}
              </span>
            </div>
          </div>
          <p v-else class="financials__empty">No subscription changes recorded yet.</p>
        </div>

        <div class="iz-card financials__panel">
          <div class="financials__panel-head">
            <h3 class="financials__panel-title">What lands next</h3>
            <span class="financials__panel-meta">
              {{ data.renewals.length }} {{ data.renewals.length === 1 ? "renewal" : "renewals" }}
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
                  :aria-label="'Open ' + renewal.orgName + ' in Organizations'"
                  @click="openOrg(renewal.orgId)"
                >{{ renewal.orgName }}</button>
                <span>{{ renewal.plan }} renews</span>
              </span>
              <span class="financials__event-amount">{{ moneyIn(renewal.price, renewal.currency) }}</span>
            </div>
          </div>
          <p v-else class="financials__empty">No renewals scheduled.</p>
        </div>
      </div>

      <div class="iz-card financials__panel">
        <div class="financials__panel-head">
          <h3 class="financials__panel-title">Plans against usage bands</h3>
          <span class="financials__panel-meta">
            {{ plans.length }} {{ plans.length === 1 ? "plan" : "plans" }}
            · active subscriptions only
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
          <h3 class="financials__panel-title">Organizations</h3>
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
              aria-label="Search organizations"
              placeholder="Search organizations…"
            />

            <div class="financials__chips" role="group" aria-label="Subscription status">
              <button
                v-for="opt in statusOptions"
                :key="opt.value"
                type="button"
                class="iz-chip"
                :class="{ 'iz-chip--active': statusFilter === opt.value }"
                :aria-pressed="String(statusFilter === opt.value)"
                @click="statusFilter = opt.value"
              >
                {{ opt.label }}
                <span class="financials__chip-count">{{ statusCount(opt.value) }}</span>
              </button>
            </div>
          </div>

          <div class="financials__toolbar-row">
            <div class="financials__field">
              <label :for="fieldId('plan')">Plan</label>
              <select :id="fieldId('plan')" v-model="planFilter" class="iz-select iz-select--sm">
                <option :value="null">All plans</option>
                <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                <option v-if="hasUnplanned" value="none">No plan</option>
              </select>
            </div>

            <div class="financials__field">
              <label :for="fieldId('usage')">Of cap</label>
              <select :id="fieldId('usage')" v-model="usageFilter" class="iz-select iz-select--sm">
                <option :value="null">Any usage</option>
                <option v-for="band in bandKeys" :key="band" :value="band">{{ bandLabel(band) }}</option>
              </select>
            </div>

            <div class="financials__field">
              <label :for="fieldId('revenue')">Revenue</label>
              <select :id="fieldId('revenue')" v-model="revenueFilter" class="iz-select iz-select--sm">
                <option value="all">Any</option>
                <option value="paying">Paying</option>
                <option value="free">Free</option>
              </select>
            </div>

            <div class="financials__field">
              <label :for="fieldId('renew')">Renews</label>
              <select :id="fieldId('renew')" v-model.number="renewFilter" class="iz-select iz-select--sm">
                <option :value="0">Any time</option>
                <option :value="30">≤ 30 days</option>
                <option :value="90">≤ 90 days</option>
                <option :value="180">≤ 180 days</option>
              </select>
            </div>

            <div class="financials__field">
              <label :for="fieldId('sort')">Sort</label>
              <select :id="fieldId('sort')" v-model="sortBy" class="iz-select iz-select--sm">
                <option value="revenue">Revenue</option>
                <option value="billed">Billed</option>
                <option value="usage">Of cap</option>
                <option value="renews">Renewal date</option>
                <option value="name">Name</option>
              </select>
            </div>

            <button
              type="button"
              class="iz-btn iz-btn--sm"
              :disabled="!anyFilter"
              @click="clearFilters"
            >Clear</button>
          </div>
        </div>

        <div v-if="!filteredOrgs.length" class="iz-empty">
          No organizations match these filters.
        </div>

        <div v-else class="iz-table-wrap">
          <table class="iz-table financials__table">
            <thead>
              <tr>
                <th scope="col">Organization</th>
                <th scope="col">Plan</th>
                <th scope="col" class="financials__num">Price / mo</th>
                <th scope="col" class="financials__num">
                  <span :title="billedTitle">Billed, {{ recordedMonths }} mo</span>
                </th>
                <th scope="col">Status</th>
                <th scope="col">Renews</th>
                <th scope="col" class="financials__num">Members</th>
                <th scope="col" class="financials__num">Projects</th>
                <th scope="col" class="financials__num">Of cap</th>
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
                      :aria-label="'Open ' + org.name + ' in Organizations'"
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
            Showing <strong>{{ rangeStart }}</strong>–<strong>{{ rangeEnd }}</strong>
            of <strong>{{ filteredOrgs.length }}</strong>
            <span v-if="filteredOrgs.length !== orgs.length">
              filtered from {{ orgs.length }}
            </span>
          </div>

          <div v-if="totalPages > 1" class="iz-pagination financials__pagination">
            <button
              type="button"
              class="iz-btn iz-btn--sm financials__page-btn"
              :disabled="currentPage === 1"
              aria-label="First page"
              @click="currentPage = 1"
            >«</button>
            <button
              type="button"
              class="iz-btn iz-btn--sm financials__page-btn"
              :disabled="currentPage <= 1"
              aria-label="Previous page"
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
              aria-label="Next page"
              @click="currentPage++"
            >›</button>
            <button
              type="button"
              class="iz-btn iz-btn--sm financials__page-btn"
              :disabled="currentPage === totalPages"
              aria-label="Last page"
              @click="currentPage = totalPages"
            >»</button>
          </div>

          <div class="financials__page-size">
            <label :for="pageSizeId">Per page</label>
            <select
              :id="pageSizeId"
              v-model.number="pageSize"
              class="iz-select iz-select--sm"
            >
              <option v-for="n in pageSizeOptions" :key="n" :value="n">{{ n }}</option>
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

const PAGE_SIZE_OPTIONS = [10, 20, 50, 100];

const STATUS_OPTIONS = [
  { value: "all", label: "All" },
  { value: "active", label: "Active" },
  { value: "paused", label: "Paused" },
  { value: "cancelled", label: "Cancelled" },
  { value: "expired", label: "Expired" },
  { value: "none", label: "No plan" },
];

// Band keys come from the payload so the service owns the thresholds; only the
// wording lives here, as an explicit table with a fallback to the raw key.
const BAND_LABELS = {
  low: "under 50%",
  mid: "50–79%",
  high: "80–99%",
  cap: "at or over cap",
  none: "not measured",
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
  active: "Active",
  paused: "Paused",
  cancelled: "Cancelled",
  expired: "Expired",
  none: "No plan",
};

const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

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
      return (
        "List price summed over the " + this.recordedMonths + " recorded months of this " +
        "window (" + this.windowLabel + " runs longer, but committed months are not " +
        "billed). Anything before the window is not counted, so this is not lifetime value."
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
        parts.push(this.orgs.length + (this.orgs.length === 1 ? " organization" : " organizations"));
      } else {
        parts.push(this.filteredOrgs.length + " of " + this.orgs.length);
      }
      if (this.planFilter !== null) {
        parts.push(this.planFilter === "none" ? "no plan" : this.planName(this.planFilter));
      }
      if (this.usageFilter !== null) parts.push(this.bandLabel(this.usageFilter));
      return parts.join(" · ");
    },
    noRevenue() {
      return this.data !== null && this.data.totals.every((t) => t === 0);
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
        return "No organizations on this platform yet, so there is nothing to bill.";
      }
      if (this.dataQuality.planCount === 0) {
        return "No plans have been created yet, so no organization can be billed.";
      }
      if (this.dataQuality.allPlansFree) {
        return (
          "No revenue recorded. Every plan on this platform is currently priced " +
          zero + ", so the grid below has nothing to shade."
        );
      }
      return (
        "No revenue recorded in this window. Priced plans exist, but no subscription " +
        "was billing during these months."
      );
    },
    windowLabel() {
      const months = (this.data && this.data.months) || [];
      if (!months.length) return "";
      const first = months[0];
      const last = months[months.length - 1];
      return first.label + " " + first.year + " – " + last.label + " " + last.year;
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
        return "You don't have permission to view this data — super-admin access is required.";
      }
      if (serverMsg) return serverMsg;
      if (status && status >= 500) {
        return "The server ran into a problem loading financials. Please try again in a moment.";
      }
      if (e && e.request && !e.response) {
        return "Couldn't reach the server. Check your connection and try again.";
      }
      return "Something went wrong loading financials. Please try again.";
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
        return new Intl.NumberFormat(undefined, {
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
      return (value > 0 ? "+" : "−") + this.money(Math.abs(value));
    },
    deltaText(value) {
      if (!value) return "±" + this.money(0);
      return (value > 0 ? "▲ " : "▼ ") + this.money(Math.abs(value));
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
      return STATUS_LABELS[status] || status;
    },
    atCap(usage) {
      return usage && usage.cap > 0 && usage.used >= usage.cap;
    },
    fieldId(name) {
      return "financials-" + name + "-" + this._uid;
    },
    bandLabel(band) {
      return BAND_LABELS[band] || band;
    },
    bandClass(band) {
      return BAND_CLASSES[band] || "financials__band-dot--none";
    },
    planName(planId) {
      const plan = this.plans.filter((p) => p.id === planId)[0];
      return plan ? plan.name : "Unknown plan";
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
        return org.name + " · this plan caps neither members nor projects";
      }
      const tighter =
        org.projects.cap > 0 && org.members.cap > 0
          ? org.projects.used / org.projects.cap > org.members.used / org.members.cap
            ? "projects"
            : "members"
          : org.members.cap > 0
            ? "members"
            : "projects";
      return (
        org.name + " · " + this.usageText(org) + " of its plan cap (" +
        this.bandLabel(org.usageBand) + "), set by " + tighter
      );
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
      return Number(m[3]) + " " + MONTHS[Number(m[2]) - 1] + " " + m[1];
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
