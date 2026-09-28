<template>
  <div class="portfolio-table-view">
    <!-- ── Top Header & Timestamp ── -->
    <header class="portfolio-table-view__header">
      <div class="portfolio-table-view__titles">
        <h3 class="portfolio-table-view__title">Planning Overview – Initiation Phase</h3>
        <p class="portfolio-table-view__subtitle">Drill-down view from approved Portfolio view</p>
      </div>
      <div class="portfolio-table-view__meta">
        <span>Last updated {{ lastUpdatedText }}</span>
        <button
          type="button"
          class="iz-btn iz-btn--quiet iz-btn--sm"
          title="Refresh"
          :disabled="loading"
          @click="fetchTableData"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="portfolio-table-view__refresh-icon" :class="{ 'portfolio-table-view__refresh-icon--spinning': loading }">
            <polyline points="23 4 23 10 17 10" />
            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
          </svg>
        </button>
      </div>
    </header>

    <!-- ── Top Controls Toolbar ── -->
    <div class="portfolio__toolbar iz-card iz-card--flat" aria-label="Planning overview filters">
      <div v-if="organizations && organizations.length" class="portfolio__filter-group portfolio__org-select-group">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="portfolio__org-icon" aria-hidden="true">
          <path d="M3 21h18M3 7v14M21 7v14M6 7V3h12v4M9 11h2M13 11h2M9 15h2M13 15h2" />
        </svg>
        <select
          class="iz-select iz-select--sm"
          :value="organizationId"
          @change="$emit('update:organization-id', Number($event.target.value))"
          aria-label="Filter by organization"
        >
          <option :value="null" disabled>Select organization</option>
          <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
        </select>
      </div>

      <div class="portfolio__segmented" role="group" aria-label="Portfolio scope">
        <button
          type="button"
          class="portfolio__segment"
          :class="{ 'portfolio__segment--active': scope === 'mine' }"
          :aria-pressed="String(scope === 'mine')"
          @click="setScope('mine')"
        >
          My projects
        </button>
        <button
          type="button"
          class="portfolio__segment"
          :class="{ 'portfolio__segment--active': scope === 'team' }"
          :aria-pressed="String(scope === 'team')"
          @click="setScope('team')"
        >
          Team
        </button>
        <button
          type="button"
          class="portfolio__segment"
          :class="{ 'portfolio__segment--active': scope === 'all' }"
          :aria-pressed="String(scope === 'all')"
          @click="setScope('all')"
        >
          All projects
        </button>
      </div>

      <div class="portfolio__period-stepper" title="Period controls the weekly workload strip below; the project list itself is not filtered by period">
        <button type="button" class="portfolio__segment portfolio__segment--icon" aria-label="Previous 6 weeks" title="Previous 6 weeks" @click="movePeriod(-42)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="15 18 9 12 15 6" />
          </svg>
        </button>
        <button
          type="button"
          class="portfolio__period-display portfolio__period-display--clickable"
          title="Click to jump to a specific date"
          :aria-label="'Current period: ' + periodLabel + '. Click to pick a specific date.'"
          @click="openDatePicker"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="3" y="4" width="18" height="17" rx="2" />
            <path d="M8 2v4M16 2v4M3 9h18" />
          </svg>
          <span>{{ periodLabel }}</span>
          <input
            ref="tableDatePicker"
            type="date"
            class="portfolio__date-input-hidden"
            :value="currentWeekStart"
            aria-hidden="true"
            tabindex="-1"
            @change="onDatePicked"
            @click.stop
          />
        </button>
        <button type="button" class="portfolio__segment portfolio__segment--icon" aria-label="Next 6 weeks" title="Next 6 weeks" @click="movePeriod(42)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="9 18 15 12 9 6" />
          </svg>
        </button>
        <button type="button" class="portfolio__segment" title="Reset to current 6 weeks" @click="resetPeriod">Current</button>
      </div>

      <div class="portfolio__capacity">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1z" /></svg>
        <div class="portfolio-table-view__capacity-box">
          <div class="portfolio-table-view__capacity-row">
            <select
              v-if="scope === 'team'"
              class="iz-select iz-select--sm"
              v-model="internalTeamId"
              :disabled="teamsLoading || !teams.length"
              aria-label="Select a team"
            >
              <option value="" disabled>Select a team</option>
              <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
            </select>
            <button
              v-if="scope !== 'mine'"
              type="button"
              class="iz-btn iz-btn--quiet iz-btn--xs portfolio-table-view__capacity-btn"
              @click="$emit('edit-teams')"
            >
              Edit
            </button>
          </div>
          <small v-if="needsTeamSelection" class="portfolio-table-view__team-notice">Select a team to load the planning overview.</small>
          <small v-else-if="teamSummaryText">{{ teamSummaryText }}</small>
        </div>
      </div>
    </div>

    <!-- ── Section 1: Weekly Work Preparation Load Strip ── -->
    <section class="iz-card portfolio-table-view__workload">
      <header class="iz-panel__header portfolio__workload-header">
        <h4 class="iz-panel__title">Weekly work preparation load</h4>
        <span class="portfolio-table-view__workload-note">Same period as Portfolio: {{ periodWeekRange }}</span>
      </header>

      <div v-if="teamWarnings.length" class="portfolio__warnings" role="status">
        <span v-for="warning in teamWarnings" :key="warning.id" class="iz-badge iz-badge--warning">
          {{ warning.name }} over capacity in {{ warning.overWeeks.join(", ") }}
        </span>
      </div>

      <div v-if="needsTeamSelection" class="portfolio__status-state iz-empty">Select a team to view the workload.</div>
      <div v-else-if="loading" class="portfolio__status-state iz-empty">Loading workload...</div>
      <div v-else-if="error" class="portfolio__status-state iz-error">{{ error }}</div>
      <div v-else-if="!weeks.length" class="portfolio__status-state iz-empty">No capacity data available.</div>
      <div v-else class="portfolio__weeks">
        <article v-for="week in weeks" :key="week.label" class="iz-card iz-card--flat portfolio-week portfolio-table-view__week-card">
          <header class="portfolio-table-view__week-header">
            <strong>{{ weekDisplayLabel(week) }}</strong>
            <small>{{ formatWeekRange(week.start, week.end) }}</small>
          </header>
          <div class="portfolio-table-view__week-sub">
            <span>Starting {{ week.starting }}</span>
            <span class="portfolio-table-view__dot">·</span>
            <span>Ongoing {{ week.continuing }}</span>
            <span class="portfolio-table-view__dot">·</span>
            <span>Ending {{ week.ending }}</span>
          </div>
          <div class="portfolio-table-view__week-badge" :class="workloadBadgeClass(week)">
            <strong>{{ week.totalActive }} / {{ week.capacity }}</strong>
            <span>{{ workloadStatusText(week) }}</span>
          </div>
        </article>
      </div>
    </section>

    <!-- ── Filter Chips Bar & Search/Export/Columns ── -->
    <div class="portfolio-table-view__filters-row">
      <div class="portfolio-table-view__chips" role="group" aria-label="Status filters">
        <button
          v-for="chip in filterChips"
          :key="chip.key"
          type="button"
          class="portfolio-table-view__chip"
          :class="{
            'portfolio-table-view__chip--active': activeFilter === chip.key,
            'portfolio-table-view__chip--danger': chip.key === 'gaps' && chip.count > 0,
            'portfolio-table-view__chip--success': chip.key === '100',
            'portfolio-table-view__chip--warning': chip.key === '75-99',
            'portfolio-table-view__chip--period': chip.key === 'in-period'
          }"
          :aria-pressed="String(activeFilter === chip.key)"
          @click="activeFilter = chip.key"
        >
          <span>{{ chip.label }}</span>
          <span class="portfolio-table-view__chip-count">· {{ chip.count }}</span>
        </button>
      </div>

      <div class="portfolio-table-view__actions">
        <div class="portfolio-table-view__search-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="portfolio-table-view__search-icon">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input
            v-model="searchQuery"
            type="search"
            class="iz-input portfolio-table-view__search"
            placeholder="Search project..."
            aria-label="Search project"
          />
        </div>

        <div class="portfolio-table-view__col-toggle">
          <button
            type="button"
            class="iz-btn iz-btn--secondary portfolio-table-view__btn"
            :class="{ 'iz-btn--active': showColumnMenu }"
            @click="showColumnMenu = !showColumnMenu"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" /><path d="M9 3v18M15 3v18" /></svg>
            Columns
          </button>
          <div v-if="showColumnMenu" class="portfolio-table-view__col-dropdown iz-card">
            <header><strong>Visible columns</strong></header>
            <label v-for="col in columns" :key="col.key" class="portfolio-table-view__col-option">
              <input type="checkbox" v-model="visibleColumns[col.key]" :disabled="col.required" />
              <span>{{ col.label }}</span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <section v-if="listedGaps.length" class="iz-card portfolio-table-view__gap-list" aria-label="Planning gap intervals">
      <h4>{{ gapListTitle }} ({{ listedGaps.length }})</h4>
      <button v-for="gap in listedGaps" :key="gap.id" type="button" class="iz-btn iz-btn--quiet iz-btn--sm" @click="selectedGap = gap">
        {{ gap.type === 'internal' ? 'Inside project' : 'Between projects' }} · {{ gap.name }} · {{ gap.startDate }} – {{ gap.endDate }} ({{ gap.duration }})
      </button>
    </section>
    <PlanningGapDetails v-if="selectedGap" :gap="selectedGap" @close="closeGapDetails" @open-project="openGapProject" />
    <details v-if="scheduleIssues.length" class="iz-card portfolio-table-view__issues">
      <summary>Schedule issues ({{ scheduleIssues.length }})</summary>
      <p v-for="issue in scheduleIssues" :key="issue.id">{{ issue.projectName }}: {{ issue.note }}</p>
    </details>
    <details v-if="planningConflicts.length" class="iz-card portfolio-table-view__issues">
      <summary>Planning conflicts ({{ planningConflicts.length }})</summary>
      <p v-for="conflict in planningConflicts" :key="conflict.id">{{ conflict.projectName }}: {{ conflict.note }}</p>
    </details>

    <!-- ── Table Section ── -->
    <section class="iz-card portfolio-table-view__table-card">
      <header class="portfolio-table-view__table-header">
        <h4 class="iz-panel__title">Projects – Initiation Phase</h4>
        <span class="portfolio-table-view__badge">Table view</span>
      </header>

      <div class="portfolio-table-view__table-container">
        <table class="portfolio-table">
          <thead>
            <tr>
              <th v-if="visibleColumns.project" scope="col" class="portfolio-table__th--sortable" tabindex="0" role="columnheader" :aria-sort="ariaSort('name')" @click="toggleSort('name')" @keydown="onSortKeydown($event, 'name')">
                <span>Project</span>
                <span class="portfolio-table__sort-arrow" aria-hidden="true">{{ sortArrow('name') }}</span>
              </th>
              <th v-if="visibleColumns.status" scope="col">Process status</th>
              <th v-if="visibleColumns.completion" scope="col" class="portfolio-table__th--sortable" tabindex="0" role="columnheader" :aria-sort="ariaSort('completionPct')" @click="toggleSort('completionPct')" @keydown="onSortKeydown($event, 'completionPct')">
                <span>% done</span>
                <span class="portfolio-table__sort-arrow" aria-hidden="true">{{ sortArrow('completionPct') }}</span>
              </th>
              <th v-if="visibleColumns.expected" scope="col">Expected / reached 100%</th>
              <th v-if="visibleColumns.startPrep" scope="col">Start Work Preparation</th>
              <th v-if="visibleColumns.countdownPrep" scope="col">Countdown to start Work Preparation</th>
              <th v-if="visibleColumns.minExec" scope="col">Min. execution start</th>
              <th v-if="visibleColumns.wensweek" scope="col" class="portfolio-table__th--sortable" tabindex="0" role="columnheader" :aria-sort="ariaSort('desiredStartDate')" @click="toggleSort('desiredStartDate')" @keydown="onSortKeydown($event, 'desiredStartDate')">
                <span>Desired week</span>
                <span class="portfolio-table__sort-arrow" aria-hidden="true">{{ sortArrow('desiredStartDate') }}</span>
              </th>
              <th v-if="visibleColumns.countdownWensweek" scope="col">Countdown to Desired Week</th>
              <th v-if="visibleColumns.actualStart" scope="col" class="portfolio-table__th--sortable" tabindex="0" role="columnheader" :aria-sort="ariaSort('actualStartDate')" @click="toggleSort('actualStartDate')" @keydown="onSortKeydown($event, 'actualStartDate')">
                <span>Actual start week</span>
                <span class="portfolio-table__sort-arrow" aria-hidden="true">{{ sortArrow('actualStartDate') }}</span>
              </th>
              <th v-if="visibleColumns.cards" scope="col">Open cards</th>
              <th v-if="visibleColumns.gap" scope="col">Planning gap</th>
              <th scope="col" class="portfolio-table__th--action"><span class="sr-only">Action</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="needsTeamSelection">
              <td colspan="13" class="portfolio-table__empty">Select a team to view projects.</td>
            </tr>
            <tr v-else-if="loading">
              <td colspan="13" class="portfolio-table__empty">Loading data...</td>
            </tr>
            <tr v-else-if="paginatedProjects.length === 0">
              <td colspan="13" class="portfolio-table__empty">No projects found for selected filters.</td>
            </tr>
            <tr
              v-else
              v-for="project in paginatedProjects"
              :key="project.id"
              class="portfolio-table__row"
              tabindex="0"
              :aria-label="'Open details for ' + project.name"
              @click="onRowClick(project)"
              @keydown="onRowKeydown($event, project)"
            >
              <!-- 1. Project Name -->
              <td v-if="visibleColumns.project" class="portfolio-table__cell-project">
                <strong>{{ project.name }}</strong>
              </td>

              <!-- 2. Process Status Badge -->
              <td v-if="visibleColumns.status">
                <span class="iz-badge" :class="statusBadgeClass(project.bucket)">
                  {{ project.bucketLabel }}
                </span>
              </td>

              <!-- 3. % Done -->
              <td v-if="visibleColumns.completion">
                <span class="portfolio-table__pct-pill" :class="{ 'portfolio-table__pct-pill--done': project.completionPct === 100 }">
                  {{ project.completionPct }}%
                </span>
              </td>

              <!-- 4. Expected / Reached 100% -->
              <td v-if="visibleColumns.expected">
                <span :class="{ 'portfolio-table__text--success': project.isCompleted }">
                  {{ project.expectedOrAchievedLabel }}
                </span>
              </td>

              <!-- 5. Start Work Preparation -->
              <td v-if="visibleColumns.startPrep">
                <span>{{ project.startPrepWeek }}</span>
              </td>

              <!-- 6. Countdown to Start Work Preparation -->
              <td v-if="visibleColumns.countdownPrep">
                <span>{{ project.startPrepCountdown }}</span>
              </td>

              <!-- 7. Min. Execution Start -->
              <td v-if="visibleColumns.minExec">
                <span>{{ project.minExecutionStartWeek }}</span>
              </td>

              <!-- 8. Desired Week -->
              <td v-if="visibleColumns.wensweek" class="portfolio-table__cell-wensweek">
                <strong>{{ project.desiredStartWeek }}</strong>
                <small v-if="project.isLeadingDesiredWeek" class="portfolio-table__leading-note">actual desired week is leading</small>
              </td>

              <!-- 9. Countdown to Desired Week -->
              <td v-if="visibleColumns.countdownWensweek">
                <span>{{ project.desiredCountdown }}</span>
              </td>

              <!-- 10. Actual Start Week -->
              <td v-if="visibleColumns.actualStart">
                <span>{{ project.actualStartWeek || "—" }}</span>
              </td>

              <!-- 10. Open Cards -->
              <td v-if="visibleColumns.cards">
                <button
                  type="button"
                  class="portfolio-table__cards-btn"
                  title="Open Deck board"
                  @click.stop="openDeckBoard(project)"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="portfolio-table__deck-icon">
                    <rect x="3" y="3" width="7" height="9" />
                    <rect x="14" y="3" width="7" height="5" />
                    <rect x="14" y="12" width="7" height="9" />
                    <rect x="3" y="16" width="7" height="5" />
                  </svg>
                  <span>{{ project.openCards }}</span>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="portfolio-table__arrow-icon">
                    <polyline points="9 18 15 12 9 6" />
                  </svg>
                </button>
              </td>

              <!-- 11. Planning Gap -->
              <td v-if="visibleColumns.gap">
                <button v-if="project.planningGap.hasGap" type="button" class="iz-badge iz-badge--danger portfolio-table-view__gap-button" @click.stop="openProjectGap(project)">
                  {{ project.planningGap.display }}
                </button>
                <span v-else class="iz-badge iz-badge--success">None</span>
              </td>

              <!-- 12. Row Action Chevron -->
              <td class="portfolio-table__cell-arrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="portfolio-table__row-arrow">
                  <polyline points="9 18 15 12 9 6" />
                </svg>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ── Table Footer & Pagination ── -->
      <footer class="portfolio-table-view__table-footer">
        <span class="portfolio-table-view__hint">
          Click a project (or press Enter on a focused row) to open its details in Project Performance Analytics below.
        </span>

        <div class="portfolio-table-view__pagination" role="navigation" aria-label="Table pages">
          <button
            type="button"
            class="portfolio-table-view__page-btn"
            :disabled="currentPage <= 1"
            aria-label="Previous page"
            @click="currentPage--"
          >
            ‹
          </button>
          <button
            v-for="p in totalPages"
            :key="p"
            type="button"
            class="portfolio-table-view__page-btn"
            :class="{ 'portfolio-table-view__page-btn--active': currentPage === p }"
            :aria-label="'Page ' + p"
            :aria-current="currentPage === p ? 'page' : null"
            @click="currentPage = p"
          >
            {{ p }}
          </button>
          <button
            type="button"
            class="portfolio-table-view__page-btn"
            :disabled="currentPage >= totalPages"
            aria-label="Next page"
            @click="currentPage++"
          >
            ›
          </button>
          <span class="portfolio-table-view__page-info">
            {{ pageCountDisplay }}
          </span>
        </div>
      </footer>
    </section>

    <!-- ── Brand Footer ── -->
    <footer class="portfolio-table-view__brand-footer">
      <span class="portfolio-table-view__brand">
        <strong>InZicht</strong> Together making projects insightful.
      </span>
      <span class="portfolio-table-view__brand-sync">
        All data is live and current for the selected filters.
      </span>
    </footer>
  </div>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";
import PlanningGapDetails from "./PlanningGapDetails.vue";

export default {
  name: "ProjectPortfolioTableView",
  components: { PlanningGapDetails },
  props: {
    organizationId: { type: Number, default: null },
    organizations: { type: Array, default: function () { return []; } },
    scope: { type: String, default: "all" },
    teamId: { type: [Number, String], default: null },
    teams: { type: Array, default: function () { return []; } },
    teamsLoading: { type: Boolean, default: false },
    initialFilter: { type: String, default: "all" },
    weekStart: { type: String, default: null },
  },
  data: function () {
    return {
      tableData: null,
      selectedGap: null,
      gapFocusProject: null,
      loading: false,
      error: null,
      tableRequestId: 0,
      activeFilter: this.initialFilter || "all",
      searchQuery: "",
      currentPage: 1,
      pageSize: 10,
      sortKey: "name",
      sortAsc: true,
      showColumnMenu: false,
      lastUpdated: new Date(),
      visibleColumns: {
        project: true,
        status: true,
        completion: true,
        expected: true,
        startPrep: true,
        countdownPrep: true,
        minExec: true,
        wensweek: true,
        countdownWensweek: true,
        actualStart: true,
        cards: true,
        gap: true,
      },
      columns: [
        { key: "project", label: "Project", required: true },
        { key: "status", label: "Process status" },
        { key: "completion", label: "% done" },
        { key: "expected", label: "Expected / reached 100%" },
        { key: "startPrep", label: "Start Work Preparation" },
        { key: "countdownPrep", label: "Countdown to start Work Preparation" },
        { key: "minExec", label: "Min. execution start" },
        { key: "wensweek", label: "Desired week" },
        { key: "countdownWensweek", label: "Countdown to Desired Week" },
        { key: "actualStart", label: "Actual start week" },
        { key: "cards", label: "Open cards" },
        { key: "gap", label: "Planning gap" },
      ],
    };
  },
  computed: {
    internalTeamId: {
      get: function () {
        return this.teamId || "";
      },
      set: function (val) {
        this.$emit("update:teamId", val);
      },
    },
    hasPositiveTeamId: function () {
      var id = Number(this.teamId);
      return Number.isInteger(id) && id > 0;
    },
    needsTeamSelection: function () {
      return this.scope === "team" && !this.hasPositiveTeamId;
    },
    lastUpdatedText: function () {
      if (!this.lastUpdated) return "today";
      var h = String(this.lastUpdated.getHours()).padStart(2, "0");
      var m = String(this.lastUpdated.getMinutes()).padStart(2, "0");
      return "today " + h + ":" + m;
    },
    teamSummaryText: function () {
      if (!this.tableData || !this.tableData.team) return "";
      var t = this.tableData.team;
      var note = this.formatNumber(t.capacity) + " concurrent projects";
      if (this.scope === "mine") {
        return "My projects (" + (this.tableData.teams ? this.tableData.teams.length : 0) + " teams): " + note;
      }
      if (this.scope === "all" || !this.hasPositiveTeamId) {
        return "All teams: " + note;
      }
      return this.formatNumber(t.fte) + " FTE × " + this.formatNumber(t.projectsPerFte) + " projects/FTE = " + note;
    },
    periodStart: function () {
      if (this.tableData && this.tableData.period && this.tableData.period.weekStart) {
        return this.tableData.period.weekStart;
      }
      return this.weekStart || "";
    },
    periodEnd: function () {
      if (this.tableData && this.tableData.period && this.tableData.period.weekEnd) {
        return this.tableData.period.weekEnd;
      }
      if (this.periodStart) {
        var start = this.parseDate(this.periodStart);
        start.setUTCDate(start.getUTCDate() + 41);
        return start.toISOString().slice(0, 10);
      }
      return "";
    },
    currentWeekStart: function () {
      return this.periodStart || "";
    },
    inPeriodCount: function () {
      var list = (this.tableData && this.tableData.projects) || [];
      var pStart = this.periodStart;
      var pEnd = this.periodEnd;
      if (!pStart || !pEnd) return 0;
      var self = this;
      return list.filter(function (p) {
        return self.isProjectInPeriod(p, pStart, pEnd);
      }).length;
    },
    periodLabel: function () {
      if (!this.tableData || !this.tableData.period) return "2026-W31 – 2026-W36 (6 weeks)";
      var start = this.parseDate(this.tableData.period.weekStart);
      var end = new Date(start.getTime());
      end.setUTCDate(end.getUTCDate() + 41);
      return this.isoYearWeek(start) + " – " + this.isoYearWeek(end) + " (6 weeks)";
    },
    periodWeekRange: function () {
      if (!this.tableData || !this.tableData.period) return "2026-W31-2026-W36";
      var start = this.parseDate(this.tableData.period.weekStart);
      var end = new Date(start.getTime());
      end.setUTCDate(end.getUTCDate() + 41);
      return this.isoYearWeek(start) + "-" + this.isoYearWeek(end);
    },
    weeks: function () {
      return (this.tableData && this.tableData.weeks) || [];
    },
    teamWarnings: function () {
      return (this.tableData && this.tableData.teamWarnings) || [];
    },
    planningGaps: function () { return (this.tableData && this.tableData.planningGaps) || []; },
    scheduleIssues: function () { return (this.tableData && this.tableData.scheduleIssues) || []; },
    planningConflicts: function () { return (this.tableData && this.tableData.planningConflicts) || []; },
    listedGaps: function () {
      if (this.gapFocusProject) {
        var ids = this.gapFocusProject.planningGap.gapIds || [];
        return this.planningGaps.filter(function (gap) { return ids.indexOf(gap.id) !== -1; });
      }
      return this.activeFilter === "gaps" ? this.planningGaps : [];
    },
    gapListTitle: function () {
      return this.gapFocusProject ? "Planning gaps · " + this.gapFocusProject.name : "Open planning gaps";
    },
    filterChips: function () {
      var buckets = (this.tableData && this.tableData.buckets) ? this.tableData.buckets.slice() : [
        { key: "all", label: "All statuses", count: 0 },
        { key: "0-24", label: "0–24%", count: 0 },
        { key: "25-49", label: "25–49%", count: 0 },
        { key: "50-74", label: "50–74%", count: 0 },
        { key: "75-99", label: "75–99% / Upcoming", count: 0 },
        { key: "100", label: "100% ready for Handover 1", count: 0 },
        { key: "gaps", label: "Projects with gaps", count: 0 },
      ];
      buckets.push({
        key: "in-period",
        label: "In selected period",
        count: this.inPeriodCount,
      });
      return buckets;
    },
    filteredProjects: function () {
      var list = (this.tableData && this.tableData.projects) || [];
      var filter = this.activeFilter;
      var q = (this.searchQuery || "").trim().toLowerCase();
      var pStart = this.periodStart;
      var pEnd = this.periodEnd;
      var self = this;

      return list.filter(function (p) {
        // Status or in-period chip filter
        if (filter === "in-period") {
          if (!self.isProjectInPeriod(p, pStart, pEnd)) return false;
        } else if (filter === "gaps") {
          if (!p.planningGap || !p.planningGap.hasGap) return false;
        } else if (filter !== "all") {
          if (p.bucket !== filter) return false;
        }

        // Substring search
        if (q) {
          var name = (p.name || "").toLowerCase();
          if (name.indexOf(q) === -1) return false;
        }

        return true;
      });
    },
    sortedProjects: function () {
      var list = this.filteredProjects.slice();
      var key = this.sortKey;
      var asc = this.sortAsc ? 1 : -1;

      return list.sort(function (a, b) {
        var valA = a[key] ?? "";
        var valB = b[key] ?? "";
        if (typeof valA === "string") valA = valA.toLowerCase();
        if (typeof valB === "string") valB = valB.toLowerCase();
        if (valA < valB) return -1 * asc;
        if (valA > valB) return 1 * asc;
        return 0;
      });
    },
    totalPages: function () {
      return Math.max(1, Math.ceil(this.sortedProjects.length / this.pageSize));
    },
    paginatedProjects: function () {
      var start = (this.currentPage - 1) * this.pageSize;
      return this.sortedProjects.slice(start, start + this.pageSize);
    },
    pageCountDisplay: function () {
      var count = this.paginatedProjects.length;
      var total = this.sortedProjects.length;
      return count + " / " + total;
    },
  },
  watch: {
    organizationId: function () {
      this.currentPage = 1;
      this.fetchTableData();
    },
    scope: function () {
      this.currentPage = 1;
      this.fetchTableData();
    },
    teamId: function () {
      this.currentPage = 1;
      this.fetchTableData();
    },
    weekStart: function () {
      this.currentPage = 1;
      this.fetchTableData();
    },
    initialFilter: function (val) {
      if (val) this.activeFilter = val;
    },
    activeFilter: function () {
      this.currentPage = 1;
      this.gapFocusProject = null;
    },
    searchQuery: function () {
      this.currentPage = 1;
    },
    sortKey: function () {
      this.currentPage = 1;
    },
    tableData: function () {
      this.selectedGap = null;
      this.gapFocusProject = null;
      this.clampPage();
    },
    sortedProjects: function () {
      this.clampPage();
    },
  },
  mounted: function () {
    this.fetchTableData();
  },
  methods: {
    openProjectGap: function (project) {
      // Several gaps: list them all so each can be opened, not only the first.
      this.gapFocusProject = (project.planningGap.gapIds || []).length > 1 ? project : null;
      var ids = project.planningGap.gapIds || [];
      this.selectedGap = this.planningGaps.find(function (gap) { return ids.indexOf(gap.id) !== -1; }) || null;
    },
    closeGapDetails: function () {
      this.selectedGap = null;
      this.gapFocusProject = null;
    },
    openGapProject: function (project) {
      // The detail view needs the full table row; gap anchors carry only id and name.
      var rows = (this.tableData && this.tableData.projects) || [];
      var row = rows.find(function (p) { return Number(p.id) === Number(project.id); });
      this.$emit("select-project", row || project);
    },
    setScope: function (scope) {
      this.$emit("update:scope", scope);
    },
    movePeriod: function (days) {
      this.$emit("move-period", days);
    },
    resetPeriod: function () {
      this.$emit("reset-period");
    },
    openDatePicker: function () {
      var input = this.$refs.tableDatePicker;
      if (!input) return;
      if (typeof input.showPicker === "function") {
        try {
          input.showPicker();
          return;
        } catch (e) {
          // fallback to focus and click
        }
      }
      input.focus();
      input.click();
    },
    onDatePicked: function (event) {
      var val = event && event.target && event.target.value ? event.target.value.trim() : null;
      if (val) {
        this.$emit("select-date", val);
      }
    },
    isProjectInPeriod: function (p, periodStart, periodEnd) {
      if (!p || !periodStart || !periodEnd) return false;
      var start = p.startDate || p.actualStartDate || p.startPrepDate || p.desiredStartDate;
      var end = p.actualEnd || p.endDate || p.plannedEnd || p.desiredStartDate;

      if (!start && !end) return false;
      if (start && start > periodEnd) return false;
      if (end && end < periodStart) return false;

      return true;
    },
    fetchTableData: async function () {
      if (!this.organizationId) return;
      if (this.needsTeamSelection) {
        this.tableRequestId++;
        this.loading = false;
        this.error = null;
        this.tableData = null;
        this.currentPage = 1;
        return;
      }
      var requestId = ++this.tableRequestId;
      this.loading = true;
      this.error = null;
      this.selectedGap = null;
      try {
        var params = { scope: this.scope };
        if (this.organizationId) {
          params.organizationId = this.organizationId;
        }
        if (this.weekStart) {
          params.weekStart = this.weekStart;
        }
        if (this.scope === "team" && this.hasPositiveTeamId) {
          params.teamId = Number(this.teamId);
        }
        var response = await axios.get(generateUrl("/apps/projectcreatoraio/api/v1/portfolio/table"), {
          params: params,
        });
        if (requestId !== this.tableRequestId) return;
        this.tableData = response.data;
        this.lastUpdated = new Date();
        this.clampPage();
      } catch (e) {
        if (requestId !== this.tableRequestId) return;
        this.error = "Planning overview could not be loaded.";
      } finally {
        if (requestId === this.tableRequestId) this.loading = false;
      }
    },
    clampPage: function () {
      var total = this.totalPages || 1;
      if (this.currentPage > total) this.currentPage = total;
      if (this.currentPage < 1) this.currentPage = 1;
    },
    parseDate: function (value) {
      if (!value) return new Date();
      var parts = String(value).split("-").map(Number);
      return new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
    },
    isoWeek: function (date) {
      var d = new Date(date.getTime());
      d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7));
      var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
      return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    },
    isoYear: function (date) {
      var d = new Date(date.getTime());
      d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7));
      return d.getUTCFullYear();
    },
    isoYearWeek: function (date) {
      var d = new Date(date.getTime());
      d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7));
      var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
      var week = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
      return d.getUTCFullYear() + "-W" + String(week).padStart(2, "0");
    },
    weekDisplayLabel: function (week) {
      if (week && week.label) return week.label;
      if (!week || !week.start) return "";
      return this.isoYearWeek(this.parseDate(week.start));
    },
    weekNumber: function (dateStr) {
      if (!dateStr) return "";
      return this.isoWeek(this.parseDate(dateStr));
    },
    formatWeekRange: function (startStr, endStr) {
      if (!startStr || !endStr) return "";
      var months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
      var s = this.parseDate(startStr);
      var e = this.parseDate(endStr);
      return s.getUTCDate() + " " + months[s.getUTCMonth()] + " – " + e.getUTCDate() + " " + months[e.getUTCMonth()];
    },
    formatNumber: function (val) {
      return Number(val || 0).toLocaleString("en-US", { maximumFractionDigits: 1 });
    },
    workloadBadgeClass: function (week) {
      if (week.overCapacity) return "portfolio-table-view__badge--over";
      if (week.totalActive === week.capacity && week.capacity > 0) return "portfolio-table-view__badge--norm";
      if (week.totalActive < week.capacity) return "portfolio-table-view__badge--space";
      return "portfolio-table-view__badge--neutral";
    },
    workloadStatusText: function (week) {
      if (week.overCapacity) {
        var over = Math.round(week.totalActive - week.capacity);
        return "+" + over + " above capacity";
      }
      if (week.totalActive === week.capacity && week.capacity > 0) {
        return "On target";
      }
      if (week.totalActive === 0) {
        return "No active projects";
      }
      var space = Math.round(week.capacity - week.totalActive);
      return "Remaining: " + space;
    },
    statusBadgeClass: function (bucket) {
      if (bucket === "100") return "iz-badge--success";
      if (bucket === "75-99") return "iz-badge--warning";
      if (bucket === "50-74" || bucket === "25-49") return "iz-badge--accent";
      return "iz-badge--neutral";
    },
    toggleSort: function (key) {
      if (this.sortKey === key) {
        this.sortAsc = !this.sortAsc;
      } else {
        this.sortKey = key;
        this.sortAsc = true;
      }
    },
    sortArrow: function (key) {
      if (this.sortKey !== key) return "↕";
      return this.sortAsc ? "↑" : "↓";
    },
    ariaSort: function (key) {
      if (this.sortKey !== key) return "none";
      return this.sortAsc ? "ascending" : "descending";
    },
    onSortKeydown: function (event, key) {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        this.toggleSort(key);
      }
    },
    onRowKeydown: function (event, project) {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        this.onRowClick(project);
      }
    },
    onRowClick: function (project) {
      this.$emit("select-project", project);
    },
    openDeckBoard: function (project) {
      if (!project.boardId) return;
      var url = generateUrl("/apps/deck/#/board/" + project.boardId);
      window.open(url, "_blank");
    },
  },
};
</script>

<style scoped>
.portfolio-table-view__gap-list { display: grid; gap: 6px; padding: 14px; }
.portfolio-table-view__gap-list h4 { margin: 0 0 4px; }
.portfolio-table-view__gap-list button { justify-self: start; text-align: left; }
.portfolio-table-view__issues { padding: 14px; }
.portfolio-table-view__gap-button { border: 0; cursor: pointer; }
.portfolio-table-view {
  display: grid;
  gap: var(--iz-gap);
}

.portfolio-table-view__header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  flex-wrap: wrap;
  gap: var(--iz-gap-tight);
}

.portfolio-table-view__titles {
  display: grid;
  gap: 2px;
}

.portfolio-table-view__title {
  margin: 0;
  font-size: var(--iz-fs-xl, 20px);
  font-weight: 700;
  color: var(--iz-text);
}

.portfolio-table-view__subtitle {
  margin: 0;
  font-size: var(--iz-fs-sm);
  color: var(--iz-text-secondary);
}

.portfolio-table-view__meta {
  display: flex;
  align-items: center;
  gap: var(--iz-gap-tight);
  font-size: var(--iz-fs-xs);
  color: var(--iz-text-muted);
}

.portfolio-table-view__refresh-icon {
  width: 14px;
  height: 14px;
}

.portfolio-table-view__refresh-icon--spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.portfolio-table-view__capacity-box {
  display: grid;
  gap: 2px;
  min-width: 180px;
}

.portfolio-table-view__capacity-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
}

.portfolio-table-view__capacity-btn {
  padding: 0 4px;
  height: auto;
  font-size: var(--iz-fs-xs);
  color: var(--iz-accent);
}

.portfolio-table-view__workload {
  padding: var(--iz-pad-card);
  display: grid;
  gap: var(--iz-gap);
}

.portfolio-table-view__workload-note {
  margin-left: auto;
  font-size: var(--iz-fs-xs);
  color: var(--iz-text-secondary);
}

.portfolio-table-view__week-card {
  display: grid;
  gap: 8px;
  padding: var(--iz-pad-card);
}

.portfolio-table-view__week-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 4px;
}

.portfolio-table-view__week-header > strong {
  font-size: var(--iz-fs-lg);
  color: var(--iz-text);
}

.portfolio-table-view__week-sub {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  font-size: var(--iz-fs-xs);
  color: var(--iz-text-secondary);
}

.portfolio-table-view__dot {
  color: var(--iz-border);
}

.portfolio-table-view__week-badge {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 6px;
  padding: 6px 8px;
  border-radius: var(--iz-radius);
  font-size: var(--iz-fs-xs);
  font-weight: 600;
}

.portfolio-table-view__badge--norm {
  background: var(--iz-success-bg, #e8f5e9);
  color: var(--iz-success-text, #2e7d32);
}

.portfolio-table-view__badge--space {
  background: var(--iz-success-bg, #e8f5e9);
  color: var(--iz-success-text, #2e7d32);
}

.portfolio-table-view__badge--over {
  background: var(--iz-danger-bg, #ffebee);
  color: var(--iz-danger-text, #c62828);
}

.portfolio-table-view__badge--neutral {
  background: var(--iz-surface-subtle);
  color: var(--iz-text-secondary);
}


.portfolio-table-view__filters-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--iz-gap);
}

.portfolio-table-view__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.portfolio-table-view__chip {
  padding: 6px 12px;
  border: 1px solid var(--iz-border);
  border-radius: var(--iz-radius-pill);
  background: var(--iz-surface);
  color: var(--iz-text-secondary);
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all var(--iz-transition);
}

.portfolio-table-view__chip:hover {
  background: var(--iz-surface-subtle);
  color: var(--iz-text);
}

.portfolio-table-view__chip--active {
  background: var(--iz-accent);
  color: var(--iz-accent-text);
  border-color: var(--iz-accent);
}

.portfolio-table-view__chip--warning.portfolio-table-view__chip--active {
  background: #fef3c7;
  color: #92400e;
  border-color: #f59e0b;
}

.portfolio-table-view__chip--success.portfolio-table-view__chip--active {
  background: #dcfce7;
  color: #166534;
  border-color: #22c55e;
}

.portfolio-table-view__chip--danger.portfolio-table-view__chip--active {
  background: #fee2e2;
  color: #991b1b;
  border-color: #ef4444;
}

.portfolio-table-view__chip--period.portfolio-table-view__chip--active {
  background: var(--iz-accent-bg, #e0f2fe);
  color: var(--iz-accent-bg-text, #0369a1);
  border-color: var(--iz-accent, #0284c7);
}

.portfolio-table-view__chip-count {
  opacity: 0.9;
}

.portfolio-table-view__actions {
  display: flex;
  align-items: center;
  gap: var(--iz-gap-tight);
  margin-left: auto;
}

.portfolio-table-view__search-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.portfolio-table-view__search-icon {
  position: absolute;
  left: 10px;
  width: 14px;
  height: 14px;
  color: var(--iz-text-muted);
  pointer-events: none;
}

.portfolio-table-view__search {
  padding-left: 32px;
  width: 180px;
  font-size: var(--iz-fs-xs);
  height: 32px;
}

.portfolio-table-view__btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 32px;
  padding: 0 10px;
  font-size: var(--iz-fs-xs);
}

.portfolio-table-view__btn svg {
  width: 14px;
  height: 14px;
}

.portfolio-table-view__col-toggle {
  position: relative;
}

.portfolio-table-view__col-dropdown {
  position: absolute;
  right: 0;
  top: 100%;
  margin-top: 4px;
  z-index: 50;
  width: 220px;
  padding: 10px;
  display: grid;
  gap: 6px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.portfolio-table-view__col-option {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--iz-fs-xs);
  cursor: pointer;
}

.portfolio-table-view__table-card {
  padding: 0;
  overflow: hidden;
}

.portfolio-table-view__table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--iz-pad-card);
  border-bottom: 1px solid var(--iz-border);
}

.portfolio-table-view__badge {
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  color: var(--iz-accent);
}

.portfolio-table-view__table-container {
  overflow-x: auto;
}

.portfolio-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: var(--iz-fs-sm);
}

.portfolio-table th {
  padding: 10px 14px;
  background: var(--iz-surface-subtle);
  color: var(--iz-text-secondary);
  font-size: var(--iz-fs-xs);
  font-weight: 700;
  white-space: nowrap;
  border-bottom: 1px solid var(--iz-border);
  user-select: none;
}

.portfolio-table__th--sortable {
  cursor: pointer;
}

.portfolio-table__th--sortable:hover {
  color: var(--iz-text);
}

.portfolio-table__sort-arrow {
  margin-left: 4px;
  font-size: 10px;
}

.portfolio-table td {
  padding: 12px 14px;
  border-bottom: 1px solid var(--iz-border);
  color: var(--iz-text);
  white-space: nowrap;
  vertical-align: middle;
}

.portfolio-table__row {
  cursor: pointer;
  transition: background var(--iz-transition);
}

.portfolio-table__row:hover {
  background: var(--iz-surface-subtle);
}

.portfolio-table__cell-project > strong {
  font-weight: 700;
  color: var(--iz-text);
}

.portfolio-table__pct-pill {
  display: inline-block;
  padding: 3px 8px;
  border-radius: var(--iz-radius-pill);
  background: #e0f2fe;
  color: #0284c7;
  font-weight: 700;
  font-size: var(--iz-fs-xs);
}

.portfolio-table__pct-pill--done {
  background: #dcfce7;
  color: #16a34a;
}

.portfolio-table__text--success {
  color: #16a34a;
  font-weight: 600;
}

.portfolio-table__cell-wensweek {
  display: grid;
  gap: 2px;
}

.portfolio-table__leading-note {
  font-size: 10px;
  color: #16a34a;
  font-weight: 500;
}

.portfolio-table__cards-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border: 1px solid var(--iz-border);
  border-radius: var(--iz-radius-pill);
  background: var(--iz-surface);
  color: var(--iz-text);
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  cursor: pointer;
}

.portfolio-table__cards-btn:hover {
  background: var(--iz-surface-subtle);
  border-color: var(--iz-accent);
}

.portfolio-table__deck-icon {
  width: 12px;
  height: 12px;
  color: var(--iz-accent);
}

.portfolio-table__arrow-icon {
  width: 10px;
  height: 10px;
  color: var(--iz-text-muted);
}

.portfolio-table__row-arrow {
  width: 16px;
  height: 16px;
  color: var(--iz-text-muted);
}

.portfolio-table__row:hover .portfolio-table__row-arrow {
  color: var(--iz-accent);
}

.portfolio-table__empty {
  text-align: center;
  padding: 30px 14px;
  color: var(--iz-text-secondary);
}

.portfolio-table-view__table-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--iz-gap);
  padding: var(--iz-pad-card);
  border-top: 1px solid var(--iz-border);
  background: var(--iz-surface);
}

.portfolio-table-view__hint {
  font-size: var(--iz-fs-xs);
  color: var(--iz-text-secondary);
}

.portfolio-table-view__pagination {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-left: auto;
}

.portfolio-table-view__page-btn {
  min-width: 28px;
  height: 28px;
  padding: 0 6px;
  border: 1px solid var(--iz-border);
  border-radius: var(--iz-radius);
  background: var(--iz-surface);
  color: var(--iz-text-secondary);
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  cursor: pointer;
}

.portfolio-table-view__page-btn:hover:not(:disabled) {
  background: var(--iz-surface-subtle);
  color: var(--iz-text);
}

.portfolio-table-view__page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.portfolio-table-view__page-btn--active {
  background: var(--iz-accent);
  color: var(--iz-accent-text);
  border-color: var(--iz-accent);
}

.portfolio-table-view__page-info {
  margin-left: 8px;
  font-size: var(--iz-fs-xs);
  color: var(--iz-text-secondary);
}

.portfolio-table-view__brand-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--iz-gap-tight);
  padding: 8px 4px;
  font-size: var(--iz-fs-xs);
  color: var(--iz-text-muted);
}

.portfolio-table-view__brand strong {
  color: var(--iz-text);
}

.portfolio-table-view__period-note {
  font-size: var(--iz-fs-xs);
  color: var(--iz-text-muted);
  white-space: nowrap;
}

.portfolio-table-view__team-notice {
  color: var(--iz-warning-text, #92400e);
  font-size: var(--iz-fs-xs);
  font-weight: 600;
}

/* ── Independent toolbar / workload / grid styles ──
   The table view reuses the portfolio toolbar, segmented control, capacity
   block, warnings and week-grid class names, but scoped CSS from
   ProjectPortfolioPanel.vue cannot reach this component. These local rules
   keep the child self-sufficient (theme tokens only, no hardcoded palette). */
.portfolio-table-view .portfolio__toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--iz-gap-tight);
  padding: var(--iz-pad-card);
}
.portfolio-table-view .portfolio__filter-group {
  display: flex;
  align-items: center;
  gap: var(--iz-gap-tight);
  min-width: 0;
}
.portfolio-table-view .portfolio__org-select-group {
  display: flex;
  align-items: center;
  gap: 6px;
}
.portfolio-table-view .portfolio__org-icon {
  width: 18px;
  height: 18px;
  color: var(--iz-accent);
  flex: 0 0 18px;
}
.portfolio-table-view .portfolio__org-select-group .iz-select {
  min-width: 160px;
}
.portfolio-table-view .portfolio__segmented {
  display: flex;
  overflow: hidden;
  border: 1px solid var(--iz-border);
  border-radius: var(--iz-radius);
  background: var(--iz-surface);
}
.portfolio-table-view .portfolio__segmented--compact .portfolio__segment {
  padding: 5px 10px;
  font-size: var(--iz-fs-xs);
}
.portfolio-table-view .portfolio__segment {
  min-height: 0;
  padding: 7px 12px;
  border: 0;
  border-right: 1px solid var(--iz-border);
  border-radius: 0;
  background: transparent;
  color: var(--iz-text-secondary);
  font-size: var(--iz-fs-sm);
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}
.portfolio-table-view .portfolio__segment:last-child { border-right: 0; }
.portfolio-table-view .portfolio__segment--active {
  background: var(--iz-accent);
  color: var(--iz-accent-text);
}
.portfolio-table-view .portfolio__segment--icon {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 7px 9px;
}
.portfolio-table-view .portfolio__segment--icon svg {
  width: 14px;
  height: 14px;
}
.portfolio-table-view .portfolio__segment:focus-visible {
  outline: 2px solid var(--iz-accent);
  outline-offset: -2px;
}
.portfolio-table-view .portfolio__period-stepper {
  display: flex;
  align-items: center;
  overflow: hidden;
  border: 1px solid var(--iz-border);
  border-radius: var(--iz-radius);
  background: var(--iz-surface);
}
.portfolio-table-view .portfolio__period-display {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 7px 10px;
  border: 0;
  border-right: 1px solid var(--iz-border);
  background: transparent;
  color: var(--iz-text);
  font-size: var(--iz-fs-sm);
  font-weight: 600;
  white-space: nowrap;
  font-family: inherit;
}
.portfolio-table-view .portfolio__period-display svg {
  width: 15px;
  height: 15px;
  color: var(--iz-accent);
}
.portfolio-table-view .portfolio__period-display--clickable {
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
  position: relative;
}
.portfolio-table-view .portfolio__period-display--clickable:hover {
  background: var(--iz-surface-hover, rgba(0, 0, 0, 0.04));
  color: var(--iz-accent);
}
.portfolio-table-view .portfolio__period-display--clickable:focus-visible {
  outline: 2px solid var(--iz-accent);
  outline-offset: -2px;
}
.portfolio-table-view .portfolio__date-input-hidden {
  position: absolute;
  width: 0;
  height: 0;
  opacity: 0;
  pointer-events: none;
  border: 0;
  padding: 0;
  margin: 0;
}
.portfolio-table-view .portfolio__capacity {
  display: flex;
  align-items: center;
  gap: var(--iz-gap-tight);
  margin-left: auto;
  padding-left: var(--iz-gap);
  border-left: 1px solid var(--iz-border);
  color: var(--iz-text);
}
.portfolio-table-view .portfolio__capacity > svg {
  width: 22px;
  height: 22px;
  flex: 0 0 22px;
  color: var(--iz-accent);
}
.portfolio-table-view .portfolio__warnings {
  display: flex;
  flex-wrap: wrap;
  gap: var(--iz-gap-tight);
}
.portfolio-table-view .portfolio__weeks {
  display: grid;
  grid-template-columns: repeat(6, minmax(150px, 1fr));
  gap: var(--iz-gap-tight);
  overflow-x: auto;
}
.portfolio-table-view .portfolio-week {
  display: grid;
  gap: var(--iz-gap-tight);
  min-width: 150px;
}
.portfolio-table-view .portfolio__status-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--iz-gap-tight);
  min-height: 120px;
}

/* Keyboard focus for sortable headers and clickable rows */
.portfolio-table__th--sortable:focus-visible {
  outline: 2px solid var(--iz-accent);
  outline-offset: -2px;
}
.portfolio-table__row:focus-visible {
  outline: 2px solid var(--iz-accent);
  outline-offset: -2px;
  background: var(--iz-surface-subtle);
}
.portfolio-table-view__chip:focus-visible,
.portfolio-table-view__page-btn:focus-visible,
.portfolio-table-view__btn:focus-visible {
  outline: 2px solid var(--iz-accent);
  outline-offset: 1px;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

@media (max-width: 920px) {
  .portfolio-table-view .portfolio__capacity {
    width: 100%;
    margin-left: 0;
    padding: var(--iz-gap-tight) 0 0;
    border-left: 0;
    border-top: 1px solid var(--iz-border);
  }
}

@media (max-width: 900px) {
  .portfolio-table-view__filters-row {
    flex-direction: column;
    align-items: flex-start;
  }
  .portfolio-table-view__actions {
    width: 100%;
    margin-left: 0;
    flex-wrap: wrap;
  }
  .portfolio-table-view__search {
    width: 100%;
  }
}

@media (max-width: 720px) {
  .portfolio-table-view .portfolio__period-stepper {
    width: 100%;
    justify-content: space-between;
  }
  .portfolio-table-view .portfolio__period-display {
    flex: 1 1 auto;
    justify-content: center;
    font-size: var(--iz-fs-xs);
  }
  .portfolio-table-view .portfolio__segmented {
    width: 100%;
    overflow-x: auto;
  }
  .portfolio-table-view .portfolio__segment {
    flex: 1 0 auto;
    text-align: center;
  }
}
</style>
