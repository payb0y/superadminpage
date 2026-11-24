<template>
  <div class="iz-modal-backdrop" @click.self="close">
    <form class="iz-modal contract-modal" @submit.prevent="$emit('submit')">
      <header class="iz-modal__header">
        <h3 class="contract-modal__title">{{ title }}</h3>
        <button class="iz-close iz-close--sm" type="button" :aria-label="t('Close')" :disabled="busy" @click="close">
          &times;
        </button>
      </header>

      <div class="iz-modal__body contract-modal__body">
        <label v-if="mode !== 'replace'" class="contract-modal__field">
          <span class="iz-label">{{ t("Contract name") }}</span>
          <input
            class="iz-input"
            type="text"
            maxlength="255"
            required
            autofocus
            :value="displayName"
            @input="$emit('update:displayName', $event.target.value)"
          />
        </label>

        <label v-if="mode !== 'rename'" class="contract-modal__field">
          <span class="iz-label">{{ t("PDF file") }}</span>
          <input
            class="iz-input contract-modal__file"
            type="file"
            accept="application/pdf,.pdf"
            required
            @change="$emit('file-change', $event.target.files[0] || null)"
          />
          <span class="contract-modal__hint">{{ t("PDF only, up to 25 MB.") }}</span>
        </label>

        <p v-if="error" class="contract-modal__error" role="alert">{{ error }}</p>
      </div>

      <footer class="iz-modal__footer">
        <button class="iz-btn" type="button" :disabled="busy" @click="close">{{ t("Cancel") }}</button>
        <button class="iz-btn iz-btn--primary" type="submit" :disabled="busy">
          {{ busy ? t("Saving…") : submitLabel }}
        </button>
      </footer>
    </form>
  </div>
</template>

<script>
import { t } from "../lib/l10n.mjs";

export default {
  name: "ContractModal",
  props: {
    mode: { type: String, required: true },
    displayName: { type: String, default: "" },
    busy: { type: Boolean, default: false },
    error: { type: String, default: "" },
  },
  computed: {
    title() {
      const titles = {
        upload: () => t("Upload contract"),
        rename: () => t("Rename contract"),
        replace: () => t("Replace contract file"),
      };
      return (titles[this.mode] || (() => t("Contract")))();
    },
    submitLabel() {
      const labels = {
        upload: () => t("Upload"),
        rename: () => t("Save name"),
        replace: () => t("Replace file"),
      };
      return (labels[this.mode] || (() => t("Save")))();
    },
  },
  mounted() {
    document.addEventListener("keydown", this.onKeydown);
  },
  beforeDestroy() {
    document.removeEventListener("keydown", this.onKeydown);
  },
  methods: {
    onKeydown(event) {
      if (event.key === "Escape") this.close();
    },
    close() {
      if (!this.busy) this.$emit("close");
    },
  },
};
</script>

<style scoped>
.contract-modal {
  width: min(480px, 100%);
}

.contract-modal__title {
  margin: 0;
  padding: 0;
  border: none;
  font-size: var(--iz-fs-lg);
}

.contract-modal__body,
.contract-modal__field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.contract-modal__file {
  padding: var(--spacing-sm);
}

.contract-modal__hint {
  color: var(--color-text-muted);
  font-size: var(--iz-fs-sm);
}

.contract-modal__error {
  margin: 0;
  color: var(--color-badge-danger-text);
}
</style>
