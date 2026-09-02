<template>
  <section class="iz-panel iz-panel--flush org-settings">
    <div
      class="iz-segment org-settings__nav"
      role="tablist"
      aria-label="Organization settings"
    >
      <button
        v-for="section in sections"
        :key="section.key"
        type="button"
        class="iz-btn iz-btn--sm"
        :class="{ 'iz-btn--active': activeSection === section.key }"
        role="tab"
        :aria-selected="activeSection === section.key"
        :disabled="busy"
        @click="activeSection = section.key"
      >{{ section.label }}</button>
    </div>

    <ContractsPanel
      v-if="activeSection === 'contracts'"
      :org-id="organizationId"
    />

    <div v-else-if="activeSection === 'pdf'" class="org-pdf">
      <header class="org-pdf__header">
        <h3 class="iz-panel__title">Default project PDF</h3>
        <p class="org-pdf__subtitle">
          This PDF is automatically added to the shared folder of every new project in this organization.
        </p>
      </header>

      <p v-if="error" class="org-pdf__error" role="alert">{{ error }}</p>

      <div v-if="loading" class="iz-empty org-pdf__loading">Loading template settings...</div>

      <template v-else>
        <div class="org-pdf__status">
          <span class="org-pdf__document" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
            </svg>
          </span>
          <span class="org-pdf__status-copy">
            <span class="iz-label">Current template</span>
            <strong>{{ hasCustomPdf ? currentFileName || "Custom organization PDF" : "System default PDF" }}</strong>
            <small>{{ hasCustomPdf ? "Custom template active" : "Fallback used for new projects" }}</small>
          </span>
        </div>

        <div class="org-pdf__field">
          <label class="iz-label" for="org-pdf-file">Upload a new PDF template</label>
          <div
            class="org-pdf__dropzone"
            :class="{ 'org-pdf__dropzone--selected': selectedFile }"
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
              class="org-pdf__file-input"
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

        <div v-if="selectedFile" class="org-pdf__field">
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

        <!-- The modal's footer buttons, now an inline action bar. "Cancel" went
             with the modal: there is no dialog left to dismiss, and the tab
             itself is the way out. -->
        <div class="org-pdf__actions">
          <button
            v-if="hasCustomPdf"
            type="button"
            class="iz-btn iz-btn--danger"
            :disabled="busy"
            @click="showResetConfirmation = true"
          >Reset to default</button>
          <span class="org-pdf__actions-spacer"></span>
          <button
            type="button"
            class="iz-btn iz-btn--primary"
            :disabled="!selectedFile || busy"
            @click="save"
          >{{ uploading ? "Uploading..." : "Save template" }}</button>
        </div>
      </template>
    </div>

    <OrganizationOcrSettings
      v-else
      :organization-id="organizationId"
      @lock-change="ocrLocked = $event"
    />

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
  </section>
</template>

<script>
import ConfirmDialog from "./ConfirmDialog.vue";
import ContractsPanel from "./ContractsPanel.vue";
import OrganizationOcrSettings from "./OrganizationOcrSettings.vue";
import {
  deleteOrganizationPdf,
  getOrganizationPdfInfo,
  uploadOrganizationPdf,
} from "../services/projectCreatorApi";

// Contracts leads: it is the section reached most often, and it is where the
// signing round-trip (?contractsOrg=…) lands after the Settings tab absorbed
// the old Contracts tab.
const SECTIONS = [
  { key: "contracts", label: "Contract files" },
  { key: "pdf", label: "Default project PDF" },
  { key: "ocr", label: "OCR document types" },
];

export default {
  name: "OrganizationSettingsPanel",
  components: { ConfirmDialog, ContractsPanel, OrganizationOcrSettings },
  props: {
    organizationId: { type: Number, required: true },
  },
  data: function () {
    return {
      activeSection: "contracts",
      loading: true,
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
    sections: function () {
      return SECTIONS;
    },
    busy: function () {
      return this.uploading || this.resetting || this.ocrLocked;
    },
  },
  mounted: function () {
    this.load();
  },
  methods: {
    // `quiet` skips the loading state for the refresh that follows an upload:
    // the section is already on screen and swapping it for a spinner reads as
    // the save having thrown something away.
    async load(quiet) {
      if (!quiet) this.loading = true;
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
        this.clearSelection();
        // The refreshed "Current template" line is what confirms the upload
        // now that closing the dialog no longer does.
        await this.load(true);
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
        this.clearSelection();
        this.showResetConfirmation = false;
      } catch (error) {
        console.error("Failed to reset organization PDF", error);
        this.resetError = this.errorMessage(error, "Could not reset the PDF template.");
      } finally {
        this.resetting = false;
      }
    },
    clearSelection() {
      this.selectedFile = null;
      this.fileName = "";
      if (this.$refs.fileInput) this.$refs.fileInput.value = "";
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
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}

/* The segment hugs its labels; stretched across the body it would read as a
   second tab strip under the first. */
.org-settings__nav {
  align-self: flex-start;
}

.org-pdf,
.org-pdf__header,
.org-pdf__field,
.org-pdf__status-copy {
  display: flex;
  flex-direction: column;
}

.org-pdf {
  gap: var(--spacing-md);
}

.org-pdf__header {
  gap: var(--spacing-xs);
}

.org-pdf__subtitle,
.org-pdf__error {
  margin: 0;
}

.org-pdf__subtitle,
.org-pdf__field small,
.org-pdf__status-copy small {
  color: var(--color-text-secondary);
}

.org-pdf__error {
  padding: var(--spacing-sm) var(--spacing-md);
  border-radius: var(--radius-sm);
  background: var(--color-badge-danger-bg);
  color: var(--color-badge-danger-text);
}

.org-pdf__loading {
  padding: var(--spacing-xl);
}

.org-pdf__status {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
  padding: var(--spacing-md);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-el);
  background: var(--bg-subtle);
}

.org-pdf__document {
  display: inline-flex;
  flex: 0 0 auto;
  color: var(--accent);
}

.org-pdf__status-copy,
.org-pdf__field {
  gap: var(--spacing-xs);
}

.org-pdf__dropzone {
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

.org-pdf__dropzone:hover,
.org-pdf__dropzone:focus-visible,
.org-pdf__dropzone--selected {
  border-color: var(--accent);
  background: var(--accent-bg);
  color: var(--color-text-primary);
}

.org-pdf__file-input {
  display: none;
}

.org-pdf__actions {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  padding-top: var(--spacing-md);
  border-top: 1px solid var(--color-border);
}

.org-pdf__actions-spacer {
  flex: 1;
}

@media (max-width: 600px) {
  .org-pdf__actions {
    align-items: stretch;
    flex-direction: column;
  }

  .org-pdf__actions-spacer {
    display: none;
  }

  .org-pdf__actions .iz-btn {
    flex: 1;
  }
}
</style>
