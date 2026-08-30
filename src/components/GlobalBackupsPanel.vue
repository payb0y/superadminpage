<template>
  <div class="global-backups">
    <section class="iz-panel global-backups__panel">
      <header class="iz-panel__header global-backups__header">
        <div>
          <h2 class="iz-panel__title">Organization backups</h2>
          <p class="global-backups__intro">Monitor and manage backup archives across the platform.</p>
        </div>
        <div class="global-backups__create">
          <select v-model.number="createOrgId" class="iz-select" aria-label="Organization for new backup">
            <option :value="0">Select organization</option>
            <option v-for="org in orgs" :key="org.id" :value="Number(org.id)">{{ org.name }}</option>
          </select>
          <select v-model="createType" class="iz-select" aria-label="Backup type">
            <option value="full">Full backup</option>
            <option value="incremental">Incremental backup</option>
          </select>
          <button class="iz-btn iz-btn--primary" type="button" :disabled="!createOrgId || busy" @click="askPassword({ type: 'create' })">New backup</button>
        </div>
      </header>

      <div class="global-backups__filters">
        <input v-model.trim="filters.q" class="iz-input" type="search" placeholder="Search organization, archive, or job ID" @keyup.enter="applyFilters">
        <select v-model.number="filters.organizationId" class="iz-select" aria-label="Filter by organization" @change="applyFilters">
          <option :value="0">All organizations</option>
          <option v-for="org in orgs" :key="org.id" :value="Number(org.id)">{{ org.name }}</option>
        </select>
        <select v-model="filters.status" class="iz-select" aria-label="Filter by status" @change="applyFilters">
          <option value="">All statuses</option><option value="queued">Queued</option><option value="running">Running</option><option value="completed">Completed</option><option value="failed">Failed</option><option value="expired">Expired</option><option value="deleted">Deleted</option>
        </select>
        <select v-model="filters.backupType" class="iz-select" aria-label="Filter by backup type" @change="applyFilters">
          <option value="">All types</option><option value="full">Full</option><option value="incremental">Incremental</option>
        </select>
        <select v-model="filters.triggerSource" class="iz-select" aria-label="Filter by trigger" @change="applyFilters">
          <option value="">All triggers</option><option value="manual">Manual</option><option value="scheduled">Scheduled</option>
        </select>
        <button class="iz-btn iz-btn--accent" type="button" :disabled="loading" @click="applyFilters">Refresh</button>
      </div>

      <div v-if="error" class="iz-error global-backups__alert" role="alert">{{ error }}</div>
      <div v-if="actionError" class="iz-error global-backups__alert" role="alert">{{ actionError }}</div>
      <p v-if="loading" class="iz-state">Loading backup jobs…</p>
      <div v-else-if="!jobs.length" class="iz-empty">No backup jobs match these filters.</div>

      <div v-else class="global-backups__rows">
        <article v-for="row in jobs" :key="row.jobId" class="iz-row iz-row--card iz-row--expandable" :class="{ 'iz-row--expanded': openJobId === row.jobId }">
          <div class="iz-row__header" role="button" tabindex="0" :aria-expanded="openJobId === row.jobId" @click="toggleJob(row)" @keydown.enter.prevent="toggleJob(row)" @keydown.space.prevent="toggleJob(row)">
            <div class="global-backups__identity">
              <strong>{{ row.organizationName }}</strong>
              <span class="global-backups__meta">Backup #{{ row.jobId }} · {{ formatDate(row.createdAt) }} · {{ triggerLabel(row.triggerSource) }}</span>
            </div>
            <div v-if="isActive(row.status)" class="global-backups__progress"><div class="iz-meter iz-meter--thin iz-meter--indeterminate"><div class="iz-meter__fill iz-meter__fill--accent"></div></div></div>
            <div class="iz-row__actions">
              <span class="iz-badge iz-badge--muted">{{ typeLabel(row.backupType) }}</span>
              <span class="iz-pill" :class="statusTone(row.status)"><span class="iz-dot" aria-hidden="true"></span>{{ statusLabel(row.status) }}</span>
              <button v-if="canRollback(row)" class="iz-btn iz-btn--sm" type="button" :disabled="busy" @click.stop="askPassword({ type: 'dryRun', row: row })">Dry-run rollback</button>
              <button v-if="row.status === 'completed'" class="iz-btn iz-btn--primary iz-btn--sm" type="button" :disabled="!!downloadBlockedReason(row) || busy" :title="downloadBlockedReason(row) || 'Download archive'" @click.stop="askPassword({ type: 'download', row: row })">Download</button>
              <button class="iz-btn iz-btn--icon iz-btn--sm" type="button" :disabled="busy" :aria-label="'Delete backup #' + row.jobId" @click.stop="askDestructive({ type: 'delete', row: row })">×</button>
              <span class="global-backups__chevron" aria-hidden="true">⌄</span>
            </div>
          </div>

          <div v-if="openJobId === row.jobId" class="iz-row__detail global-backups__detail">
            <p v-if="detailLoading && !detail" class="iz-state">Loading job detail…</p>
            <div v-if="detailError" class="iz-error" role="alert">{{ detailError }}</div>
            <template v-if="detail">
              <div v-if="detail.errorMessage" class="iz-error" role="alert">{{ detail.errorMessage }}</div>
              <dl class="global-backups__facts">
                <div><dt class="iz-label">Organization</dt><dd>{{ row.organizationName }}</dd></div>
                <div><dt class="iz-label">Requested by</dt><dd>{{ detail.requestedByUid === '__system__' ? 'Scheduler' : detail.requestedByUid }}</dd></div>
                <div><dt class="iz-label">Started</dt><dd>{{ formatDate(detail.startedAt) }}</dd></div>
                <div><dt class="iz-label">Finished</dt><dd>{{ formatDate(detail.finishedAt) }}</dd></div>
                <div><dt class="iz-label">Expires</dt><dd>{{ formatDate(detail.expiresAt) }}</dd></div>
                <div><dt class="iz-label">Archive</dt><dd>{{ detail.artifactName || 'Not available' }}<template v-if="detail.artifactSize"> · {{ formatSize(detail.artifactSize) }}</template></dd></div>
              </dl>
              <section v-if="summaryCounts.length" class="global-backups__section"><span class="iz-section-title">Contents</span><div class="global-backups__chips"><span v-for="entry in summaryCounts" :key="entry[0]" class="iz-badge iz-badge--muted">{{ humanKey(entry[0]) }}: {{ entry[1] }}</span></div></section>
              <section v-if="detail.steps && detail.steps.length" class="global-backups__section"><span class="iz-section-title">Steps</span><div class="global-backups__timeline"><div v-for="step in detail.steps" :key="step.id" class="global-backups__timeline-row"><span class="iz-pill" :class="statusTone(step.status)"><span class="iz-dot"></span>{{ statusLabel(step.status) }}</span><strong>{{ humanKey(step.stepKey) }}</strong><span>{{ step.errorMessage || formatDate(step.finishedAt || step.startedAt) }}</span></div></div></section>
              <section class="global-backups__section"><span class="iz-section-title">Activity log</span><div v-if="!events.length" class="iz-empty">No events recorded.</div><div v-else class="global-backups__timeline"><div v-for="event in events" :key="event.id" class="global-backups__timeline-row"><span class="iz-badge" :class="eventTone(event.level)">{{ event.level }}</span><strong>{{ event.message }}</strong><span>{{ formatDate(event.createdAt) }}</span></div></div></section>
            </template>
          </div>
        </article>
      </div>

      <div class="global-backups__paging">
        <span>{{ total }} jobs</span>
        <button class="iz-btn iz-btn--sm" type="button" :disabled="offset === 0 || loading" @click="previousPage">Previous</button>
        <button class="iz-btn iz-btn--sm" type="button" :disabled="!hasMore || loading" @click="nextPage">Next</button>
      </div>
    </section>

    <section v-if="selectedOrgId" class="iz-panel global-backups__panel">
      <header class="iz-panel__header"><div><h2 class="iz-panel__title">Rollback jobs</h2><p class="global-backups__intro">{{ selectedOrgName }}</p></div></header>
      <p v-if="rollbackLoading" class="iz-state">Loading rollback jobs…</p>
      <div v-else-if="!rollbacks.length" class="iz-empty">No rollback jobs for this organization.</div>
      <div v-else class="global-backups__rows">
        <article v-for="row in rollbacks" :key="row.jobId" class="iz-row iz-row--card iz-row--expandable" :class="{ 'iz-row--expanded': openRollbackId === row.jobId }">
          <div class="iz-row__header" role="button" tabindex="0" @click="toggleRollback(row)">
            <div class="global-backups__identity"><strong>Rollback #{{ row.jobId }}</strong><span class="global-backups__meta">Backup #{{ row.sourceBackupJobId }} · {{ formatDate(row.createdAt) }}</span></div>
            <div class="iz-row__actions"><span class="iz-badge" :class="row.mode === 'apply' ? 'iz-badge--warning' : 'iz-badge--muted'">{{ row.mode === 'apply' ? 'Apply' : 'Dry run' }}</span><span class="iz-pill" :class="statusTone(row.status)"><span class="iz-dot"></span>{{ statusLabel(row.status) }}</span><button v-if="canApply(row)" class="iz-btn iz-btn--danger iz-btn--sm" type="button" :disabled="busy" @click.stop="askDestructive({ type: 'apply', row: row })">Apply rollback</button><span class="global-backups__chevron">⌄</span></div>
          </div>
          <div v-if="openRollbackId === row.jobId" class="iz-row__detail global-backups__detail">
            <p v-if="rollbackDetailLoading && !rollbackDetail" class="iz-state">Loading rollback detail…</p>
            <div v-if="rollbackDetailError" class="iz-error">{{ rollbackDetailError }}</div>
            <template v-if="rollbackDetail">
              <div v-if="rollbackDetail.errorMessage" class="iz-error">{{ rollbackDetail.errorMessage }}</div>
              <div v-if="rollbackDetail.result" class="global-backups__section"><span class="iz-pill" :class="rollbackDetail.result.canApply ? 'iz-pill--success' : 'iz-pill--danger'">{{ rollbackDetail.result.canApply ? 'Validation passed' : 'Validation blocked' }}</span><ul v-if="rollbackDetail.result.validationErrors && rollbackDetail.result.validationErrors.length"><li v-for="(message, i) in rollbackDetail.result.validationErrors" :key="i">{{ message }}</li></ul><div class="global-backups__chips"><span v-for="entry in rollbackImpact" :key="entry[0]" class="iz-badge iz-badge--muted">{{ humanKey(entry[0]) }}: {{ entry[1] }}</span></div></div>
              <section v-if="rollbackDetail.steps && rollbackDetail.steps.length" class="global-backups__section"><span class="iz-section-title">Steps</span><div class="global-backups__timeline"><div v-for="step in rollbackDetail.steps" :key="step.id" class="global-backups__timeline-row"><span class="iz-pill" :class="statusTone(step.status)"><span class="iz-dot"></span>{{ statusLabel(step.status) }}</span><strong>{{ humanKey(step.stepKey) }}</strong><span>{{ step.errorMessage || formatDate(step.finishedAt || step.startedAt) }}</span></div></div></section>
              <section class="global-backups__section"><span class="iz-section-title">Activity log</span><div class="global-backups__timeline"><div v-for="event in rollbackEvents" :key="event.id" class="global-backups__timeline-row"><span class="iz-badge" :class="eventTone(event.level)">{{ event.level }}</span><strong>{{ event.message }}</strong><span>{{ formatDate(event.createdAt) }}</span></div></div></section>
            </template>
          </div>
        </article>
      </div>
    </section>

    <ConfirmDialog v-if="destructiveAction" :title="destructiveTitle" :message="destructiveMessage" :confirm-label="destructiveAction.type === 'delete' ? 'Delete backup' : 'Continue to password'" danger @confirm="confirmDestructive" @cancel="destructiveAction = null" />

    <div v-if="passwordAction" class="iz-modal-backdrop" @click.self="closePassword">
      <div class="iz-modal global-backups__password" role="dialog" aria-modal="true" aria-label="Confirm administrator password">
        <header class="iz-modal__header"><h3 class="global-backups__modal-title">Confirm administrator password</h3><button class="iz-close" type="button" :disabled="busy" @click="closePassword">×</button></header>
        <form class="iz-modal__body global-backups__password-body" autocomplete="on" @submit.prevent="runPasswordAction">
          <p class="iz-modal__confirm-text">{{ passwordPrompt }}</p>
          <input ref="passwordInput" v-model="password" class="iz-input" type="password" name="password" autocomplete="current-password" placeholder="Your admin password" :disabled="busy">
          <div v-if="passwordError" class="iz-error" role="alert">{{ passwordError }}</div>
        </form>
        <footer class="iz-modal__footer"><button class="iz-btn" type="button" :disabled="busy" @click="closePassword">Cancel</button><button class="iz-btn" :class="passwordAction.type === 'delete' || passwordAction.type === 'apply' ? 'iz-btn--danger' : 'iz-btn--primary'" type="button" :disabled="!password || busy" @click="runPasswordAction"><span v-if="busy" class="iz-spinner"></span>{{ busy ? 'Working…' : 'Confirm' }}</button></footer>
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

export default {
  name: "GlobalBackupsPanel",
  components: { ConfirmDialog },
  props: { orgs: { type: Array, default: () => [] } },
  data() {
    return {
      jobs: [], total: 0, hasMore: false, offset: 0, loading: false, error: "", actionError: "",
      filters: { q: "", organizationId: 0, status: "", backupType: "", triggerSource: "" },
      createOrgId: 0, createType: "full", openJobId: null, detail: null, events: [], detailLoading: false, detailError: "",
      selectedOrgId: 0, rollbacks: [], rollbackLoading: false, openRollbackId: null, rollbackDetail: null, rollbackEvents: [], rollbackDetailLoading: false, rollbackDetailError: "",
      destructiveAction: null, passwordAction: null, password: "", passwordError: "", busy: false, pollTimer: null, listTimer: null, rollbackTimer: null,
      detailRequestId: 0, rollbackRequestId: 0, detailPollBusy: false, listPollBusy: false, rollbackPollBusy: false,
    };
  },
  computed: {
    selectedOrgName() { const org = this.orgs.find((item) => Number(item.id) === this.selectedOrgId); return org ? org.name : "Selected organization"; },
    summaryCounts() { return Object.entries((((this.detail || {}).result || {}).summary || {}).counts || {}); },
    rollbackImpact() { return Object.entries(((this.rollbackDetail || {}).result || {}).impact || {}); },
    destructiveTitle() { if (!this.destructiveAction) return "Confirm action"; return this.destructiveAction.type === "delete" ? "Delete backup #" + this.destructiveAction.row.jobId + "?" : "Apply rollback?"; },
    destructiveMessage() { return this.destructiveAction && this.destructiveAction.type === "delete" ? "The archive will be permanently removed. This cannot be undone." : "This restores organization data from the selected backup. A safety snapshot is created first, but the operation changes live data."; },
    passwordPrompt() { const labels = { create: "Creating a backup", download: "Downloading this archive", delete: "Deleting this backup", dryRun: "Starting a rollback dry run", apply: "Applying this rollback" }; return (labels[this.passwordAction && this.passwordAction.type] || "This action") + " requires re-confirming your password."; },
  },
  mounted() { this.fetchJobs(); document.addEventListener("visibilitychange", this.onVisibility); },
  beforeDestroy() { this.stopPolling(); this.stopListPolling(); this.stopRollbackPolling(); document.removeEventListener("visibilitychange", this.onVisibility); },
  methods: {
    async fetchJobs(silent) {
      if (!silent) this.loading = true; this.error = "";
      try {
        const res = await axios.get(generateUrl("/apps/superadminpage/api/super/backups"), { params: { ...this.filters, limit: PAGE_SIZE, offset: this.offset } });
        const data = res.data || {}; this.jobs = data.jobs || []; this.total = Number(data.total || 0); this.hasMore = !!data.hasMore;
        if (!this.jobs.length && this.offset > 0) { this.offset = Math.max(0, this.offset - PAGE_SIZE); return this.fetchJobs(silent); }
        if (this.jobs.some((job) => this.isActive(job.status))) this.startListPolling(); else this.stopListPolling();
      } catch (e) { this.error = this.describe(e, "Could not load backup jobs."); } finally { if (!silent) this.loading = false; }
    },
    applyFilters() { this.offset = 0; this.closeDetail(); this.fetchJobs(); },
    previousPage() { this.offset = Math.max(0, this.offset - PAGE_SIZE); this.closeDetail(); this.fetchJobs(); },
    nextPage() { if (!this.hasMore) return; this.offset += PAGE_SIZE; this.closeDetail(); this.fetchJobs(); },
    orgBase(orgId) { return "/apps/organization/organizations/" + orgId + "/backups"; },
    async ocs(path, options) {
      const config = options || {}; config.params = { ...(config.params || {}), format: "json" }; config.headers = { ...OCS_HEADERS, ...(config.headers || {}) };
      const res = await axios({ url: generateOcsUrl(path), ...config }); return res.data && res.data.ocs ? res.data.ocs.data : (res.data || {});
    },
    async toggleJob(row) {
      if (this.openJobId === row.jobId) return this.closeDetail();
      this.stopPolling();
      this.openRollbackId = null; this.rollbackDetail = null; this.rollbackEvents = [];
      if (this.selectedOrgId !== Number(row.organizationId)) this.stopRollbackPolling();
      this.openJobId = row.jobId; this.selectedOrgId = Number(row.organizationId); this.detail = null; this.events = [];
      await Promise.all([this.loadDetail(row), this.fetchRollbacks()]);
      if (this.detail && this.isActive(this.detail.status)) this.startPolling(row);
    },
    closeDetail() { this.stopPolling(); this.detailRequestId += 1; this.openJobId = null; this.detail = null; this.events = []; },
    async loadDetail(row) {
      const requestId = ++this.detailRequestId;
      this.detailLoading = true; this.detailError = "";
      try { const base = this.orgBase(row.organizationId); const values = await Promise.all([this.ocs(base + "/jobs/" + row.jobId), this.ocs(base + "/jobs/" + row.jobId + "/events", { params: { limit: 200, offset: 0 } })]); if (requestId !== this.detailRequestId || this.openJobId !== row.jobId) return; this.detail = values[0].job || null; this.events = values[1].events || []; }
      catch (e) { if (requestId === this.detailRequestId) this.detailError = this.describe(e, "Could not load backup details."); } finally { if (requestId === this.detailRequestId) this.detailLoading = false; }
    },
    startPolling(row) { this.stopPolling(); this.pollTimer = window.setInterval(async () => { if (document.visibilityState !== "visible" || this.detailPollBusy) return; this.detailPollBusy = true; try { await this.loadDetail(row); await this.fetchJobs(true); if (!this.detail || !this.isActive(this.detail.status)) this.stopPolling(); } finally { this.detailPollBusy = false; } }, 2500); },
    stopPolling() { if (this.pollTimer) window.clearInterval(this.pollTimer); this.pollTimer = null; },
    startListPolling() { if (this.listTimer) return; this.listTimer = window.setInterval(async () => { if (document.visibilityState !== "visible" || this.listPollBusy) return; this.listPollBusy = true; try { await this.fetchJobs(true); } finally { this.listPollBusy = false; } }, 3000); },
    stopListPolling() { if (this.listTimer) window.clearInterval(this.listTimer); this.listTimer = null; },
    startRollbackPolling() { if (this.rollbackTimer) return; this.rollbackTimer = window.setInterval(async () => { if (document.visibilityState !== "visible" || this.rollbackPollBusy) return; this.rollbackPollBusy = true; try { await this.fetchRollbacks(true); if (this.openRollbackId) { const row = this.rollbacks.find((item) => item.jobId === this.openRollbackId); if (row) await this.loadRollbackDetail(row); } } finally { this.rollbackPollBusy = false; } }, 3000); },
    stopRollbackPolling() { if (this.rollbackTimer) window.clearInterval(this.rollbackTimer); this.rollbackTimer = null; },
    onVisibility() { if (document.visibilityState === "visible" && this.openJobId && this.detail && this.isActive(this.detail.status)) { const row = this.jobs.find((item) => item.jobId === this.openJobId); if (row) this.loadDetail(row); } },
    async fetchRollbacks(silent) { if (!this.selectedOrgId) return; if (!silent) this.rollbackLoading = true; try { const data = await this.ocs(this.orgBase(this.selectedOrgId) + "/rollback-jobs", { params: { limit: 20, offset: 0 } }); this.rollbacks = data.jobs || []; if (this.rollbacks.some((job) => this.isActive(job.status))) this.startRollbackPolling(); else this.stopRollbackPolling(); } catch (e) { this.actionError = this.describe(e, "Could not load rollback jobs."); } finally { if (!silent) this.rollbackLoading = false; } },
    async loadRollbackDetail(row) { const requestId = ++this.rollbackRequestId; this.rollbackDetailLoading = true; this.rollbackDetailError = ""; try { const base = this.orgBase(this.selectedOrgId); const values = await Promise.all([this.ocs(base + "/rollback-jobs/" + row.jobId), this.ocs(base + "/rollback-jobs/" + row.jobId + "/events", { params: { limit: 200, offset: 0 } })]); if (requestId !== this.rollbackRequestId || this.openRollbackId !== row.jobId) return; this.rollbackDetail = values[0].job || null; this.rollbackEvents = values[1].events || []; } catch (e) { if (requestId === this.rollbackRequestId) this.rollbackDetailError = this.describe(e, "Could not load rollback details."); } finally { if (requestId === this.rollbackRequestId) this.rollbackDetailLoading = false; } },
    async toggleRollback(row) { if (this.openRollbackId === row.jobId) { this.openRollbackId = null; this.rollbackDetail = null; return; } this.openRollbackId = row.jobId; this.rollbackDetail = null; await this.loadRollbackDetail(row); },
    askDestructive(action) { this.destructiveAction = action; },
    confirmDestructive() { const action = this.destructiveAction; this.destructiveAction = null; this.askPassword(action); },
    askPassword(action) { this.passwordAction = action; this.password = ""; this.passwordError = ""; this.$nextTick(() => this.$refs.passwordInput && this.$refs.passwordInput.focus()); },
    closePassword() { if (this.busy) return; this.passwordAction = null; this.password = ""; },
    async runPasswordAction() {
      if (!this.passwordAction || !this.password || this.busy) return;
      this.busy = true; this.passwordError = ""; this.actionError = "";
      const action = this.passwordAction;
      try {
        await axios.post(generateUrl("/login/confirm"), { password: this.password });
        let createdJob = null;
        if (action.type === "create") { const data = await this.ocs(this.orgBase(this.createOrgId) + "/jobs", { method: "POST", data: new URLSearchParams({ backupType: this.createType }), headers: { "Content-Type": "application/x-www-form-urlencoded" } }); createdJob = data.job || null; this.selectedOrgId = this.createOrgId; this.offset = 0; }
        if (action.type === "delete") await this.ocs(this.orgBase(action.row.organizationId) + "/jobs/" + action.row.jobId, { method: "DELETE" });
        if (action.type === "dryRun" || action.type === "apply") { const orgId = action.type === "apply" ? this.selectedOrgId : Number(action.row.organizationId); const sourceId = action.type === "apply" ? action.row.sourceBackupJobId : action.row.jobId; await this.ocs(this.orgBase(orgId) + "/rollback-jobs", { method: "POST", data: new URLSearchParams({ sourceBackupJobId: String(sourceId), mode: action.type === "apply" ? "apply" : "dry_run" }), headers: { "Content-Type": "application/x-www-form-urlencoded" } }); this.selectedOrgId = orgId; }
        if (action.type === "download") { window.location.href = generateUrl(this.orgBase(action.row.organizationId) + "/jobs/" + action.row.jobId + "/download"); }
        this.passwordAction = null; this.password = ""; await Promise.all([this.fetchJobs(), this.selectedOrgId ? this.fetchRollbacks() : Promise.resolve()]);
        if (createdJob) { const row = { ...createdJob, organizationId: this.selectedOrgId, organizationName: this.selectedOrgName }; this.openJobId = row.jobId; await this.loadDetail(row); if (this.detail && this.isActive(this.detail.status)) this.startPolling(row); }
      } catch (e) { const url = e && e.response && e.response.config && e.response.config.url; if (url && url.indexOf("/login/confirm") !== -1 && e.response.status === 403) { this.passwordError = "Wrong password. Try again."; this.password = ""; this.$nextTick(() => this.$refs.passwordInput && this.$refs.passwordInput.focus()); } else { this.passwordError = this.describe(e, "Could not complete the action."); } } finally { this.busy = false; }
    },
    canRollback(row) { return row.status === "completed" && row.backupType === "full" && !!row.artifactName && !this.downloadBlockedReason(row); },
    canApply(row) { return row.mode === "dry_run" && row.status === "completed" && row.result && row.result.canApply === true; },
    downloadBlockedReason(row) { if (!row.artifactName) return "No archive was produced."; if (row.expiresAt) { const expires = Date.parse(String(row.expiresAt).replace(" ", "T") + "Z"); if (Number.isFinite(expires) && expires <= Date.now()) return "The archive has expired."; } return ""; },
    isActive(status) { return status === "queued" || status === "running"; },
    statusLabel(status) { return ({ queued: "Queued", running: "Running", completed: "Completed", failed: "Failed", expired: "Expired", deleted: "Deleted", skipped: "Skipped" }[status] || status || "Unknown"); },
    statusTone(status) { return ({ completed: "iz-pill--success", failed: "iz-pill--danger", running: "iz-pill--accent", queued: "iz-pill--accent", expired: "iz-pill--muted", deleted: "iz-pill--muted", skipped: "iz-pill--muted" }[status] || "iz-pill--muted"); },
    eventTone(level) { return ({ error: "iz-badge--danger", warning: "iz-badge--warning", info: "iz-badge--accent" }[level] || "iz-badge--muted"); },
    typeLabel(type) { return type === "incremental" ? "Incremental" : "Full"; },
    triggerLabel(source) { return source === "scheduled" ? "Scheduled" : "Manual"; },
    humanKey(key) { const value = String(key || "").replace(/([A-Z])/g, " $1").replace(/_/g, " ").trim(); return value.charAt(0).toUpperCase() + value.slice(1); },
    formatDate(value) { if (!value) return "Not available"; const date = new Date(String(value).replace(" ", "T") + (String(value).includes("Z") ? "" : "Z")); return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString(); },
    formatSize(bytes) { const value = Number(bytes || 0); if (value < 1024) return value + " B"; if (value < 1048576) return (value / 1024).toFixed(1) + " KB"; if (value < 1073741824) return (value / 1048576).toFixed(1) + " MB"; return (value / 1073741824).toFixed(2) + " GB"; },
    describe(e, fallback) { const data = e && e.response && e.response.data; return (data && data.ocs && data.ocs.meta && data.ocs.meta.message) || (data && data.message) || fallback; },
  },
};
</script>

<style scoped>
.global-backups { display: flex; flex-direction: column; gap: var(--spacing-lg); }
.global-backups__panel { display: flex; flex-direction: column; gap: var(--iz-gap); }
.global-backups__header, .global-backups__create, .global-backups__filters, .global-backups__paging, .global-backups__chips { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.global-backups__header { justify-content: space-between; }
.global-backups__intro { margin: 4px 0 0; color: var(--color-text-secondary); }
.global-backups__create .iz-select, .global-backups__filters .iz-select, .global-backups__filters .iz-input { width: auto; }
.global-backups__filters .iz-input { flex: 1 1 240px; }
.global-backups__rows, .global-backups__timeline, .global-backups__detail, .global-backups__section, .global-backups__password-body { display: flex; flex-direction: column; gap: 10px; }
.global-backups__identity { display: flex; flex-direction: column; min-width: 0; }
.global-backups__meta { color: var(--color-text-secondary); }
.global-backups__progress { flex: 1; min-width: 80px; }
.global-backups__chevron { margin-left: 4px; }
.global-backups__facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin: 0; }
.global-backups__facts div { min-width: 0; }
.global-backups__facts dd { margin: 3px 0 0; overflow-wrap: anywhere; }
.global-backups__timeline-row { display: grid; grid-template-columns: minmax(90px, auto) minmax(160px, 1fr) minmax(120px, auto); align-items: center; gap: 10px; }
.global-backups__paging { justify-content: flex-end; }
.global-backups__paging span { margin-right: auto; color: var(--color-text-secondary); }
.global-backups__password { width: min(460px, 100%); }
.global-backups__modal-title { margin: 0; padding: 0; border: 0; font-size: var(--iz-fs-lg); }
@media (max-width: 760px) { .global-backups__facts { grid-template-columns: 1fr; } .global-backups__timeline-row { grid-template-columns: 1fr; } .global-backups__create, .global-backups__filters { align-items: stretch; flex-direction: column; } .global-backups__create .iz-select, .global-backups__filters .iz-select, .global-backups__filters .iz-input { width: 100%; } }
</style>
