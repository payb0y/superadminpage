<template>
  <div class="sub-panel">
    <!-- ── Empty state (no active subscription) ─────────────────────── -->
    <div
      v-if="!hasSubscription"
      class="iz-empty sub-panel__empty"
    >
      <div class="sub-panel__empty-title">No active subscription</div>
      <div class="sub-panel__empty-body">
        This organization has no active subscription record yet. Subscription
        edits require an existing active record on the server.
      </div>
    </div>

    <!-- ── Read mode ────────────────────────────────────────────────── -->
    <div
      v-else-if="mode === 'read'"
      class="sub-panel__read"
    >
      <!-- Top row: Status + Plan -->
      <div class="sub-panel__row sub-panel__row--two">
        <div class="sub-panel__card">
          <div class="sub-panel__label">STATUS</div>
          <span
            class="iz-pill iz-pill--lg"
            :class="statusTone"
          >
            <span class="iz-dot" aria-hidden="true"></span>
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
            <div class="iz-meter iz-meter--thin">
              <div
                class="iz-meter__fill"
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
            <div class="iz-meter iz-meter--thin">
              <div
                class="iz-meter__fill"
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
          <div class="sub-panel__storage-total">
            <div class="sub-panel__limit-row">
              <span class="sub-panel__limit-num">{{ formatBytes(storage.usedBytes) }}</span>
              <span class="sub-panel__limit-of">of {{ formatBytes(storage.capacityBytes) }}</span>
            </div>
            <span>{{ storagePercentageDisplay }}</span>
          </div>
          <div class="iz-meter iz-meter--thin">
            <div class="iz-meter__fill" :style="{ width: storageMeterWidth + '%' }"></div>
          </div>
          <div class="sub-panel__storage-breakdown">
            <div class="sub-panel__kv-line">
              <span>Private · {{ privateStorage.memberCount || 0 }} members</span>
              <span class="sub-panel__kv-strong">{{ storagePartDisplay(privateStorage) }}</span>
            </div>
            <div class="sub-panel__kv-line">
              <span>Shared · {{ sharedStorage.projectCount || 0 }} projects</span>
              <span class="sub-panel__kv-strong">{{ storagePartDisplay(sharedStorage) }}</span>
            </div>
          </div>
          <div class="sub-panel__sub">
            Allocation: {{ sub.privateStorageGb }} GB per user and {{ sub.sharedStorageGb }} GB per project.
            <template v-if="storage.calculatedAt"> Calculated {{ formatDateTime(storage.calculatedAt) }}.</template>
            <template v-if="storage.complete === false"> Some storage could not be measured.</template>
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
          class="iz-btn iz-btn--primary"
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
        <div v-if="plansLoading" class="iz-state sub-panel__field-state">Loading plans…</div>
        <div v-else-if="plansError" class="sub-panel__field-error">
          {{ plansError }}
          <button
            type="button"
            class="iz-btn iz-btn--sm iz-btn--accent"
            @click="loadPlans"
          >Retry</button>
        </div>
        <div v-else class="sub-panel__plan-row">
          <select
            id="sub-plan"
            v-model.number="form.planId"
            class="iz-input sub-panel__plan-select"
            :disabled="saving || customPlanOpen"
          >
            <option
              v-for="p in plans"
              :key="p.id"
              :value="p.id"
            >
              {{ p.name }} — {{ planSummary(p) }}
            </option>
          </select>
          <button
            type="button"
            class="sub-panel__plan-custom-btn"
            :class="{ 'sub-panel__plan-custom-btn--active': customPlanOpen }"
            :disabled="saving"
            @click="customPlanOpen ? closeCustomPlan() : openCustomPlan()"
          >{{ customPlanOpen ? "Cancel custom" : "+ Create custom" }}</button>
        </div>
        <div class="sub-panel__field-hint">
          Changing the plan auto-fills caps and pricing from the chosen plan.
        </div>

        <!-- Custom plan sub-form -->
        <div v-if="customPlanOpen" class="sub-panel__custom">
          <div class="sub-panel__custom-header">
            <span class="sub-panel__custom-title">NEW CUSTOM PLAN</span>
            <button
              type="button"
              class="iz-close iz-close--sm"
              :disabled="saving"
              aria-label="Cancel custom plan"
              @click="closeCustomPlan"
            >×</button>
          </div>

          <div class="sub-panel__custom-field">
            <label class="sub-panel__custom-label">
              Plan name <span class="sub-panel__field-required">*</span>
            </label>
            <input
              type="text"
              v-model="customPlan.name"
              class="iz-input"
              :disabled="saving"
              @blur="markCustomBlurred('name')"
            />
            <div
              v-if="customPlanBlurred.name && customPlanFieldErrors.name"
              class="sub-panel__field-error sub-panel__custom-field-error"
            >{{ customPlanFieldErrors.name }}</div>
          </div>

          <div class="sub-panel__custom-grid">
            <div class="sub-panel__custom-field">
              <label class="sub-panel__custom-label">
                Members <span class="sub-panel__field-required">*</span>
              </label>
              <input
                type="number"
                min="1"
                step="1"
                v-model.number="customPlan.maxMembers"
                class="iz-input"
                :disabled="saving"
                @blur="markCustomBlurred('maxMembers')"
              />
              <div
                v-if="customPlanBlurred.maxMembers && customPlanFieldErrors.maxMembers"
                class="sub-panel__field-error sub-panel__custom-field-error"
              >{{ customPlanFieldErrors.maxMembers }}</div>
            </div>
            <div class="sub-panel__custom-field">
              <label class="sub-panel__custom-label">
                Projects <span class="sub-panel__field-required">*</span>
              </label>
              <input
                type="number"
                min="1"
                step="1"
                v-model.number="customPlan.maxProjects"
                class="iz-input"
                :disabled="saving"
                @blur="markCustomBlurred('maxProjects')"
              />
              <div
                v-if="customPlanBlurred.maxProjects && customPlanFieldErrors.maxProjects"
                class="sub-panel__field-error sub-panel__custom-field-error"
              >{{ customPlanFieldErrors.maxProjects }}</div>
            </div>
          </div>

          <div class="sub-panel__custom-grid">
            <div class="sub-panel__custom-field">
              <label class="sub-panel__custom-label">
                Shared/project <span class="sub-panel__field-required">*</span>
              </label>
              <div class="sub-panel__unit-row">
                <input
                  type="number"
                  min="0"
                  step="0.1"
                  v-model.number="customPlan.sharedStorageGb"
                  class="iz-input"
                  :disabled="saving"
                  @blur="markCustomBlurred('sharedStorageGb')"
                />
                <span class="sub-panel__unit-suffix">GB</span>
              </div>
              <div
                v-if="customPlanBlurred.sharedStorageGb && customPlanFieldErrors.sharedStorageGb"
                class="sub-panel__field-error sub-panel__custom-field-error"
              >{{ customPlanFieldErrors.sharedStorageGb }}</div>
            </div>
            <div class="sub-panel__custom-field">
              <label class="sub-panel__custom-label">
                Private/user <span class="sub-panel__field-required">*</span>
              </label>
              <div class="sub-panel__unit-row">
                <input
                  type="number"
                  min="0"
                  step="0.1"
                  v-model.number="customPlan.privateStorageGb"
                  class="iz-input"
                  :disabled="saving"
                  @blur="markCustomBlurred('privateStorageGb')"
                />
                <span class="sub-panel__unit-suffix">GB</span>
              </div>
              <div
                v-if="customPlanBlurred.privateStorageGb && customPlanFieldErrors.privateStorageGb"
                class="sub-panel__field-error sub-panel__custom-field-error"
              >{{ customPlanFieldErrors.privateStorageGb }}</div>
            </div>
          </div>

          <div class="sub-panel__custom-grid">
            <div class="sub-panel__custom-field">
              <label class="sub-panel__custom-label">
                Price <span class="sub-panel__field-optional">(optional)</span>
              </label>
              <input
                type="number"
                min="0"
                step="0.01"
                v-model="customPlan.price"
                class="iz-input"
                :disabled="saving"
                @blur="markCustomBlurred('price')"
              />
              <div
                v-if="customPlanBlurred.price && customPlanFieldErrors.price"
                class="sub-panel__field-error sub-panel__custom-field-error"
              >{{ customPlanFieldErrors.price }}</div>
            </div>
            <div class="sub-panel__custom-field">
              <label class="sub-panel__custom-label">Currency</label>
              <select
                v-model="customPlan.currency"
                class="iz-input"
                :disabled="saving"
              >
                <option value="EUR">EUR</option>
                <option value="USD">USD</option>
                <option value="GBP">GBP</option>
                <option value="CHF">CHF</option>
              </select>
            </div>
          </div>

          <div
            v-if="customPlanError"
            class="sub-panel__field-error sub-panel__custom-error"
          >{{ customPlanError }}</div>
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
      <div
        v-if="customPlanOpen && canCreateCustomPlan"
        class="iz-inset sub-panel__preview"
      >
        <div class="sub-panel__label">VALUES THAT WILL BE SUBMITTED</div>
        <div class="sub-panel__preview-section">NEW PLAN</div>
        <div class="sub-panel__preview-grid">
          <span>name</span><span>{{ customPlan.name }}</span>
          <span>maxMembers</span><span>{{ customPlan.maxMembers }}</span>
          <span>maxProjects</span><span>{{ customPlan.maxProjects }}</span>
          <span>sharedStoragePerProject</span><span>{{ Math.round(customPlan.sharedStorageGb * 1073741824) }}</span>
          <span>privateStoragePerUser</span><span>{{ Math.round(customPlan.privateStorageGb * 1073741824) }}</span>
          <span v-if="customPlan.price !== '' && customPlan.price !== null">price</span>
          <span v-if="customPlan.price !== '' && customPlan.price !== null">{{ customPlan.price }}</span>
          <span>currency</span><span>{{ customPlan.currency }}</span>
          <span>isPublic</span><span>{{ customPlan.isPublic }}</span>
        </div>
        <div class="sub-panel__preview-section">SUBSCRIPTION</div>
        <div class="sub-panel__preview-grid">
          <span>planId</span><span>(new plan id)</span>
          <span>maxMembers</span><span>{{ customPlan.maxMembers }}</span>
          <span>maxProjects</span><span>{{ customPlan.maxProjects }}</span>
          <span>price</span><span>{{ customPlan.price !== "" && customPlan.price !== null ? customPlan.price : 0 }}</span>
          <span>currency</span><span>{{ customPlan.currency || "EUR" }}</span>
          <span>status</span><span>{{ form.status }}</span>
          <span v-if="form.extendDuration">extendDuration</span><span v-if="form.extendDuration">{{ form.extendDuration }}</span>
        </div>
      </div>
      <div v-else-if="!customPlanOpen && selectedPlan" class="iz-inset sub-panel__preview">
        <div class="sub-panel__label">VALUES THAT WILL BE SUBMITTED</div>
        <div class="sub-panel__preview-grid">
          <span>Plan ID</span><span>{{ selectedPlan.id }}</span>
          <span>maxMembers</span><span>{{ selectedPlan.maxMembers }}</span>
          <span>maxProjects</span><span>{{ selectedPlan.maxProjects }}</span>
          <span>sharedStoragePerProject</span><span>{{ selectedPlan.sharedStoragePerProject }}</span>
          <span>privateStoragePerUser</span><span>{{ selectedPlan.privateStoragePerUser }}</span>
          <span>price</span><span>{{ selectedPlan.price != null ? selectedPlan.price : 0 }}</span>
          <span>currency</span><span>{{ selectedPlan.currency || "EUR" }}</span>
          <span v-if="form.extendDuration">extendDuration</span><span v-if="form.extendDuration">{{ form.extendDuration }}</span>
        </div>
      </div>

      <!-- Server error (when modal is closed) -->
      <div
        v-if="saveError && !confirmOpen"
        class="sub-panel__field-error"
      >{{ saveError }}</div>

      <!-- Actions -->
      <div class="sub-panel__actions">
        <button
          type="button"
          class="iz-btn iz-btn--ghost"
          :disabled="saving"
          @click="cancelEdit"
        >Cancel</button>
        <button
          type="button"
          class="iz-btn iz-btn--primary"
          :disabled="!canSave"
          @click="openConfirm"
        >Save changes</button>
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
      <div class="iz-modal sub-panel__modal">
        <div class="iz-modal__header">
          <span>Confirm with your password</span>
          <button
            type="button"
            class="iz-close"
            aria-label="Close"
            :disabled="saving"
            @click="closeConfirm"
          >×</button>
        </div>
        <form
          class="iz-modal__body sub-panel__modal-body"
          autocomplete="on"
          @submit.prevent="saveChanges"
        >
          <p class="iz-modal__confirm-text">
            Nextcloud requires re-confirming your admin password before
            applying subscription changes.
          </p>
          <!--
            Hidden username companion: password managers pair a password
            input with the nearest preceding username/text field. Without
            this companion, managers walked up the DOM and dumped the
            username into the org-list search box. Keep this readonly
            input in the same <form> so the pair stays inside the modal.
          -->
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
            class="sub-panel__modal-password"
            name="password"
            placeholder="Your admin password"
            :disabled="saving"
            autocomplete="current-password"
            @keydown.enter="onPasswordEnter"
          />
          <div
            v-if="saveError"
            class="sub-panel__field-error sub-panel__modal-error"
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
            @click="saveChanges"
          >
            <span
              v-if="saving"
              class="iz-spinner"
              aria-hidden="true"
            ></span>
            Confirm &amp; save
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl, generateOcsUrl } from "@nextcloud/router";

// Org-app endpoints are exposed over OCS — use generateOcsUrl + the
// OCS-APIRequest header + format=json query, then unwrap res.data.ocs.data.
// Matches the pattern MembersPanel already uses for /apps/organization/...
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
      },
      // Password confirmation modal — opened by Save, owns its own state.
      confirmOpen: false,
      confirmPassword: "",
      saving: false,
      saveError: null,
      // Inline "create custom plan" sub-form, expanded under the Plan field.
      // Mutually exclusive with the regular plan dropdown.
      customPlanOpen: false,
      customPlan: {
        name: "",
        maxMembers: 1,
        maxProjects: 1,
        sharedStorageGb: 0,
        privateStorageGb: 0,
        price: "",
        currency: "EUR",
        // Always public — private plans hit a server-side bug
        // ("Cannot assign null to property Plan::$currency") and surface
        // no real value for our use case. Keep this in state (rather than
        // hardcoded in the body) so it remains visible in the submit
        // preview and easy to expose again if needed.
        isPublic: true,
      },
      customPlanFieldErrors: {},
      customPlanBlurred: {},
      customPlanError: null,
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
        case "active": return "iz-pill--success";
        case "paused": return "iz-pill--warning";
        case "cancelled": return "iz-pill--danger";
        default: return "iz-pill--muted";
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
    storage() {
      return (this.org.usageSummary && this.org.usageSummary.storage) || {};
    },
    privateStorage() {
      return this.storage.private || {};
    },
    sharedStorage() {
      return this.storage.shared || {};
    },
    storageMeterWidth() {
      return Math.min(100, Math.max(0, Number(this.storage.percentage) || 0));
    },
    storagePercentageDisplay() {
      return this.storage.percentage == null ? "—" : this.storage.percentage + "%";
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
      if (this.customPlanOpen) {
        // Custom plan path: dropdown is irrelevant, must validate the
        // sub-form fields instead.
        return this.canCreateCustomPlan;
      }
      if (!this.form.planId) return false;
      if (!this.selectedPlan) return false;
      return true;
    },
    canCreateCustomPlan() {
      const cp = this.customPlan;
      if (!cp.name || !cp.name.trim()) return false;
      if (!Number.isFinite(+cp.maxMembers) || +cp.maxMembers < 1) return false;
      if (!Number.isFinite(+cp.maxProjects) || +cp.maxProjects < 1) return false;
      if (!Number.isFinite(+cp.sharedStorageGb) || +cp.sharedStorageGb <= 0) return false;
      if (!Number.isFinite(+cp.privateStorageGb) || +cp.privateStorageGb <= 0) return false;
      if (cp.price !== "" && cp.price !== null) {
        if (!Number.isFinite(+cp.price) || +cp.price < 0) return false;
      }
      return true;
    },
    canConfirm() {
      return !this.saving && this.confirmPassword.length > 0;
    },
    currentUserUid() {
      // Used only to populate the hidden username field paired with the
      // password input, so password managers fill the credentials inside
      // the modal instead of treating the visible search box as a target.
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
    formatBytes(value) {
      if (value == null) return "—";
      const bytes = Number(value) || 0;
      if (bytes <= 0) return "0 B";
      const units = ["B", "KB", "MB", "GB", "TB"];
      const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
      const scaled = bytes / Math.pow(1024, index);
      return scaled.toFixed(index >= 3 && scaled < 10 ? 1 : 0) + " " + units[index];
    },
    storagePartDisplay(part) {
      return this.formatBytes(part.usedBytes) + " / " + this.formatBytes(part.capacityBytes);
    },
    formatDateTime(input) {
      if (!input) return "";
      const date = new Date(input);
      if (isNaN(date.getTime())) return "";
      return date.toLocaleString(undefined, {
        day: "2-digit",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
      });
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
      this.confirmPassword = "";
      this.confirmOpen = false;
      this.saveError = null;
      this.customPlanOpen = false;
      this.customPlanError = null;
      this.customPlanFieldErrors = {};
      this.customPlanBlurred = {};
      if (this.plans.length === 0 && !this.plansLoading) {
        this.loadPlans();
      }
    },
    cancelEdit() {
      this.mode = "read";
      this.confirmPassword = "";
      this.confirmOpen = false;
      this.saveError = null;
      this.customPlanOpen = false;
      this.customPlanError = null;
    },
    openConfirm() {
      if (!this.canSave) return;
      this.confirmPassword = "";
      this.saveError = null;
      this.confirmOpen = true;
      this.focusConfirmInput();
    },
    focusConfirmInput() {
      // Two RAFs to clear Vue's nextTick AND let the browser paint the
      // newly-mounted modal before we call focus(). Calling focus() on a
      // freshly-inserted node in the same tick sometimes silently no-ops,
      // leaving keystrokes to land on whatever was focused before — which
      // is how the admin's password ended up in the org search box.
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
    closeConfirm() {
      if (this.saving) return;
      this.confirmOpen = false;
      this.confirmPassword = "";
    },
    async loadPlans() {
      this.plansLoading = true;
      this.plansError = null;
      try {
        const res = await axios.get(
          generateOcsUrl("/apps/organization/plans"),
          {
            params: { limit: 200, format: "json" },
            headers: ORG_OCS_HEADERS,
          },
        );
        const data = unwrapOcs(res);
        this.plans = data.plans || [];
      } catch (e) {
        this.plansError = this.extractServerError(e, "Couldn't load plans.");
      } finally {
        this.plansLoading = false;
      }
    },
    openCustomPlan() {
      // Seed defaults from the currently-selected plan so the admin has
      // sensible starting values (in GB for storage). If the dropdown
      // isn't usable (e.g., fresh edit, no plan picked yet), seed zeros.
      const sp = this.selectedPlan;
      const sharedGb = sp ? sp.sharedStoragePerProject / 1073741824 : 0;
      const privateGb = sp ? sp.privateStoragePerUser / 1073741824 : 0;
      this.customPlan = {
        name: "",
        maxMembers: sp ? sp.maxMembers : 1,
        maxProjects: sp ? sp.maxProjects : 1,
        sharedStorageGb: Math.round(sharedGb * 100) / 100,
        privateStorageGb: Math.round(privateGb * 100) / 100,
        price: sp && sp.price != null ? sp.price : "",
        currency: (sp && sp.currency) || "EUR",
        isPublic: true,
      };
      this.customPlanError = null;
      this.customPlanFieldErrors = {};
      this.customPlanBlurred = {};
      this.customPlanOpen = true;
    },
    closeCustomPlan() {
      this.customPlanOpen = false;
      this.customPlanError = null;
    },
    markCustomBlurred(field) {
      this.$set(this.customPlanBlurred, field, true);
      this.validateCustomPlan();
    },
    validateCustomPlan() {
      const cp = this.customPlan;
      const errs = {};
      if (!cp.name || !cp.name.trim()) errs.name = "Required";
      if (!Number.isFinite(+cp.maxMembers) || +cp.maxMembers < 1) {
        errs.maxMembers = "Must be at least 1";
      }
      if (!Number.isFinite(+cp.maxProjects) || +cp.maxProjects < 1) {
        errs.maxProjects = "Must be at least 1";
      }
      if (!Number.isFinite(+cp.sharedStorageGb) || +cp.sharedStorageGb <= 0) {
        errs.sharedStorageGb = "Must be greater than 0";
      }
      if (!Number.isFinite(+cp.privateStorageGb) || +cp.privateStorageGb <= 0) {
        errs.privateStorageGb = "Must be greater than 0";
      }
      if (cp.price !== "" && cp.price !== null) {
        if (!Number.isFinite(+cp.price) || +cp.price < 0) {
          errs.price = "Must be a non-negative number";
        }
      }
      this.customPlanFieldErrors = errs;
      return Object.keys(errs).length === 0;
    },
    customPlanBodyForPost() {
      const cp = this.customPlan;
      const body = {
        name: cp.name.trim(),
        maxMembers: parseInt(cp.maxMembers, 10),
        maxProjects: parseInt(cp.maxProjects, 10),
        sharedStoragePerProject: Math.round(+cp.sharedStorageGb * 1073741824),
        privateStoragePerUser: Math.round(+cp.privateStorageGb * 1073741824),
        isPublic: !!cp.isPublic,
      };
      if (cp.price !== "" && cp.price !== null) {
        body.price = +cp.price;
      }
      if (cp.currency) body.currency = cp.currency;
      return body;
    },
    onPasswordEnter() {
      if (this.canConfirm) this.saveChanges();
    },
    async saveChanges() {
      if (!this.canConfirm) return;
      if (this.customPlanOpen && !this.validateCustomPlan()) return;
      this.saving = true;
      this.saveError = null;
      this.customPlanError = null;
      let createdPlanId = null;
      try {
        // Step 1 — Nextcloud's PasswordConfirmationRequired gate. One
        // confirmation covers both the plan POST and the subscription PUT.
        await axios.post(generateUrl("/login/confirm"), {
          password: this.confirmPassword,
        });

        // Step 2 — if the custom-plan sub-form is open, create the plan
        // first. On success, push it into this.plans and select it so the
        // subscription PUT body below pulls caps from the just-created plan.
        let planToUse = this.selectedPlan;
        if (this.customPlanOpen) {
          const res = await axios.post(
            generateOcsUrl("/apps/organization/plans"),
            this.customPlanBodyForPost(),
            {
              params: { format: "json" },
              headers: ORG_OCS_HEADERS,
            },
          );
          planToUse = unwrapOcs(res);
          if (!planToUse || !planToUse.id) {
            throw new Error("Plan create returned no id");
          }
          this.plans.push(planToUse);
          this.form.planId = planToUse.id;
          createdPlanId = planToUse.id;
          this.customPlanOpen = false;
        }

        // Step 3 — actual subscription update. All caps fields ride along
        // from the chosen (or just-created) plan to satisfy the API's
        // all-fields-required body. We also send price + currency: the
        // server's SubscriptionService::updateSubscription calls
        // PlanService::handlePlanUpdate, which can create a derivative
        // plan internally — and that path fails ("Cannot assign null to
        // property Plan::$currency of type string") when currency is
        // omitted/null. Match the chosen plan's values so the derivation
        // doesn't see nulls.
        const body = {
          displayName: this.org.profile.name,
          planId: planToUse.id,
          maxMembers: planToUse.maxMembers,
          maxProjects: planToUse.maxProjects,
          sharedStoragePerProject: planToUse.sharedStoragePerProject,
          privateStoragePerUser: planToUse.privateStoragePerUser,
          status: this.form.status,
          currency: planToUse.currency || "EUR",
        };
        if (planToUse.price != null) {
          body.price = Number(planToUse.price);
        } else {
          body.price = 0;
        }
        if (this.form.extendDuration) {
          body.extendDuration = this.form.extendDuration;
        }
        await axios.put(
          generateOcsUrl(
            "/apps/organization/organizations/" +
              this.org.profile.id +
              "/subscription",
          ),
          body,
          {
            params: { format: "json" },
            headers: ORG_OCS_HEADERS,
          },
        );
        this.confirmOpen = false;
        this.confirmPassword = "";
        this.mode = "read";
        this.$emit("reload");
      } catch (e) {
        // Distinguish wrong-password (from /login/confirm), plan-create
        // failure (from POST /plans, only if no createdPlanId yet) and
        // generic PUT errors.
        const status = e && e.response && e.response.status;
        const url =
          (e && e.response && e.response.config && e.response.config.url) ||
          "";
        if (url.indexOf("/login/confirm") !== -1 && status === 403) {
          this.saveError = "Wrong password. Try again.";
          this.confirmPassword = "";
          this.focusConfirmInput();
        } else if (
          url.indexOf("/apps/organization/plans") !== -1 &&
          !createdPlanId
        ) {
          // Plan creation failed — surface inside the sub-form, leave the
          // password modal closed so the admin can fix the inputs first.
          this.customPlanError = this.extractServerError(
            e,
            "Couldn't create plan. Try again.",
          );
          this.confirmOpen = false;
          this.confirmPassword = "";
        } else if (status === 404) {
          this.saveError =
            "Subscription no longer exists. The organization may have been deleted.";
          this.confirmOpen = false;
          this.$emit("reload");
        } else {
          // Includes the case where the plan WAS created (createdPlanId
          // set) but the subscription PUT failed. The plan is selected;
          // the admin can retype the password and retry — only the PUT
          // re-runs, no duplicate plan.
          this.saveError = this.extractServerError(
            e,
            "Couldn't save changes. Try again.",
          );
        }
      } finally {
        this.saving = false;
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
  background: var(--bg-subtle);
  border: 1px dashed var(--color-border-strong);
  border-radius: var(--radius-lg);
  padding: 24px;
  text-align: center;
}

.sub-panel__empty-title {
  font-size: var(--iz-fs-md);
  font-weight: 700;
  color: var(--color-text-primary, var(--color-text-primary));
  margin-bottom: 6px;
}

.sub-panel__empty-body {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-muted, var(--color-text-secondary));
  line-height: 1.5;
}

/* ── Layout ──────────────────────────────────────────────────────────── */
/* Read-mode wrapper: stack the rows with the same 14px rhythm as .sub-panel
   (the intermediate v-else-if div previously had no gap, so rows were flush). */
.sub-panel__read {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.sub-panel__row--two {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.sub-panel__row--full {
  width: 100%;
}

.sub-panel__card {
  background: var(--bg-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card);
  padding: 14px;
}

.sub-panel__label {
  font-size: var(--iz-fs-micro);
  font-weight: 700;
  color: var(--color-text-muted, var(--color-text-secondary));
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 10px;
}

.sub-panel__sub {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-muted, var(--color-text-secondary));
  margin-top: 8px;
}

/* ── Status pill ─────────────────────────────────────────────────────── */

/* ── Plan pill ───────────────────────────────────────────────────────── */
.sub-panel__plan-line {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.sub-panel__plan-pill {
  background: var(--iz-accent-bg);
  color: var(--accent);
  padding: 5px 12px;
  border-radius: var(--radius-pill);
  font-size: var(--iz-fs-md);
  font-weight: 700;
}

.sub-panel__plan-meta {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted, var(--color-text-secondary));
}

/* ── Key-value grids ─────────────────────────────────────────────────── */
.sub-panel__kv-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 24px;
}

.sub-panel__kv-label {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted);
  margin-bottom: 4px;
}

.sub-panel__kv-value {
  font-size: var(--iz-fs-md);
  font-weight: 600;
  color: var(--color-text-primary, var(--color-text-primary));
}

.sub-panel__kv-relative {
  font-size: var(--iz-fs-xs);
  font-weight: 500;
  color: var(--color-warning-text);
}

.sub-panel__kv-relative--past {
  color: var(--color-danger-text);
}

.sub-panel__kv-line {
  display: flex;
  justify-content: space-between;
  font-size: var(--iz-fs-md);
  color: var(--color-text-muted, var(--color-text-secondary));
  padding: 4px 0;
}

.sub-panel__kv-strong {
  font-weight: 600;
  color: var(--color-text-primary, var(--color-text-primary));
}

/* ── Limits / progress bars ──────────────────────────────────────────── */
.sub-panel__limit-row {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.sub-panel__limit-num {
  font-size: var(--iz-fs-xl);
  font-weight: 700;
  color: var(--color-text-primary, var(--color-text-primary));
}

.sub-panel__limit-of {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-muted, var(--color-text-secondary));
}

.sub-panel__storage-total {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
  color: var(--color-text-secondary);
  font-size: var(--iz-fs-sm);
}

.sub-panel__storage-breakdown {
  margin-top: 10px;
}

/* ── Actions / buttons ───────────────────────────────────────────────── */
.sub-panel__actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 4px;
}


/* ── Edit mode header ────────────────────────────────────────────────── */
.sub-panel__edit-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.sub-panel__edit-title {
  font-size: var(--iz-fs-lg);
  font-weight: 700;
  color: var(--color-text-primary, var(--color-text-primary));
}

.sub-panel__edit-meta {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted, var(--color-text-muted));
}

/* ── Form fields ─────────────────────────────────────────────────────── */
.sub-panel__field {
  margin-bottom: 14px;
}

.sub-panel__field-label {
  display: block;
  font-size: var(--iz-fs-sm);
  font-weight: 600;
  color: var(--color-text-secondary, var(--color-text-secondary));
  margin-bottom: 6px;
}

.sub-panel__field-required {
  color: var(--color-danger-text);
}

.sub-panel__field-optional {
  font-weight: 400;
  color: var(--color-text-muted);
}

.sub-panel__field-hint {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted, var(--color-text-muted));
  margin-top: 4px;
}

.sub-panel__field-error {
  font-size: var(--iz-fs-sm);
  color: var(--color-danger-text);
  background: var(--color-danger-bg);
  border: 1px solid var(--color-danger-bg);
  border-radius: var(--radius-sm);
  padding: 8px 12px;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
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
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-el);
  background: var(--bg-card);
  font-size: var(--iz-fs-md);
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
  border-color: var(--accent);
  background: var(--iz-accent-bg);
  color: var(--color-text-primary);
}

/* ── Extend buttons ──────────────────────────────────────────────────── */
.sub-panel__extend-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.sub-panel__extend-pill {
  padding: 7px 14px;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-el);
  background: var(--bg-card);
  font-size: var(--iz-fs-sm);
  font-weight: 600;
  color: var(--color-text-secondary, var(--color-text-secondary));
  cursor: pointer;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}

.sub-panel__extend-pill:hover:not(:disabled) {
  border-color: var(--accent);
  color: var(--accent);
}

.sub-panel__extend-pill--active {
  border-color: var(--accent);
  background: var(--iz-accent-bg);
  color: var(--accent);
}

.sub-panel__extend-pill:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ── Submit preview ──────────────────────────────────────────────────── */
/* Chrome from .iz-inset; only the spacing below it is local. */
.sub-panel__preview { margin-bottom: 14px; }

.sub-panel__preview-grid {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 4px 16px;
  font-size: var(--iz-fs-xs);
  color: var(--color-text-secondary);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.sub-panel__preview-grid > span:nth-child(even) {
  text-align: right;
  font-weight: 600;
  color: var(--color-text-primary, var(--color-text-primary));
}

.sub-panel__preview-section {
  font-size: var(--iz-fs-micro);
  font-weight: 700;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  margin: 8px 0 4px;
}

.sub-panel__preview-section:first-of-type {
  margin-top: 0;
}

/* ── Plan field — dropdown + create-custom button ────────────────────── */
.sub-panel__plan-row {
  display: flex;
  gap: 8px;
  align-items: stretch;
}

.sub-panel__plan-select {
  flex: 1;
  min-width: 0;
}

.sub-panel__plan-custom-btn {
  background: var(--bg-card);
  border: 1px solid var(--accent);
  color: var(--accent);
  border-radius: var(--radius-el);
  font-size: var(--iz-fs-sm);
  font-weight: 600;
  padding: 0 12px;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}

.sub-panel__plan-custom-btn:hover:not(:disabled) {
  background: var(--accent);
  color: #fff;
}

.sub-panel__plan-custom-btn--active {
  background: var(--iz-accent-bg);
}

.sub-panel__plan-custom-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ── Custom plan sub-form ────────────────────────────────────────────── */
.sub-panel__custom {
  background: var(--bg-subtle);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 14px;
  margin-top: 10px;
}

.sub-panel__custom-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.sub-panel__custom-title {
  font-size: var(--iz-fs-xs);
  font-weight: 700;
  color: var(--accent);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.sub-panel__custom-field {
  margin-bottom: 10px;
}

.sub-panel__custom-label {
  display: block;
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  color: var(--color-text-secondary, var(--color-text-secondary));
  margin-bottom: 4px;
}

.sub-panel__custom-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 4px;
}

.sub-panel__unit-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.sub-panel__unit-row .iz-input {
  flex: 1;
  min-width: 0;
}

.sub-panel__unit-suffix {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted, var(--color-text-muted));
}

.sub-panel__custom-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary, var(--color-text-secondary));
  margin-top: 6px;
  cursor: pointer;
  user-select: none;
}

.sub-panel__custom-checkbox input {
  margin: 0;
  cursor: pointer;
}

.sub-panel__custom-field-error {
  margin-top: 4px;
  margin-bottom: 0;
  padding: 0;
  background: transparent;
  border: 0;
  font-size: var(--iz-fs-xs);
}

.sub-panel__custom-error {
  margin-top: 10px;
  margin-bottom: 0;
}

@media (max-width: 720px) {
  .sub-panel__custom-grid {
    grid-template-columns: 1fr;
  }
  .sub-panel__plan-row {
    flex-direction: column;
  }
}

/* ── Password confirmation modal ─────────────────────────────────────── */


@keyframes sub-panel-modal-in {
  from { transform: scale(0.96); opacity: 0; }
  to   { transform: scale(1); opacity: 1; }
}

.sub-panel__modal-body {
  padding: 16px 18px;
}

.sub-panel__modal-password {
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

.sub-panel__modal-password:focus {
  border-color: var(--accent);
}

/* Hidden username companion for password managers. Must remain in the
   DOM (display:none would be ignored by autofill) but invisible to the
   user. position:absolute keeps it off-flow. */

.sub-panel__modal-error {
  margin-top: 12px;
  margin-bottom: 0;
}

/* ── Responsive ──────────────────────────────────────────────────────── */
@media (max-width: 720px) {
  .sub-panel__row--two,
  .sub-panel__kv-grid {
    grid-template-columns: 1fr;
  }
}
</style>
