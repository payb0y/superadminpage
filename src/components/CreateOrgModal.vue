<template>
  <div
    class="iz-modal-backdrop"
    role="dialog"
    aria-modal="true"
    @click.self="cancel"
    @keydown.esc="cancel"
  >
    <div class="iz-modal co-modal">
      <!-- Header -->
      <div class="iz-modal__header">
        <span class="co-modal__title">Create organization</span>
        <button
          type="button"
          class="iz-close"
          :disabled="saving"
          aria-label="Close"
          @click="cancel"
        >×</button>
      </div>

      <!-- Body -->
      <div class="iz-modal__body co-modal__body">
        <!-- ── Credentials reveal (post-success with auto-generated pw) ─── -->
        <div v-if="createdOrg" class="iz-reveal co-modal__reveal">
          <div class="iz-reveal__heading">
            <span class="iz-reveal__check" aria-hidden="true">✓</span>
            Organization created
          </div>
          <div class="iz-reveal__row">
            <span class="iz-reveal__label">Org</span>
            <span class="iz-reveal__value">{{ createdOrg.displayname }}</span>
          </div>
          <div class="iz-reveal__row">
            <span class="iz-reveal__label">Admin UID</span>
            <span class="iz-reveal__value">{{ createdOrg.adminUserId }}</span>
            <button
              type="button"
              class="iz-btn iz-btn--sm"
              @click="copyToClipboard(createdOrg.adminUserId, 'uid')"
            >{{ copyFlags.uid ? "Copied!" : "Copy" }}</button>
          </div>
          <div class="iz-reveal__row">
            <span class="iz-reveal__label">Password</span>
            <span class="iz-reveal__value">
              <template v-if="revealPassword">{{ createdOrg.adminPassword }}</template>
              <template v-else>{{ "•".repeat(createdOrg.adminPassword.length) }}</template>
            </span>
            <button
              type="button"
              class="iz-btn iz-btn--sm"
              @click="revealPassword = !revealPassword"
            >{{ revealPassword ? "Hide" : "Show" }}</button>
            <button
              type="button"
              class="iz-btn iz-btn--sm"
              @click="copyToClipboard(createdOrg.adminPassword, 'password')"
            >{{ copyFlags.password ? "Copied!" : "Copy" }}</button>
          </div>
          <div class="iz-reveal__row iz-reveal__row--actions">
            <button
              type="button"
              class="iz-btn iz-btn--sm"
              @click="copyToClipboard(createdOrg.adminUserId + ' / ' + createdOrg.adminPassword, 'both')"
            >{{ copyFlags.both ? "Copied!" : "Copy both" }}</button>
          </div>
          <p class="iz-reveal__warning">
            Share these credentials with the new admin now — the password
            won't be visible again.
          </p>
        </div>

        <!-- ── Form (hidden while reveal is showing) ──────────────────── -->
        <template v-else>
          <!-- Mode toggle -->
          <div class="iz-segment iz-segment--fill" role="tablist">
            <label
              class="iz-btn iz-btn--sm"
              :class="{ 'iz-btn--active': form.mode === 'trial' }"
            >
              <input
                type="radio"
                value="trial"
                v-model="form.mode"
                :disabled="saving"
                @change="onModeChange"
              />
              <span aria-hidden="true">⚡</span> Trial
            </label>
            <label
              class="iz-btn iz-btn--sm"
              :class="{ 'iz-btn--active': form.mode === 'standard' }"
            >
              <input
                type="radio"
                value="standard"
                v-model="form.mode"
                :disabled="saving"
                @change="onModeChange"
              />
              Standard
            </label>
          </div>

          <div v-if="form.mode === 'trial'" class="co-modal__hint">
            Trial orgs use server-configured limits. No plan picker is required.
          </div>

          <!-- Organization -->
          <div class="co-modal__section-label">ORGANIZATION</div>
          <div class="co-modal__field">
            <label class="co-modal__label" for="co-displayname">
              Display name <span class="co-modal__req">*</span>
            </label>
            <input
              id="co-displayname"
              type="text"
              class="iz-input"
              v-model="form.displayname"
              :disabled="saving"
              @blur="markBlurred('displayname')"
            />
            <div
              v-if="blurred.displayname && fieldErrors.displayname"
              class="co-modal__field-error"
            >{{ fieldErrors.displayname }}</div>
          </div>
          <div class="co-modal__field">
            <label class="co-modal__label" for="co-validity">
              {{ form.mode === "trial" ? "Trial validity" : "Subscription validity" }}
              <span class="co-modal__req">*</span>
            </label>
            <select
              id="co-validity"
              class="iz-input"
              v-model="form.validity"
              :disabled="saving"
            >
              <option
                v-for="v in validityOptions"
                :key="v"
                :value="v"
              >{{ v }}</option>
            </select>
          </div>

          <!-- Plan (Standard only) -->
          <template v-if="form.mode === 'standard'">
            <div class="co-modal__section-label">PLAN</div>
            <div v-if="plansLoading" class="iz-state co-modal__state">Loading plans…</div>
            <div v-else-if="plansError" class="co-modal__field-error">
              {{ plansError }}
              <button
                type="button"
                class="iz-btn iz-btn--sm iz-btn--accent"
                @click="loadPlans"
              >Retry</button>
            </div>
            <div v-else class="co-modal__plan-row">
              <select
                v-model.number="form.planId"
                class="iz-input co-modal__plan-select"
                :disabled="saving || form.customPlanOpen"
              >
                <option
                  v-for="p in plans"
                  :key="p.id"
                  :value="p.id"
                >{{ p.name }} — {{ planSummary(p) }}</option>
              </select>
              <button
                type="button"
                class="co-modal__plan-custom-btn"
                :class="{ 'co-modal__plan-custom-btn--active': form.customPlanOpen }"
                :disabled="saving"
                @click="form.customPlanOpen ? closeCustomPlan() : openCustomPlan()"
              >{{ form.customPlanOpen ? "Cancel custom" : "+ Create custom" }}</button>
            </div>
            <!-- Custom plan sub-form (only when toggle is on) -->
            <div v-if="form.customPlanOpen" class="co-modal__custom">
              <div class="co-modal__custom-header">
                <span class="co-modal__custom-title">NEW CUSTOM PLAN</span>
                <button
                  type="button"
                  class="iz-close iz-close--sm"
                  :disabled="saving"
                  aria-label="Cancel custom plan"
                  @click="closeCustomPlan"
                >×</button>
              </div>
              <div class="co-modal__field">
                <label class="co-modal__label">
                  Plan name <span class="co-modal__req">*</span>
                </label>
                <input
                  type="text"
                  class="iz-input"
                  v-model="form.customPlan.name"
                  :disabled="saving"
                  @blur="markBlurred('cpName')"
                />
                <div
                  v-if="blurred.cpName && fieldErrors.cpName"
                  class="co-modal__field-error"
                >{{ fieldErrors.cpName }}</div>
              </div>
              <div class="co-modal__grid">
                <div class="co-modal__field">
                  <label class="co-modal__label">
                    Members <span class="co-modal__req">*</span>
                  </label>
                  <input
                    type="number"
                    min="1"
                    step="1"
                    class="iz-input"
                    v-model.number="form.customPlan.maxMembers"
                    :disabled="saving"
                    @blur="markBlurred('cpMembers')"
                  />
                  <div
                    v-if="blurred.cpMembers && fieldErrors.cpMembers"
                    class="co-modal__field-error"
                  >{{ fieldErrors.cpMembers }}</div>
                </div>
                <div class="co-modal__field">
                  <label class="co-modal__label">
                    Projects <span class="co-modal__req">*</span>
                  </label>
                  <input
                    type="number"
                    min="1"
                    step="1"
                    class="iz-input"
                    v-model.number="form.customPlan.maxProjects"
                    :disabled="saving"
                    @blur="markBlurred('cpProjects')"
                  />
                  <div
                    v-if="blurred.cpProjects && fieldErrors.cpProjects"
                    class="co-modal__field-error"
                  >{{ fieldErrors.cpProjects }}</div>
                </div>
              </div>
              <div class="co-modal__grid">
                <div class="co-modal__field">
                  <label class="co-modal__label">
                    Shared/project <span class="co-modal__req">*</span>
                  </label>
                  <div class="co-modal__unit-row">
                    <input
                      type="number"
                      min="0"
                      step="0.1"
                      class="iz-input"
                      v-model.number="form.customPlan.sharedStorageGb"
                      :disabled="saving"
                      @blur="markBlurred('cpShared')"
                    />
                    <span class="co-modal__unit">GB</span>
                  </div>
                  <div
                    v-if="blurred.cpShared && fieldErrors.cpShared"
                    class="co-modal__field-error"
                  >{{ fieldErrors.cpShared }}</div>
                </div>
                <div class="co-modal__field">
                  <label class="co-modal__label">
                    Private/user <span class="co-modal__req">*</span>
                  </label>
                  <div class="co-modal__unit-row">
                    <input
                      type="number"
                      min="0"
                      step="0.1"
                      class="iz-input"
                      v-model.number="form.customPlan.privateStorageGb"
                      :disabled="saving"
                      @blur="markBlurred('cpPrivate')"
                    />
                    <span class="co-modal__unit">GB</span>
                  </div>
                  <div
                    v-if="blurred.cpPrivate && fieldErrors.cpPrivate"
                    class="co-modal__field-error"
                  >{{ fieldErrors.cpPrivate }}</div>
                </div>
              </div>
              <div class="co-modal__grid">
                <div class="co-modal__field">
                  <label class="co-modal__label">
                    Price <span class="co-modal__optional">(optional)</span>
                  </label>
                  <input
                    type="number"
                    min="0"
                    step="0.01"
                    class="iz-input"
                    v-model="form.customPlan.price"
                    :disabled="saving"
                    @blur="markBlurred('cpPrice')"
                  />
                  <div
                    v-if="blurred.cpPrice && fieldErrors.cpPrice"
                    class="co-modal__field-error"
                  >{{ fieldErrors.cpPrice }}</div>
                </div>
                <div class="co-modal__field">
                  <label class="co-modal__label">Currency</label>
                  <select
                    v-model="form.customPlan.currency"
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
            </div>
          </template>

          <!-- Contact -->
          <div class="co-modal__section-label">
            CONTACT <span class="co-modal__optional">(optional)</span>
          </div>
          <div class="co-modal__grid">
            <div class="co-modal__field">
              <input
                type="text"
                class="iz-input"
                v-model="form.contactFirstName"
                placeholder="First name"
                :disabled="saving"
              />
            </div>
            <div class="co-modal__field">
              <input
                type="text"
                class="iz-input"
                v-model="form.contactLastName"
                placeholder="Last name"
                :disabled="saving"
              />
            </div>
          </div>
          <div class="co-modal__field">
            <input
              type="email"
              class="iz-input"
              v-model="form.contactEmail"
              placeholder="Email"
              :disabled="saving"
              @blur="markBlurred('contactEmail')"
            />
            <div
              v-if="blurred.contactEmail && fieldErrors.contactEmail"
              class="co-modal__field-error"
            >{{ fieldErrors.contactEmail }}</div>
          </div>
          <div class="co-modal__field">
            <input
              type="text"
              class="iz-input"
              v-model="form.contactPhone"
              placeholder="Phone"
              :disabled="saving"
            />
          </div>

          <!-- Admin user -->
          <div class="co-modal__section-label">
            ADMIN USER
            <span class="co-modal__optional">(created fresh)</span>
          </div>
          <div class="co-modal__grid">
            <div class="co-modal__field">
              <label class="co-modal__label">
                UID <span class="co-modal__req">*</span>
              </label>
              <input
                type="text"
                class="iz-input"
                v-model="form.adminUserId"
                autocomplete="off"
                spellcheck="false"
                :disabled="saving"
                @blur="markBlurred('adminUserId')"
              />
              <div
                v-if="blurred.adminUserId && fieldErrors.adminUserId"
                class="co-modal__field-error"
              >{{ fieldErrors.adminUserId }}</div>
            </div>
            <div class="co-modal__field">
              <label class="co-modal__label">
                Display name <span class="co-modal__req">*</span>
              </label>
              <input
                type="text"
                class="iz-input"
                v-model="form.adminDisplayName"
                :disabled="saving"
                @blur="markBlurred('adminDisplayName')"
              />
              <div
                v-if="blurred.adminDisplayName && fieldErrors.adminDisplayName"
                class="co-modal__field-error"
              >{{ fieldErrors.adminDisplayName }}</div>
            </div>
          </div>
          <div class="co-modal__field">
            <label class="co-modal__label">Email</label>
            <input
              type="email"
              class="iz-input"
              v-model="form.adminEmail"
              :disabled="saving"
              @blur="markBlurred('adminEmail')"
            />
            <div
              v-if="blurred.adminEmail && fieldErrors.adminEmail"
              class="co-modal__field-error"
            >{{ fieldErrors.adminEmail }}</div>
          </div>
          <div class="co-modal__field">
            <label class="co-modal__label">
              Password <span class="co-modal__req">*</span>
            </label>
            <div class="co-modal__password-row">
              <input
                :type="form.adminShowPassword ? 'text' : 'password'"
                class="iz-input co-modal__password-input"
                v-model="form.adminPassword"
                :readonly="form.adminAutoGenerate"
                :disabled="saving"
                autocomplete="new-password"
                @blur="markBlurred('adminPassword')"
              />
              <button
                type="button"
                class="co-modal__icon-btn"
                :disabled="saving"
                :aria-label="form.adminShowPassword ? 'Hide password' : 'Show password'"
                @click="form.adminShowPassword = !form.adminShowPassword"
              >
                <svg
                  v-if="form.adminShowPassword"
                  xmlns="http://www.w3.org/2000/svg"
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94" />
                  <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19" />
                  <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                  <line x1="1" y1="1" x2="23" y2="23" />
                </svg>
                <svg
                  v-else
                  xmlns="http://www.w3.org/2000/svg"
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
              </button>
              <button
                v-if="form.adminAutoGenerate"
                type="button"
                class="co-modal__icon-btn"
                :disabled="saving"
                aria-label="Regenerate password"
                @click="form.adminPassword = generatePassword()"
              >↻</button>
            </div>
            <label class="co-modal__autogen">
              <input
                type="checkbox"
                v-model="form.adminAutoGenerate"
                :disabled="saving"
                @change="onAutoGenerateChange"
              />
              Auto-generate
            </label>
            <div
              v-if="blurred.adminPassword && fieldErrors.adminPassword"
              class="co-modal__field-error"
            >{{ fieldErrors.adminPassword }}</div>
          </div>

          <!-- Outer server error -->
          <div
            v-if="saveError && !confirmOpen"
            class="co-modal__field-error"
          >{{ saveError }}</div>
        </template>
      </div>

      <!-- Footer -->
      <div class="iz-modal__footer">
        <template v-if="createdOrg">
          <button
            type="button"
            class="iz-btn iz-btn--primary"
            @click="confirmCreatedOrg"
          >Done</button>
        </template>
        <template v-else>
          <button
            type="button"
            class="iz-btn iz-btn--ghost"
            :disabled="saving"
            @click="cancel"
          >Cancel</button>
          <button
            type="button"
            class="iz-btn iz-btn--primary"
            :disabled="!canSave"
            @click="openConfirm"
          >Create organization</button>
        </template>
      </div>
    </div>

    <!-- ── Password confirmation modal ─────────────────────────────── -->
    <div
      v-if="confirmOpen"
      class="co-modal__confirm-backdrop"
      role="dialog"
      aria-modal="true"
      @click.self="closeConfirm"
      @keydown.esc="closeConfirm"
    >
      <div class="co-modal__confirm">
        <div class="co-modal__confirm-header">
          <span>Confirm with your password</span>
          <button
            type="button"
            class="iz-close"
            :disabled="saving"
            aria-label="Close"
            @click="closeConfirm"
          >×</button>
        </div>
        <form
          class="co-modal__confirm-body"
          autocomplete="on"
          @submit.prevent="saveOrg"
        >
          <p class="iz-modal__confirm-text">
            Nextcloud requires re-confirming your super-admin password
            before creating an organization.
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
            class="co-modal__confirm-password"
            name="password"
            placeholder="Your admin password"
            :disabled="saving"
            autocomplete="current-password"
            @keydown.enter="onConfirmEnter"
          />
          <div
            v-if="saveError"
            class="co-modal__field-error co-modal__confirm-error"
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
            @click="saveOrg"
          >
            <span
              v-if="saving"
              class="iz-spinner"
              aria-hidden="true"
            ></span>
            Confirm &amp; create
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

const TRIAL_VALIDITY = ["14 days", "30 days", "3 months"];
const STANDARD_VALIDITY = [
  "1 month",
  "3 months",
  "6 months",
  "1 year",
  "2 years",
];

export default {
  name: "CreateOrgModal",
  emits: ["close", "created"],
  data() {
    return {
      form: {
        mode: "trial",
        displayname: "",
        validity: TRIAL_VALIDITY[1],
        // Standard
        planId: null,
        customPlanOpen: false,
        customPlan: {
          name: "",
          maxMembers: 1,
          maxProjects: 1,
          sharedStorageGb: 0,
          privateStorageGb: 0,
          price: "",
          currency: "EUR",
        },
        // Contact
        contactFirstName: "",
        contactLastName: "",
        contactEmail: "",
        contactPhone: "",
        // Admin user
        adminUserId: "",
        adminDisplayName: "",
        adminEmail: "",
        adminPassword: "",
        adminAutoGenerate: true,
        adminShowPassword: false,
      },
      plans: [],
      plansLoading: false,
      plansError: null,
      fieldErrors: {},
      blurred: {},
      confirmOpen: false,
      confirmPassword: "",
      saving: false,
      saveError: null,
      createdOrg: null,
      revealPassword: false,
      copyFlags: {},
    };
  },
  computed: {
    validityOptions() {
      return this.form.mode === "trial" ? TRIAL_VALIDITY : STANDARD_VALIDITY;
    },
    selectedPlan() {
      if (!this.form.planId) return null;
      for (let i = 0; i < this.plans.length; i++) {
        if (this.plans[i].id === this.form.planId) return this.plans[i];
      }
      return null;
    },
    canCreateCustomPlan() {
      const cp = this.form.customPlan;
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
    canSave() {
      if (this.saving) return false;
      if (!this.form.displayname.trim()) return false;
      if (!this.form.validity) return false;
      if (this.form.mode === "standard") {
        if (this.plansLoading || this.plansError) return false;
        if (this.form.customPlanOpen) {
          if (!this.canCreateCustomPlan) return false;
        } else {
          if (!this.form.planId || !this.selectedPlan) return false;
        }
      }
      // Contact email format
      const e = this.form.contactEmail.trim();
      if (e && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) return false;
      // Admin user
      const uid = this.form.adminUserId.trim();
      if (!uid || !/^[A-Za-z0-9._@-]+$/.test(uid)) return false;
      if (!this.form.adminDisplayName.trim()) return false;
      const ae = this.form.adminEmail.trim();
      if (ae && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(ae)) return false;
      if (!this.form.adminAutoGenerate) {
        if (!this.form.adminPassword || this.form.adminPassword.length < 10) {
          return false;
        }
      }
      if (!this.form.adminPassword) return false;
      return true;
    },
    canConfirm() {
      return !this.saving && this.confirmPassword.length > 0;
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
  },
  created() {
    this._copyTimers = {};
    // Seed an initial auto-generated admin password.
    this.form.adminPassword = this.generatePassword();
  },
  mounted() {
    // Lock background scroll while the modal is open.
    document.body.style.overflow = "hidden";
  },
  beforeDestroy() {
    document.body.style.overflow = "";
    Object.keys(this._copyTimers || {}).forEach((k) => {
      clearTimeout(this._copyTimers[k]);
    });
  },
  methods: {
    cancel() {
      if (this.saving) return;
      this.$emit("close");
    },
    onModeChange() {
      // Reset validity to the first preset for the new mode.
      this.form.validity = this.validityOptions[0];
      // In Trial mode, plan UI is hidden — also collapse the custom form.
      if (this.form.mode === "trial") {
        this.form.customPlanOpen = false;
      } else {
        // Entering Standard mode → load plans if we haven't already.
        if (this.plans.length === 0 && !this.plansLoading) {
          this.loadPlans();
        }
      }
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
        // Pre-select the first plan if none chosen yet.
        if (this.plans.length > 0 && !this.form.planId) {
          this.form.planId = this.plans[0].id;
        }
      } catch (e) {
        this.plansError = this.extractServerError(e, "Couldn't load plans.");
      } finally {
        this.plansLoading = false;
      }
    },
    openCustomPlan() {
      const sp = this.selectedPlan;
      const sharedGb = sp ? sp.sharedStoragePerProject / 1073741824 : 0;
      const privateGb = sp ? sp.privateStoragePerUser / 1073741824 : 0;
      this.form.customPlan = {
        name: "",
        maxMembers: sp ? sp.maxMembers : 1,
        maxProjects: sp ? sp.maxProjects : 1,
        sharedStorageGb: Math.round(sharedGb * 100) / 100,
        privateStorageGb: Math.round(privateGb * 100) / 100,
        price: sp && sp.price != null ? sp.price : "",
        currency: (sp && sp.currency) || "EUR",
      };
      this.form.customPlanOpen = true;
    },
    closeCustomPlan() {
      this.form.customPlanOpen = false;
    },
    generatePassword() {
      const alphabet =
        "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*-_=+";
      const len = 20;
      const arr = new Uint32Array(len);
      window.crypto.getRandomValues(arr);
      let out = "";
      for (let i = 0; i < len; i++) {
        out += alphabet.charAt(arr[i] % alphabet.length);
      }
      return out;
    },
    onAutoGenerateChange() {
      if (this.form.adminAutoGenerate) {
        this.form.adminPassword = this.generatePassword();
      }
    },
    markBlurred(field) {
      this.$set(this.blurred, field, true);
      this.validate();
    },
    validate() {
      const errs = {};
      const f = this.form;
      if (!f.displayname.trim()) errs.displayname = "Required";
      // Contact email
      const e = f.contactEmail.trim();
      if (e && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) {
        errs.contactEmail = "Invalid email";
      }
      // Admin uid
      const uid = f.adminUserId.trim();
      if (!uid) {
        errs.adminUserId = "Required";
      } else if (!/^[A-Za-z0-9._@-]+$/.test(uid)) {
        errs.adminUserId = "Use letters, digits, or . _ @ - (no spaces).";
      }
      if (!f.adminDisplayName.trim()) errs.adminDisplayName = "Required";
      const ae = f.adminEmail.trim();
      if (ae && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(ae)) {
        errs.adminEmail = "Invalid email";
      }
      if (!f.adminAutoGenerate) {
        if (!f.adminPassword) errs.adminPassword = "Required";
        else if (f.adminPassword.length < 10) {
          errs.adminPassword = "At least 10 characters";
        }
      }
      // Custom plan
      if (f.mode === "standard" && f.customPlanOpen) {
        const cp = f.customPlan;
        if (!cp.name || !cp.name.trim()) errs.cpName = "Required";
        if (!Number.isFinite(+cp.maxMembers) || +cp.maxMembers < 1) {
          errs.cpMembers = "Must be at least 1";
        }
        if (!Number.isFinite(+cp.maxProjects) || +cp.maxProjects < 1) {
          errs.cpProjects = "Must be at least 1";
        }
        if (!Number.isFinite(+cp.sharedStorageGb) || +cp.sharedStorageGb <= 0) {
          errs.cpShared = "Must be greater than 0";
        }
        if (
          !Number.isFinite(+cp.privateStorageGb) ||
          +cp.privateStorageGb <= 0
        ) {
          errs.cpPrivate = "Must be greater than 0";
        }
        if (cp.price !== "" && cp.price !== null) {
          if (!Number.isFinite(+cp.price) || +cp.price < 0) {
            errs.cpPrice = "Must be a non-negative number";
          }
        }
      }
      this.fieldErrors = errs;
      return Object.keys(errs).length === 0;
    },
    openConfirm() {
      // Force-blur every field so any pending validation errors render.
      this.blurred = {
        displayname: true,
        contactEmail: true,
        adminUserId: true,
        adminDisplayName: true,
        adminEmail: true,
        adminPassword: true,
        cpName: true,
        cpMembers: true,
        cpProjects: true,
        cpShared: true,
        cpPrivate: true,
        cpPrice: true,
      };
      if (!this.validate()) return;
      if (!this.canSave) return;
      this.confirmPassword = "";
      this.saveError = null;
      this.confirmOpen = true;
      this.focusConfirmInput();
    },
    closeConfirm() {
      if (this.saving) return;
      this.confirmOpen = false;
      this.confirmPassword = "";
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
    onConfirmEnter() {
      if (this.canConfirm) this.saveOrg();
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
    buildCreateBody() {
      const f = this.form;
      const body = {
        validity: f.validity,
        displayname: f.displayname.trim(),
        trial: f.mode === "trial",
        adminUserId: f.adminUserId.trim(),
        adminPassword: f.adminPassword,
      };
      if (f.adminDisplayName.trim()) {
        body.adminDisplayName = f.adminDisplayName.trim();
      }
      if (f.adminEmail.trim()) body.adminEmail = f.adminEmail.trim();
      // Contact
      if (f.contactFirstName.trim()) {
        body.contactFirstName = f.contactFirstName.trim();
      }
      if (f.contactLastName.trim()) {
        body.contactLastName = f.contactLastName.trim();
      }
      if (f.contactEmail.trim()) body.contactEmail = f.contactEmail.trim();
      if (f.contactPhone.trim()) body.contactPhone = f.contactPhone.trim();
      // Plan only in Standard mode
      if (f.mode === "standard") {
        if (f.customPlanOpen) {
          // Custom plan: planId null, send caps + price + currency from form.
          const cp = f.customPlan;
          body.planId = null;
          body.memberLimit = parseInt(cp.maxMembers, 10);
          body.projectsLimit = parseInt(cp.maxProjects, 10);
          body.sharedStoragePerProject = Math.round(
            +cp.sharedStorageGb * 1073741824,
          );
          body.privateStorage = Math.round(+cp.privateStorageGb * 1073741824);
          if (cp.price !== "" && cp.price !== null) {
            body.price = +cp.price;
          } else {
            body.price = 0;
          }
          body.currency = cp.currency || "EUR";
        } else {
          // Existing plan: send id + ride caps along (server treats them
          // consistently with how SubscriptionPanel does — see the
          // "Plan::$currency null" bug fix in commit b4d8f51).
          const sp = this.selectedPlan;
          body.planId = sp.id;
          body.memberLimit = sp.maxMembers;
          body.projectsLimit = sp.maxProjects;
          body.sharedStoragePerProject = sp.sharedStoragePerProject;
          body.privateStorage = sp.privateStoragePerUser;
          body.price = sp.price != null ? Number(sp.price) : 0;
          body.currency = sp.currency || "EUR";
        }
      }
      return body;
    },
    async saveOrg() {
      if (!this.canConfirm) return;
      this.saving = true;
      this.saveError = null;
      try {
        // 1. PasswordConfirmationRequired gate.
        await axios.post(generateUrl("/login/confirm"), {
          password: this.confirmPassword,
        });
        // 2. Create org (single call — plan creation happens inline on the
        //    server when planId is null + caps are sent).
        await axios.post(
          generateOcsUrl("/apps/organization/organizations"),
          this.buildCreateBody(),
          {
            params: { format: "json" },
            headers: ORG_OCS_HEADERS,
          },
        );
        // 3. Branch on whether to show the reveal card.
        if (this.form.adminAutoGenerate) {
          this.createdOrg = {
            displayname: this.form.displayname.trim(),
            adminUserId: this.form.adminUserId.trim(),
            adminPassword: this.form.adminPassword,
          };
          this.confirmOpen = false;
          this.confirmPassword = "";
          // Reveal stays open; admin clicks Done to emit @created.
        } else {
          this.confirmOpen = false;
          this.confirmPassword = "";
          this.$emit("created", {
            displayname: this.form.displayname.trim(),
          });
        }
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
            "Couldn't create organization. Try again.",
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
    copyToClipboard(text, key) {
      const setFlag = (val) => {
        this.$set(this.copyFlags, key, val);
      };
      const schedule = () => {
        if (this._copyTimers[key]) clearTimeout(this._copyTimers[key]);
        this._copyTimers[key] = setTimeout(() => setFlag(false), 1500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard
          .writeText(text)
          .then(() => {
            setFlag(true);
            schedule();
          })
          .catch(() => setFlag(false));
      } else {
        setFlag(false);
      }
    },
    confirmCreatedOrg() {
      const displayname = this.createdOrg
        ? this.createdOrg.displayname
        : "";
      this.$emit("created", { displayname });
    },
  },
};
</script>

<style scoped>


@keyframes co-modal-in {
  from { transform: scale(0.96); opacity: 0; }
  to   { transform: scale(1); opacity: 1; }
}


.co-modal__title {
  font-size: var(--iz-fs-lg);
  font-weight: 700;
  color: var(--color-text-primary);
}

.co-modal__body {
  padding: 18px;
  overflow-y: auto;
  flex: 1;
  min-height: 0;
}


/* ── Mode toggle ─────────────────────────────────────────────────── */

.co-modal__hint {
  background: var(--iz-accent-bg);
  border-left: 3px solid var(--accent);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
  font-size: var(--iz-fs-xs);
  color: var(--accent-strong);
  margin-bottom: 14px;
}

/* ── Sections ────────────────────────────────────────────────────── */
.co-modal__section-label {
  font-size: var(--iz-fs-micro);
  font-weight: 700;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 4px 0 8px;
}

.co-modal__section-label:first-of-type {
  margin-top: 0;
}

.co-modal__optional {
  font-weight: 400;
  color: var(--color-text-muted);
}

/* ── Fields ──────────────────────────────────────────────────────── */
.co-modal__field {
  margin-bottom: 10px;
}

.co-modal__label {
  display: block;
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  color: var(--color-text-secondary);
  margin-bottom: 4px;
}

.co-modal__req {
  color: var(--color-danger-text);
}




.co-modal__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.co-modal__field-error {
  margin-top: 4px;
  font-size: var(--iz-fs-xs);
  color: var(--color-danger-text);
  background: var(--color-danger-bg);
  border: 1px solid var(--color-danger-bg);
  border-radius: var(--radius-sm);
  padding: 6px 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.co-modal__state {
  margin-bottom: 10px;
}

/* ── Plan row ────────────────────────────────────────────────────── */
.co-modal__plan-row {
  display: flex;
  gap: 6px;
  margin-bottom: 6px;
}

.co-modal__plan-select {
  flex: 1;
  min-width: 0;
}

.co-modal__plan-custom-btn {
  background: var(--bg-card);
  border: 1px solid var(--accent);
  color: var(--accent);
  border-radius: var(--radius-sm);
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  padding: 0 12px;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s, color 0.15s;
}

.co-modal__plan-custom-btn:hover:not(:disabled) {
  background: var(--accent);
  color: #fff;
}

.co-modal__plan-custom-btn--active {
  background: var(--iz-accent-bg);
}

.co-modal__plan-custom-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ── Custom plan sub-form ────────────────────────────────────────── */
.co-modal__custom {
  background: var(--bg-subtle);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-el);
  padding: 12px;
  margin: 6px 0 10px;
}

.co-modal__custom-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.co-modal__custom-title {
  font-size: var(--iz-fs-xs);
  font-weight: 700;
  color: var(--accent);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.co-modal__unit-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.co-modal__unit {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted);
}

/* ── Password row (admin user) ───────────────────────────────────── */
.co-modal__password-row {
  display: flex;
  gap: 4px;
}

.co-modal__password-input {
  flex: 1;
  min-width: 0;
}

.co-modal__icon-btn {
  width: 34px;
  background: var(--bg-card);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  color: var(--color-text-secondary);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--iz-fs-lg);
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}

.co-modal__icon-btn:hover:not(:disabled) {
  background: var(--bg-subtle);
  color: var(--color-text-primary);
  border-color: var(--color-text-muted);
}

.co-modal__icon-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.co-modal__autogen {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: var(--iz-fs-xs);
  color: var(--color-text-secondary);
  margin-top: 6px;
  cursor: pointer;
  user-select: none;
}

.co-modal__autogen input {
  margin: 0;
  cursor: pointer;
}

/* ── Password confirmation modal ────────────────────────────────── */
.co-modal__confirm-backdrop {
  position: fixed;
  inset: 0;
  background: var(--iz-scrim);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  z-index: 9100;
  padding: 120px 16px 24px;
}

.co-modal__confirm {
  background: var(--bg-card);
  border-radius: var(--radius-card);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
  width: 100%;
  max-width: 420px;
  overflow: hidden;
  animation: co-modal-in 0.15s ease-out;
}

.co-modal__confirm-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid var(--color-border);
  font-size: var(--iz-fs-md);
  font-weight: 700;
  color: var(--color-text-primary);
}

.co-modal__confirm-body {
  padding: 16px 18px;
}

.co-modal__confirm-password {
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

.co-modal__confirm-password:focus {
  border-color: var(--accent);
}

.co-modal__confirm-error {
  margin-top: 12px;
}

@media (max-width: 720px) {
  .co-modal__grid {
    grid-template-columns: 1fr;
  }
  .co-modal__plan-row {
    flex-direction: column;
  }
}
</style>
