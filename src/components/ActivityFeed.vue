<template>
  <component
    :is="embedded ? 'div' : 'section'"
    :class="['activity-feed', { 'activity-feed--embedded': embedded }]"
  >
    <header v-if="!embedded" class="activity-feed__header">
      <h3 class="activity-feed__title">Activity</h3>
    </header>

    <div v-if="projectId" class="activity-feed__streams">
      <button
        v-for="s in streamOptions"
        :key="s.key"
        class="activity-feed__stream"
        :class="{ 'activity-feed__stream--active': stream === s.key }"
        @click="setStream(s.key)"
      >
        {{ s.label }}
      </button>
    </div>

    <div class="activity-feed__filters">
      <button
        class="activity-feed__pill"
        :class="{ 'activity-feed__pill--active': activeSources.length === 0 }"
        @click="clearSources"
      >
        All
      </button>
      <button
        v-for="src in availableSources"
        :key="src.key"
        class="activity-feed__pill"
        :class="[
          'activity-feed__pill--' + src.key,
          { 'activity-feed__pill--active': activeSources.includes(src.key) },
        ]"
        @click="toggleSource(src.key)"
      >
        {{ src.label }}
      </button>
    </div>

    <div v-if="loading && rows.length === 0" class="activity-feed__state">
      Loading…
    </div>
    <div v-else-if="error" class="activity-feed__state activity-feed__state--error">
      {{ error }}
    </div>
    <div v-else-if="rows.length === 0" class="activity-feed__state">
      No activity in this view.
    </div>

    <ul v-else class="activity-feed__list">
      <li v-for="row in rows" :key="rowKey(row)" class="activity-feed__row">
        <span
          class="activity-feed__icon"
          :class="'activity-feed__icon--' + row.source"
        ></span>
        <div class="activity-feed__row-body">
          <div class="activity-feed__row-summary">{{ row.summary }}</div>
          <div class="activity-feed__row-meta">
            <span class="activity-feed__row-source">{{ sourceLabel(row.source) }}</span>
            <span class="activity-feed__row-dot">·</span>
            <span class="activity-feed__row-time">{{ formatTime(row.ts) }}</span>
            <template v-if="row.actor_uid">
              <span class="activity-feed__row-dot">·</span>
              <span class="activity-feed__row-actor">{{ row.actor_uid }}</span>
            </template>
          </div>
        </div>
      </li>
    </ul>

    <div v-if="nextCursor !== null" class="activity-feed__load-more">
      <button
        class="activity-feed__load-button"
        :disabled="loading"
        @click="loadMore"
      >
        {{ loading ? "Loading…" : "Load more" }}
      </button>
    </div>
  </component>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";

const SOURCES = [
  { key: "deck", label: "Tasks" },
  { key: "files", label: "Files" },
  { key: "talk", label: "Calls" },
  { key: "calendar", label: "Calendar" },
  { key: "subscription", label: "Subscription" },
  { key: "backup", label: "Backups" },
  { key: "aho", label: "Hand-offs" },
  { key: "member", label: "Members" },
  { key: "project", label: "Projects" },
  { key: "share", label: "Shares" },
  { key: "auth", label: "Sessions" },
];

// Sources that *can* anchor to a project — used to trim the filter row in
// "In this project" mode so we don't offer pills that will never produce rows.
const PROJECT_ANCHORED = ["deck", "talk", "files", "project"];

export default {
  name: "ActivityFeed",
  props: {
    orgId: { type: Number, required: true },
    projectId: { type: Number, default: null },
    embedded: { type: Boolean, default: false },
  },
  data() {
    return {
      rows: [],
      nextCursor: null,
      loading: false,
      error: null,
      activeSources: [],
      stream: "in_project",
    };
  },
  computed: {
    streamOptions() {
      return [
        { key: "in_project", label: "In this project" },
        { key: "org_wide", label: "Org-wide" },
      ];
    },
    availableSources() {
      if (!this.projectId) return SOURCES;
      if (this.stream === "in_project") {
        return SOURCES.filter((s) => PROJECT_ANCHORED.includes(s.key));
      }
      return SOURCES.filter((s) => !PROJECT_ANCHORED.includes(s.key));
    },
  },
  watch: {
    activeSources() {
      this.refetch();
    },
    stream() {
      this.activeSources = [];
      this.refetch();
    },
    orgId() {
      this.refetch();
    },
    projectId() {
      this.refetch();
    },
  },
  mounted() {
    this.refetch();
  },
  methods: {
    sourceLabel(key) {
      const s = SOURCES.find((x) => x.key === key);
      return s ? s.label : key;
    },
    rowKey(row) {
      return [row.source, row.ts, row.target_id, row.action].join("|");
    },
    toggleSource(key) {
      const i = this.activeSources.indexOf(key);
      if (i === -1) this.activeSources = [...this.activeSources, key];
      else this.activeSources = this.activeSources.filter((s) => s !== key);
    },
    clearSources() {
      this.activeSources = [];
    },
    setStream(key) {
      this.stream = key;
    },
    async refetch() {
      this.rows = [];
      this.nextCursor = null;
      await this.fetchPage();
    },
    async loadMore() {
      if (this.nextCursor === null) return;
      await this.fetchPage(this.nextCursor);
    },
    async fetchPage(since = null) {
      this.loading = true;
      this.error = null;
      try {
        const res = await axios.get(this.endpoint(), {
          params: this.queryParams(since),
        });
        const { rows = [], nextCursor = null } = res.data || {};
        this.rows = since === null ? rows : [...this.rows, ...rows];
        this.nextCursor = nextCursor;
      } catch (e) {
        this.error = "Failed to load activity.";
      } finally {
        this.loading = false;
      }
    },
    endpoint() {
      if (this.projectId) {
        return generateUrl(
          "/apps/superadminpage/api/super/orgs/" +
            this.orgId +
            "/projects/" +
            this.projectId +
            "/activity"
        );
      }
      return generateUrl(
        "/apps/superadminpage/api/super/orgs/" + this.orgId + "/activity"
      );
    },
    queryParams(since) {
      const p = { limit: 50 };
      if (this.activeSources.length > 0) p.sources = this.activeSources.join(",");
      if (since !== null) p.since = since;
      if (this.projectId) p.stream = this.stream;
      return p;
    },
    formatTime(ts) {
      if (!ts) return "";
      const now = Date.now() / 1000;
      const diff = now - ts;
      if (diff < 60) return "just now";
      if (diff < 3600) return Math.floor(diff / 60) + "m ago";
      if (diff < 86400) return Math.floor(diff / 3600) + "h ago";
      if (diff < 604800) return Math.floor(diff / 86400) + "d ago";
      return new Date(ts * 1000).toLocaleDateString();
    },
  },
};
</script>

<style scoped>
.activity-feed {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  padding: 20px;
}

.activity-feed--embedded {
  background: transparent;
  box-shadow: none;
  padding: 0;
}

.activity-feed__header {
  margin-bottom: 16px;
}

.activity-feed__title {
  font-size: 15px;
  font-weight: 600;
  color: #1d2939;
  margin: 0;
}

.activity-feed__streams {
  display: flex;
  gap: 4px;
  margin-bottom: 12px;
  border-bottom: 1px solid #eaecf0;
}

.activity-feed__stream {
  background: transparent;
  border: 0;
  padding: 8px 14px;
  font-size: 13px;
  font-weight: 500;
  color: #667085;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
}

.activity-feed__stream--active {
  color: #4a90d9;
  border-bottom-color: #4a90d9;
}

.activity-feed__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 16px;
}

.activity-feed__pill {
  background: #f2f4f7;
  border: 1px solid transparent;
  border-radius: 999px;
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 500;
  color: #475467;
  cursor: pointer;
  line-height: 1.4;
}

.activity-feed__pill:hover {
  background: #eaecf0;
}

.activity-feed__pill--active {
  background: #e8f0fa;
  border-color: #4a90d9;
  color: #1f5e9c;
}

.activity-feed__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.activity-feed__row {
  display: flex;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid #f2f4f7;
}

.activity-feed__row:last-child {
  border-bottom: 0;
}

.activity-feed__icon {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-top: 7px;
  flex-shrink: 0;
  background: #98a2b3;
}

.activity-feed__icon--deck { background: #4a90d9; }
.activity-feed__icon--files { background: #6c8eff; }
.activity-feed__icon--talk { background: #06b6d4; }
.activity-feed__icon--calendar { background: #8b5cf6; }
.activity-feed__icon--subscription { background: #f59e0b; }
.activity-feed__icon--backup { background: #10b981; }
.activity-feed__icon--aho { background: #14b8a6; }
.activity-feed__icon--member { background: #ec4899; }
.activity-feed__icon--project { background: #6366f1; }
.activity-feed__icon--share { background: #ef4444; }
.activity-feed__icon--auth { background: #94a3b8; }

.activity-feed__row-body {
  flex: 1;
  min-width: 0;
}

.activity-feed__row-summary {
  font-size: 13px;
  color: #1d2939;
  line-height: 1.4;
  word-break: break-word;
}

.activity-feed__row-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 2px;
  font-size: 11px;
  color: #667085;
}

.activity-feed__row-dot {
  color: #d0d5dd;
}

.activity-feed__row-source {
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.activity-feed__state {
  text-align: center;
  padding: 24px;
  font-size: 13px;
  color: #667085;
}

.activity-feed__state--error {
  color: #b42318;
}

.activity-feed__load-more {
  text-align: center;
  margin-top: 12px;
}

.activity-feed__load-button {
  background: #fff;
  border: 1px solid #d0d5dd;
  border-radius: 8px;
  padding: 6px 16px;
  font-size: 12px;
  font-weight: 500;
  color: #344054;
  cursor: pointer;
}

.activity-feed__load-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.activity-feed__load-button:hover:not(:disabled) {
  background: #f9fafb;
}
</style>
