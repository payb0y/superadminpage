<template>
  <div class="externals-panel">
    <div class="externals-panel__toolbar">
      <span v-if="seatsLine" class="externals-panel__count">{{ seatsLine }}</span>
      <button class="iz-btn iz-btn--sm" type="button" :disabled="loading" @click="load">
        {{ loading ? "Refreshing…" : "Refresh" }}
      </button>
    </div>

    <p class="iz-inset">
      External collaborators are clients, grid operators and subcontractors with access to one or more projects only.
      Each takes one member seat. Invite them, change their end date or revoke them on the project's Members tab.
    </p>

    <div v-if="error" class="iz-error" role="alert">
      {{ error }}
      <button class="iz-btn iz-btn--accent iz-btn--sm" type="button" @click="load">Try again</button>
    </div>
    <p v-else-if="loading && !loaded" class="iz-state">Loading external collaborators…</p>
    <div v-else-if="!externals.length" class="iz-empty">No external collaborators.</div>
    <ul v-else class="externals-panel__list">
      <li v-for="external in externals" :key="external.userId" class="iz-row iz-row--card externals-panel__row">
        <span class="iz-identity__avatar iz-identity__avatar--sm iz-identity__avatar--soft" aria-hidden="true">
          {{ initial(external) }}
        </span>
        <div class="iz-identity__body">
          <span class="iz-identity__name">
            {{ external.displayName || external.email }}<template v-if="external.company"> · {{ external.company }}</template>
          </span>
          <span class="iz-identity__meta">
            <template v-if="external.email">{{ external.email }} · </template>{{ lastSeen(external) }}
          </span>
          <span class="externals-panel__projects">
            <a
              v-for="project in external.projects"
              :key="project.projectId"
              class="iz-pill externals-panel__project"
              :href="projectUrl(project.projectId)"
            >{{ project.projectName }} · {{ projectState(project) }}</a>
          </span>
        </div>
        <span class="iz-badge" :class="status(external).tone">{{ status(external).label }}</span>
      </li>
    </ul>

    <section v-if="loaded && !error && activity.length" class="externals-panel__activity">
      <span class="iz-section-title">Activity</span>
      <ul class="externals-panel__log">
        <li v-for="entry in activity" :key="entry.id" class="externals-panel__entry">
          <time class="externals-panel__when" :datetime="entry.createdAt">{{ formatDateTime(entry.createdAt) }}</time>
          <span>{{ describe(entry) }}</span>
        </li>
      </ul>
    </section>
  </div>
</template>

<script>
import { generateUrl } from "@nextcloud/router";
import { listExternalActivity, listExternalCollaborators } from "../services/organizationApi.js";

// Who outside the organization works on its projects. Read only: externals
// are invited, moved and revoked on a project's Members tab in the project app.

const STATUS = {
  invited: { label: "Invited", tone: "iz-badge--warning" },
  active: { label: "Active", tone: "iz-badge--success" },
  suspended: { label: "Suspended", tone: "iz-badge--danger" },
  disabled: { label: "Disabled", tone: "iz-badge--muted" },
};
const UNKNOWN_STATUS = { label: "Unknown", tone: "iz-badge--muted" };

export default {
  name: "ExternalCollaboratorsPanel",
  props: {
    orgId: {
      type: Number,
      required: true,
    },
  },
  data: function () {
    return {
      externals: [],
      seats: null,
      activity: [],
      loading: false,
      loaded: false,
      error: null,
      // Drops answers that arrive after the panel moved to another org.
      requestToken: 0,
    };
  },
  computed: {
    seatsLine: function () {
      if (!this.seats) return "";
      var used = this.seats.used;
      var of = this.seats.max == null ? used + " seats used" : used + " of " + this.seats.max + " seats used";
      var n = this.seats.externals;
      return of + " · " + n + " by external collaborator" + (n === 1 ? "" : "s");
    },
  },
  watch: {
    orgId: function () {
      this.externals = [];
      this.seats = null;
      this.activity = [];
      this.loaded = false;
      this.load();
    },
  },
  mounted: function () {
    this.load();
  },
  methods: {
    load: async function () {
      var token = ++this.requestToken;
      this.loading = true;
      this.error = null;
      try {
        var results = await Promise.all([
          listExternalCollaborators(this.orgId),
          listExternalActivity(this.orgId),
        ]);
        if (token !== this.requestToken) return;
        this.externals = results[0].externals;
        this.seats = results[0].seats;
        this.activity = results[1];
        this.loaded = true;
        this.$emit("counted", this.externals.length);
      } catch (e) {
        if (token !== this.requestToken) return;
        this.error = e && e.message ? e.message : "Couldn't load external collaborators.";
      } finally {
        if (token === this.requestToken) this.loading = false;
      }
    },
    initial: function (external) {
      return (external.displayName || external.email || "?").charAt(0).toUpperCase();
    },
    status: function (external) {
      return STATUS[external.accountStatus] || UNKNOWN_STATUS;
    },
    lastSeen: function (external) {
      return external.lastSeenAt ? "last seen " + this.formatDateTime(external.lastSeenAt) : "never signed in";
    },
    projectUrl: function (projectId) {
      return generateUrl("/apps/projectcreatoraio/new/projects/" + projectId + "/members");
    },
    projectState: function (project) {
      if (project.status === "pending") return "invitation pending";
      return project.expiresAt ? "until " + this.formatDate(project.expiresAt) : "active";
    },
    describe: function (entry) {
      var who = entry.displayName;
      var by = entry.actorName ? " by " + entry.actorName : "";
      var on = entry.projectName ? " " + entry.projectName : " the project";
      var details = entry.details || {};
      switch (entry.action) {
        case "invited":
          return who + " was invited to" + on + by;
        case "invite_resent":
          return "The invitation of " + who + " to" + on + " was sent again" + by;
        case "accepted":
          return who + " accepted the invitation to" + on + " and the terms";
        case "end_date_changed":
          return "Access of " + who + " to" + on + " now ends " + (details.to ? this.formatDate(details.to) : "later") + by;
        case "roles_changed":
          return "Roles of " + who + " on" + on + " changed" + by;
        case "revoked":
          return details.accepted
            ? "Access of " + who + " to" + on + " was revoked" + by
            : "The invitation of " + who + " to" + on + " was cancelled" + by;
        case "expired":
          return details.reason === "not_accepted"
            ? "The invitation of " + who + " to" + on + " lapsed unaccepted"
            : "Access of " + who + " to" + on + " ended on its end date";
        case "link_requested":
          return who + " asked for a new invitation link to" + on;
        case "account_disabled":
          return "The account of " + who + " was disabled after 30 days without access";
        case "account_deleted":
          return "The account of " + who + " was deleted";
        default:
          return who + ": " + entry.action;
      }
    },
    formatDate: function (input) {
      if (!input) return "";
      var d = new Date(input);
      if (isNaN(d.getTime())) return "";
      return d.toLocaleDateString(undefined, { day: "2-digit", month: "short", year: "numeric" });
    },
    formatDateTime: function (input) {
      if (!input) return "";
      var d = new Date(input);
      if (isNaN(d.getTime())) return "";
      return d.toLocaleString(undefined, {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    },
  },
};
</script>

<style scoped>
/* Layout only — chrome comes from the theme's .iz-* primitives. */
.externals-panel {
  display: flex;
  flex-direction: column;
  gap: var(--iz-gap);
}

.externals-panel__toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.externals-panel__count {
  font-size: var(--iz-fs-sm);
  color: var(--iz-text-secondary);
  margin-right: auto;
}

.externals-panel__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.externals-panel__projects {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 6px;
}

.externals-panel__project {
  text-decoration: none;
}

.externals-panel__activity {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 8px;
}

.externals-panel__log {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: var(--iz-fs-sm);
}

.externals-panel__entry {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.externals-panel__when {
  color: var(--iz-text-secondary);
  min-width: 130px;
}
</style>
