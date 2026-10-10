<template>
  <div class="people-load">
    <div v-if="warnings.length" class="people-load__warnings" role="status">
      <p v-for="warning in warnings" :key="warning.uid" class="people-load__warning">
        <strong>{{ warning.displayName }}</strong> is overloaded in {{ warning.period }} with {{ warning.peakLoad }} projects: {{ warning.projects }}
      </p>
    </div>

    <div v-if="!people.length" class="people-load__empty iz-empty">{{ emptyText }}</div>
    <div v-else class="people-load__scroll">
      <table class="people-load__table">
        <caption class="people-load__caption">Running projects per person and week, at most {{ maxPerPerson }} at a time</caption>
        <thead>
          <tr>
            <th scope="col" class="people-load__person-col">Person</th>
            <th v-for="week in weeks" :key="week.label" scope="col">{{ shortLabel(week.label) }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="person in people" :key="person.uid">
            <th scope="row" class="people-load__person">
              <span class="people-load__name">{{ person.displayName }}</span>
              <small v-if="showTeams && person.teams.length">{{ teamNames(person) }}</small>
            </th>
            <td v-for="(cell, index) in person.weeks" :key="index">
              <button
                type="button"
                class="people-load__cell"
                :class="['people-load__cell--' + cell.state, { 'people-load__cell--selected': isSelected(person, index) }]"
                :aria-pressed="String(isSelected(person, index))"
                :aria-label="cellLabel(person, index)"
                :title="cellLabel(person, index)"
                @click="toggle(person, index)"
              >
                {{ cell.load }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="selectedCell" class="people-load__detail">
      <strong>{{ selectedCell.title }}</strong>
      <p v-if="!selectedCell.projects.length">No running projects.</p>
      <ul v-else>
        <li v-for="project in selectedCell.projects" :key="project.id">
          {{ project.name }}<small v-if="project.teamName"> · {{ project.teamName }}</small>
        </li>
      </ul>
    </div>

    <div v-if="people.length" class="people-load__legend" aria-hidden="true">
      <span><span class="people-load__swatch people-load__cell--free" />Room</span>
      <span><span class="people-load__swatch people-load__cell--full" />Full ({{ maxPerPerson }})</span>
      <span><span class="people-load__swatch people-load__cell--overloaded" />Overloaded (more than {{ maxPerPerson }})</span>
    </div>
  </div>
</template>

<script>
export default {
  name: "PeopleLoadGrid",
  props: {
    people: { type: Array, default: function () { return []; } },
    weeks: { type: Array, default: function () { return []; } },
    projects: { type: Array, default: function () { return []; } },
    overloadWarnings: { type: Array, default: function () { return []; } },
    maxPerPerson: { type: Number, default: 2 },
    showTeams: { type: Boolean, default: true },
    emptyText: { type: String, default: "No team members in this view." },
  },
  data: function () {
    return { selected: null };
  },
  computed: {
    projectsById: function () {
      var map = {};
      this.projects.forEach(function (project) { map[project.id] = project; });
      return map;
    },
    warnings: function () {
      return this.overloadWarnings.map(function (warning) {
        return {
          uid: warning.uid,
          displayName: warning.displayName,
          peakLoad: warning.peakLoad,
          period: this.periodText(warning.overWeeks),
          projects: this.projectList(warning.projectIds),
        };
      }, this);
    },
    selectedCell: function () {
      if (!this.selected) return null;
      var person = this.people.find(function (candidate) { return candidate.uid === this.selected.uid; }, this);
      var cell = person && person.weeks[this.selected.index];
      var week = this.weeks[this.selected.index];
      if (!cell || !week) return null;
      return {
        title: person.displayName + " · " + this.shortLabel(week.label) + ": " + this.loadText(cell.load),
        projects: cell.projectIds.map(function (id) { return this.projectsById[id] || { id: id, name: "Project " + id }; }, this),
      };
    },
  },
  watch: {
    people: function () { this.selected = null; },
  },
  methods: {
    shortLabel: function (label) {
      var parts = String(label || "").split("-");
      return parts.length > 1 ? parts[parts.length - 1] : label;
    },
    teamNames: function (person) {
      return person.teams.map(function (team) { return team.name; }).join(", ");
    },
    loadText: function (load) {
      return load === 1 ? "1 project" : load + " projects";
    },
    cellLabel: function (person, index) {
      var cell = person.weeks[index];
      var label = person.displayName + ", " + this.shortLabel(this.weeks[index] && this.weeks[index].label) + ": " + this.loadText(cell.load);
      if (cell.state === "overloaded") return label + ", overloaded";
      if (cell.state === "full") return label + ", full";
      return label;
    },
    isSelected: function (person, index) {
      return !!this.selected && this.selected.uid === person.uid && this.selected.index === index;
    },
    toggle: function (person, index) {
      this.selected = this.isSelected(person, index) ? null : { uid: person.uid, index: index };
    },
    projectList: function (projectIds) {
      return projectIds.map(function (id) {
        var project = this.projectsById[id];
        if (!project) return "Project " + id;
        return project.teamName ? project.name + " (" + project.teamName + ")" : project.name;
      }, this).join(", ");
    },
    // Consecutive overloaded weeks read as one period: "W41–W43, W45".
    periodText: function (labels) {
      var order = this.weeks.map(function (week) { return week.label; });
      var ranges = [];
      labels.forEach(function (label) {
        var index = order.indexOf(label);
        var last = ranges[ranges.length - 1];
        if (last && index !== -1 && index === last.end + 1) {
          last.end = index;
          last.to = label;
        } else {
          ranges.push({ end: index, from: label, to: label });
        }
      });
      return ranges.map(function (range) {
        var from = this.shortLabel(range.from);
        return range.from === range.to ? from : from + "–" + this.shortLabel(range.to);
      }, this).join(", ");
    },
  },
};
</script>

<style scoped>
.people-load { display: grid; gap: var(--iz-gap-tight); }
.people-load__warnings { display: grid; gap: 4px; }
.people-load__warning { margin: 0; padding: 8px 12px; border-radius: var(--iz-radius); background: var(--iz-danger-bg); color: var(--iz-danger-text); font-size: var(--iz-fs-sm); }
.people-load__empty { padding: var(--iz-gap-tight); }
.people-load__scroll { overflow-x: auto; }
.people-load__table { width: 100%; border-collapse: separate; border-spacing: 4px; font-size: var(--iz-fs-sm); }
.people-load__caption { padding-bottom: 4px; color: var(--iz-text-secondary); font-size: var(--iz-fs-xs); text-align: left; caption-side: top; }
.people-load__table thead th { color: var(--iz-text-secondary); font-size: var(--iz-fs-xs); font-weight: 600; text-align: center; }
.people-load__table thead th.people-load__person-col { text-align: left; }
.people-load__person { display: grid; min-width: 140px; padding-right: var(--iz-gap-tight); text-align: left; font-weight: 400; }
.people-load__name { color: var(--iz-text); font-weight: 600; }
.people-load__person small { color: var(--iz-text-muted); font-size: var(--iz-fs-xs); }
.people-load__table td { min-width: 52px; padding: 0; }
button.people-load__cell { width: 100%; min-height: 32px; margin: 0; padding: 4px; border: 1px solid transparent; border-radius: var(--iz-radius-sm); font: inherit; font-weight: 700; text-align: center; cursor: pointer; }
button.people-load__cell:focus-visible { outline: 2px solid var(--iz-accent); outline-offset: 1px; }
.people-load__cell--free { background: var(--iz-success-bg); color: var(--iz-success-text); }
.people-load__cell--full { background: var(--iz-warning-bg); color: var(--iz-warning-text); }
.people-load__cell--overloaded { background: var(--iz-danger-bg); color: var(--iz-danger-text); }
.people-load__cell--selected { border-color: var(--iz-text); }
.people-load__detail { padding: 8px 12px; border: 1px solid var(--iz-border); border-radius: var(--iz-radius); background: var(--iz-surface); font-size: var(--iz-fs-sm); }
.people-load__detail p, .people-load__detail ul { margin: 4px 0 0; }
.people-load__detail ul { padding-left: 18px; }
.people-load__detail small { color: var(--iz-text-muted); }
.people-load__legend { display: flex; flex-wrap: wrap; gap: var(--iz-gap); color: var(--iz-text-secondary); font-size: var(--iz-fs-xs); }
.people-load__legend > span { display: inline-flex; align-items: center; gap: 6px; }
.people-load__swatch { display: inline-block; width: 12px; height: 12px; border-radius: 3px; }
</style>
