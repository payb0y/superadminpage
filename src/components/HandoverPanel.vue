<template>
  <div class="ho-panel">
    <!-- ── Initiate handover ─────────────────────────────────────────── -->
    <div class="ho-panel__card">
      <div class="ho-panel__label">INITIATE HANDOVER</div>

      <div v-if="notEnoughMembers" class="iz-state ho-panel__field-state">
        Need at least two members to hand off.
      </div>

      <div class="ho-panel__initiate-grid">
        <div>
          <label class="ho-panel__field-label" for="ho-source">
            Source member <span class="ho-panel__req">*</span>
          </label>
          <select
            id="ho-source"
            v-model="form.sourceUserId"
            class="iz-input"
            :disabled="saving || notEnoughMembers"
            @change="onSourceChange"
          >
            <option value="">— pick a source —</option>
            <option
              v-for="m in sortedMembers"
              :key="'src-' + m.userId"
              :value="m.userId"
            >{{ memberLabel(m) }}</option>
          </select>
        </div>
        <div class="ho-panel__arrow" aria-hidden="true">→</div>
        <div>
          <label class="ho-panel__field-label" for="ho-target">
            Target member <span class="ho-panel__req">*</span>
          </label>
          <select
            id="ho-target"
            v-model="form.targetUserId"
            class="iz-input"
            :disabled="saving || notEnoughMembers"
            @change="clearDryRun"
          >
            <option value="">— pick a target —</option>
            <option
              v-for="m in availableTargets"
              :key="'tgt-' + m.userId"
              :value="m.userId"
            >{{ memberLabel(m) }}</option>
          </select>
        </div>
      </div>

      <div class="ho-panel__options">
        <div class="ho-panel__label">OPTIONS</div>
        <label class="ho-panel__option">
          <input
            type="checkbox"
            v-model="form.dryRun"
            :disabled="saving"
            @change="clearDryRun"
          />
          <span>
            <strong>Dry run</strong>
            <span class="ho-panel__hint"> — preview only, no changes applied</span>
          </span>
        </label>
        <label class="ho-panel__option">
          <input
            type="checkbox"
            v-model="form.removeSourceFromGroups"
            :disabled="saving"
            @change="clearDryRun"
          />
          <span>
            <strong>Remove source from groups</strong>
            <span class="ho-panel__hint"> — after handover completes</span>
          </span>
        </label>
        <label class="ho-panel__option">
          <input
            type="checkbox"
            v-model="form.remapDeckContent"
            :disabled="saving"
            @change="clearDryRun"
          />
          <span>
            <strong>Remap Deck content</strong>
            <span class="ho-panel__hint"> — transfer board/card ownership (default on)</span>
          </span>
        </label>
      </div>

      <div v-if="!form.dryRun" class="ho-panel__warning">
        ⚠ This will reassign the source member's ownership across projects,
        Deck boards, and files. Consider running with
        <strong>Dry run</strong> first.
      </div>

      <div v-if="dryRunResult" class="ho-panel__dry-result">
        <div class="ho-panel__dry-heading">
          <span aria-hidden="true">✓</span>
          Dry run complete — no changes applied.
        </div>
        <pre class="ho-panel__dry-json">{{ dryRunResult }}</pre>
      </div>

      <div v-if="startError" class="ho-panel__field-error">{{ startError }}</div>

      <div class="ho-panel__actions">
        <button
          type="button"
          class="iz-btn iz-btn--ghost"
          :disabled="saving"
          @click="resetForm"
        >Reset</button>
        <button
          type="button"
          class="iz-btn iz-btn--primary"
          :disabled="!canStart"
          @click="onStartClick"
        >
          <span
            v-if="saving && confirmAction === 'start'"
            class="ho-panel__spinner"
            aria-hidden="true"
          ></span>
          {{ form.dryRun ? "Preview" : "Start handover" }}
        </button>
      </div>
    </div>

    <!-- ── Recent jobs ───────────────────────────────────────────────── -->
    <div class="ho-panel__card">
      <div class="ho-panel__jobs-header">
        <span class="ho-panel__label">RECENT JOBS</span>
        <div class="ho-panel__jobs-header-right">
          <span
            v-if="hasActiveJobs"
            class="ho-panel__live"
          >
            <span class="ho-panel__live-dot"></span>Live
          </span>
          <button
            type="button"
            class="ho-panel__refresh"
            :disabled="jobsLoading"
            @click="loadJobs"
          >↻ Refresh</button>
        </div>
      </div>

      <div v-if="jobsError" class="ho-panel__field-error">{{ jobsError }}</div>

      <div v-if="jobsLoading && jobs.length === 0" class="iz-state ho-panel__field-state">
        Loading jobs…
      </div>

      <div
        v-else-if="jobs.length === 0"
        class="ho-panel__empty"
      >
        No handovers yet. Start one above.
      </div>

      <div v-else class="ho-panel__jobs-table">
        <div class="ho-panel__jobs-row ho-panel__jobs-row--head">
          <span>Source → Target</span>
          <span>Status</span>
          <span>Started</span>
          <span>Ended</span>
          <span></span>
        </div>
        <template v-for="job in jobs">
          <div
            :key="'job-' + job.id"
            class="ho-panel__jobs-row"
            :class="{ 'ho-panel__jobs-row--failed': isFailed(job) }"
          >
            <span class="ho-panel__job-endpoints">
              <span class="ho-panel__uid">{{ job.sourceUserId }}</span>
              <span class="ho-panel__arrow-small"> → </span>
              <span class="ho-panel__uid">{{ job.targetUserId }}</span>
            </span>
            <span>
              <span
                class="ho-panel__status"
                :class="'ho-panel__status--' + statusKey(job)"
              >
                <span
                  v-if="isRunning(job)"
                  class="ho-panel__status-spinner"
                  aria-hidden="true"
                ></span>
                <span
                  v-else-if="statusKey(job) !== 'queued'"
                  class="ho-panel__status-dot"
                  aria-hidden="true"
                ></span>
                {{ statusLabel(job) }}
              </span>
            </span>
            <span class="ho-panel__ts">{{ formatDate(job.startedAt) || "—" }}</span>
            <span class="ho-panel__ts">{{ formatDate(job.endedAt) || "—" }}</span>
            <span class="ho-panel__jobs-actions">
              <button
                type="button"
                class="iz-btn iz-btn--sm"
                @click="toggleExpandJob(job.id)"
              >{{ expandedJobs[job.id] ? "Hide" : "Events" }}</button>
              <button
                v-if="isFailed(job)"
                type="button"
                class="iz-btn iz-btn--sm iz-btn--primary"
                :disabled="saving"
                @click="onRetryClick(job)"
              >↻ Retry</button>
            </span>
          </div>
          <div
            v-if="expandedJobs[job.id]"
            :key="'events-' + job.id"
            class="ho-panel__events"
          >
            <div class="ho-panel__label">EVENTS</div>
            <div
              v-if="eventsByJob[job.id] && eventsByJob[job.id].loading"
              class="iz-state ho-panel__field-state"
            >Loading events…</div>
            <div
              v-else-if="eventsByJob[job.id] && eventsByJob[job.id].error"
              class="ho-panel__field-error"
            >
              {{ eventsByJob[job.id].error }}
              <button
                type="button"
                class="ho-panel__retry-link"
                @click="loadJobEvents(job.id, true)"
              >Retry</button>
            </div>
            <div
              v-else-if="eventsByJob[job.id] && eventsByJob[job.id].events.length"
              class="ho-panel__events-grid"
            >
              <template v-for="(ev, i) in eventsByJob[job.id].events">
                <span
                  :key="'ev-icon-' + job.id + '-' + i"
                  :class="'ho-panel__events-icon ho-panel__events-icon--' + eventIcon(ev)"
                >{{ eventIconChar(ev) }}</span>
                <span
                  :key="'ev-ts-' + job.id + '-' + i"
                  class="ho-panel__events-ts"
                >{{ formatDateTime(ev.createdAt) }}</span>
                <span
                  :key="'ev-msg-' + job.id + '-' + i"
                  class="ho-panel__events-msg"
                >{{ ev.message || ev.step || "—" }}</span>
              </template>
            </div>
            <div v-else class="iz-state ho-panel__field-state">
              No events recorded for this job.
            </div>
          </div>
        </template>
      </div>

      <div
        v-if="totalPages > 1"
        class="iz-pagination ho-panel__pagination"
      >
        <span class="ho-panel__pagination-info">
          Showing {{ pageRange }} of {{ jobsTotal }} jobs
        </span>
        <div class="ho-panel__pagination-buttons">
          <button
            type="button"
            :disabled="jobsPage <= 1"
            @click="setPage(jobsPage - 1)"
          >‹</button>
          <button
            type="button"
            :disabled="jobsPage >= totalPages"
            @click="setPage(jobsPage + 1)"
          >›</button>
        </div>
      </div>
    </div>

    <!-- ── Password confirmation modal ─────────────────────────────── -->
    <div
      v-if="confirmOpen"
      class="iz-modal-backdrop"
      role="dialog"
      aria-modal="true"
      @click.self="closeConfirm"
      @keydown.esc="closeConfirm"
    >
      <div class="iz-modal ho-panel__modal">
        <div class="iz-modal__header">
          <span>Confirm with your password</span>
          <button
            type="button"
            class="ho-panel__modal-close"
            :disabled="saving"
            aria-label="Close"
            @click="closeConfirm"
          >×</button>
        </div>
        <form
          class="iz-modal__body ho-panel__modal-body"
          autocomplete="on"
          @submit.prevent="saveAction"
        >
          <p class="iz-modal__confirm-text">
            {{ confirmActionLabel }} requires re-confirming your admin
            password.
          </p>
          <input
            type="text"
            :value="currentUserUid"
            name="username"
            autocomplete="username"
            class="iz-hidden-username"
            tabindex="-1"
            aria-hidden="true"
            readonly
          />
          <input
            ref="confirmPasswordInput"
            type="password"
            v-model="confirmPassword"
            class="ho-panel__modal-password"
            name="password"
            placeholder="Your admin password"
            :disabled="saving"
            autocomplete="current-password"
          />
          <div
            v-if="saveError"
            class="ho-panel__field-error ho-panel__modal-error"
          >{{ saveError }}</div>
        </form>
        <div class="iz-modal__confirm-actions">
          <button
            type="button"
            class="iz-btn iz-btn--ghost"
            :disabled="saving"
            @click="closeConfirm"
          >Cancel</button>
          <button
            type="button"
            class="iz-btn iz-btn--primary"
            :disabled="!canConfirm"
            @click="saveAction"
          >
            <span
              v-if="saving"
              class="ho-panel__spinner"
              aria-hidden="true"
            ></span>
            Confirm &amp; {{ confirmVerb }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl, generateOcsUrl } from "@nextcloud/router";

const ORG_OCS_HEADERS = {
  "OCS-APIRequest": "true",
  Accept: "application/json",
  "Content-Type": "application/json",
};

function unwrapOcs(res) {
  const d = res && res.data;
  if (d && d.ocs && d.ocs.data) return d.ocs.data;
  return d || {};
}

function safeUuid() {
  if (typeof crypto !== "undefined" && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  // Fallback UUID v4 using crypto.getRandomValues.
  const buf = new Uint8Array(16);
  crypto.getRandomValues(buf);
  buf[6] = (buf[6] & 0x0f) | 0x40;
  buf[8] = (buf[8] & 0x3f) | 0x80;
  const hex = Array.from(buf, (b) => b.toString(16).padStart(2, "0")).join("");
  return (
    hex.slice(0, 8) +
    "-" +
    hex.slice(8, 12) +
    "-" +
    hex.slice(12, 16) +
    "-" +
    hex.slice(16, 20) +
    "-" +
    hex.slice(20)
  );
}

export default {
  name: "HandoverPanel",
  emits: ["reload"],
  props: {
    org: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      form: {
        sourceUserId: "",
        targetUserId: "",
        dryRun: false,
        removeSourceFromGroups: false,
        remapDeckContent: true,
      },
      dryRunResult: null,
      startError: null,

      jobs: [],
      jobsLoading: false,
      jobsError: null,
      jobsPage: 1,
      jobsPageSize: 10,
      jobsTotal: 0,

      eventsByJob: {},
      expandedJobs: {},

      confirmOpen: false,
      confirmPassword: "",
      // 'start' | { type: 'retry', jobId }
      confirmAction: null,
      saving: false,
      saveError: null,
    };
  },
  computed: {
    sortedMembers() {
      const arr = (this.org && this.org.members) || [];
      const copy = arr.slice();
      copy.sort((a, b) =>
        (a.displayName || a.userId || "").localeCompare(
          b.displayName || b.userId || "",
        ),
      );
      return copy;
    },
    availableTargets() {
      const src = this.form.sourceUserId;
      return this.sortedMembers.filter((m) => m.userId !== src);
    },
    notEnoughMembers() {
      return this.sortedMembers.length < 2;
    },
    canStart() {
      if (this.saving) return false;
      if (this.notEnoughMembers) return false;
      if (!this.form.sourceUserId || !this.form.targetUserId) return false;
      if (this.form.sourceUserId === this.form.targetUserId) return false;
      return true;
    },
    canConfirm() {
      return !this.saving && this.confirmPassword.length > 0;
    },
    confirmActionLabel() {
      if (this.confirmAction === "start") return "Starting a handover";
      if (this.confirmAction && this.confirmAction.type === "retry") {
        return "Retrying a handover job";
      }
      return "This action";
    },
    confirmVerb() {
      if (this.confirmAction && this.confirmAction.type === "retry") {
        return "retry";
      }
      return "start";
    },
    currentUserUid() {
      const oc = typeof window !== "undefined" ? window.OC : null;
      if (oc) {
        if (oc.currentUser) return oc.currentUser;
        if (typeof oc.getCurrentUser === "function") {
          const u = oc.getCurrentUser();
          if (u && u.uid) return u.uid;
        }
      }
      return "";
    },
    hasActiveJobs() {
      return this.jobs.some(
        (j) => j.status === "queued" || j.status === "running",
      );
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.jobsTotal / this.jobsPageSize));
    },
    pageRange() {
      const start = (this.jobsPage - 1) * this.jobsPageSize + 1;
      const end = Math.min(this.jobsTotal, this.jobsPage * this.jobsPageSize);
      return start + "–" + end;
    },
  },
  mounted() {
    this._pollTimer = null;
    this._visHandler = () => {
      // No-op on hidden; on becoming visible again, fire an immediate
      // refresh so the admin sees any status changes that happened while
      // the tab was in the background.
      if (document.visibilityState === "visible") this.loadJobs();
    };
    document.addEventListener("visibilitychange", this._visHandler);
    this.loadJobs();
  },
  beforeDestroy() {
    this.stopPolling();
    if (this._visHandler) {
      document.removeEventListener("visibilitychange", this._visHandler);
    }
  },
  methods: {
    memberLabel(m) {
      const parts = [m.displayName || m.userId];
      if (m.email) parts.push(m.email);
      parts.push("uid: " + m.userId);
      return parts.join(" · ");
    },
    onSourceChange() {
      // If the new source equals the current target, clear target so the
      // dropdown never displays "source = target".
      if (this.form.targetUserId === this.form.sourceUserId) {
        this.form.targetUserId = "";
      }
      this.clearDryRun();
    },
    clearDryRun() {
      this.dryRunResult = null;
      this.startError = null;
    },
    resetForm() {
      this.form = {
        sourceUserId: "",
        targetUserId: "",
        dryRun: false,
        removeSourceFromGroups: false,
        remapDeckContent: true,
      };
      this.dryRunResult = null;
      this.startError = null;
    },
    async loadJobs() {
      if (this.jobsLoading) return;
      this.jobsLoading = true;
      this.jobsError = null;
      try {
        const offset = (this.jobsPage - 1) * this.jobsPageSize;
        const res = await axios.get(
          generateOcsUrl(
            "/apps/organization/organizations/" +
              this.org.profile.id +
              "/handover/jobs",
          ),
          {
            params: {
              limit: this.jobsPageSize,
              offset,
              format: "json",
            },
            headers: ORG_OCS_HEADERS,
          },
        );
        const data = unwrapOcs(res);
        // The API's response shape isn't fully specified; accept a bare
        // array, a wrapped { jobs, total } object, or a nested envelope.
        if (Array.isArray(data)) {
          this.jobs = data;
          this.jobsTotal = data.length;
        } else if (data.jobs) {
          this.jobs = data.jobs;
          this.jobsTotal =
            typeof data.total === "number" ? data.total : data.jobs.length;
        } else {
          this.jobs = [];
          this.jobsTotal = 0;
        }
      } catch (e) {
        this.jobsError = this.extractServerError(
          e,
          "Couldn't load handover jobs.",
        );
      } finally {
        this.jobsLoading = false;
        this.updatePolling();
      }
    },
    updatePolling() {
      if (this.hasActiveJobs) this.startPolling();
      else this.stopPolling();
    },
    startPolling() {
      if (this._pollTimer) return;
      this._pollTimer = setInterval(() => {
        if (document.visibilityState !== "visible") return;
        this.loadJobs();
      }, 5000);
    },
    stopPolling() {
      if (this._pollTimer) {
        clearInterval(this._pollTimer);
        this._pollTimer = null;
      }
    },
    setPage(p) {
      if (p < 1 || p > this.totalPages) return;
      this.jobsPage = p;
      this.loadJobs();
    },
    toggleExpandJob(jobId) {
      if (this.expandedJobs[jobId]) {
        this.$set(this.expandedJobs, jobId, false);
        return;
      }
      this.$set(this.expandedJobs, jobId, true);
      if (!this.eventsByJob[jobId]) {
        this.loadJobEvents(jobId, false);
      }
    },
    async loadJobEvents(jobId, force) {
      if (!force && this.eventsByJob[jobId] && this.eventsByJob[jobId].events) {
        return;
      }
      this.$set(this.eventsByJob, jobId, { events: null, loading: true, error: null });
      try {
        const res = await axios.get(
          generateOcsUrl(
            "/apps/organization/organizations/" +
              this.org.profile.id +
              "/handover/jobs/" +
              jobId +
              "/events",
          ),
          {
            params: { limit: 50, offset: 0, format: "json" },
            headers: ORG_OCS_HEADERS,
          },
        );
        const data = unwrapOcs(res);
        const events = Array.isArray(data)
          ? data
          : (data.events || []);
        this.$set(this.eventsByJob, jobId, {
          events,
          loading: false,
          error: null,
        });
      } catch (e) {
        this.$set(this.eventsByJob, jobId, {
          events: null,
          loading: false,
          error: this.extractServerError(e, "Couldn't load events."),
        });
      }
    },
    onStartClick() {
      if (!this.canStart) return;
      // Dry-run path: no password modal (server-side read-only per spec).
      if (this.form.dryRun) {
        this.submitDryRun();
        return;
      }
      this.confirmAction = "start";
      this.confirmPassword = "";
      this.saveError = null;
      this.confirmOpen = true;
      this.focusConfirmInput();
    },
    onRetryClick(job) {
      // Defensive: only failed jobs are retryable. The Retry button is
      // already hidden for non-failed rows; double-check in case of a
      // race between polling and click.
      if (!this.isFailed(job)) return;
      this.confirmAction = { type: "retry", jobId: job.id };
      this.confirmPassword = "";
      this.saveError = null;
      this.confirmOpen = true;
      this.focusConfirmInput();
    },
    closeConfirm() {
      if (this.saving) return;
      this.confirmOpen = false;
      this.confirmPassword = "";
      this.confirmAction = null;
    },
    focusConfirmInput() {
      const tryFocus = () => {
        const el = this.$refs.confirmPasswordInput;
        if (el && el.focus) {
          el.focus();
          if (el.select) el.select();
        }
      };
      window.requestAnimationFrame(() => {
        window.requestAnimationFrame(tryFocus);
      });
    },
    async submitDryRun() {
      if (!this.canStart) return;
      this.saving = true;
      this.confirmAction = "start";
      this.startError = null;
      this.dryRunResult = null;
      try {
        const body = this.buildHandoverBody(true);
        const res = await axios.post(
          generateOcsUrl(
            "/apps/organization/organizations/" +
              this.org.profile.id +
              "/handover",
          ),
          body,
          {
            params: { format: "json" },
            headers: {
              ...ORG_OCS_HEADERS,
              "Idempotency-Key": safeUuid(),
            },
          },
        );
        // Server's dry-run payload shape isn't spec'd — render whatever
        // comes back so the admin has visibility into the preview.
        const data = unwrapOcs(res);
        this.dryRunResult = JSON.stringify(data, null, 2);
      } catch (e) {
        this.startError = this.extractServerError(
          e,
          "Dry run failed. Try again.",
        );
      } finally {
        this.saving = false;
        this.confirmAction = null;
      }
    },
    async saveAction() {
      if (!this.canConfirm) return;
      this.saving = true;
      this.saveError = null;
      const action = this.confirmAction;
      try {
        await axios.post(generateUrl("/login/confirm"), {
          password: this.confirmPassword,
        });

        if (action === "start") {
          await axios.post(
            generateOcsUrl(
              "/apps/organization/organizations/" +
                this.org.profile.id +
                "/handover",
            ),
            this.buildHandoverBody(false),
            {
              params: { format: "json" },
              headers: {
                ...ORG_OCS_HEADERS,
                "Idempotency-Key": safeUuid(),
              },
            },
          );
        } else if (action && action.type === "retry") {
          await axios.post(
            generateOcsUrl(
              "/apps/organization/organizations/" +
                this.org.profile.id +
                "/handover/jobs/" +
                action.jobId +
                "/retry",
            ),
            null,
            {
              params: { failedStepsOnly: true, format: "json" },
              headers: {
                ...ORG_OCS_HEADERS,
                "Idempotency-Key": safeUuid(),
              },
            },
          );
        }

        // Success. Close modal, refresh jobs, and if the source may have
        // left the org (removeSourceFromGroups), bubble @reload so
        // OrgListPanel refetches org.members.
        this.confirmOpen = false;
        this.confirmPassword = "";
        this.confirmAction = null;
        if (action === "start" && this.form.removeSourceFromGroups) {
          this.$emit("reload");
        }
        await this.loadJobs();
      } catch (e) {
        const status = e && e.response && e.response.status;
        const url =
          (e && e.response && e.response.config && e.response.config.url) ||
          "";
        if (url.indexOf("/login/confirm") !== -1 && status === 403) {
          this.saveError = "Wrong password. Try again.";
          this.confirmPassword = "";
          this.focusConfirmInput();
        } else {
          this.saveError = this.extractServerError(
            e,
            "Couldn't complete the action. Try again.",
          );
        }
      } finally {
        this.saving = false;
      }
    },
    buildHandoverBody(forceDryRun) {
      return {
        sourceUserId: this.form.sourceUserId,
        targetUserId: this.form.targetUserId,
        dryRun: forceDryRun || !!this.form.dryRun,
        removeSourceFromGroups: !!this.form.removeSourceFromGroups,
        remapDeckContent: !!this.form.remapDeckContent,
      };
    },
    statusKey(job) {
      const s = (job.status || "").toLowerCase();
      if (["queued", "running", "succeeded", "failed", "cancelled"].indexOf(s) !== -1) {
        return s;
      }
      return "queued";
    },
    statusLabel(job) {
      const s = this.statusKey(job);
      return s.charAt(0).toUpperCase() + s.slice(1);
    },
    isRunning(job) {
      return this.statusKey(job) === "running";
    },
    isFailed(job) {
      return this.statusKey(job) === "failed";
    },
    eventIcon(ev) {
      const s = (ev.status || "").toLowerCase();
      if (s === "succeeded" || s === "ok") return "ok";
      if (s === "failed" || s === "error") return "fail";
      return "neutral";
    },
    eventIconChar(ev) {
      const t = this.eventIcon(ev);
      if (t === "ok") return "✓";
      if (t === "fail") return "✗";
      return "·";
    },
    formatDate(input) {
      if (!input) return "";
      const d = new Date(input);
      if (isNaN(d.getTime())) return "";
      return d.toLocaleString(undefined, {
        month: "short",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
      });
    },
    formatDateTime(input) {
      if (!input) return "";
      const d = new Date(input);
      if (isNaN(d.getTime())) return "";
      return d.toLocaleTimeString(undefined, {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
      });
    },
    extractServerError(err, fallback) {
      if (!err) return fallback;
      if (!err.response) return "Couldn't reach the server. Try again.";
      const data = err.response.data;
      const ocsMsg =
        data && data.ocs && data.ocs.meta && data.ocs.meta.message;
      if (ocsMsg) return ocsMsg;
      if (data && typeof data.message === "string" && data.message) {
        return data.message;
      }
      return fallback + " (HTTP " + err.response.status + ")";
    },
  },
};
</script>

<style scoped>
.ho-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ho-panel__card {
  background: var(--bg-card);
  border: 1px solid var(--color-border, var(--color-border));
  border-radius: var(--radius-lg);
  padding: 16px;
}

.ho-panel__label {
  font-size: var(--iz-fs-micro);
  font-weight: 700;
  color: var(--color-text-muted, var(--color-text-secondary));
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 10px;
}

.ho-panel__initiate-grid {
  display: grid;
  grid-template-columns: 1fr 40px 1fr;
  gap: 10px;
  align-items: end;
  margin-bottom: 14px;
}

.ho-panel__arrow {
  text-align: center;
  padding-bottom: 8px;
  color: var(--color-text-muted);
  font-size: var(--iz-fs-xl);
}

.ho-panel__field-label {
  display: block;
  font-size: var(--iz-fs-xs);
  color: var(--color-text-secondary);
  margin-bottom: 4px;
  font-weight: 600;
}

.ho-panel__req {
  color: var(--color-danger-text);
}



.ho-panel__input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.ho-panel__options {
  background: var(--bg-subtle);
  border: 1px dashed var(--color-border-strong);
  border-radius: var(--radius-el);
  padding: 10px 12px;
  margin-bottom: 14px;
}

.ho-panel__option {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--iz-fs-sm);
  color: var(--color-text-primary);
  margin-bottom: 6px;
  cursor: pointer;
  user-select: none;
}

.ho-panel__option:last-child {
  margin-bottom: 0;
}

.ho-panel__option input {
  margin: 0;
  cursor: pointer;
}

.ho-panel__hint {
  color: var(--color-text-secondary);
}

.ho-panel__warning {
  background: var(--color-warning-bg);
  border: 1px solid var(--color-warning-text);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
  font-size: var(--iz-fs-xs);
  color: var(--color-warning-text);
  margin-bottom: 12px;
  line-height: 1.4;
}

.ho-panel__dry-result {
  background: var(--iz-success-bg);
  border: 1px solid var(--color-success-bg);
  border-radius: var(--radius-sm);
  padding: 10px 12px;
  margin-bottom: 12px;
}

.ho-panel__dry-heading {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--iz-fs-sm);
  font-weight: 700;
  color: var(--color-success-text);
  margin-bottom: 8px;
}

.ho-panel__dry-json {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: var(--iz-fs-xs);
  color: var(--color-text-primary);
  background: var(--bg-card);
  border: 1px solid var(--color-success-bg);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
  overflow-x: auto;
  margin: 0;
  white-space: pre;
  max-height: 260px;
  overflow-y: auto;
}
.ho-panel__field-state {
  margin-bottom: 10px;
}

.ho-panel__field-error {
  font-size: var(--iz-fs-sm);
  color: var(--color-danger-text);
  background: var(--color-danger-bg);
  border: 1px solid var(--color-danger-bg);
  border-radius: var(--radius-sm);
  padding: 6px 10px;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.ho-panel__retry-link {
  background: var(--bg-card);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  color: var(--accent);
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  padding: 4px 10px;
  cursor: pointer;
}

.ho-panel__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

/* ── Buttons ─────────────────────────────────────────────────────────── */











.ho-panel__spinner {
  width: 12px;
  height: 12px;
  border: 2px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: ho-panel-spin 0.8s linear infinite;
}

@keyframes ho-panel-spin {
  to { transform: rotate(360deg); }
}

/* ── Jobs section ────────────────────────────────────────────────────── */
.ho-panel__jobs-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.ho-panel__jobs-header-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ho-panel__live {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: var(--iz-success-bg);
  color: var(--color-success-text);
  padding: 3px 8px;
  border-radius: var(--radius-pill);
  font-size: var(--iz-fs-xs);
  font-weight: 700;
}

.ho-panel__live-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--color-success);
  animation: ho-panel-pulse 2s ease-in-out infinite;
}

@keyframes ho-panel-pulse {
  0%, 100% { opacity: 1; }
  50%      { opacity: 0.4; }
}

.ho-panel__refresh {
  background: transparent;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  padding: 3px 10px;
  color: var(--color-text-secondary);
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.ho-panel__refresh:hover:not(:disabled) {
  background: var(--bg-subtle);
  color: var(--color-text-primary);
}

.ho-panel__refresh:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.ho-panel__empty {
  padding: 24px 12px;
  text-align: center;
  font-size: var(--iz-fs-md);
  color: var(--color-text-muted);
  font-style: italic;
  background: var(--bg-subtle);
  border: 1px dashed var(--color-border-strong);
  border-radius: var(--radius-el);
}

.ho-panel__jobs-table {
  border: 1px solid var(--bg-subtle);
  border-radius: var(--radius-el);
  overflow: hidden;
}

.ho-panel__jobs-row {
  display: grid;
  grid-template-columns: 2fr 1fr 1.2fr 1.2fr 1fr;
  gap: 10px;
  padding: 10px 14px;
  font-size: var(--iz-fs-sm);
  align-items: center;
  border-bottom: 1px solid var(--bg-subtle);
}

.ho-panel__jobs-row:last-child {
  border-bottom: none;
}

.ho-panel__jobs-row--head {
  background: var(--bg-subtle);
  font-size: var(--iz-fs-xs);
  font-weight: 700;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.ho-panel__jobs-row--failed {
  background: var(--color-danger-bg);
}

.ho-panel__job-endpoints {
  color: var(--color-text-primary);
}

.ho-panel__uid {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.ho-panel__arrow-small {
  color: var(--color-text-muted);
}

.ho-panel__ts {
  color: var(--color-text-secondary);
  font-size: var(--iz-fs-xs);
}

.ho-panel__jobs-actions {
  display: flex;
  gap: 4px;
  justify-content: flex-end;
}

/* Status pills */
.ho-panel__status {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: var(--radius-pill);
  font-size: var(--iz-fs-xs);
  font-weight: 700;
  text-transform: capitalize;
}

.ho-panel__status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.ho-panel__status-spinner {
  display: inline-block;
  width: 8px;
  height: 8px;
  border: 2px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: ho-panel-spin 0.8s linear infinite;
}

.ho-panel__status--queued {
  background: var(--bg-subtle);
  color: var(--color-text-secondary);
}

.ho-panel__status--running {
  background: var(--iz-accent-bg);
  color: var(--accent-strong);
}

.ho-panel__status--succeeded {
  background: var(--iz-success-bg);
  color: var(--color-success-text);
}

.ho-panel__status--failed {
  background: var(--iz-danger-bg);
  color: var(--color-danger-text);
}

.ho-panel__status--cancelled {
  background: var(--iz-warning-bg);
  color: var(--color-warning-text);
}

/* Expanded events */
.ho-panel__events {
  padding: 12px 20px;
  background: var(--bg-subtle);
  border-bottom: 1px solid var(--bg-subtle);
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary);
}

.ho-panel__events:last-child {
  border-bottom: none;
}

.ho-panel__events-grid {
  display: grid;
  grid-template-columns: auto auto 1fr;
  gap: 6px 14px;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: var(--iz-fs-xs);
}

.ho-panel__events-icon {
  font-weight: 700;
}

.ho-panel__events-icon--ok      { color: var(--color-success-text); }
.ho-panel__events-icon--fail    { color: var(--color-danger-text); }
.ho-panel__events-icon--neutral { color: var(--color-text-muted); }

.ho-panel__events-ts {
  color: var(--color-text-secondary);
  white-space: nowrap;
}

.ho-panel__events-msg {
  color: var(--color-text-primary);
  word-break: break-word;
}

/* Pagination */
/* Chrome from .iz-pagination; only placement is local. */
.ho-panel__pagination {
  justify-content: space-between;
  margin-top: 10px;
}

.ho-panel__pagination-info {
  color: var(--color-text-secondary);
}

.ho-panel__pagination-buttons {
  display: flex;
  gap: 4px;
}

.ho-panel__pagination-buttons button {
  border: 1px solid var(--color-border-strong);
  background: var(--bg-card);
  color: var(--color-text-primary);
  border-radius: var(--radius-sm);
  padding: 3px 10px;
  font-size: var(--iz-fs-xs);
  cursor: pointer;
}

.ho-panel__pagination-buttons button:disabled {
  color: var(--color-text-muted);
  cursor: not-allowed;
}

/* ── Password confirmation modal (mirrors SubscriptionPanel's) ─────── */



.ho-panel__modal-close {
  background: transparent;
  border: 0;
  font-size: var(--iz-fs-xl);
  line-height: 1;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: 2px 8px;
  border-radius: var(--radius-sm);
}

.ho-panel__modal-close:hover:not(:disabled) {
  background: var(--bg-subtle);
  color: var(--color-text-primary);
}

.ho-panel__modal-close:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.ho-panel__modal-body {
  padding: 16px 18px;
}

.ho-panel__modal-password {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-el);
  font-size: var(--iz-fs-md);
  background: var(--bg-card);
  box-sizing: border-box;
  outline: none;
  transition: border-color 0.15s;
}

.ho-panel__modal-password:focus {
  border-color: var(--accent);
}

.ho-panel__modal-error {
  margin-top: 12px;
}

@media (max-width: 720px) {
  .ho-panel__initiate-grid {
    grid-template-columns: 1fr;
  }
  .ho-panel__arrow {
    display: none;
  }
  .ho-panel__jobs-row {
    grid-template-columns: 1fr 1fr;
    row-gap: 6px;
  }
  .ho-panel__jobs-row--head {
    display: none;
  }
}
</style>
