<template>
  <div class="iz-modal-backdrop" @click.self="close">
    <form class="iz-modal signature-modal" @submit.prevent="$emit('submit')">
      <header class="iz-modal__header">
        <div>
          <h3 class="signature-modal__title">Request signatures</h3>
          <p class="signature-modal__subtitle">Add external signers. Invitations are sent only after field placement and review.</p>
        </div>
        <button class="iz-close iz-close--sm" type="button" aria-label="Close" :disabled="busy" @click="close">&times;</button>
      </header>
      <div class="iz-modal__body signature-modal__body">
        <div v-for="(signer, index) in signers" :key="index" class="signature-modal__signer">
          <span class="iz-badge iz-badge--muted">{{ index + 1 }}</span>
          <label class="signature-modal__field">
            <span class="iz-label">Name</span>
            <input v-model.trim="signer.displayName" class="iz-input" type="text" maxlength="255" required />
          </label>
          <label class="signature-modal__field">
            <span class="iz-label">Email</span>
            <input v-model.trim="signer.email" class="iz-input" type="email" maxlength="255" required />
          </label>
          <button v-if="signers.length > 1" class="iz-btn iz-btn--icon iz-btn--danger" type="button" aria-label="Remove signer" @click="remove(index)">&times;</button>
        </div>
        <button class="iz-btn" type="button" @click="add">Add another signer</button>
        <p v-if="error" class="signature-modal__error" role="alert">{{ error }}</p>
      </div>
      <footer class="iz-modal__footer">
        <button class="iz-btn" type="button" :disabled="busy" @click="close">Cancel</button>
        <button class="iz-btn iz-btn--primary" type="submit" :disabled="busy">{{ busy ? "Creating draft…" : "Continue to placement" }}</button>
      </footer>
    </form>
  </div>
</template>

<script>
export default {
  name: "SignatureRequestModal",
  props: {
    signers: { type: Array, required: true },
    busy: { type: Boolean, default: false },
    error: { type: String, default: "" },
  },
  methods: {
    add() {
      this.signers.push({ displayName: "", email: "" });
    },
    remove(index) {
      this.signers.splice(index, 1);
    },
    close() {
      if (!this.busy) this.$emit("close");
    },
  },
};
</script>

<style scoped>
.signature-modal {
  width: min(720px, 100%);
}

.signature-modal__title,
.signature-modal__subtitle {
  margin: 0;
}

.signature-modal__title {
  font-size: var(--iz-fs-lg);
}

.signature-modal__subtitle {
  margin-top: var(--spacing-xs);
  color: var(--color-text-muted);
}

.signature-modal__body {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}

.signature-modal__signer {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) minmax(0, 1fr) auto;
  align-items: end;
  gap: var(--spacing-sm);
}

.signature-modal__field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
}

.signature-modal__error {
  margin: 0;
  color: var(--color-badge-danger-text);
}

@media (max-width: 640px) {
  .signature-modal__signer {
    grid-template-columns: auto 1fr auto;
  }

  .signature-modal__field {
    grid-column: 2;
  }
}
</style>
