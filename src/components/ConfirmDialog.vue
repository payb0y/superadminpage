<template>
  <div
    class="iz-modal-backdrop confirm-dialog__backdrop"
    @click.self="onCancel"
  >
    <div
      class="iz-modal confirm-dialog"
      role="alertdialog"
      aria-modal="true"
      :aria-label="title"
    >
      <header class="iz-modal__header">
        <h3 class="confirm-dialog__title">{{ title }}</h3>
        <button
          class="iz-close iz-close--sm"
          type="button"
          aria-label="Close"
          :disabled="busy"
          @click="onCancel"
        >
          &times;
        </button>
      </header>

      <div class="iz-modal__body confirm-dialog__body">
        <p class="iz-modal__confirm-text">{{ message }}</p>
        <!-- Failures land here rather than in a second dialog: a destructive
             action that fails should explain itself where the user is looking,
             with the dialog still open so they can retry or back out. -->
        <p v-if="error" class="confirm-dialog__error" role="alert">
          {{ error }}
        </p>
      </div>

      <footer class="iz-modal__footer">
        <button
          v-if="!alertOnly"
          class="iz-btn"
          type="button"
          :disabled="busy"
          @click="onCancel"
        >
          {{ cancelLabel }}
        </button>
        <button
          ref="confirmBtn"
          class="iz-btn"
          :class="danger ? 'iz-btn--danger' : 'iz-btn--primary'"
          type="button"
          :disabled="busy"
          @click="$emit('confirm')"
        >
          {{ busy ? busyLabel : confirmLabel }}
        </button>
      </footer>
    </div>
  </div>
</template>

<script>
/**
 * The one confirmation / notice dialog.
 *
 * Before this, destructive actions were spread across three patterns — native
 * confirm(), native alert(), and a bespoke inline "Remove?" row — so the same
 * decision looked different depending on which panel you were in, and the
 * native ones could not be themed at all.
 *
 * The chrome is entirely theme primitives (.iz-modal*, .iz-close, .iz-btn), so
 * this file is layout and behaviour only. It is deliberately controlled by the
 * parent: the parent owns the pending flag and the error string, because it is
 * the one making the request. Emits `confirm` and `cancel`; it never closes
 * itself, so an action that fails keeps the dialog open with its message.
 *
 * VENDORED: superadminpage and employee-dashboard carry a copy of this file.
 * A Nextcloud theme cannot ship a Vue component, so a change here has to be
 * copied to the other apps in the same commit.
 */
export default {
  name: "ConfirmDialog",
  props: {
    title: { type: String, required: true },
    message: { type: String, default: "" },
    confirmLabel: { type: String, default: "Confirm" },
    cancelLabel: { type: String, default: "Cancel" },
    // Shown in place of confirmLabel while the parent's request is in flight.
    busyLabel: { type: String, default: "Working…" },
    // Red confirm button for irreversible actions; blue-pink primary otherwise.
    danger: { type: Boolean, default: false },
    // Notice mode: one dismiss button, no cancel. This is the alert() shape.
    alertOnly: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    error: { type: String, default: "" },
  },
  mounted() {
    // Focus the action so Enter confirms and Escape cancels without a reach for
    // the mouse — the affordance the native dialogs gave us for free.
    if (this.$refs.confirmBtn) this.$refs.confirmBtn.focus();
    document.addEventListener("keydown", this.onKeydown);
  },
  beforeDestroy() {
    document.removeEventListener("keydown", this.onKeydown);
  },
  methods: {
    onKeydown(e) {
      if (e.key === "Escape") this.onCancel();
    },
    onCancel() {
      // A request in flight must not be dismissed out from under itself.
      if (this.busy) return;
      this.$emit("cancel");
    },
  },
};
</script>

<style scoped>
.confirm-dialog {
  width: min(420px, 100%);
}

/* NC core styles bare <h3>. */
.confirm-dialog__title {
  margin: 0;
  padding: 0;
  border: none;
  font-size: 16px;
  font-weight: 700;
}

.confirm-dialog__body {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.confirm-dialog__error {
  margin: 0;
  padding: 8px 10px;
  border-radius: var(--radius-sm);
  background: var(--color-badge-danger-bg);
  color: var(--color-badge-danger-text);
  font-size: 12px;
}
</style>
