<template>
  <div class="externals-panel">
    <div class="externals-panel__toolbar">
      <span v-if="seatsLine" class="externals-panel__count">{{ seatsLine }}</span>
      <button class="iz-btn iz-btn--sm" type="button" :disabled="loading" @click="load">
        {{ loading ? t("Refreshing…") : t("Refresh") }}
      </button>
    </div>

    <p class="iz-inset">
      {{ t("External collaborators are clients, grid operators and subcontractors with access to one or more projects only. Each takes one member seat. Invite them, change their end date or revoke them on the project's Members tab.") }}
    </p>

    <div v-if="error" class="iz-error" role="alert">
      {{ error }}
      <button class="iz-btn iz-btn--accent iz-btn--sm" type="button" @click="load">{{ t("Try again") }}</button>
    </div>
    <p v-else-if="loading && !loaded" class="iz-state">{{ t("Loading external collaborators…") }}</p>
    <div v-else-if="!externals.length" class="iz-empty">{{ t("No external collaborators.") }}</div>
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
        <span class="iz-badge" :class="status(external).tone">{{ status(external).label() }}</span>
      </li>
    </ul>

    <section v-if="loaded && !error && activity.length" class="externals-panel__activity">
      <span class="iz-section-title">{{ t("Activity") }}</span>
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
import { t, n, locale } from "../lib/l10n.mjs";

// Who outside the organization works on its projects. Read only: externals
// are invited, moved and revoked on a project's Members tab in the project app.

// Account status code → label (made at display time) and badge tone.
const STATUS = {
  invited: { label: () => t("Invited"), tone: "iz-badge--warning" },
  active: { label: () => t("Active"), tone: "iz-badge--success" },
  suspended: { label: () => t("Suspended"), tone: "iz-badge--danger" },
  disabled: { label: () => t("Disabled"), tone: "iz-badge--muted" },
};
const UNKNOWN_STATUS = { label: () => t("Unknown"), tone: "iz-badge--muted" };

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
      var seats = this.seats.max == null
        ? t("{used} seats used", { used: used })
        : t("{used} of {max} seats used", { used: used, max: this.seats.max });
      return n("{seats} · %n by external collaborator", "{seats} · %n by external collaborators", this.seats.externals, { seats: seats });
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
        this.error = e && e.message ? e.message : t("Couldn't load external collaborators.");
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
      return external.lastSeenAt
        ? t("last seen {datetime}", { datetime: this.formatDateTime(external.lastSeenAt) })
        : t("never signed in");
    },
    projectUrl: function (projectId) {
      return generateUrl("/apps/projectcreatoraio/new/projects/" + projectId + "/members");
    },
    projectState: function (project) {
      if (project.status === "pending") return t("invitation pending");
      return project.expiresAt ? t("until {date}", { date: this.formatDate(project.expiresAt) }) : t("active");
    },
    describe: function (entry) {
      // One whole sentence per action, with and without an actor, because
      // the word order changes around "by {actor}" in Dutch.
      var details = entry.details || {};
      var actor = entry.actorName || "";
      var vars = {
        name: entry.displayName,
        project: entry.projectName || t("the project"),
        actor: actor,
      };
      switch (entry.action) {
        case "invited":
          return actor
            ? t("{name} was invited to {project} by {actor}", vars)
            : t("{name} was invited to {project}", vars);
        case "invite_resent":
          return actor
            ? t("The invitation of {name} to {project} was sent again by {actor}", vars)
            : t("The invitation of {name} to {project} was sent again", vars);
        case "accepted":
          return t("{name} accepted the invitation to {project} and the terms", vars);
        case "end_date_changed":
          vars.date = details.to ? this.formatDate(details.to) : t("later");
          return actor
            ? t("Access of {name} to {project} now ends {date} by {actor}", vars)
            : t("Access of {name} to {project} now ends {date}", vars);
        case "roles_changed":
          return actor
            ? t("Roles of {name} on {project} changed by {actor}", vars)
            : t("Roles of {name} on {project} changed", vars);
        case "revoked":
          if (details.accepted) {
            return actor
              ? t("Access of {name} to {project} was revoked by {actor}", vars)
              : t("Access of {name} to {project} was revoked", vars);
          }
          return actor
            ? t("The invitation of {name} to {project} was cancelled by {actor}", vars)
            : t("The invitation of {name} to {project} was cancelled", vars);
        case "expired":
          return details.reason === "not_accepted"
            ? t("The invitation of {name} to {project} lapsed unaccepted", vars)
            : t("Access of {name} to {project} ended on its end date", vars);
        case "link_requested":
          return t("{name} asked for a new invitation link to {project}", vars);
        case "account_disabled":
          return t("The account of {name} was disabled after 30 days without access", vars);
        case "account_deleted":
          return t("The account of {name} was deleted", vars);
        default:
          return t("{name}: {action}", { name: entry.displayName, action: entry.action });
      }
    },
    formatDate: function (input) {
      if (!input) return "";
      var d = new Date(input);
      if (isNaN(d.getTime())) return "";
      return d.toLocaleDateString(locale(), { day: "2-digit", month: "short", year: "numeric" });
    },
    formatDateTime: function (input) {
      if (!input) return "";
      var d = new Date(input);
      if (isNaN(d.getTime())) return "";
      return d.toLocaleString(locale(), {
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
