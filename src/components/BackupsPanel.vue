<template>
  <div :class="['backups-panel', 'iz-panel', { 'iz-panel--flush': embedded }]">
    <div class="backups-panel__toolbar">
      <input
        v-model.trim="filters.q"
        class="iz-input backups-panel__search"
        type="search"
        placeholder="Search archive or job ID"
        aria-label="Search backup jobs"
        @keyup.enter="applyFilters"
      />
      <button
        type="button"
        class="iz-btn"
        :disabled="loading"
        @click="applyFilters"
      >Refresh</button>
      <span class="backups-panel__toolbar-spacer"></span>
      <select
        v-model="createType"
        class="iz-select backups-panel__type-select"
        aria-label="Backup type to create"
      >
        <option value="full">Full backup</option>
        <option value="incremental">Incremental backup</option>
      </select>
      <button
        type="button"
        class="iz-btn iz-btn--primary"
        :disabled="busy"
        @click="askPassword({ type: 'create' })"
      >New backup</button>
    </div>

    <div class="backups-panel__filters">
      <div class="backups-panel__filter-group">
        <span
          v-for="s in statusOptions"
          :key="'sf-' + s.value"
          class="iz-chip"
          :class="[chipTone(s.value), { 'iz-chip--active': filters.status === s.value }]"
          @click="setFilter('status', s.value)"
        >{{ s.label }}</span>
      </div>

      <div class="backups-panel__filter-group">
        <span
          v-for="t in typeOptions"
          :key="'tf-' + t.value"
          class="iz-chip"
          :class="[chipTone(t.value), { 'iz-chip--active': filters.backupType === t.value }]"
          @click="setFilter('backupType', t.value)"
        >{{ t.label }}</span>
      </div>

      <div class="backups-panel__filter-group">
        <span
          v-for="tr in triggerOptions"
          :key="'trf-' + tr.value"
          class="iz-chip"
          :class="[chipTone(tr.value), { 'iz-chip--active': filters.triggerSource === tr.value }]"
          @click="setFilter('triggerSource', tr.value)"
        >{{ tr.label }}</span>
      </div>
    </div>

    <div v-if="error" class="iz-error" role="alert">{{ error }}</div>
    <div v-if="actionError" class="iz-error" role="alert">{{ actionError }}</div>

    <p v-if="loading" class="iz-state">Loading backup jobs…</p>
    <div v-else-if="!jobs.length" class="iz-empty">{{ emptyMessage }}</div>

    <div v-else class="backups-panel__rows">
      <article
        v-for="row in jobs"
        :key="row.jobId"
        class="iz-row iz-row--card iz-row--expandable"
        :class="{ 'iz-row--expanded': openJobId === row.jobId }"
      >
        <div
          class="iz-row__header"
          role="button"
          tabindex="0"
          :aria-expanded="openJobId === row.jobId"
          @click="toggleJob(row)"
          @keydown.enter.prevent="toggleJob(row)"
          @keydown.space.prevent="toggleJob(row)"
        >
          <div class="backups-panel__identity">
            <strong>Backup #{{ row.jobId }}</strong>
            <span class="backups-panel__meta">
              {{ typeLabel(row.backupType) }} · {{ triggerLabel(row.triggerSource) }} · {{ formatDate(row.createdAt) }}
            </span>
          </div>

          <div v-if="isActive(row.status)" class="backups-panel__progress">
            <div class="iz-meter iz-meter--thin iz-meter--indeterminate">
              <div class="iz-meter__fill iz-meter__fill--accent"></div>
            </div>
          </div>

          <div class="iz-row__actions">
            <span
              v-if="expiryNote(row)"
              class="iz-badge"
              :class="expiryTone(row)"
            >{{ expiryNote(row) }}</span>
            <span v-if="row.artifactSize" class="backups-panel__size">{{ formatSize(row.artifactSize) }}</span>
            <span class="iz-pill" :class="statusTone(row.status)">
              <span class="iz-dot" aria-hidden="true"></span>
              {{ statusLabel(row.status) }}
            </span>
            <button
              v-if="canRollback(row)"
              type="button"
              class="iz-btn iz-btn--sm"
              :disabled="busy"
              @click.stop="askPassword({ type: 'dryRun', row: row })"
            >Dry-run rollback</button>
            <button
              v-if="row.status === 'completed'"
              type="button"
              class="iz-btn iz-btn--primary iz-btn--sm"
              :disabled="!!downloadBlockedReason(row) || busy"
              :title="downloadBlockedReason(row) || 'Download archive'"
              @click.stop="askPassword({ type: 'download', row: row })"
            >Download</button>
            <button
              type="button"
              class="iz-btn iz-btn--icon iz-btn--sm"
              :disabled="busy"
              :aria-label="'Delete backup #' + row.jobId"
              @click.stop="askDestructive({ type: 'delete', row: row })"
            >&times;</button>
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="iz-row__chevron"
              :class="{ 'iz-row__chevron--open': openJobId === row.jobId }"
              aria-hidden="true"
            >
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </div>
        </div>

        <div v-if="openJobId === row.jobId" class="iz-row__detail backups-panel__detail">
          <p v-if="detailLoading && !detail" class="iz-state">Loading job detail…</p>
          <div v-if="detailError" class="iz-error" role="alert">{{ detailError }}</div>

          <template v-if="detail">
            <div v-if="detail.errorMessage" class="iz-error" role="alert">{{ detail.errorMessage }}</div>

            <dl class="backups-panel__facts">
              <div>
                <dt class="iz-label">Requested by</dt>
                <dd>{{ detail.requestedByUid === '__system__' ? 'Scheduler' : detail.requestedByUid }}</dd>
              </div>
              <div>
                <dt class="iz-label">Started</dt>
                <dd>{{ formatDate(detail.startedAt) }}</dd>
              </div>
              <div>
                <dt class="iz-label">Finished</dt>
                <dd>{{ formatDate(detail.finishedAt) }}</dd>
              </div>
              <div>
                <dt class="iz-label">Duration</dt>
                <dd>{{ formatDuration(detail.startedAt, detail.finishedAt) }}</dd>
              </div>
              <div>
                <dt class="iz-label">Expires</dt>
                <dd :class="expiryClass(detail)">{{ formatDate(detail.expiresAt) }}</dd>
              </div>
              <div>
                <dt class="iz-label">Archive</dt>
                <dd>
                  <span v-if="detail.artifactName" class="backups-panel__archive">{{ detail.artifactName }}</span>
                  <template v-else>Not available</template>
                  <template v-if="detail.artifactSize"> · {{ formatSize(detail.artifactSize) }}</template>
                </dd>
              </div>
            </dl>

            <section v-if="summaryCounts.length" class="backups-panel__section">
              <span class="iz-section-title">Contents</span>
              <div class="backups-panel__chips">
                <span
                  v-for="entry in summaryCounts"
                  :key="entry[0]"
                  class="iz-badge iz-badge--muted"
                >{{ humanKey(entry[0]) }}: {{ entry[1] }}</span>
              </div>
            </section>

            <section v-if="detail.steps && detail.steps.length" class="backups-panel__section">
              <span class="iz-section-title">Steps</span>
              <div class="backups-panel__timeline">
                <div
                  v-for="step in detail.steps"
                  :key="step.id"
                  class="backups-panel__timeline-row"
                >
                  <span class="iz-pill" :class="statusTone(step.status)">
                    <span class="iz-dot" aria-hidden="true"></span>
                    {{ statusLabel(step.status) }}
                  </span>
                  <strong>{{ humanKey(step.stepKey) }}</strong>
                  <span class="backups-panel__meta">{{ step.errorMessage || formatDate(step.finishedAt || step.startedAt) }}</span>
                </div>
              </div>
            </section>

            <section class="backups-panel__section">
              <span class="iz-section-title">Activity log</span>
              <div v-if="!events.length" class="iz-empty">No events recorded.</div>
              <div v-else class="backups-panel__timeline">
                <div
                  v-for="event in events"
                  :key="event.id"
                  class="backups-panel__timeline-row"
                >
                  <span class="iz-badge" :class="eventTone(event.level)">{{ event.level }}</span>
                  <strong>{{ event.message }}</strong>
                  <span class="backups-panel__meta">{{ formatDate(event.createdAt) }}</span>
                </div>
              </div>
            </section>
          </template>
        </div>
      </article>
    </div>

    <div v-if="jobs.length" class="backups-panel__paging">
      <span class="backups-panel__paging-total">{{ total }} {{ total === 1 ? 'job' : 'jobs' }}</span>
      <button
        type="button"
        class="iz-btn iz-btn--sm"
        :disabled="offset === 0 || loading"
        @click="previousPage"
      >Previous</button>
      <button
        type="button"
        class="iz-btn iz-btn--sm"
        :disabled="!hasMore || loading"
        @click="nextPage"
      >Next</button>
    </div>

    <!-- Rollbacks appear once this organization has any: starting a dry run
         from a row above brings the section into view on the next refresh. -->
    <section v-if="rollbacks.length" class="backups-panel__section">
      <h4 class="iz-panel__title">Rollback jobs</h4>
      <div class="backups-panel__rows">
        <article
          v-for="row in rollbacks"
          :key="'rb-' + row.jobId"
          class="iz-row iz-row--card iz-row--expandable"
          :class="{ 'iz-row--expanded': openRollbackId === row.jobId }"
        >
          <div
            class="iz-row__header"
            role="button"
            tabindex="0"
            :aria-expanded="openRollbackId === row.jobId"
            @click="toggleRollback(row)"
            @keydown.enter.prevent="toggleRollback(row)"
            @keydown.space.prevent="toggleRollback(row)"
          >
            <div class="backups-panel__identity">
              <strong>Rollback #{{ row.jobId }}</strong>
              <span class="backups-panel__meta">From backup #{{ row.sourceBackupJobId }} · {{ formatDate(row.createdAt) }}</span>
            </div>
            <div class="iz-row__actions">
              <span class="iz-badge" :class="row.mode === 'apply' ? 'iz-badge--warning' : 'iz-badge--muted'">
                {{ row.mode === 'apply' ? 'Apply' : 'Dry run' }}
              </span>
              <span class="iz-pill" :class="statusTone(row.status)">
                <span class="iz-dot" aria-hidden="true"></span>
                {{ statusLabel(row.status) }}
              </span>
              <button
                v-if="canApply(row)"
                type="button"
                class="iz-btn iz-btn--danger iz-btn--sm"
                :disabled="busy"
                @click.stop="askDestructive({ type: 'apply', row: row })"
              >Apply rollback</button>
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="iz-row__chevron"
                :class="{ 'iz-row__chevron--open': openRollbackId === row.jobId }"
                aria-hidden="true"
              >
                <polyline points="6 9 12 15 18 9" />
              </svg>
            </div>
          </div>

          <div v-if="openRollbackId === row.jobId" class="iz-row__detail backups-panel__detail">
            <p v-if="rollbackDetailLoading && !rollbackDetail" class="iz-state">Loading rollback detail…</p>
            <div v-if="rollbackDetailError" class="iz-error" role="alert">{{ rollbackDetailError }}</div>

            <template v-if="rollbackDetail">
              <div v-if="rollbackDetail.errorMessage" class="iz-error" role="alert">{{ rollbackDetail.errorMessage }}</div>

              <section v-if="rollbackDetail.result" class="backups-panel__section">
                <span class="iz-pill" :class="rollbackDetail.result.canApply ? 'iz-pill--success' : 'iz-pill--danger'">
                  {{ rollbackDetail.result.canApply ? 'Validation passed' : 'Validation blocked' }}
                </span>
                <ul
                  v-if="rollbackDetail.result.validationErrors && rollbackDetail.result.validationErrors.length"
                  class="backups-panel__errors"
                >
                  <li v-for="(message, i) in rollbackDetail.result.validationErrors" :key="i">{{ message }}</li>
                </ul>
                <div v-if="rollbackImpact.length" class="backups-panel__chips">
                  <span
                    v-for="entry in rollbackImpact"
                    :key="entry[0]"
                    class="iz-badge iz-badge--muted"
                  >{{ humanKey(entry[0]) }}: {{ entry[1] }}</span>
                </div>
              </section>

              <section v-if="rollbackDetail.steps && rollbackDetail.steps.length" class="backups-panel__section">
                <span class="iz-section-title">Steps</span>
                <div class="backups-panel__timeline">
                  <div
                    v-for="step in rollbackDetail.steps"
                    :key="step.id"
                    class="backups-panel__timeline-row"
                  >
                    <span class="iz-pill" :class="statusTone(step.status)">
                      <span class="iz-dot" aria-hidden="true"></span>
                      {{ statusLabel(step.status) }}
                    </span>
                    <strong>{{ humanKey(step.stepKey) }}</strong>
                    <span class="backups-panel__meta">{{ step.errorMessage || formatDate(step.finishedAt || step.startedAt) }}</span>
                  </div>
                </div>
              </section>

              <section class="backups-panel__section">
                <span class="iz-section-title">Activity log</span>
                <div v-if="!rollbackEvents.length" class="iz-empty">No events recorded.</div>
                <div v-else class="backups-panel__timeline">
                  <div
                    v-for="event in rollbackEvents"
                    :key="event.id"
                    class="backups-panel__timeline-row"
                  >
                    <span class="iz-badge" :class="eventTone(event.level)">{{ event.level }}</span>
                    <strong>{{ event.message }}</strong>
                    <span class="backups-panel__meta">{{ formatDate(event.createdAt) }}</span>
                  </div>
                </div>
              </section>
            </template>
          </div>
        </article>
      </div>
    </section>

    <ConfirmDialog
      v-if="destructiveAction"
      :title="destructiveTitle"
      :message="destructiveMessage"
      :confirm-label="destructiveAction.type === 'delete' ? 'Delete backup' : 'Continue to password'"
      :danger="true"
      @confirm="confirmDestructive"
      @cancel="destructiveAction = null"
    />

    <div v-if="passwordAction" class="iz-modal-backdrop" @click.self="closePassword">
      <div
        class="iz-modal backups-panel__password"
        role="dialog"
        aria-modal="true"
        aria-label="Confirm administrator password"
      >
        <header class="iz-modal__header">
          <h3 class="backups-panel__modal-title">Confirm administrator password</h3>
          <button
            type="button"
            class="iz-close iz-close--sm"
            aria-label="Close"
            :disabled="busy"
            @click="closePassword"
          >&times;</button>
        </header>
        <form class="iz-modal__body backups-panel__password-body" autocomplete="on" @submit.prevent="runPasswordAction">
          <p class="iz-modal__confirm-text">{{ passwordPrompt }}</p>
          <input
            ref="passwordInput"
            v-model="password"
            class="iz-input"
            type="password"
            name="password"
            autocomplete="current-password"
            placeholder="Your admin password"
            :disabled="busy"
          />
          <div v-if="passwordError" class="iz-error" role="alert">{{ passwordError }}</div>
        </form>
        <footer class="iz-modal__footer">
          <button type="button" class="iz-btn" :disabled="busy" @click="closePassword">Cancel</button>
          <button
            type="button"
            class="iz-btn"
            :class="passwordAction.type === 'delete' || passwordAction.type === 'apply' ? 'iz-btn--danger' : 'iz-btn--primary'"
            :disabled="!password || busy"
            @click="runPasswordAction"
          >
            <span v-if="busy" class="iz-spinner"></span>
            {{ busy ? 'Working…' : 'Confirm' }}
          </button>
        </footer>
      </div>
    </div>
  </div>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateOcsUrl, generateUrl } from "@nextcloud/router";
import ConfirmDialog from "./ConfirmDialog.vue";

const PAGE_SIZE = 20;
const OCS_HEADERS = { "OCS-APIRequest": "true", Accept: "application/json" };

// Job list, detail, and rollback polls all run only while something is
// queued/running, and only while the tab is visible.
const DETAIL_POLL_MS = 2500;
const LIST_POLL_MS = 3000;

export default {
  name: "BackupsPanel",
  components: { ConfirmDialog },
  props: {
    orgId: {
      type: Number,
      required: true,
    },
    embedded: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      jobs: [],
      total: 0,
      hasMore: false,
      offset: 0,
      loading: true,
      error: "",
      actionError: "",
      filters: { q: "", status: "", backupType: "", triggerSource: "" },
      createType: "full",
      openJobId: null,
      detail: null,
      events: [],
      detailLoading: false,
      detailError: "",
      rollbacks: [],
      openRollbackId: null,
      rollbackDetail: null,
      rollbackEvents: [],
      rollbackDetailLoading: false,
      rollbackDetailError: "",
      destructiveAction: null,
      passwordAction: null,
      password: "",
      passwordError: "",
      busy: false,
      pollTimer: null,
      listTimer: null,
      rollbackTimer: null,
      // A stale in-flight response must not overwrite a newer one: every load
      // stamps its request and drops the result if another has started since.
      detailRequestId: 0,
      rollbackRequestId: 0,
      detailPollBusy: false,
      listPollBusy: false,
      rollbackPollBusy: false,
    };
  },
  computed: {
    statusOptions() {
      return [
        { label: "All", value: "" },
        { label: "Queued", value: "queued" },
        { label: "Running", value: "running" },
        { label: "Completed", value: "completed" },
        { label: "Failed", value: "failed" },
        { label: "Expired", value: "expired" },
        { label: "Deleted", value: "deleted" },
      ];
    },
    typeOptions() {
      return [
        { label: "All types", value: "" },
        { label: "Full", value: "full" },
        { label: "Incremental", value: "incremental" },
      ];
    },
    triggerOptions() {
      return [
        { label: "All triggers", value: "" },
        { label: "Scheduled", value: "scheduled" },
        { label: "Manual", value: "manual" },
      ];
    },
    hasFilters() {
      const f = this.filters;
      return !!(f.q || f.status || f.backupType || f.triggerSource);
    },
    emptyMessage() {
      return this.hasFilters
        ? "No backup jobs match your filters."
        : "No backup jobs for this organization yet.";
    },
    summaryCounts() {
      return Object.entries((((this.detail || {}).result || {}).summary || {}).counts || {});
    },
    rollbackImpact() {
      return Object.entries(((this.rollbackDetail || {}).result || {}).impact || {});
    },
    destructiveTitle() {
      if (!this.destructiveAction) return "Confirm action";
      return this.destructiveAction.type === "delete"
        ? "Delete backup #" + this.destructiveAction.row.jobId + "?"
        : "Apply rollback?";
    },
    destructiveMessage() {
      return this.destructiveAction && this.destructiveAction.type === "delete"
        ? "The archive will be permanently removed. This cannot be undone."
        : "This restores organization data from the selected backup. A safety snapshot is created first, but the operation changes live data.";
    },
    passwordPrompt() {
      const labels = {
        create: "Creating a backup",
        download: "Downloading this archive",
        delete: "Deleting this backup",
        dryRun: "Starting a rollback dry run",
        apply: "Applying this rollback",
      };
      return (labels[this.passwordAction && this.passwordAction.type] || "This action")
        + " requires re-confirming your password.";
    },
  },
  mounted() {
    this.fetchJobs();
    // Silent: most organizations have never rolled back, and a failure here
    // must not greet the tab with a banner before anything has been asked of it.
    this.fetchRollbacks(true);
    document.addEventListener("visibilitychange", this.onVisibility);
  },
  beforeDestroy() {
    this.stopPolling();
    this.stopListPolling();
    this.stopRollbackPolling();
    document.removeEventListener("visibilitychange", this.onVisibility);
  },
  methods: {
    orgBase() {
      return "/apps/organization/organizations/" + this.orgId + "/backups";
    },
    async ocs(path, options) {
      const config = options || {};
      config.params = { ...(config.params || {}), format: "json" };
      config.headers = { ...OCS_HEADERS, ...(config.headers || {}) };
      const res = await axios({ url: generateOcsUrl(path), ...config });
      return res.data && res.data.ocs ? res.data.ocs.data : (res.data || {});
    },

    // ── Job list ──────────────────────────────────────────────────────────
    async fetchJobs(silent) {
      if (!silent) this.loading = true;
      this.error = "";
      try {
        const res = await axios.get(generateUrl("/apps/superadminpage/api/super/backups"), {
          params: {
            ...this.filters,
            organizationId: this.orgId,
            limit: PAGE_SIZE,
            offset: this.offset,
          },
        });
        const data = res.data || {};
        this.jobs = data.jobs || [];
        this.total = Number(data.total || 0);
        this.hasMore = !!data.hasMore;
        // Deleting the last job on a page would otherwise leave an empty view
        // with a Previous button as the only way out.
        if (!this.jobs.length && this.offset > 0) {
          this.offset = Math.max(0, this.offset - PAGE_SIZE);
          return this.fetchJobs(silent);
        }
        if (this.jobs.some((job) => this.isActive(job.status))) this.startListPolling();
        else this.stopListPolling();
      } catch (e) {
        this.error = this.describe(e, "Could not load backup jobs.");
      } finally {
        if (!silent) this.loading = false;
      }
    },
    setFilter(key, value) {
      this.filters[key] = value;
      this.applyFilters();
    },
    applyFilters() {
      this.offset = 0;
      this.closeDetail();
      this.fetchJobs();
    },
    previousPage() {
      this.offset = Math.max(0, this.offset - PAGE_SIZE);
      this.closeDetail();
      this.fetchJobs();
    },
    nextPage() {
      if (!this.hasMore) return;
      this.offset += PAGE_SIZE;
      this.closeDetail();
      this.fetchJobs();
    },

    // ── Job detail ────────────────────────────────────────────────────────
    async toggleJob(row) {
      if (this.openJobId === row.jobId) return this.closeDetail();
      this.stopPolling();
      this.openJobId = row.jobId;
      this.detail = null;
      this.events = [];
      await this.loadDetail(row);
      if (this.detail && this.isActive(this.detail.status)) this.startPolling(row);
    },
    closeDetail() {
      this.stopPolling();
      this.detailRequestId += 1;
      this.openJobId = null;
      this.detail = null;
      this.events = [];
    },
    async loadDetail(row) {
      const requestId = ++this.detailRequestId;
      this.detailLoading = true;
      this.detailError = "";
      try {
        const base = this.orgBase();
        const values = await Promise.all([
          this.ocs(base + "/jobs/" + row.jobId),
          this.ocs(base + "/jobs/" + row.jobId + "/events", { params: { limit: 200, offset: 0 } }),
        ]);
        if (requestId !== this.detailRequestId || this.openJobId !== row.jobId) return;
        this.detail = values[0].job || null;
        this.events = values[1].events || [];
      } catch (e) {
        if (requestId === this.detailRequestId) {
          this.detailError = this.describe(e, "Could not load backup details.");
        }
      } finally {
        if (requestId === this.detailRequestId) this.detailLoading = false;
      }
    },

    // ── Rollback jobs ─────────────────────────────────────────────────────
    async fetchRollbacks(silent) {
      try {
        const data = await this.ocs(this.orgBase() + "/rollback-jobs", { params: { limit: 20, offset: 0 } });
        this.rollbacks = data.jobs || [];
        if (this.rollbacks.some((job) => this.isActive(job.status))) this.startRollbackPolling();
        else this.stopRollbackPolling();
      } catch (e) {
        if (!silent) this.actionError = this.describe(e, "Could not load rollback jobs.");
      }
    },
    async toggleRollback(row) {
      if (this.openRollbackId === row.jobId) {
        this.openRollbackId = null;
        this.rollbackDetail = null;
        this.rollbackEvents = [];
        return;
      }
      this.openRollbackId = row.jobId;
      this.rollbackDetail = null;
      this.rollbackEvents = [];
      await this.loadRollbackDetail(row);
    },
    async loadRollbackDetail(row) {
      const requestId = ++this.rollbackRequestId;
      this.rollbackDetailLoading = true;
      this.rollbackDetailError = "";
      try {
        const base = this.orgBase();
        const values = await Promise.all([
          this.ocs(base + "/rollback-jobs/" + row.jobId),
          this.ocs(base + "/rollback-jobs/" + row.jobId + "/events", { params: { limit: 200, offset: 0 } }),
        ]);
        if (requestId !== this.rollbackRequestId || this.openRollbackId !== row.jobId) return;
        this.rollbackDetail = values[0].job || null;
        this.rollbackEvents = values[1].events || [];
      } catch (e) {
        if (requestId === this.rollbackRequestId) {
          this.rollbackDetailError = this.describe(e, "Could not load rollback details.");
        }
      } finally {
        if (requestId === this.rollbackRequestId) this.rollbackDetailLoading = false;
      }
    },

    // ── Polling ───────────────────────────────────────────────────────────
    startPolling(row) {
      this.stopPolling();
      this.pollTimer = window.setInterval(async () => {
        if (document.visibilityState !== "visible" || this.detailPollBusy) return;
        this.detailPollBusy = true;
        try {
          await this.loadDetail(row);
          await this.fetchJobs(true);
          if (!this.detail || !this.isActive(this.detail.status)) this.stopPolling();
        } finally {
          this.detailPollBusy = false;
        }
      }, DETAIL_POLL_MS);
    },
    stopPolling() {
      if (this.pollTimer) window.clearInterval(this.pollTimer);
      this.pollTimer = null;
    },
    startListPolling() {
      if (this.listTimer) return;
      this.listTimer = window.setInterval(async () => {
        if (document.visibilityState !== "visible" || this.listPollBusy) return;
        this.listPollBusy = true;
        try {
          await this.fetchJobs(true);
        } finally {
          this.listPollBusy = false;
        }
      }, LIST_POLL_MS);
    },
    stopListPolling() {
      if (this.listTimer) window.clearInterval(this.listTimer);
      this.listTimer = null;
    },
    startRollbackPolling() {
      if (this.rollbackTimer) return;
      this.rollbackTimer = window.setInterval(async () => {
        if (document.visibilityState !== "visible" || this.rollbackPollBusy) return;
        this.rollbackPollBusy = true;
        try {
          await this.fetchRollbacks(true);
          if (this.openRollbackId) {
            const row = this.rollbacks.find((item) => item.jobId === this.openRollbackId);
            if (row) await this.loadRollbackDetail(row);
          }
        } finally {
          this.rollbackPollBusy = false;
        }
      }, LIST_POLL_MS);
    },
    stopRollbackPolling() {
      if (this.rollbackTimer) window.clearInterval(this.rollbackTimer);
      this.rollbackTimer = null;
    },
    onVisibility() {
      if (document.visibilityState !== "visible") return;
      if (!this.openJobId || !this.detail || !this.isActive(this.detail.status)) return;
      const row = this.jobs.find((item) => item.jobId === this.openJobId);
      if (row) this.loadDetail(row);
    },

    // ── Actions ───────────────────────────────────────────────────────────
    askDestructive(action) {
      this.destructiveAction = action;
    },
    confirmDestructive() {
      const action = this.destructiveAction;
      this.destructiveAction = null;
      this.askPassword(action);
    },
    askPassword(action) {
      this.passwordAction = action;
      this.password = "";
      this.passwordError = "";
      this.$nextTick(() => this.$refs.passwordInput && this.$refs.passwordInput.focus());
    },
    closePassword() {
      if (this.busy) return;
      this.passwordAction = null;
      this.password = "";
    },
    async runPasswordAction() {
      if (!this.passwordAction || !this.password || this.busy) return;
      this.busy = true;
      this.passwordError = "";
      this.actionError = "";
      const action = this.passwordAction;
      const base = this.orgBase();
      try {
        await axios.post(generateUrl("/login/confirm"), { password: this.password });

        let createdJob = null;
        if (action.type === "create") {
          const data = await this.ocs(base + "/jobs", {
            method: "POST",
            data: new URLSearchParams({ backupType: this.createType }),
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
          });
          createdJob = data.job || null;
          this.offset = 0;
        }
        if (action.type === "delete") {
          await this.ocs(base + "/jobs/" + action.row.jobId, { method: "DELETE" });
        }
        if (action.type === "dryRun" || action.type === "apply") {
          const sourceId = action.type === "apply"
            ? action.row.sourceBackupJobId
            : action.row.jobId;
          await this.ocs(base + "/rollback-jobs", {
            method: "POST",
            data: new URLSearchParams({
              sourceBackupJobId: String(sourceId),
              mode: action.type === "apply" ? "apply" : "dry_run",
            }),
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
          });
        }
        if (action.type === "download") {
          window.location.href = generateUrl(base + "/jobs/" + action.row.jobId + "/download");
        }

        this.passwordAction = null;
        this.password = "";
        await Promise.all([this.fetchJobs(), this.fetchRollbacks()]);
        // The tab's job count comes from the org payload, so a create or a
        // delete leaves it stale until the drill-down refetches.
        if (action.type === "create" || action.type === "delete") this.$emit("reload");

        if (createdJob) {
          this.openJobId = createdJob.jobId;
          await this.loadDetail(createdJob);
          if (this.detail && this.isActive(this.detail.status)) this.startPolling(createdJob);
        }
      } catch (e) {
        const url = e && e.response && e.response.config && e.response.config.url;
        if (url && url.indexOf("/login/confirm") !== -1 && e.response.status === 403) {
          this.passwordError = "Wrong password. Try again.";
          this.password = "";
          this.$nextTick(() => this.$refs.passwordInput && this.$refs.passwordInput.focus());
        } else {
          this.passwordError = this.describe(e, "Could not complete the action.");
        }
      } finally {
        this.busy = false;
      }
    },

    // ── Row predicates ────────────────────────────────────────────────────
    canRollback(row) {
      return row.status === "completed"
        && row.backupType === "full"
        && !!row.artifactName
        && !this.downloadBlockedReason(row);
    },
    canApply(row) {
      return row.mode === "dry_run"
        && row.status === "completed"
        && row.result
        && row.result.canApply === true;
    },
    downloadBlockedReason(row) {
      if (!row.artifactName) return "No archive was produced.";
      if (this.isExpired(row.expiresAt)) return "The archive has expired.";
      return "";
    },
    isActive(status) {
      return status === "queued" || status === "running";
    },

    // ── Labels and tones (explicit maps, neutral fallback) ────────────────
    statusLabel(status) {
      return {
        queued: "Queued",
        running: "Running",
        completed: "Completed",
        failed: "Failed",
        expired: "Expired",
        deleted: "Deleted",
        skipped: "Skipped",
      }[status] || status || "Unknown";
    },
    statusTone(status) {
      return {
        completed: "iz-pill--success",
        failed: "iz-pill--danger",
        running: "iz-pill--accent",
        queued: "iz-pill--accent",
        expired: "iz-pill--muted",
        deleted: "iz-pill--muted",
        skipped: "iz-pill--muted",
      }[status] || "iz-pill--muted";
    },
    eventTone(level) {
      return {
        error: "iz-badge--danger",
        warning: "iz-badge--warning",
        info: "iz-badge--accent",
      }[level] || "iz-badge--muted";
    },
    chipTone(value) {
      return {
        queued: "iz-chip--accent",
        running: "iz-chip--accent",
        completed: "iz-chip--success",
        failed: "iz-chip--danger",
        expired: "iz-chip--muted",
        deleted: "iz-chip--muted",
        full: "iz-chip--accent",
        incremental: "iz-chip--warning",
        scheduled: "iz-chip--muted",
        manual: "iz-chip--cat-5",
      }[value] || "iz-chip--muted";
    },
    typeLabel(type) {
      return type === "incremental" ? "Incremental" : "Full";
    },
    triggerLabel(source) {
      return source === "scheduled" ? "Scheduled" : "Manual";
    },
    humanKey(key) {
      const value = String(key || "").replace(/([A-Z])/g, " $1").replace(/_/g, " ").trim();
      return value.charAt(0).toUpperCase() + value.slice(1);
    },

    // ── Retention ─────────────────────────────────────────────────────────
    // The old read-only table carried expiry emphasis in its Expires column;
    // with the column gone it rides in the row summary as a badge.
    expiryNote(row) {
      if (!row.expiresAt || row.status !== "completed") return "";
      if (this.isExpired(row.expiresAt)) return "Archive expired";
      const hours = this.hoursUntil(row.expiresAt);
      if (hours === null || hours >= 24) return "";
      return hours < 1 ? "Expires within the hour" : "Expires in " + Math.round(hours) + "h";
    },
    expiryTone(row) {
      return this.isExpired(row.expiresAt) ? "iz-badge--muted" : "iz-badge--warning";
    },
    expiryClass(job) {
      if (!job || !job.expiresAt) return "";
      if (this.isExpired(job.expiresAt)) return "backups-panel__value--expired";
      const hours = this.hoursUntil(job.expiresAt);
      return hours !== null && hours < 24 ? "backups-panel__value--expiring" : "";
    },
    hoursUntil(value) {
      const ms = this.parseDate(value);
      if (ms === null) return null;
      return (ms - Date.now()) / 3600000;
    },
    isExpired(value) {
      const ms = this.parseDate(value);
      return ms !== null && ms <= Date.now();
    },

    // ── Formatting ────────────────────────────────────────────────────────
    // Timestamps come back as UTC without a zone marker; parsing them without
    // the Z would read them as local time and shift every date by the offset.
    parseDate(value) {
      if (!value) return null;
      const raw = String(value);
      const ms = Date.parse(raw.replace(" ", "T") + (raw.includes("Z") ? "" : "Z"));
      return Number.isFinite(ms) ? ms : null;
    },
    formatDate(value) {
      const ms = this.parseDate(value);
      if (ms === null) return value ? String(value) : "—";
      return new Date(ms).toLocaleString("en-GB", {
        day: "2-digit",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
      });
    },
    formatDuration(startValue, endValue) {
      const start = this.parseDate(startValue);
      const end = this.parseDate(endValue);
      if (start === null || end === null) return "—";
      const seconds = Math.round((end - start) / 1000);
      if (seconds < 0) return "—";
      if (seconds < 60) return seconds + "s";
      const minutes = Math.floor(seconds / 60);
      if (minutes < 60) return minutes + "m " + (seconds % 60) + "s";
      return Math.floor(minutes / 60) + "h " + (minutes % 60) + "m";
    },
    formatSize(bytes) {
      const value = Number(bytes || 0);
      if (value < 1024) return value + " B";
      if (value < 1048576) return (value / 1024).toFixed(1) + " KB";
      if (value < 1073741824) return (value / 1048576).toFixed(1) + " MB";
      return (value / 1073741824).toFixed(2) + " GB";
    },
    describe(e, fallback) {
      const data = e && e.response && e.response.data;
      return (data && data.ocs && data.ocs.meta && data.ocs.meta.message)
        || (data && (data.message || data.error))
        || fallback;
    },
  },
};
</script>

<style scoped>
.backups-panel {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}

.backups-panel__toolbar {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  flex-wrap: wrap;
}

/* .iz-input and .iz-select are width:100% for stacked form fields; in a
   toolbar row they size to their track instead. */
.backups-panel__search {
  flex: 1 1 220px;
  width: auto;
}

.backups-panel__type-select {
  width: auto;
}

.backups-panel__toolbar-spacer {
  flex: 1 1 0;
}

.backups-panel__filters {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.backups-panel__filter-group {
  display: flex;
  gap: 5px;
  padding-right: 12px;
  border-right: 1px solid var(--color-border);
}

.backups-panel__filter-group:last-child {
  border-right: none;
  padding-right: 0;
}

.backups-panel__rows,
.backups-panel__detail,
.backups-panel__section,
.backups-panel__timeline,
.backups-panel__password-body {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.backups-panel__identity {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.backups-panel__meta {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary);
}

.backups-panel__size {
  font-size: var(--iz-fs-sm);
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.backups-panel__progress {
  flex: 1;
  min-width: 80px;
}

.backups-panel__facts {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin: 0;
}

.backups-panel__facts div {
  min-width: 0;
}

.backups-panel__facts dd {
  margin: 3px 0 0;
  font-size: var(--iz-fs-md);
  overflow-wrap: anywhere;
}

.backups-panel__archive {
  font-family: var(--iz-font-mono);
}

.backups-panel__value--expiring {
  color: var(--color-warning-text);
  font-weight: 600;
}

.backups-panel__value--expired {
  color: var(--color-text-muted);
  text-decoration: line-through;
}

.backups-panel__chips {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.backups-panel__timeline-row {
  display: grid;
  grid-template-columns: minmax(90px, auto) minmax(160px, 1fr) minmax(120px, auto);
  align-items: center;
  gap: 10px;
  font-size: var(--iz-fs-md);
}

.backups-panel__errors {
  margin: 0;
  padding-left: 18px;
  font-size: var(--iz-fs-md);
  color: var(--color-danger-text);
}

.backups-panel__paging {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--spacing-sm);
}

.backups-panel__paging-total {
  margin-right: auto;
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary);
}

.backups-panel__password {
  width: min(460px, 100%);
}

.backups-panel__modal-title {
  margin: 0;
  padding: 0;
  border: 0;
  font-size: var(--iz-fs-lg);
}

@media (max-width: 760px) {
  .backups-panel__facts,
  .backups-panel__timeline-row {
    grid-template-columns: 1fr;
  }

  .backups-panel__toolbar {
    align-items: stretch;
    flex-direction: column;
  }

  .backups-panel__search,
  .backups-panel__type-select {
    width: 100%;
  }

  .backups-panel__toolbar-spacer {
    display: none;
  }
}
</style>
