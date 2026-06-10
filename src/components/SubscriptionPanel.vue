<template>
  <div class="sub-panel">
    <!-- ── Empty state (no active subscription) ─────────────────────── -->
    <div
      v-if="!hasSubscription"
      class="sub-panel__empty"
    >
      <div class="sub-panel__empty-title">No active subscription</div>
      <div class="sub-panel__empty-body">
        This organization has no active subscription record yet. Subscription
        edits require an existing active record on the server.
      </div>
    </div>

    <!-- ── Read mode ────────────────────────────────────────────────── -->
    <div v-else-if="mode === 'read'">
      <!-- Top row: Status + Plan -->
      <div class="sub-panel__row sub-panel__row--two">
        <div class="sub-panel__card">
          <div class="sub-panel__label">STATUS</div>
          <span
            class="sub-panel__pill"
            :class="'sub-panel__pill--' + statusTone"
          >
            <span class="sub-panel__pill-dot" aria-hidden="true"></span>
            {{ statusLabel }}
          </span>
          <div class="sub-panel__sub" v-if="sub.startedAt">
            Since {{ formatDate(sub.startedAt) }}
          </div>
        </div>
        <div class="sub-panel__card">
          <div class="sub-panel__label">PLAN</div>
          <div class="sub-panel__plan-line">
            <span class="sub-panel__plan-pill">{{ sub.planName }}</span>
            <span v-if="sub.isPublic" class="sub-panel__plan-meta">· Public plan</span>
            <span v-else class="sub-panel__plan-meta">· Private plan</span>
          </div>
          <div class="sub-panel__sub">{{ priceLine }}</div>
        </div>
      </div>

      <!-- Dates -->
      <div class="sub-panel__card sub-panel__row--full">
        <div class="sub-panel__label">DATES</div>
        <div class="sub-panel__kv-grid">
          <div>
            <div class="sub-panel__kv-label">Started</div>
            <div class="sub-panel__kv-value">{{ formatDate(sub.startedAt) || "—" }}</div>
          </div>
          <div>
            <div class="sub-panel__kv-label">Ends</div>
            <div class="sub-panel__kv-value">
              {{ formatDate(sub.endedAt) || "—" }}
              <span
                v-if="endsRelative"
                class="sub-panel__kv-relative"
                :class="{ 'sub-panel__kv-relative--past': endsInPast }"
              >· {{ endsRelative }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Limits -->
      <div class="sub-panel__card sub-panel__row--full">
        <div class="sub-panel__label">LIMITS</div>
        <div class="sub-panel__kv-grid">
          <div>
            <div class="sub-panel__kv-label">Members</div>
            <div class="sub-panel__limit-row">
              <span class="sub-panel__limit-num">{{ memberCount }}</span>
              <span class="sub-panel__limit-of">of {{ sub.maxMembers || "∞" }}</span>
            </div>
            <div class="sub-panel__bar">
              <div
                class="sub-panel__bar-fill"
                :style="{ width: memberPct + '%' }"
              ></div>
            </div>
          </div>
          <div>
            <div class="sub-panel__kv-label">Projects</div>
            <div class="sub-panel__limit-row">
              <span class="sub-panel__limit-num">{{ projectCount }}</span>
              <span class="sub-panel__limit-of">of {{ sub.maxProjects || "∞" }}</span>
            </div>
            <div class="sub-panel__bar">
              <div
                class="sub-panel__bar-fill"
                :style="{ width: projectPct + '%' }"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Storage + Pricing -->
      <div class="sub-panel__row sub-panel__row--two">
        <div class="sub-panel__card">
          <div class="sub-panel__label">STORAGE</div>
          <div class="sub-panel__kv-line">
            <span>Shared per project</span>
            <span class="sub-panel__kv-strong">{{ sub.sharedStorageGb }} GB</span>
          </div>
          <div class="sub-panel__kv-line">
            <span>Private per user</span>
            <span class="sub-panel__kv-strong">{{ sub.privateStorageGb }} GB</span>
          </div>
        </div>
        <div class="sub-panel__card">
          <div class="sub-panel__label">PRICING</div>
          <div class="sub-panel__kv-line">
            <span>Monthly</span>
            <span class="sub-panel__kv-strong">{{ formatMoney(sub.price, sub.currency) }}</span>
          </div>
          <div class="sub-panel__kv-line">
            <span>Yearly</span>
            <span class="sub-panel__kv-strong">{{ formatMoney(sub.price * 12, sub.currency) }}</span>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="sub-panel__actions">
        <button
          type="button"
          class="sub-panel__btn sub-panel__btn--primary"
          @click="enterEditMode"
        >Edit subscription…</button>
      </div>
    </div>

    <!-- ── Edit mode ────────────────────────────────────────────────── -->
    <div v-else>
      <div class="sub-panel__edit-header">
        <div class="sub-panel__edit-title">Edit subscription</div>
        <div class="sub-panel__edit-meta">{{ org.profile.name }}</div>
      </div>

      <!-- Plan -->
      <div class="sub-panel__field">
        <label class="sub-panel__field-label" for="sub-plan">
          Plan <span class="sub-panel__field-required">*</span>
        </label>
        <div v-if="plansLoading" class="sub-panel__field-state">Loading plans…</div>
        <div v-else-if="plansError" class="sub-panel__field-error">
          {{ plansError }}
          <button
            type="button"
            class="sub-panel__retry"
            @click="loadPlans"
          >Retry</button>
        </div>
        <select
          v-else
          id="sub-plan"
          v-model.number="form.planId"
          class="sub-panel__input"
          :disabled="saving"
        >
          <option
            v-for="p in plans"
            :key="p.id"
            :value="p.id"
          >
            {{ p.name }} — {{ planSummary(p) }}
          </option>
        </select>
        <div class="sub-panel__field-hint">
          Changing the plan auto-fills caps and pricing from the chosen plan.
        </div>
      </div>

      <!-- Status -->
      <div class="sub-panel__field">
        <label class="sub-panel__field-label">
          Status <span class="sub-panel__field-required">*</span>
        </label>
        <div class="sub-panel__status-row">
          <label
            v-for="opt in statusOptions"
            :key="opt.value"
            class="sub-panel__status-pill"
            :class="{ 'sub-panel__status-pill--active': form.status === opt.value }"
          >
            <input
              type="radio"
              :value="opt.value"
              v-model="form.status"
              :disabled="saving"
            />
            {{ opt.label }}
          </label>
        </div>
      </div>

      <!-- Extend duration -->
      <div class="sub-panel__field">
        <label class="sub-panel__field-label">
          Extend duration
          <span class="sub-panel__field-optional">(optional)</span>
        </label>
        <div class="sub-panel__extend-row">
          <button
            v-for="opt in extendOptions"
            :key="opt.value || 'none'"
            type="button"
            class="sub-panel__extend-pill"
            :class="{ 'sub-panel__extend-pill--active': form.extendDuration === opt.value }"
            :disabled="saving"
            @click="form.extendDuration = opt.value"
          >{{ opt.label }}</button>
        </div>
        <div v-if="endDatePreview" class="sub-panel__field-hint">
          New end date will be {{ endDatePreview }}.
        </div>
      </div>

      <!-- Submit preview -->
      <div v-if="selectedPlan" class="sub-panel__preview">
        <div class="sub-panel__label">VALUES THAT WILL BE SUBMITTED</div>
        <div class="sub-panel__preview-grid">
          <span>Plan ID</span><span>{{ selectedPlan.id }}</span>
          <span>maxMembers</span><span>{{ selectedPlan.maxMembers }}</span>
          <span>maxProjects</span><span>{{ selectedPlan.maxProjects }}</span>
          <span>sharedStoragePerProject</span><span>{{ selectedPlan.sharedStoragePerProject }}</span>
          <span>privateStoragePerUser</span><span>{{ selectedPlan.privateStoragePerUser }}</span>
          <span v-if="form.extendDuration">extendDuration</span><span v-if="form.extendDuration">{{ form.extendDuration }}</span>
        </div>
      </div>

      <!-- Password confirmation -->
      <div class="sub-panel__password-box">
        <div class="sub-panel__password-title">⚠ Admin password required</div>
        <div class="sub-panel__password-body">
          Nextcloud requires re-confirming your password for subscription
          changes.
        </div>
        <input
          type="password"
          v-model="form.password"
          class="sub-panel__password-input"
          placeholder="Your admin password"
          :disabled="saving"
          autocomplete="current-password"
          @keydown.enter="onPasswordEnter"
        />
      </div>

      <!-- Server error -->
      <div v-if="saveError" class="sub-panel__field-error">{{ saveError }}</div>

      <!-- Actions -->
      <div class="sub-panel__actions">
        <button
          type="button"
          class="sub-panel__btn sub-panel__btn--ghost"
          :disabled="saving"
          @click="cancelEdit"
        >Cancel</button>
        <button
          type="button"
          class="sub-panel__btn sub-panel__btn--primary"
          :disabled="!canSave"
          @click="saveChanges"
        >
          <span
            v-if="saving"
            class="sub-panel__spinner"
            aria-hidden="true"
          ></span>
          Save changes
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";

// Org-app endpoints are plain Nextcloud app routes (not OCS), but the
// controllers still require the OCS-APIRequest header per their attribute
// set. Matches the pattern we already use in MembersPanel and ProjectsPanel.
const ORG_APP_HEADERS = {
  "OCS-APIRequest": "true",
  Accept: "application/json",
  "Content-Type": "application/json",
};

const STATUS_OPTIONS = [
  { value: "active", label: "Active" },
  { value: "paused", label: "Paused" },
  { value: "cancelled", label: "Cancelled" },
];

const EXTEND_OPTIONS = [
  { value: "", label: "None" },
  { value: "+1 month", label: "+1 month" },
  { value: "+3 months", label: "+3 months" },
  { value: "+6 months", label: "+6 months" },
  { value: "+1 year", label: "+1 year" },
];

export default {
  name: "SubscriptionPanel",
  emits: ["reload"],
  props: {
    org: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      mode: "read",
      plansLoading: false,
      plansError: null,
      plans: [],
      form: {
        planId: null,
        status: "active",
        extendDuration: "",
        password: "",
      },
      saving: false,
      saveError: null,
      statusOptions: STATUS_OPTIONS,
      extendOptions: EXTEND_OPTIONS,
    };
  },
  computed: {
    sub() {
      return this.org.subscription || {};
    },
    hasSubscription() {
      return this.sub.status && this.sub.status !== "none";
    },
    statusTone() {
      switch (this.sub.status) {
        case "active": return "active";
        case "paused": return "paused";
        case "cancelled": return "cancelled";
        default: return "none";
      }
    },
    statusLabel() {
      if (!this.sub.status) return "—";
      return this.sub.status.charAt(0).toUpperCase() + this.sub.status.slice(1);
    },
    priceLine() {
      if (!this.sub.price) return "—";
      return this.formatMoney(this.sub.price, this.sub.currency) + " / month";
    },
    memberCount() {
      return (this.org.members || []).length;
    },
    projectCount() {
      return (this.org.projects || []).length;
    },
    memberPct() {
      const max = this.sub.maxMembers || 0;
      if (max <= 0) return 0;
      return Math.min(100, Math.round((this.memberCount / max) * 100));
    },
    projectPct() {
      const max = this.sub.maxProjects || 0;
      if (max <= 0) return 0;
      return Math.min(100, Math.round((this.projectCount / max) * 100));
    },
    endsRelative() {
      if (!this.sub.endedAt) return "";
      const end = new Date(this.sub.endedAt);
      if (isNaN(end.getTime())) return "";
      const diffMs = end.getTime() - Date.now();
      const days = Math.round(diffMs / (1000 * 60 * 60 * 24));
      if (days === 0) return "today";
      const abs = Math.abs(days);
      let str;
      if (abs < 60) str = abs + " day" + (abs === 1 ? "" : "s");
      else if (abs < 730) {
        const months = Math.round(abs / 30);
        str = months + " month" + (months === 1 ? "" : "s");
      } else {
        const years = Math.round(abs / 365);
        str = years + " year" + (years === 1 ? "" : "s");
      }
      return days >= 0 ? "in " + str : str + " ago";
    },
    endsInPast() {
      if (!this.sub.endedAt) return false;
      const end = new Date(this.sub.endedAt);
      return !isNaN(end.getTime()) && end.getTime() < Date.now();
    },
    selectedPlan() {
      if (!this.form.planId) return null;
      for (let i = 0; i < this.plans.length; i++) {
        if (this.plans[i].id === this.form.planId) return this.plans[i];
      }
      return null;
    },
    endDatePreview() {
      if (!this.form.extendDuration) return "";
      // Parse "+N months" / "+N days" / "+N year[s]" and add to current end.
      const base = this.sub.endedAt
        ? new Date(this.sub.endedAt)
        : new Date();
      if (isNaN(base.getTime())) return "";
      const match = /^\+(\d+)\s+(day|days|month|months|year|years)$/i.exec(
        this.form.extendDuration,
      );
      if (!match) return "";
      const n = parseInt(match[1], 10);
      const unit = match[2].toLowerCase();
      const d = new Date(base.getTime());
      if (unit.startsWith("day")) {
        d.setDate(d.getDate() + n);
      } else if (unit.startsWith("month")) {
        d.setMonth(d.getMonth() + n);
      } else {
        d.setFullYear(d.getFullYear() + n);
      }
      return this.formatDate(d.toISOString());
    },
    canSave() {
      if (this.saving) return false;
      if (this.plansLoading || this.plansError) return false;
      if (!this.form.planId) return false;
      if (!this.form.password) return false;
      if (!this.selectedPlan) return false;
      return true;
    },
  },
  methods: {
    formatDate(input) {
      if (!input) return "";
      const d = new Date(input);
      if (isNaN(d.getTime())) return "";
      return d.toLocaleDateString(undefined, {
        day: "2-digit",
        month: "short",
        year: "numeric",
      });
    },
    formatMoney(amount, currency) {
      const n = Number(amount) || 0;
      const cur = currency || "EUR";
      try {
        return new Intl.NumberFormat(undefined, {
          style: "currency",
          currency: cur,
          maximumFractionDigits: 0,
        }).format(n);
      } catch (e) {
        return n.toFixed(0) + " " + cur;
      }
    },
    planSummary(p) {
      const price =
        p.price != null
          ? this.formatMoney(p.price, p.currency || "EUR") + "/mo"
          : "—";
      return (
        price +
        " · " +
        p.maxMembers +
        " members · " +
        p.maxProjects +
        " projects"
      );
    },
    enterEditMode() {
      this.mode = "edit";
      this.form.planId = this.sub.planId || null;
      this.form.status = this.sub.status || "active";
      this.form.extendDuration = "";
      this.form.password = "";
      this.saveError = null;
      if (this.plans.length === 0 && !this.plansLoading) {
        this.loadPlans();
      }
    },
    cancelEdit() {
      this.mode = "read";
      this.form.password = "";
      this.saveError = null;
    },
    async loadPlans() {
      this.plansLoading = true;
      this.plansError = null;
      try {
        const res = await axios.get(
          generateUrl("/apps/organization/plans"),
          {
            params: { limit: 200 },
            headers: ORG_APP_HEADERS,
          },
        );
        this.plans = (res.data && res.data.plans) || [];
      } catch (e) {
        this.plansError = this.extractServerError(e, "Couldn't load plans.");
      } finally {
        this.plansLoading = false;
      }
    },
    onPasswordEnter() {
      if (this.canSave) this.saveChanges();
    },
    async saveChanges() {
      if (!this.canSave) return;
      this.saving = true;
      this.saveError = null;
      try {
        // Step 1 — Nextcloud's PasswordConfirmationRequired gate.
        await axios.post(generateUrl("/login/confirm"), {
          password: this.form.password,
        });
        // Step 2 — actual subscription update. All caps fields ride along
        // from the chosen plan so the API's all-fields-required body is
        // satisfied without exposing a per-org cap override in this round.
        const p = this.selectedPlan;
        const body = {
          displayName: this.org.profile.name,
          planId: p.id,
          maxMembers: p.maxMembers,
          maxProjects: p.maxProjects,
          sharedStoragePerProject: p.sharedStoragePerProject,
          privateStoragePerUser: p.privateStoragePerUser,
          status: this.form.status,
        };
        if (this.form.extendDuration) {
          body.extendDuration = this.form.extendDuration;
        }
        await axios.put(
          generateUrl(
            "/apps/organization/organizations/" +
              this.org.profile.id +
              "/subscription",
          ),
          body,
          { headers: ORG_APP_HEADERS },
        );
        this.mode = "read";
        this.$emit("reload");
      } catch (e) {
        // Distinguish wrong-password (from /login/confirm) from PUT errors.
        const status = e && e.response && e.response.status;
        const url =
          e && e.response && e.response.config && e.response.config.url;
        if (url && url.indexOf("/login/confirm") !== -1 && status === 403) {
          this.saveError = "Wrong password. Try again.";
        } else if (status === 404) {
          this.saveError =
            "Subscription no longer exists. The organization may have been deleted.";
          this.$emit("reload");
        } else {
          this.saveError = this.extractServerError(
            e,
            "Couldn't save changes. Try again.",
          );
        }
      } finally {
        this.saving = false;
        this.form.password = "";
      }
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
.sub-panel {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

/* ── Empty state ─────────────────────────────────────────────────────── */
.sub-panel__empty {
  background: #f9fafb;
  border: 1px dashed #d0d5dd;
  border-radius: 10px;
  padding: 24px;
  text-align: center;
}

.sub-panel__empty-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  margin-bottom: 6px;
}

.sub-panel__empty-body {
  font-size: 12px;
  color: var(--color-text-muted, #6b7280);
  line-height: 1.5;
}

/* ── Layout ──────────────────────────────────────────────────────────── */
.sub-panel__row--two {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.sub-panel__row--full {
  width: 100%;
}

.sub-panel__card {
  background: #f9fafb;
  border: 1px solid #eaecf0;
  border-radius: 10px;
  padding: 14px;
}

.sub-panel__label {
  font-size: 10px;
  font-weight: 700;
  color: var(--color-text-muted, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 10px;
}

.sub-panel__sub {
  font-size: 12px;
  color: var(--color-text-muted, #6b7280);
  margin-top: 8px;
}

/* ── Status pill ─────────────────────────────────────────────────────── */
.sub-panel__pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
}

.sub-panel__pill-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.sub-panel__pill--active {
  background: rgba(16, 185, 129, 0.14);
  color: #047857;
}

.sub-panel__pill--paused {
  background: rgba(245, 158, 11, 0.14);
  color: #b45309;
}

.sub-panel__pill--cancelled {
  background: rgba(220, 38, 38, 0.12);
  color: #b91c1c;
}

.sub-panel__pill--none {
  background: #eef2f7;
  color: #6b7280;
}

/* ── Plan pill ───────────────────────────────────────────────────────── */
.sub-panel__plan-line {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.sub-panel__plan-pill {
  background: rgba(74, 144, 217, 0.12);
  color: #4a90d9;
  padding: 5px 12px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
}

.sub-panel__plan-meta {
  font-size: 11px;
  color: var(--color-text-muted, #6b7280);
}

/* ── Key-value grids ─────────────────────────────────────────────────── */
.sub-panel__kv-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 24px;
}

.sub-panel__kv-label {
  font-size: 11px;
  color: #9ca3af;
  margin-bottom: 4px;
}

.sub-panel__kv-value {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
}

.sub-panel__kv-relative {
  font-size: 11px;
  font-weight: 500;
  color: #b45309;
}

.sub-panel__kv-relative--past {
  color: #b91c1c;
}

.sub-panel__kv-line {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: var(--color-text-muted, #6b7280);
  padding: 4px 0;
}

.sub-panel__kv-strong {
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
}

/* ── Limits / progress bars ──────────────────────────────────────────── */
.sub-panel__limit-row {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.sub-panel__limit-num {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
}

.sub-panel__limit-of {
  font-size: 12px;
  color: var(--color-text-muted, #6b7280);
}

.sub-panel__bar {
  height: 4px;
  background: #eef0f3;
  border-radius: 2px;
  margin-top: 6px;
  overflow: hidden;
}

.sub-panel__bar-fill {
  height: 100%;
  background: #4a90d9;
  transition: width 0.2s;
}

/* ── Actions / buttons ───────────────────────────────────────────────── */
.sub-panel__actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 4px;
}

.sub-panel__btn {
  border: 1px solid transparent;
  border-radius: 8px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}

.sub-panel__btn--primary {
  background: #4a90d9;
  color: #fff;
  border-color: #4a90d9;
}

.sub-panel__btn--primary:hover:not(:disabled) {
  background: #3a7bc3;
  border-color: #3a7bc3;
}

.sub-panel__btn--ghost {
  background: transparent;
  color: var(--color-text-secondary, #6b7280);
  border-color: #d0d5dd;
}

.sub-panel__btn--ghost:hover:not(:disabled) {
  background: #f0f1f5;
  color: var(--color-text-primary, #1a1a2e);
}

.sub-panel__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.sub-panel__spinner {
  width: 12px;
  height: 12px;
  border: 2px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: sub-panel-spin 0.8s linear infinite;
}

@keyframes sub-panel-spin {
  to { transform: rotate(360deg); }
}

/* ── Edit mode header ────────────────────────────────────────────────── */
.sub-panel__edit-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.sub-panel__edit-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
}

.sub-panel__edit-meta {
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
}

/* ── Form fields ─────────────────────────────────────────────────────── */
.sub-panel__field {
  margin-bottom: 14px;
}

.sub-panel__field-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  margin-bottom: 6px;
}

.sub-panel__field-required {
  color: #b42318;
}

.sub-panel__field-optional {
  font-weight: 400;
  color: #9ca3af;
}

.sub-panel__field-hint {
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
  margin-top: 4px;
}

.sub-panel__field-state {
  font-size: 12px;
  color: var(--color-text-muted, #9ca3af);
  font-style: italic;
}

.sub-panel__field-error {
  font-size: 12px;
  color: #b42318;
  background: #fef3f2;
  border: 1px solid #fecdca;
  border-radius: 6px;
  padding: 8px 12px;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.sub-panel__retry {
  background: #fff;
  border: 1px solid #d0d5dd;
  border-radius: 6px;
  color: #4a90d9;
  font-size: 11px;
  font-weight: 600;
  padding: 4px 10px;
  cursor: pointer;
}

.sub-panel__input {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #d0d5dd;
  border-radius: 8px;
  font-size: 13px;
  background: #fff;
  color: var(--color-text-primary, #1a1a2e);
  outline: none;
  box-sizing: border-box;
  transition: border-color 0.15s;
}

.sub-panel__input:focus {
  border-color: #4a90d9;
}

/* ── Status radio pills ──────────────────────────────────────────────── */
.sub-panel__status-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.sub-panel__status-pill {
  flex: 1;
  min-width: 110px;
  padding: 8px 12px;
  border: 1px solid #d0d5dd;
  border-radius: 8px;
  background: #fff;
  font-size: 13px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  user-select: none;
  transition: background 0.15s, border-color 0.15s;
}

.sub-panel__status-pill input {
  margin: 0;
}

.sub-panel__status-pill--active {
  border-color: #4a90d9;
  background: rgba(74, 144, 217, 0.08);
  color: #1a1a2e;
}

/* ── Extend buttons ──────────────────────────────────────────────────── */
.sub-panel__extend-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.sub-panel__extend-pill {
  padding: 7px 14px;
  border: 1px solid #d0d5dd;
  border-radius: 8px;
  background: #fff;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  cursor: pointer;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}

.sub-panel__extend-pill:hover:not(:disabled) {
  border-color: #4a90d9;
  color: #4a90d9;
}

.sub-panel__extend-pill--active {
  border-color: #4a90d9;
  background: rgba(74, 144, 217, 0.08);
  color: #4a90d9;
}

.sub-panel__extend-pill:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ── Submit preview ──────────────────────────────────────────────────── */
.sub-panel__preview {
  background: #f9fafb;
  border: 1px dashed #d0d5dd;
  border-radius: 8px;
  padding: 10px 12px;
  margin-bottom: 14px;
}

.sub-panel__preview-grid {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 4px 16px;
  font-size: 11px;
  color: #4b5563;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.sub-panel__preview-grid > span:nth-child(even) {
  text-align: right;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
}

/* ── Password block ──────────────────────────────────────────────────── */
.sub-panel__password-box {
  background: #fffaeb;
  border: 1px solid #fec84b;
  border-radius: 8px;
  padding: 14px;
  margin-bottom: 14px;
}

.sub-panel__password-title {
  font-size: 12px;
  font-weight: 700;
  color: #b54708;
  margin-bottom: 6px;
}

.sub-panel__password-body {
  font-size: 11px;
  color: #b54708;
  margin-bottom: 10px;
  line-height: 1.5;
}

.sub-panel__password-input {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #fec84b;
  border-radius: 6px;
  font-size: 13px;
  background: #fff;
  box-sizing: border-box;
  outline: none;
}

.sub-panel__password-input:focus {
  border-color: #b54708;
}

/* ── Responsive ──────────────────────────────────────────────────────── */
@media (max-width: 720px) {
  .sub-panel__row--two,
  .sub-panel__kv-grid {
    grid-template-columns: 1fr;
  }
}
</style>
