<template>
  <div class="iz-modal-backdrop org-settings__backdrop" @click.self="close">
    <div class="iz-modal org-settings" role="dialog" aria-modal="true" aria-labelledby="org-settings-title">
      <header class="iz-modal__header">
        <div>
          <p class="org-settings__eyebrow">Organization-wide configuration</p>
          <h3 id="org-settings-title" class="org-settings__title">Organization settings</h3>
        </div>
        <button
          type="button"
          class="iz-close iz-close--sm"
          aria-label="Close"
          :disabled="busy"
          @click="close"
        >&times;</button>
      </header>

      <div class="iz-tabs org-settings__tabs" role="tablist" aria-label="Organization settings">
        <button
          type="button"
          class="iz-tab"
          :class="{ 'iz-tab--active': activeTab === 'pdf' }"
          role="tab"
          :aria-selected="activeTab === 'pdf'"
          :disabled="busy"
          @click="activeTab = 'pdf'"
        >Default project PDF</button>
        <button
          type="button"
          class="iz-tab"
          :class="{ 'iz-tab--active': activeTab === 'ocr' }"
          role="tab"
          :aria-selected="activeTab === 'ocr'"
          :disabled="busy"
          @click="activeTab = 'ocr'"
        >OCR document types</button>
      </div>

      <div v-if="activeTab === 'pdf'" class="iz-modal__body org-pdf-modal__body">
        <p class="org-pdf-modal__intro">
          This PDF is automatically added to the shared folder of every new project in this organization.
        </p>

        <p v-if="error" class="org-pdf-modal__error" role="alert">{{ error }}</p>

        <div v-if="loading" class="iz-empty org-pdf-modal__loading">Loading template settings...</div>

        <template v-else>
          <div class="org-pdf-modal__status">
            <span class="org-pdf-modal__document" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
              </svg>
            </span>
            <span class="org-pdf-modal__status-copy">
              <span class="iz-label">Current template</span>
              <strong>{{ hasCustomPdf ? currentFileName || "Custom organization PDF" : "System default PDF" }}</strong>
              <small>{{ hasCustomPdf ? "Custom template active" : "Fallback used for new projects" }}</small>
            </span>
          </div>

          <div class="org-pdf-modal__field">
            <label class="iz-label" for="org-pdf-file">Upload a new PDF template</label>
            <div
              class="org-pdf-modal__dropzone"
              :class="{ 'org-pdf-modal__dropzone--selected': selectedFile }"
              role="button"
              tabindex="0"
              @click="chooseFile"
              @keydown.enter.prevent="chooseFile"
              @keydown.space.prevent="chooseFile"
              @dragover.prevent
              @drop.prevent="onDrop"
            >
              <input
                id="org-pdf-file"
                ref="fileInput"
                class="org-pdf-modal__file-input"
                type="file"
                accept="application/pdf,.pdf"
                :disabled="busy"
                @change="onFileSelected"
              />
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              <template v-if="selectedFile">
                <strong>{{ selectedFile.name }}</strong>
                <small>{{ formatFileSize(selectedFile.size) }}</small>
              </template>
              <template v-else>
                <strong>Choose or drop a PDF here</strong>
                <small>Only PDF documents are accepted</small>
              </template>
            </div>
          </div>

          <div v-if="selectedFile" class="org-pdf-modal__field">
            <label class="iz-label" for="org-pdf-name">Filename in new projects</label>
            <input
              id="org-pdf-name"
              v-model="fileName"
              class="iz-input"
              type="text"
              placeholder="e.g. Welcome guide.pdf"
              :disabled="busy"
            />
            <small>The .pdf extension is added if omitted.</small>
          </div>
        </template>
      </div>

      <OrganizationOcrSettings
        v-else
        :organization-id="organizationId"
        @lock-change="ocrLocked = $event"
      />

      <footer v-if="activeTab === 'pdf'" class="iz-modal__footer org-pdf-modal__footer">
        <button
          v-if="hasCustomPdf && !loading"
          type="button"
          class="iz-btn iz-btn--danger"
          :disabled="busy"
          @click="showResetConfirmation = true"
        >Reset to default</button>
        <span class="org-pdf-modal__actions">
          <button type="button" class="iz-btn" :disabled="busy" @click="close">Cancel</button>
          <button
            type="button"
            class="iz-btn iz-btn--primary"
            :disabled="!selectedFile || busy"
            @click="save"
          >{{ uploading ? "Uploading..." : "Save template" }}</button>
        </span>
      </footer>

      <ConfirmDialog
        v-if="showResetConfirmation"
        title="Reset project PDF?"
        message="The custom template will be removed and new projects will use the system default PDF."
        confirm-label="Reset to default"
        busy-label="Resetting..."
        :danger="true"
        :busy="resetting"
        :error="resetError"
        @confirm="resetToDefault"
        @cancel="closeResetConfirmation"
      />
    </div>
  </div>
</template>

<script>
import ConfirmDialog from "./ConfirmDialog.vue";
import OrganizationOcrSettings from "./OrganizationOcrSettings.vue";
import {
  deleteOrganizationPdf,
  getOrganizationPdfInfo,
  uploadOrganizationPdf,
} from "../services/projectCreatorApi";

export default {
  name: "OrganizationSettingsModal",
  components: { ConfirmDialog, OrganizationOcrSettings },
  props: {
    organizationId: { type: Number, required: true },
  },
  data: function () {
    return {
      loading: true,
      activeTab: "pdf",
      ocrLocked: false,
      uploading: false,
      resetting: false,
      hasCustomPdf: false,
      currentFileName: "",
      selectedFile: null,
      fileName: "",
      error: "",
      showResetConfirmation: false,
      resetError: "",
    };
  },
  computed: {
    busy: function () {
      return this.uploading || this.resetting || this.ocrLocked;
    },
  },
  mounted: function () {
    this.load();
    document.addEventListener("keydown", this.onKeydown);
  },
  beforeDestroy: function () {
    document.removeEventListener("keydown", this.onKeydown);
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = "";
      try {
        const info = await getOrganizationPdfInfo(this.organizationId);
        this.hasCustomPdf = Boolean(info && info.has_custom_pdf);
        this.currentFileName = (info && info.file_name) || "";
      } catch (error) {
        console.error("Failed to load organization PDF settings", error);
        this.error = this.errorMessage(error, "Could not load the PDF template settings.");
      } finally {
        this.loading = false;
      }
    },
    close() {
      if (!this.busy && !this.showResetConfirmation) this.$emit("close");
    },
    onKeydown(event) {
      if (event.key === "Escape") this.close();
    },
    chooseFile() {
      if (!this.busy && this.$refs.fileInput) this.$refs.fileInput.click();
    },
    onFileSelected(event) {
      this.setFile(event.target.files && event.target.files[0]);
    },
    onDrop(event) {
      if (!this.busy) this.setFile(event.dataTransfer.files && event.dataTransfer.files[0]);
    },
    setFile(file) {
      if (!file) return;
      if (file.type !== "application/pdf" && !file.name.toLowerCase().endsWith(".pdf")) {
        this.error = "Please select a valid PDF document (.pdf).";
        this.selectedFile = null;
        return;
      }
      this.error = "";
      this.selectedFile = file;
      this.fileName = file.name;
    },
    normalizedFileName() {
      const name = String(this.fileName || "").trim();
      if (!name) return "";
      return /\.pdf$/i.test(name) ? name : `${name}.pdf`;
    },
    async save() {
      const fileName = this.normalizedFileName();
      if (!fileName) {
        this.error = "Enter a filename for the PDF template.";
        return;
      }
      this.uploading = true;
      this.error = "";
      try {
        await uploadOrganizationPdf(this.organizationId, this.selectedFile, fileName);
        this.$emit("updated");
        this.$emit("close");
      } catch (error) {
        console.error("Failed to upload organization PDF", error);
        this.error = this.errorMessage(error, "Could not upload the PDF template.");
      } finally {
        this.uploading = false;
      }
    },
    async resetToDefault() {
      this.resetting = true;
      this.resetError = "";
      try {
        await deleteOrganizationPdf(this.organizationId);
        this.hasCustomPdf = false;
        this.currentFileName = "";
        this.selectedFile = null;
        this.fileName = "";
        if (this.$refs.fileInput) this.$refs.fileInput.value = "";
        this.showResetConfirmation = false;
        this.$emit("updated");
      } catch (error) {
        console.error("Failed to reset organization PDF", error);
        this.resetError = this.errorMessage(error, "Could not reset the PDF template.");
      } finally {
        this.resetting = false;
      }
    },
    closeResetConfirmation() {
      if (!this.resetting) {
        this.showResetConfirmation = false;
        this.resetError = "";
      }
    },
    errorMessage(error, fallback) {
      const data = error && error.response && error.response.data;
      return (data && (data.error || data.message)) || fallback;
    },
    formatFileSize(bytes) {
      if (!bytes) return "0 B";
      const units = ["B", "KB", "MB", "GB"];
      const unit = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
      return `${parseFloat((bytes / Math.pow(1024, unit)).toFixed(1))} ${units[unit]}`;
    },
  },
};
</script>

<style scoped>
.org-settings {
  width: min(980px, 100%);
  max-height: min(860px, calc(100vh - (2 * var(--spacing-lg))));
  overflow: hidden;
}

.org-settings__eyebrow,
.org-settings__title,
.org-pdf-modal__intro,
.org-pdf-modal__error {
  margin: 0;
}

.org-settings__eyebrow {
  color: var(--color-text-muted);
  font-size: var(--iz-fs-xs);
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.org-settings__title {
  padding: 0;
  border: 0;
  font-size: var(--iz-fs-lg);
}

.org-settings__tabs {
  padding: 0 var(--spacing-lg);
}

.org-pdf-modal__body,
.org-pdf-modal__field,
.org-pdf-modal__status-copy {
  display: flex;
  flex-direction: column;
}

.org-pdf-modal__body {
  gap: var(--spacing-md);
  overflow-y: auto;
}

.org-pdf-modal__intro,
.org-pdf-modal__field small,
.org-pdf-modal__status-copy small {
  color: var(--color-text-secondary);
}

.org-pdf-modal__error {
  padding: var(--spacing-sm) var(--spacing-md);
  border-radius: var(--radius-sm);
  background: var(--color-badge-danger-bg);
  color: var(--color-badge-danger-text);
}

.org-pdf-modal__loading {
  padding: var(--spacing-xl);
}

.org-pdf-modal__status {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
  padding: var(--spacing-md);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-el);
  background: var(--bg-subtle);
}

.org-pdf-modal__document {
  display: inline-flex;
  flex: 0 0 auto;
  color: var(--accent);
}

.org-pdf-modal__status-copy,
.org-pdf-modal__field {
  gap: var(--spacing-xs);
}

.org-pdf-modal__dropzone {
  display: flex;
  min-height: 132px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-xs);
  padding: var(--spacing-lg);
  border: 2px dashed var(--color-border);
  border-radius: var(--radius-el);
  color: var(--color-text-secondary);
  text-align: center;
  cursor: pointer;
}

.org-pdf-modal__dropzone:hover,
.org-pdf-modal__dropzone:focus-visible,
.org-pdf-modal__dropzone--selected {
  border-color: var(--accent);
  background: var(--accent-bg);
  color: var(--color-text-primary);
}

.org-pdf-modal__file-input {
  display: none;
}

.org-pdf-modal__footer {
  justify-content: space-between;
}

.org-pdf-modal__actions {
  display: flex;
  gap: var(--spacing-sm);
  margin-left: auto;
}

@media (max-width: 600px) {
  .org-pdf-modal__footer {
    align-items: stretch;
    flex-direction: column;
  }

  .org-pdf-modal__actions {
    margin-left: 0;
  }

  .org-pdf-modal__actions .iz-btn {
    flex: 1;
  }
}
</style>
