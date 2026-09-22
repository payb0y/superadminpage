<template>
  <section class="portfolio iz-panel iz-panel--list">
    <h3 class="portfolio__heading">
      <button
        type="button"
        class="portfolio__toggle"
        :aria-expanded="String(!collapsed)"
        :aria-controls="'portfolio-body-' + _uid"
        @click="toggle"
      >
        <span class="portfolio__toggle-title iz-panel__title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <path d="M8 2v4M16 2v4M3 9h18" />
          </svg>
          Project portfolio - Initiation phase
        </span>
        <span class="portfolio__toggle-meta">{{ trackedProjects }} projects</span>
        <svg class="portfolio__chevron" :class="{ 'portfolio__chevron--open': !collapsed }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </button>
    </h3>

    <div v-show="!collapsed" :id="'portfolio-body-' + _uid" class="portfolio__body">
      <div class="portfolio__mode-nav">
        <div class="portfolio__segmented" role="group" aria-label="Portfolio view mode">
          <button
            type="button"
            class="portfolio__segment"
            :class="{ 'portfolio__segment--active': viewMode === 'summary' }"
            :aria-pressed="String(viewMode === 'summary')"
            @click="viewMode = 'summary'"
          >
            Summary
          </button>
          <button
            type="button"
            class="portfolio__segment"
            :class="{ 'portfolio__segment--active': viewMode === 'table' }"
            :aria-pressed="String(viewMode === 'table')"
            @click="viewMode = 'table'"
          >
            Table view
          </button>
          <button
            v-if="viewMode === 'detail'"
            type="button"
            class="portfolio__segment portfolio__segment--active"
            aria-pressed="true"
          >
            Timeline: {{ selectedDetailProject ? selectedDetailProject.name : 'Project' }}
          </button>
        </div>
      </div>

      <ProjectDetailPlanning
        v-if="viewMode === 'detail'"
        :project="selectedDetailProject"
        :organization-id="effectiveOrgId"
        :is-admin="true"
        @back="viewMode = 'table'"
      />

      <ProjectPortfolioTableView
        v-else-if="viewMode === 'table'"
        :organization-id="effectiveOrgId"
        :organizations="organizations"
        :scope="viewScope"
        :team-id="selectedTeamId"
        :teams="teams"
        :teams-loading="teamsLoading"
        :initial-filter="tableInitialFilter"
        :week-start="displayedWeekStart"
        @update:organization-id="onSelectOrg"
        @update:scope="setViewScope"
        @update:teamId="selectedTeamId = $event"
        @move-period="movePeriod"
        @reset-period="resetPeriod"
        @select-project="onOpenDetailPlanning"
        @edit-teams="$emit('edit-teams')"
      />

      <div v-else class="portfolio__summary-view">
        <div class="portfolio__toolbar iz-card iz-card--flat" aria-label="Portfolio filters">
          <div v-if="organizations && organizations.length" class="portfolio__filter-group portfolio__org-select-group">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="portfolio__org-icon" aria-hidden="true">
              <path d="M3 21h18M3 7v14M21 7v14M6 7V3h12v4M9 11h2M13 11h2M9 15h2M13 15h2" />
            </svg>
            <select class="iz-select iz-select--sm" v-model="internalOrgId" aria-label="Filter by organization">
              <option :value="null" disabled>Select organization</option>
              <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
            </select>
          </div>

          <div class="portfolio__segmented" role="group" aria-label="Portfolio scope">
            <button type="button" class="portfolio__segment" :class="{ 'portfolio__segment--active': viewScope === 'mine' }" :aria-pressed="String(viewScope === 'mine')" @click="setViewScope('mine')">My projects</button>
            <button type="button" class="portfolio__segment" :class="{ 'portfolio__segment--active': viewScope === 'team' }" :aria-pressed="String(viewScope === 'team')" @click="setViewScope('team')">Team</button>
            <button type="button" class="portfolio__segment" :class="{ 'portfolio__segment--active': viewScope === 'all' }" :aria-pressed="String(viewScope === 'all')" @click="setViewScope('all')">All projects</button>
          </div>

          <div class="portfolio__period-stepper" title="Period controls the weekly workload strip below; the project list itself is not filtered by period">
            <button type="button" class="portfolio__segment portfolio__segment--icon" aria-label="Previous 6 weeks" title="Previous 6 weeks" @click="movePeriod(-42)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="15 18 9 12 15 6" />
              </svg>
            </button>
            <span class="portfolio__period-display">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <rect x="3" y="4" width="18" height="17" rx="2" />
                <path d="M8 2v4M16 2v4M3 9h18" />
              </svg>
              {{ periodLabel }}
            </span>
            <button type="button" class="portfolio__segment portfolio__segment--icon" aria-label="Next 6 weeks" title="Next 6 weeks" @click="movePeriod(42)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="9 18 15 12 9 6" />
              </svg>
            </button>
            <button type="button" class="portfolio__segment" title="Reset to current 6 weeks" @click="resetPeriod">Current</button>
          </div>

          <div class="portfolio__capacity">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1z" /></svg>
            <div class="portfolio__capacity-controls">
              <select class="iz-select iz-select--sm" v-model="selectedTeamId" :disabled="teamsLoading || !teams.length || viewScope === 'mine'" aria-label="Filter by team capacity">
                <option value="all">All teams</option>
                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
              </select>
              <small v-if="selectedTeam && viewScope !== 'mine'" class="portfolio__capacity-meta">{{ formatNumber(selectedTeam.fte) }} FTE · {{ formatNumber(selectedTeam.projectsPerFte) }}/FTE</small>
              <small v-if="teamsLoading">Loading teams...</small>
              <small v-else-if="teamError" class="portfolio__team-error">
                {{ teamError }}
                <button type="button" class="iz-btn iz-btn--danger-quiet iz-btn--sm" @click="fetchTeams">Retry</button>
              </small>
              <small v-else-if="!teams.length">No teams available.</small>
            </div>
          </div>
        </div>

        <div class="portfolio__kpis iz-stat-grid">
          <article
            v-for="metric in metrics"
            :key="metric.label"
            class="iz-kpi portfolio-kpi portfolio-kpi--clickable"
            title="View in table"
            tabindex="0"
            role="button"
            :aria-label="metric.label + ': ' + metric.value + '. View in table.'"
            @click="openTableView(metric.filter)"
            @keydown="onDrilldownKeydown($event, metric.filter)"
          >
            <div class="portfolio-kpi__icon" :class="metric.tone">
              <svg v-if="metric.icon === 'folder'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h6l2 2h10v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /></svg>
              <svg v-else-if="metric.icon === 'check'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="m8 12 3 3 5-6" /></svg>
              <svg v-else-if="metric.icon === 'alert'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10.3 3.5 2.4 18a2 2 0 0 0 1.8 3h15.6a2 2 0 0 0 1.8-3L13.7 3.5a2 2 0 0 0-3.4 0z" /><path d="M12 9v4M12 17h.01" /></svg>
              <span v-else class="portfolio-kpi__ring" />
            </div>
            <div class="portfolio-kpi__copy">
              <strong class="portfolio-kpi__value">{{ metric.value }}</strong>
              <span class="portfolio-kpi__label">{{ metric.label }}</span>
              <small>{{ metric.note }}</small>
              <div v-if="metric.filter === 'all' && projectStatusBreakdown.length" class="portfolio-kpi__status-breakdown">
                <span
                  v-for="item in projectStatusBreakdown"
                  :key="item.key"
                  class="portfolio-kpi__status-item"
                  :title="item.label + ': ' + item.count"
                >
                  <span class="portfolio-kpi__status-dot" :class="'portfolio-kpi__status-dot--' + item.tone" aria-hidden="true" />
                  <strong class="portfolio-kpi__status-count">{{ item.count }}</strong>
                  <span class="portfolio-kpi__status-label">{{ item.label }}</span>
                </span>
              </div>
            </div>
            <svg class="portfolio__row-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6" /></svg>
          </article>
        </div>

        <div class="portfolio__overview-grid">
          <section class="iz-card portfolio__status-card">
            <header class="iz-panel__header">
              <h4 class="iz-panel__title">Process status - Initiation phase</h4>
            </header>
            <div class="portfolio__status-content">
              <div v-if="portfolioLoading" class="portfolio__status-state iz-empty">Loading project progress...</div>
              <div v-else-if="portfolioError" class="portfolio__status-state iz-error">
                <span>{{ portfolioError }}</span>
                <button type="button" class="iz-btn iz-btn--danger-quiet iz-btn--sm" @click="fetchPortfolio">Retry</button>
              </div>
              <div v-else class="portfolio__donut" :style="donutStyle" role="img" :aria-label="trackedProjects + ' projects across five progress categories'">
                <span><strong>{{ trackedProjects }}</strong><small>projects</small></span>
              </div>
              <div v-if="!portfolioLoading && !portfolioError" class="portfolio__legend">
                <div class="portfolio__legend-header" aria-hidden="true">
                  <span class="portfolio__legend-th-progression">Progression</span>
                  <span class="portfolio__legend-th-count" title="Total projects in this stage">Projects</span>
                  <span class="portfolio__legend-th-share" title="Share of portfolio">Share</span>
                  <span class="portfolio__legend-th-arrow"></span>
                </div>
                <div
                  v-for="status in displayStatuses"
                  :key="status.key"
                  class="portfolio__legend-row portfolio__legend-row--clickable"
                  title="Filter table by this status"
                  tabindex="0"
                  role="button"
                  :aria-label="'Filter table by ' + status.label + ', ' + status.count + ' projects'"
                  @click="openTableView(status.key)"
                  @keydown="onDrilldownKeydown($event, status.key)"
                >
                  <span class="portfolio__legend-dot" :class="status.tone" />
                  <strong>{{ status.label }}</strong>
                  <span v-if="status.badge" class="iz-badge" :class="status.badgeClass">{{ status.badge }}</span>
                  <strong class="portfolio__legend-count">{{ status.count }}</strong>
                  <span class="portfolio__legend-percent">{{ status.percent }}</span>
                  <svg class="portfolio__row-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6" /></svg>
                </div>
              </div>
            </div>
            <p v-if="untrackedProjectCount && !portfolioLoading && !portfolioError" class="portfolio__untracked">
              {{ untrackedProjectCount }} {{ untrackedProjectCount === 1 ? 'project could' : 'projects could' }} not be linked to an active Deck board.
            </p>
          </section>

          <section class="iz-card portfolio__gaps-card">
            <header class="iz-panel__header">
              <h4 class="iz-panel__title portfolio__danger-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10.3 3.5 2.4 18a2 2 0 0 0 1.8 3h15.6a2 2 0 0 0 1.8-3L13.7 3.5a2 2 0 0 0-3.4 0z" /><path d="M12 9v4M12 17h.01" /></svg>
                Open planning gaps ({{ planningGaps.length }})
              </h4>
              <button type="button" class="portfolio__link portfolio__link-btn" @click="openTableView('all')">View all projects</button>
            </header>
            <div class="portfolio__gap-list">
              <div v-if="capacityLoading" class="portfolio__status-state iz-empty">Loading capacity...</div>
              <div v-else-if="capacityError" class="portfolio__status-state iz-error">
                <span>{{ capacityError }}</span>
                <button type="button" class="iz-btn iz-btn--danger-quiet iz-btn--sm" @click="fetchCapacity">Retry</button>
              </div>
              <div v-else-if="!planningGaps.length" class="portfolio__status-state iz-empty">No open planning gaps.</div>
              <div
                v-else
                v-for="gap in planningGaps"
                :key="gap.id || gap.name"
                class="iz-row iz-row--card portfolio__gap-row portfolio__gap-row--clickable"
                title="View planning gaps in table"
                tabindex="0"
                role="button"
                :aria-label="'View planning gaps in table: ' + gap.name"
                @click="openTableView('gaps')"
                @keydown="onDrilldownKeydown($event, 'gaps')"
              >
                <svg class="portfolio__pin" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.3 7 13 7 13s7-7.7 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5z" /></svg>
                <span class="portfolio__gap-copy"><strong>{{ gap.name }}</strong><small>{{ gap.note }}</small></span>
                <strong>{{ gap.duration }}</strong>
                <span class="iz-badge iz-badge--danger">{{ gap.weeks }}</span>
                <svg class="portfolio__row-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6" /></svg>
              </div>
            </div>
          </section>
        </div>

        <section class="iz-card portfolio__workload">
          <header class="iz-panel__header portfolio__workload-header">
            <h4 class="iz-panel__title">Weekly work preparation load</h4>
            <span v-if="capacity" class="portfolio__capacity-note">{{ capacityNote }}</span>
          </header>
          <div v-if="teamWarnings.length" class="portfolio__warnings" role="status">
            <span v-for="warning in teamWarnings" :key="warning.id" class="iz-badge iz-badge--warning">{{ warning.name }} over capacity in {{ warning.overWeeks.join(", ") }}</span>
          </div>
          <div v-if="capacityLoading" class="portfolio__status-state iz-empty">Loading capacity...</div>
          <div v-else-if="capacityError" class="portfolio__status-state iz-error">{{ capacityError }}</div>
          <div v-else-if="!weeks.length" class="portfolio__status-state iz-empty">No capacity data available.</div>
          <div v-else class="portfolio__weeks">
            <article v-for="week in weeks" :key="week.label" class="iz-card iz-card--flat portfolio-week">
              <header><strong>{{ week.label }}</strong><small>{{ formatDate(week.start) }} - {{ formatDate(week.end) }}</small></header>
              <div v-for="item in week.items" :key="item.label" class="portfolio-week__row">
                <span class="portfolio__legend-dot" :class="item.tone" />
                <span>{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
              </div>
              <div class="portfolio-week__total"><span class="portfolio__legend-dot tone-neutral" /><strong>Total active</strong><strong>{{ week.totalActive }}</strong></div>
              <div class="portfolio-week__capacity" :class="capacityClass(week)">
                <strong>{{ week.totalActive }} / {{ week.capacity }}</strong>
                <span>{{ capacityStatus(week) }}</span>
              </div>
            </article>
          </div>
        </section>
      </div>
    </div>
  </section>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";
import { listOrganizationTeams } from "../services/organizationApi";
import ProjectPortfolioTableView from "./ProjectPortfolioTableView.vue";
import ProjectDetailPlanning from "./ProjectDetailPlanning.vue";

export default {
  name: "ProjectPortfolioPanel",
  components: {
    ProjectPortfolioTableView,
    ProjectDetailPlanning,
  },
  props: {
    organizationId: { type: Number, default: null },
    organizations: { type: Array, default: function () { return []; } },
    initialExpanded: { type: Boolean, default: true },
    refreshRevision: { type: Number, default: 0 },
  },
  data: function () {
    return {
      collapsed: !this.initialExpanded,
      internalOrgId: this.organizationId || (this.organizations && this.organizations.length ? Number(this.organizations[0].id) : null),
      viewMode: "summary",
      tableInitialFilter: "all",
      selectedDetailProject: null,
      portfolio: null,
      portfolioLoading: false,
      portfolioError: null,
      teams: [],
      teamsLoading: false,
      teamError: null,
      selectedTeamId: null,
      displayedWeekStart: null,
      viewScope: "all",
      capacity: null,
      capacityLoading: false,
      capacityError: null,
      capacityRequestId: 0,
      portfolioRequestId: 0,
    };
  },
  computed: {
    effectiveOrgId: function () {
      return Number(this.internalOrgId || this.organizationId) || null;
    },
    hasPositiveTeamId: function () {
      var id = Number(this.selectedTeamId);
      return Number.isInteger(id) && id > 0;
    },
    needsTeamSelection: function () {
      return this.viewScope === "team" && !this.hasPositiveTeamId;
    },
    metrics: function () {
      var completionAvailable = this.portfolio !== null && !this.portfolioError;
      var capacityAvailable = this.capacity !== null && !this.capacityError;
      return [
        {
          value: completionAvailable ? Number(this.portfolio.totalProjects || 0) : "—",
          label: "Total projects",
          note: "Within selected period",
          icon: "folder",
          tone: "tone-accent",
          filter: "all",
        },
        {
          value: completionAvailable ? this.completionBucketCount("75-99") : "—",
          label: "Upcoming (75 - 99%)",
          note: "Desired week visible up to 99%",
          icon: "progress",
          tone: "tone-warning",
          filter: "75-99",
        },
        {
          value: completionAvailable ? this.completionBucketCount("100") : "—",
          label: "100% ready for handover",
          note: "Actual target week is leading",
          icon: "check",
          tone: "tone-success",
          filter: "100",
        },
        {
          value: capacityAvailable ? this.planningGaps.length : "—",
          label: "Open planning gaps",
          note: "In selected period",
          icon: "alert",
          tone: "tone-danger",
          filter: "gaps",
        },
      ];
    },
    projectStatusBreakdown: function () {
      var counts = (this.portfolio && this.portfolio.statusCounts) || null;
      if (!counts) {
        return [];
      }
      return [
        { key: "active", label: "Active", count: Number(counts.active || 0), tone: "active" },
        { key: "waiting", label: "Waiting on customer", count: Number(counts.waiting || 0), tone: "waiting" },
        { key: "on_hold", label: "On hold", count: Number(counts.on_hold || 0), tone: "on-hold" },
        { key: "done", label: "Done", count: Number(counts.done || 0), tone: "done" },
        { key: "archived", label: "Archived", count: Number(counts.archived || 0), tone: "archived" },
      ];
    },
    selectedTeam: function () {
      return this.teams.find(function (team) { return Number(team.id) === Number(this.selectedTeamId); }, this) || null;
    },
    weeks: function () {
      return ((this.capacity && this.capacity.weeks) || []).map(function (week) {
        return { ...week, items: [
          { label: "Starting", value: week.starting, tone: "tone-cat-1" },
          { label: "Ongoing", value: week.continuing, tone: "tone-accent" },
          { label: "Ending", value: week.ending, tone: "tone-cat-4" },
        ] };
      });
    },
    planningGaps: function () { return (this.capacity && this.capacity.planningGaps) || []; },
    teamWarnings: function () { return (this.capacity && this.capacity.teamWarnings) || []; },
    capacityNote: function () {
      if (!this.capacity || !this.capacity.team) return "";
      var team = this.capacity.team;
      var note = this.formatNumber(team.capacity) + " concurrent projects";
      if (team.id === 0 && Array.isArray(this.capacity.teams)) {
        if (team.name === "My teams") {
          return "My teams (" + this.capacity.teams.length + " teams): " + note;
        }
        return "All " + this.capacity.teams.length + " teams: " + note;
      }
      return team.name + ": " + note;
    },
    periodLabel: function () {
      if (!this.capacity || !this.capacity.period) return "Capacity";
      var start = this.parseDate(this.capacity.period.weekStart);
      var end = new Date(start.getTime());
      end.setUTCDate(end.getUTCDate() + 41);
      return this.isoYearWeek(start) + " - " + this.isoYearWeek(end) + " (6 weeks)";
    },
    displayStatuses: function () {
      var tones = ["tone-neutral", "tone-cat-1", "tone-accent", "tone-warning", "tone-success"];
      return ((this.portfolio && this.portfolio.buckets) || []).map(function (bucket, index) {
        var badge = null;
        var badgeClass = null;
        if (index === 3) {
          badge = "Upcoming";
          badgeClass = "iz-badge--warning";
        } else if (index === 4) {
          badge = "Ready for handover";
          badgeClass = "iz-badge--success";
        }
        return {
          ...bucket,
          tone: tones[index],
          percent: bucket.percent.toLocaleString("en-US", { maximumFractionDigits: 1 }) + "%",
          badge: badge,
          badgeClass: badgeClass,
        };
      });
    },
    trackedProjects: function () {
      return (this.portfolio && this.portfolio.trackedProjects) || 0;
    },
    untrackedProjectCount: function () {
      return (this.portfolio && this.portfolio.untrackedProjects && this.portfolio.untrackedProjects.length) || 0;
    },
    donutStyle: function () {
      var buckets = (this.portfolio && this.portfolio.buckets) || [];
      if (!buckets.length || !this.trackedProjects) return { background: "var(--iz-surface-inset)" };

      var colors = ["var(--iz-text-muted)", "var(--iz-cat-1)", "var(--iz-accent)", "var(--iz-warning)", "var(--iz-success)"];
      var start = 0;
      var stops = buckets.map(function (bucket, index) {
        var end = start + Number(bucket.percent || 0);
        var stop = colors[index] + " " + start + "% " + end + "%";
        start = end;
        return stop;
      });
      return { background: "conic-gradient(" + stops.join(", ") + ")" };
    },
  },
  watch: {
    refreshRevision: function () {
      if (!this.collapsed) this.fetchTeams();
    },
    organizationId: function (newVal) {
      this.internalOrgId = newVal ? Number(newVal) : null;
    },
    organizations: function (newOrgs) {
      if (!this.internalOrgId && newOrgs && newOrgs.length) {
        this.internalOrgId = Number(newOrgs[0].id);
      }
    },
    internalOrgId: function (newVal, oldVal) {
      if (newVal !== oldVal) {
        this.$emit("update:organization-id", newVal);
        this.selectedTeamId = "all";
        this.portfolio = null;
        this.capacity = null;
        if (newVal && !this.collapsed) {
          this.fetchTeams();
          this.fetchPortfolio();
        }
      }
    },
    selectedTeamId: function () {
      if (this.viewScope !== "mine") {
        this.viewScope = this.selectedTeamId === "all" ? "all" : "team";
      }
      if (this.selectedTeamId && !this.teamsLoading) {
        this.fetchPortfolio();
        this.fetchCapacity();
      }
    },
  },
  mounted: function () {
    if (!this.collapsed && this.effectiveOrgId) {
      this.fetchTeams();
      this.fetchPortfolio();
    }
  },
  methods: {
    onSelectOrg: function (orgId) {
      this.internalOrgId = Number(orgId) || null;
    },
    onOpenDetailPlanning: function (project) {
      this.selectedDetailProject = project;
      this.viewMode = "detail";
    },
    openTableView: function (filter) {
      this.tableInitialFilter = filter || "all";
      this.viewMode = "table";
    },
    completionBucketCount: function (key) {
      var bucket = ((this.portfolio && this.portfolio.buckets) || []).find(function (item) {
        return item.key === key;
      });
      return bucket ? Number(bucket.count || 0) : 0;
    },
    toggle: function () {
      this.collapsed = !this.collapsed;
      if (!this.collapsed && this.effectiveOrgId) {
        if (!this.portfolio && !this.portfolioLoading) this.fetchPortfolio();
        if (!this.teams.length && !this.teamsLoading) this.fetchTeams();
      }
    },
    setViewScope: function (scope) {
      var previousTeam = this.selectedTeamId;
      if (scope === "team" && (previousTeam === "all" || !previousTeam)) {
        this.selectedTeamId = this.teams.length ? this.teams[0].id : null;
      } else if (scope === "all") {
        this.selectedTeamId = "all";
      }
      this.viewScope = scope;
      this.fetchPortfolio();
      if (this.selectedTeamId === previousTeam) {
        this.fetchCapacity();
      }
    },
    fetchPortfolio: async function () {
      if (!this.effectiveOrgId) {
        this.portfolio = null;
        this.portfolioLoading = false;
        return;
      }
      if (this.needsTeamSelection) {
        this.portfolioRequestId++;
        this.portfolioLoading = false;
        this.portfolioError = "Select a team to view project progress.";
        this.portfolio = null;
        return;
      }
      var requestId = ++this.portfolioRequestId;
      this.portfolioLoading = true;
      this.portfolioError = null;
      try {
        var params = { organizationId: this.effectiveOrgId };
        if (this.viewScope === "mine") {
          params.scope = "mine";
        } else if (this.viewScope === "team" && this.hasPositiveTeamId) {
          params.scope = "team";
          params.teamId = Number(this.selectedTeamId);
        }
        var response = await axios.get(generateUrl("/apps/projectcreatoraio/api/v1/portfolio/completion"), { params: params });
        if (requestId !== this.portfolioRequestId) return;
        this.portfolio = response.data;
      } catch (error) {
        if (requestId !== this.portfolioRequestId) return;
        this.portfolioError = error && error.response && error.response.status === 403
          ? "You do not have access to this project data."
          : "Project progress could not be loaded.";
      } finally {
        if (requestId === this.portfolioRequestId) this.portfolioLoading = false;
      }
    },
    parseDate: function (value) {
      var parts = String(value).split("-").map(Number);
      return new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
    },
    dateOnly: function (date) { return date.toISOString().slice(0, 10); },
    formatDate: function (value) {
      return this.parseDate(value).toLocaleDateString("en-US", { day: "numeric", month: "short", timeZone: "UTC" });
    },
    formatNumber: function (value) { return Number(value || 0).toLocaleString("en-US", { maximumFractionDigits: 2 }); },
    isoWeek: function (date) {
      var d = new Date(date.getTime());
      d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7));
      var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
      return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    },
    isoYearWeek: function (date) {
      var d = new Date(date.getTime());
      d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7));
      var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
      var week = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
      return d.getUTCFullYear() + "-W" + String(week).padStart(2, "0");
    },
    onDrilldownKeydown: function (event, filter) {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        this.openTableView(filter);
      }
    },
    currentMonday: function () {
      var monday = this.parseDate(new Date().toISOString().slice(0, 10));
      monday.setUTCDate(monday.getUTCDate() - ((monday.getUTCDay() + 6) % 7));
      return monday;
    },
    movePeriod: function (days) {
      var start = this.displayedWeekStart
        ? this.parseDate(this.displayedWeekStart)
        : this.currentMonday();
      start.setUTCDate(start.getUTCDate() + days);
      var weekStart = this.dateOnly(start);
      this.displayedWeekStart = weekStart;
      this.fetchCapacity(weekStart);
    },
    resetPeriod: function () {
      var weekStart = this.dateOnly(this.currentMonday());
      this.displayedWeekStart = weekStart;
      this.fetchCapacity(weekStart);
    },
    capacityClass: function (week) {
      return week.overCapacity
        ? "portfolio-week__capacity--over"
        : "portfolio-week__capacity--ok";
    },
    capacityStatus: function (week) {
      if (week.overCapacity) return "Over capacity";
      if (week.totalActive === 0) return "No active projects";
      return "Remaining: " + this.formatNumber(week.remaining);
    },
    fetchTeams: async function () {
      if (!this.effectiveOrgId) {
        this.teams = [];
        this.teamsLoading = false;
        return;
      }
      this.teamsLoading = true;
      this.teamError = null;
      try {
        this.teams = await listOrganizationTeams(this.effectiveOrgId);
        var selectedExists = this.selectedTeamId !== "all" && this.teams.some(function (team) {
          return Number(team.id) === Number(this.selectedTeamId);
        }, this);
        if (!selectedExists) {
          this.selectedTeamId = this.teams.length ? "all" : null;
          if (!this.selectedTeamId) this.capacity = null;
        }
      } catch (e) {
        this.teamError = "Teams could not be loaded.";
      } finally {
        this.teamsLoading = false;
        if (this.selectedTeamId) this.fetchCapacity(this.displayedWeekStart || this.dateOnly(this.currentMonday()));
      }
    },
    fetchCapacity: async function (weekStart) {
      if (!this.effectiveOrgId) {
        this.capacity = null;
        this.capacityLoading = false;
        return;
      }
      if (!this.selectedTeamId) return;
      if (this.needsTeamSelection) {
        this.capacityRequestId++;
        this.capacityLoading = false;
        this.capacityError = "Select a team to view capacity.";
        this.capacity = null;
        return;
      }
      var requestId = ++this.capacityRequestId;
      this.capacityLoading = true;
      this.capacityError = null;
      try {
        var start = weekStart || this.displayedWeekStart || this.dateOnly(this.currentMonday());
        var params = { organizationId: this.effectiveOrgId, scope: this.viewScope, weekStart: start };
        if (this.viewScope === "team" && this.hasPositiveTeamId) {
          params.teamId = Number(this.selectedTeamId);
        }
        var response = await axios.get(
          generateUrl("/apps/projectcreatoraio/api/v1/portfolio/capacity"),
          { params: params },
        );
        if (requestId !== this.capacityRequestId) return;
        this.capacity = response.data;
        this.displayedWeekStart = response.data.period.weekStart;
      } catch (e) {
        if (requestId !== this.capacityRequestId) return;
        this.capacityError = "Team capacity could not be loaded.";
      } finally {
        if (requestId === this.capacityRequestId) this.capacityLoading = false;
      }
    },
  },
};
</script>

<style scoped>
.portfolio { margin-bottom: var(--iz-gap); }
.portfolio__heading { margin: 0; padding: 0; }
button.portfolio__toggle { width: 100%; min-height: 0; margin: 0; padding: var(--iz-pad-card); border: 0; border-radius: 0; background: transparent; color: var(--iz-text); display: flex; align-items: center; gap: var(--iz-gap-tight); text-align: left; cursor: pointer; }
button.portfolio__toggle:hover { background: var(--iz-surface-subtle); }
button.portfolio__toggle:focus-visible { outline: none; box-shadow: inset 0 0 0 2px var(--iz-accent); }
.portfolio__toggle-title { display: flex; align-items: center; gap: var(--iz-gap-tight); }
.portfolio__toggle-title svg { width: 20px; height: 20px; color: var(--iz-accent); }
.portfolio__toggle-meta { margin-left: auto; color: var(--iz-text-secondary); font-size: var(--iz-fs-sm); font-weight: 600; }
.portfolio__chevron { width: 18px; height: 18px; color: var(--iz-text-muted); transition: transform var(--iz-transition), color var(--iz-transition); }
.portfolio__toggle:hover .portfolio__chevron, .portfolio__chevron--open { color: var(--iz-accent); }
.portfolio__chevron--open { transform: rotate(180deg); }
.portfolio__body { display: grid; gap: var(--iz-gap); padding: var(--iz-pad-panel); border-top: 1px solid var(--iz-border); background: var(--iz-surface-subtle); }
.portfolio__mode-nav { display: flex; justify-content: flex-end; margin-bottom: var(--iz-gap-tight); }
.portfolio-kpi--clickable, .portfolio__legend-row--clickable, .portfolio__gap-row--clickable { cursor: pointer; transition: transform var(--iz-transition), box-shadow var(--iz-transition); }
.portfolio-kpi--clickable:hover, .portfolio__gap-row--clickable:hover { transform: translateY(-1px); box-shadow: var(--iz-shadow, 0 2px 8px rgba(0, 0, 0, 0.08)); }
.portfolio-kpi--clickable:focus-visible, .portfolio__legend-row--clickable:focus-visible, .portfolio__gap-row--clickable:focus-visible { outline: 2px solid var(--iz-accent); outline-offset: 2px; }
.portfolio__period-note { font-size: var(--iz-fs-xs); color: var(--iz-text-muted); white-space: nowrap; }
.portfolio__link-btn { background: transparent; border: 0; padding: 0; cursor: pointer; font: inherit; text-align: left; }
.portfolio__link-btn:hover { text-decoration: underline; }
.portfolio__summary-view { display: grid; gap: var(--iz-gap); }
.portfolio__toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: var(--iz-gap-tight); }
.portfolio__filter-group { display: flex; align-items: center; gap: var(--iz-gap-tight); min-width: 0; }
.portfolio__org-select-group { display: flex; align-items: center; gap: 6px; }
.portfolio__org-icon { width: 18px; height: 18px; color: var(--iz-accent); flex: 0 0 18px; }
.portfolio__org-select-group .iz-select { min-width: 160px; }
.portfolio__segmented { display: flex; overflow: hidden; border: 1px solid var(--iz-border); border-radius: var(--iz-radius); background: var(--iz-surface); }
.portfolio__segment { min-height: 0; padding: 7px 12px; border: 0; border-right: 1px solid var(--iz-border); border-radius: 0; background: transparent; color: var(--iz-text-secondary); font-size: var(--iz-fs-sm); font-weight: 600; white-space: nowrap; cursor: pointer; }
.portfolio__segment:last-child { border-right: 0; }
.portfolio__segment--active { background: var(--iz-accent); color: var(--iz-accent-text); }
.portfolio__segment--icon { display: flex; align-items: center; justify-content: center; padding: 7px 9px; }
.portfolio__segment--icon svg { width: 14px; height: 14px; }
.portfolio__period-stepper { display: flex; align-items: center; overflow: hidden; border: 1px solid var(--iz-border); border-radius: var(--iz-radius); background: var(--iz-surface); }
.portfolio__period-display { display: flex; align-items: center; gap: 6px; padding: 7px 10px; border-right: 1px solid var(--iz-border); color: var(--iz-text); font-size: var(--iz-fs-sm); font-weight: 600; white-space: nowrap; }
.portfolio__period-display svg { width: 15px; height: 15px; color: var(--iz-accent); }
.portfolio__capacity { display: flex; align-items: center; gap: var(--iz-gap-tight); margin-left: auto; padding-left: var(--iz-gap); border-left: 1px solid var(--iz-border); color: var(--iz-text); }
.portfolio__capacity > svg { width: 22px; height: 22px; flex: 0 0 22px; color: var(--iz-accent); }
.portfolio__capacity-controls { display: flex; align-items: center; gap: var(--iz-gap-tight); }
.portfolio__capacity-controls .iz-select { width: auto; min-width: 130px; }
.portfolio__capacity-meta { color: var(--iz-text-secondary); font-size: var(--iz-fs-xs); white-space: nowrap; }
.portfolio__capacity span { display: grid; gap: 2px; }
.portfolio__capacity small, .portfolio-kpi small, .portfolio__gap-copy small, .portfolio-week small { color: var(--iz-text-secondary); font-size: var(--iz-fs-xs); }
.portfolio__kpis { gap: var(--iz-gap); }
.portfolio-kpi { flex-direction: row; align-items: center; gap: var(--iz-gap); padding: var(--iz-pad-card); }
.portfolio-kpi__icon { width: 48px; height: 48px; flex: 0 0 48px; display: grid; place-items: center; border-radius: var(--iz-radius-pill); background: color-mix(in srgb, currentColor 14%, transparent); }
.portfolio-kpi__icon svg { width: 26px; height: 26px; }
.portfolio-kpi__ring { width: 24px; height: 24px; border: 4px solid currentColor; border-right-color: transparent; border-radius: var(--iz-radius-pill); }
.portfolio-kpi__copy { display: grid; min-width: 0; }
.portfolio-kpi__value { color: var(--iz-text); font-size: var(--iz-fs-2xl); line-height: 1; }
.portfolio-kpi__label { color: var(--iz-text); font-size: var(--iz-fs-md); font-weight: 700; }
.portfolio-kpi__status-breakdown { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; margin-top: 8px; padding-top: 6px; border-top: 1px solid var(--iz-border); }
.portfolio-kpi__status-item { display: inline-flex; align-items: center; gap: 4px; font-size: var(--iz-fs-xs); color: var(--iz-text-secondary); white-space: nowrap; }
.portfolio-kpi__status-count { color: var(--iz-text); font-weight: 700; font-variant-numeric: tabular-nums; }
.portfolio-kpi__status-label { font-size: var(--iz-fs-xs); }
.portfolio-kpi__status-dot { width: 7px; height: 7px; border-radius: var(--iz-radius-pill); flex: 0 0 7px; }
.portfolio-kpi__status-dot--active { background-color: var(--iz-success, #1f7a3e); }
.portfolio-kpi__status-dot--waiting { background-color: var(--iz-cat-4, #d98a2b); }
.portfolio-kpi__status-dot--on-hold { background-color: var(--iz-text-muted, #9a94a2); }
.portfolio-kpi__status-dot--done { background-color: var(--iz-cat-5, #7c5cbf); }
.portfolio-kpi__status-dot--archived { background-color: var(--iz-text-secondary, #64748b); }
.portfolio__row-arrow { width: 16px; height: 16px; flex: 0 0 auto; color: var(--iz-accent); }
.portfolio-kpi > .portfolio__row-arrow { margin-left: auto; }
.portfolio__overview-grid { display: grid; grid-template-columns: minmax(0, 3fr) minmax(360px, 2fr); gap: var(--iz-gap); }
.portfolio__status-content { display: grid; grid-template-columns: 220px minmax(0, 1fr); align-items: center; gap: var(--iz-gap); }
.portfolio__donut { width: 180px; aspect-ratio: 1; margin: auto; display: grid; place-items: center; border-radius: var(--iz-radius-pill); }
.portfolio__donut::before { content: ""; grid-area: 1 / 1; width: 55%; aspect-ratio: 1; border-radius: var(--iz-radius-pill); background: var(--iz-surface); }
.portfolio__donut span { grid-area: 1 / 1; z-index: 1; display: grid; text-align: center; }
.portfolio__donut strong { font-size: var(--iz-fs-2xl); color: var(--iz-text); }
.portfolio__donut small { color: var(--iz-text-secondary); font-size: var(--iz-fs-xs); }
.portfolio__legend { border: 1px solid var(--iz-border); border-radius: var(--iz-radius); overflow: hidden; background: var(--iz-surface); }
.portfolio__legend-header { display: grid; grid-template-columns: auto minmax(75px, auto) minmax(0, 1fr) minmax(54px, auto) minmax(46px, auto) 16px; align-items: center; gap: var(--iz-gap-tight); padding: 8px 12px; background: var(--iz-surface-subtle); border-bottom: 1px solid var(--iz-border); color: var(--iz-text-secondary); font-size: var(--iz-fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; user-select: none; }
.portfolio__legend-th-progression { grid-column: 1 / 4; }
.portfolio__legend-th-count, .portfolio__legend-th-share { text-align: right; }
.portfolio__legend-row { display: grid; grid-template-columns: auto minmax(75px, auto) minmax(0, 1fr) minmax(54px, auto) minmax(46px, auto) 16px; align-items: center; gap: var(--iz-gap-tight); min-height: 42px; padding: 0 12px; border-bottom: 1px solid var(--iz-border); color: var(--iz-text-secondary); font-size: var(--iz-fs-sm); }
.portfolio__legend-row:last-child { border-bottom: 0; }
.portfolio__legend-row > strong:first-of-type { color: var(--iz-text); }
.portfolio__legend-count { text-align: right; color: var(--iz-text); font-variant-numeric: tabular-nums; }
.portfolio__legend-percent { text-align: right; font-variant-numeric: tabular-nums; }
.portfolio__status-state { grid-column: 1 / -1; display: flex; align-items: center; justify-content: center; gap: var(--iz-gap-tight); min-height: 180px; }
.portfolio__untracked { margin: var(--iz-gap-tight) 0 0; color: var(--iz-text-secondary); font-size: var(--iz-fs-xs); }
.portfolio__legend-dot { width: 10px; height: 10px; flex: 0 0 10px; border-radius: var(--iz-radius-pill); background: currentColor; }
.tone-accent { color: var(--iz-accent); }.tone-warning { color: var(--iz-warning); }.tone-success { color: var(--iz-success); }.tone-danger { color: var(--iz-danger); }.tone-neutral { color: var(--iz-text-muted); }.tone-cat-1 { color: var(--iz-cat-1); }.tone-cat-4 { color: var(--iz-cat-4); }
.portfolio__danger-title { display: flex; align-items: center; gap: var(--iz-gap-tight); }
.portfolio__danger-title svg { width: 22px; height: 22px; color: var(--iz-danger); }
.portfolio__link { color: var(--iz-accent); font-size: var(--iz-fs-sm); font-weight: 600; }
.portfolio__gap-list { display: grid; gap: var(--iz-gap-tight); }
.portfolio__gap-row { display: grid; grid-template-columns: auto minmax(0, 1fr) auto auto auto; gap: var(--iz-gap-tight); padding: var(--iz-pad-row); }
.portfolio__pin { width: 18px; height: 18px; color: var(--iz-accent); }
.portfolio__gap-copy { display: grid; min-width: 0; }
.portfolio__workload-header { align-items: center; }
.portfolio__warnings { display: flex; flex-wrap: wrap; gap: var(--iz-gap-tight); }
.portfolio__capacity-note { padding: 8px 12px; border-radius: var(--iz-radius); background: var(--iz-accent-bg); color: var(--iz-accent-bg-text); font-size: var(--iz-fs-sm); }
.portfolio__team-select { display: grid; gap: 2px; min-width: 150px; }
.portfolio__team-select .iz-select { width: auto; min-width: 150px; }
.portfolio__team-error { color: var(--iz-danger-text); }
.portfolio__weeks { display: grid; grid-template-columns: repeat(6, minmax(150px, 1fr)); gap: var(--iz-gap-tight); overflow-x: auto; }
.portfolio-week { display: grid; gap: var(--iz-gap-tight); min-width: 150px; }
.portfolio-week header { display: flex; justify-content: space-between; gap: var(--iz-gap-tight); align-items: baseline; }
.portfolio-week header > strong { color: var(--iz-text); font-size: var(--iz-fs-lg); }
.portfolio-week__row, .portfolio-week__total { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 7px; color: var(--iz-text-secondary); font-size: var(--iz-fs-xs); }
.portfolio-week__total { padding-top: var(--iz-gap-tight); border-top: 1px solid var(--iz-border); color: var(--iz-text); }
.portfolio-week__capacity { display: flex; justify-content: space-between; gap: 5px; margin-top: auto; padding: 8px 10px; border-radius: var(--iz-radius); font-size: var(--iz-fs-xs); }
.portfolio-week__capacity--ok { background: var(--iz-success-bg); color: var(--iz-success-text); }
.portfolio-week__capacity--over { background: var(--iz-danger-bg); color: var(--iz-danger-text); }
@media (max-width: 1200px) { .portfolio__overview-grid { grid-template-columns: 1fr; } }
@media (max-width: 920px) { .portfolio__capacity { width: 100%; margin-left: 0; padding: var(--iz-gap-tight) 0 0; border-left: 0; border-top: 1px solid var(--iz-border); } }
@media (max-width: 720px) { .portfolio__body { padding: var(--iz-pad-card); }.portfolio__filter-group { width: 100%; align-items: flex-start; flex-direction: column; }.portfolio__period-stepper { width: 100%; justify-content: space-between; }.portfolio__period-display { flex: 1 1 auto; justify-content: center; font-size: var(--iz-fs-xs); }.portfolio__segmented { width: 100%; overflow-x: auto; }.portfolio__segment { flex: 1 0 auto; text-align: center; }.portfolio__status-content { grid-template-columns: 1fr; }.portfolio__overview-grid { grid-template-columns: minmax(0, 1fr); }.portfolio__gap-row { grid-template-columns: auto minmax(0, 1fr) auto; }.portfolio__gap-row > strong { grid-column: 2; }.portfolio__gap-row .iz-badge { grid-column: 2; justify-self: start; }.portfolio__gap-row .portfolio__row-arrow { grid-column: 3; grid-row: 1 / 4; }.portfolio__toggle-meta { display: none; }.portfolio__workload-header { align-items: flex-start; flex-direction: column; } }
@media (prefers-reduced-motion: reduce) { .portfolio__chevron { transition: none; } }
</style>
