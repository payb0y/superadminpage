<template>
  <component
    :is="embedded ? 'div' : 'section'"
    :class="['activity-feed', { 'activity-feed--embedded': embedded }]"
  >
    <header v-if="!embedded" class="activity-feed__header">
      <h3 class="activity-feed__title">Activity</h3>
    </header>

    <div class="activity-feed__layout">
      <aside class="activity-feed__rail">
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

        <ul class="activity-feed__rail-list">
          <li>
            <button
              class="activity-feed__rail-item"
              :class="{ 'activity-feed__rail-item--active': selectedSource === null }"
              @click="selectSource(null)"
            >
              <span class="activity-feed__rail-icon activity-feed__rail-icon--all"></span>
              <span class="activity-feed__rail-label">All activity</span>
            </button>
          </li>
          <li v-for="src in railSources" :key="src.key">
            <button
              class="activity-feed__rail-item"
              :class="{ 'activity-feed__rail-item--active': selectedSource === src.key }"
              @click="selectSource(src.key)"
            >
              <span
                class="activity-feed__rail-icon"
                :class="'activity-feed__rail-icon--' + src.key"
              ></span>
              <span class="activity-feed__rail-label">{{ src.label }}</span>
            </button>
          </li>
        </ul>
      </aside>

      <div class="activity-feed__main">
        <div class="activity-feed__toolbar">
          <label class="activity-feed__field">
            <span class="activity-feed__field-label">From</span>
            <input
              type="date"
              class="activity-feed__input"
              :value="filterFrom"
              @change="onDateChange('filterFrom', $event)"
            />
          </label>
          <label class="activity-feed__field">
            <span class="activity-feed__field-label">To</span>
            <input
              type="date"
              class="activity-feed__input"
              :value="filterTo"
              @change="onDateChange('filterTo', $event)"
            />
          </label>
          <label class="activity-feed__field">
            <span class="activity-feed__field-label">Actor</span>
            <select
              class="activity-feed__input"
              :value="filterActor"
              @change="onActorChange($event)"
            >
              <option value="">All actors</option>
              <option
                v-for="m in actorOptions"
                :key="m.uid"
                :value="m.uid"
              >
                {{ m.label }}
              </option>
            </select>
          </label>
          <label class="activity-feed__field activity-feed__field--grow">
            <span class="activity-feed__field-label">Search</span>
            <input
              type="search"
              class="activity-feed__input"
              placeholder="match summary or actor"
              :value="filterQ"
              @input="onTextInput('filterQ', $event)"
            />
          </label>
          <button
            v-if="hasActiveFilters"
            class="activity-feed__clear"
            @click="clearFilters"
          >
            Clear filters
          </button>
        </div>

        <div v-if="loading && rows.length === 0" class="activity-feed__state">
          Loading…
        </div>
        <div v-else-if="error" class="activity-feed__state activity-feed__state--error">
          {{ error }}
        </div>
        <div v-else-if="rows.length === 0" class="activity-feed__state">
          No activity matches the current filters.
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

        <nav
          v-if="rows.length > 0 || currentPage > 1"
          class="activity-feed__pagination"
          aria-label="Pagination"
        >
          <button
            class="activity-feed__page-btn"
            :disabled="loading || currentPage === 1"
            @click="setPage(currentPage - 1)"
          >
            ‹ Prev
          </button>

          <template v-for="(b, i) in pageButtons">
            <button
              v-if="b.type === 'page'"
              :key="'p' + i"
              class="activity-feed__page-btn"
              :class="{ 'activity-feed__page-btn--active': b.value === currentPage }"
              :disabled="loading"
              @click="setPage(b.value)"
            >
              {{ b.value }}
            </button>
            <span
              v-else
              :key="'e' + i"
              class="activity-feed__page-ellipsis"
            >…</span>
          </template>

          <button
            class="activity-feed__page-btn"
            :disabled="loading || !hasNext"
            @click="setPage(currentPage + 1)"
          >
            Next ›
          </button>
        </nav>
      </div>
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

const PROJECT_ANCHORED = ["deck", "talk", "files", "project"];

function dateToUnixStart(yyyymmdd) {
  if (!yyyymmdd) return null;
  const t = new Date(yyyymmdd + "T00:00:00").getTime();
  return Number.isFinite(t) ? Math.floor(t / 1000) : null;
}

function dateToUnixEnd(yyyymmdd) {
  if (!yyyymmdd) return null;
  const t = new Date(yyyymmdd + "T23:59:59").getTime();
  return Number.isFinite(t) ? Math.floor(t / 1000) : null;
}

export default {
  name: "ActivityFeed",
  props: {
    orgId: { type: Number, required: true },
    projectId: { type: Number, default: null },
    embedded: { type: Boolean, default: false },
    members: { type: Array, default: () => [] },
  },
  data() {
    return {
      rows: [],
      currentPage: 1,
      pageSize: 50,
      hasNext: false,
      loading: false,
      error: null,
      selectedSource: null,
      stream: "in_project",
      filterFrom: "",
      filterTo: "",
      filterActor: "",
      filterQ: "",
    };
  },
  computed: {
    streamOptions() {
      return [
        { key: "in_project", label: "In this project" },
        { key: "org_wide", label: "Org-wide" },
      ];
    },
    railSources() {
      if (!this.projectId) return SOURCES;
      if (this.stream === "in_project") {
        return SOURCES.filter((s) => PROJECT_ANCHORED.includes(s.key));
      }
      return SOURCES.filter((s) => !PROJECT_ANCHORED.includes(s.key));
    },
    hasActiveFilters() {
      return Boolean(this.filterFrom || this.filterTo || this.filterActor || this.filterQ);
    },
    actorOptions() {
      // Normalize members coming from OrgOverviewService into {uid, label} sorted.
      const seen = new Set();
      const out = [];
      for (const m of this.members) {
        const uid = m.user_uid || m.uid;
        if (!uid || seen.has(uid)) continue;
        seen.add(uid);
        out.push({ uid, label: m.displayName || uid });
      }
      out.sort((a, b) => a.label.localeCompare(b.label));
      return out;
    },
    pageButtons() {
      // Numbered pagination without a known total: always offer page 1 + a
      // small window around the current page + a probe for the next page when
      // hasNext. Ellipsis hides the gap between page 1 and the window.
      const c = this.currentPage;
      const window = [];
      const start = Math.max(2, c - 1);
      const end = c + (this.hasNext ? 1 : 0);
      for (let p = start; p <= end; p++) window.push(p);

      const buttons = [{ type: "page", value: 1 }];
      if (window.length > 0 && window[0] > 2) {
        buttons.push({ type: "ellipsis" });
      }
      for (const p of window) {
        buttons.push({ type: "page", value: p });
      }
      // Dedupe (handles current=1 etc.)
      const seen = new Set();
      return buttons.filter((b) => {
        if (b.type !== "page") return true;
        if (seen.has(b.value)) return false;
        seen.add(b.value);
        return true;
      });
    },
  },
  watch: {
    selectedSource() { this.resetAndFetch(); },
    stream() {
      this.selectedSource = null;
      this.resetAndFetch();
    },
    orgId() { this.resetAndFetch(); },
    projectId() { this.resetAndFetch(); },
    filterFrom() { this.resetAndFetch(); },
    filterTo() { this.resetAndFetch(); },
  },
  created() {
    this._textDebounce = null;
  },
  mounted() {
    this.resetAndFetch();
  },
  beforeDestroy() {
    if (this._textDebounce) clearTimeout(this._textDebounce);
  },
  methods: {
    sourceLabel(key) {
      const s = SOURCES.find((x) => x.key === key);
      return s ? s.label : key;
    },
    rowKey(row) {
      return [row.source, row.ts, row.target_id, row.action].join("|");
    },
    selectSource(key) {
      this.selectedSource = key;
    },
    setStream(key) {
      this.stream = key;
    },
    clearFilters() {
      this.filterFrom = "";
      this.filterTo = "";
      this.filterActor = "";
      this.filterQ = "";
    },
    onDateChange(field, ev) {
      this[field] = ev.target.value;
    },
    onActorChange(ev) {
      this.filterActor = ev.target.value;
      this.resetAndFetch();
    },
    onTextInput(field, ev) {
      this[field] = ev.target.value;
      if (this._textDebounce) clearTimeout(this._textDebounce);
      this._textDebounce = setTimeout(() => this.resetAndFetch(), 400);
    },
    setPage(page) {
      const target = Math.max(1, page);
      if (target === this.currentPage) return;
      this.currentPage = target;
      this.fetchPage();
    },
    resetAndFetch() {
      this.currentPage = 1;
      this.fetchPage();
    },
    async fetchPage() {
      this.loading = true;
      this.error = null;
      try {
        const res = await axios.get(this.endpoint(), {
          params: this.queryParams(),
        });
        const { rows = [], hasNext = false } = res.data || {};
        this.rows = rows;
        this.hasNext = hasNext;
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
    queryParams() {
      const p = { page: this.currentPage, size: this.pageSize };
      if (this.selectedSource) p.sources = this.selectedSource;
      if (this.projectId) p.stream = this.stream;

      const fromTs = dateToUnixStart(this.filterFrom);
      const toTs = dateToUnixEnd(this.filterTo);
      if (fromTs !== null) p.from = fromTs;
      if (toTs !== null) p.to = toTs;
      if (this.filterActor) p.actor = this.filterActor.trim();
      if (this.filterQ) p.q = this.filterQ.trim();

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

.activity-feed__layout {
  display: flex;
  gap: 20px;
  align-items: flex-start;
}

.activity-feed__rail {
  flex: 0 0 200px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.activity-feed__streams {
  display: flex;
  background: #f2f4f7;
  border-radius: 8px;
  padding: 2px;
  gap: 2px;
}

.activity-feed__stream {
  flex: 1;
  background: transparent;
  border: 0;
  padding: 6px 8px;
  font-size: 12px;
  font-weight: 500;
  color: #475467;
  cursor: pointer;
  border-radius: 6px;
}

.activity-feed__stream--active {
  background: #fff;
  color: #1f5e9c;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}

.activity-feed__rail-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.activity-feed__rail-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  background: transparent;
  border: 0;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 13px;
  color: #344054;
  cursor: pointer;
  text-align: left;
  font-weight: 500;
}

.activity-feed__rail-item:hover {
  background: #f2f4f7;
}

.activity-feed__rail-item--active {
  background: #e8f0fa;
  color: #1f5e9c;
}

.activity-feed__rail-icon {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
  background: #98a2b3;
}

.activity-feed__rail-icon--all { background: #1d2939; }
.activity-feed__rail-icon--deck { background: #4a90d9; }
.activity-feed__rail-icon--files { background: #6c8eff; }
.activity-feed__rail-icon--talk { background: #06b6d4; }
.activity-feed__rail-icon--calendar { background: #8b5cf6; }
.activity-feed__rail-icon--subscription { background: #f59e0b; }
.activity-feed__rail-icon--backup { background: #10b981; }
.activity-feed__rail-icon--aho { background: #14b8a6; }
.activity-feed__rail-icon--member { background: #ec4899; }
.activity-feed__rail-icon--project { background: #6366f1; }
.activity-feed__rail-icon--share { background: #ef4444; }
.activity-feed__rail-icon--auth { background: #94a3b8; }

.activity-feed__rail-label {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.activity-feed__main {
  flex: 1;
  min-width: 0;
}

.activity-feed__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: flex-end;
  margin-bottom: 16px;
  padding-bottom: 14px;
  border-bottom: 1px solid #eaecf0;
}

.activity-feed__field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.activity-feed__field--grow {
  flex: 1;
  min-width: 160px;
}

.activity-feed__field-label {
  font-size: 11px;
  font-weight: 500;
  color: #667085;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.activity-feed__input {
  background: #fff;
  border: 1px solid #d0d5dd;
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 13px;
  color: #1d2939;
  min-width: 0;
  width: 100%;
}

.activity-feed__input:focus {
  outline: none;
  border-color: #4a90d9;
  box-shadow: 0 0 0 2px rgba(74, 144, 217, 0.15);
}

.activity-feed__clear {
  background: #fff;
  border: 1px solid #d0d5dd;
  border-radius: 8px;
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 500;
  color: #344054;
  cursor: pointer;
  height: fit-content;
}

.activity-feed__clear:hover {
  background: #f9fafb;
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

.activity-feed__pagination {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  align-items: center;
  justify-content: center;
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #f2f4f7;
}

.activity-feed__page-btn {
  background: #fff;
  border: 1px solid #d0d5dd;
  border-radius: 6px;
  padding: 5px 10px;
  font-size: 12px;
  font-weight: 500;
  color: #344054;
  cursor: pointer;
  min-width: 32px;
  text-align: center;
}

.activity-feed__page-btn:hover:not(:disabled):not(.activity-feed__page-btn--active) {
  background: #f9fafb;
}

.activity-feed__page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.activity-feed__page-btn--active {
  background: #4a90d9;
  border-color: #4a90d9;
  color: #fff;
  cursor: default;
}

.activity-feed__page-ellipsis {
  padding: 0 4px;
  font-size: 12px;
  color: #98a2b3;
  user-select: none;
}

@media (max-width: 720px) {
  .activity-feed__layout {
    flex-direction: column;
  }
  .activity-feed__rail {
    flex: 0 0 auto;
    width: 100%;
  }
  .activity-feed__rail-list {
    flex-direction: row;
    flex-wrap: wrap;
  }
  .activity-feed__rail-item {
    width: auto;
  }
}
</style>
