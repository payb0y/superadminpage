<template>
  <div class="ocr-settings">
    <p v-if="error" class="ocr-settings__error" role="alert">{{ error }}</p>

    <div v-if="loading" class="iz-empty ocr-settings__loading">
      Loading OCR document types...
    </div>

    <div v-else class="ocr-settings__layout">
      <aside class="ocr-settings__sidebar">
        <button
          type="button"
          class="iz-btn iz-btn--primary ocr-settings__new"
          :disabled="actionBusy"
          @click="startCreate"
        >
          <span aria-hidden="true">+</span>
          New document type
        </button>

        <div class="ocr-settings__type-list">
          <span class="iz-label">Configured types</span>
          <p v-if="documentTypes.length === 0" class="iz-empty ocr-settings__empty">
            No document types defined yet.
          </p>
          <template v-else>
            <button
              v-for="documentType in documentTypes"
              :key="documentType.id"
              type="button"
              class="iz-btn ocr-settings__type"
              :class="Number(selectedTypeId) === Number(documentType.id) ? 'iz-btn--primary' : 'iz-btn--ghost'"
              :disabled="actionBusy"
              @click="selectType(documentType)"
            >
              <span class="ocr-settings__type-copy">
                <strong>{{ documentType.name }}</strong>
                <small>{{ fieldCount(documentType) }} fields</small>
              </span>
              <span
                class="ocr-settings__status"
                :class="{ 'ocr-settings__status--active': documentType.is_active }"
                :title="documentType.is_active ? 'Active' : 'Inactive'"
              />
            </button>
          </template>
        </div>
      </aside>

      <main class="ocr-settings__editor">
        <div class="ocr-settings__scroll">
          <div>
            <h4 class="ocr-settings__title">{{ form.id ? "Edit document type" : "Create document type" }}</h4>
            <p class="ocr-settings__intro">
              Define the fields OCR should extract from documents of this type.
            </p>
          </div>

          <section class="ocr-settings__section">
            <div class="ocr-settings__section-heading">
              <div>
                <h5>General information</h5>
                <p>Name this schema and choose whether it is available in projects.</p>
              </div>
              <label class="ocr-settings__active">
                <input v-model="form.is_active" type="checkbox" :disabled="actionBusy" />
                Active
              </label>
            </div>
            <label class="ocr-settings__field">
              <span class="iz-label">Document type name</span>
              <input
                v-model="form.name"
                class="iz-input"
                type="text"
                placeholder="e.g. Invoice"
                :disabled="actionBusy"
              />
            </label>
          </section>

          <section class="ocr-settings__section">
            <div class="ocr-settings__section-heading">
              <div>
                <h5>Extraction fields</h5>
                <p>Add every data point that OCR must extract from this document type.</p>
              </div>
            </div>

            <div class="ocr-settings__fields">
              <div v-for="(field, index) in form.fields" :key="`field-${index}`" class="iz-panel ocr-settings__field-row">
                <label class="ocr-settings__field">
                  <span class="iz-label">Field name</span>
                  <input
                    v-model="field.name"
                    class="iz-input"
                    type="text"
                    placeholder="e.g. Total amount"
                    :disabled="actionBusy"
                  />
                </label>
                <button
                  type="button"
                  class="iz-btn iz-btn--ghost"
                  :disabled="actionBusy || form.fields.length === 1"
                  @click="removeField(index)"
                >Remove</button>
              </div>
            </div>

            <button type="button" class="iz-btn" :disabled="actionBusy" @click="addField">
              <span aria-hidden="true">+</span>
              Add another field
            </button>
          </section>
        </div>

        <footer class="iz-modal__footer ocr-settings__actions">
          <button
            v-if="form.id"
            type="button"
            class="iz-btn iz-btn--danger"
            :disabled="actionBusy"
            @click="showDeleteConfirmation = true"
          >Delete type</button>
          <span class="ocr-settings__actions-right">
            <button type="button" class="iz-btn" :disabled="actionBusy" @click="resetForm">Reset</button>
            <button type="button" class="iz-btn iz-btn--primary" :disabled="actionBusy" @click="saveType">
              {{ saveLabel }}
            </button>
          </span>
        </footer>
      </main>
    </div>

    <ConfirmDialog
      v-if="showDeleteConfirmation"
      title="Delete OCR document type?"
      :message="`The document type '${form.name}' will be permanently deleted.`"
      confirm-label="Delete type"
      busy-label="Deleting..."
      :danger="true"
      :busy="deleting"
      :error="deleteError"
      @confirm="deleteType"
      @cancel="closeDeleteConfirmation"
    />
  </div>
</template>

<script>
import ConfirmDialog from "./ConfirmDialog.vue";
import {
  createOrganizationDocumentType,
  deleteOrganizationDocumentType,
  listOrganizationDocumentTypes,
  updateOrganizationDocumentType,
} from "../services/projectCreatorApi";

function emptyField() {
  return { name: "" };
}

function emptyForm() {
  return {
    id: null,
    name: "",
    is_active: true,
    fields: [emptyField()],
  };
}

export default {
  name: "OrganizationOcrSettings",
  components: { ConfirmDialog },
  props: {
    organizationId: { type: Number, required: true },
  },
  data: function () {
    return {
      loading: true,
      saving: false,
      deleting: false,
      error: "",
      deleteError: "",
      showDeleteConfirmation: false,
      documentTypes: [],
      selectedTypeId: null,
      form: emptyForm(),
    };
  },
  computed: {
    actionBusy: function () {
      return this.saving || this.deleting;
    },
    locked: function () {
      return this.actionBusy || this.showDeleteConfirmation;
    },
    saveLabel: function () {
      if (this.saving) return "Saving...";
      return this.form.id ? "Update type" : "Create type";
    },
  },
  watch: {
    locked: {
      immediate: true,
      handler: function (locked) {
        this.$emit("lock-change", locked);
      },
    },
  },
  mounted: function () {
    this.loadDocumentTypes();
  },
  beforeDestroy: function () {
    this.$emit("lock-change", false);
  },
  methods: {
    async loadDocumentTypes() {
      this.loading = true;
      this.error = "";
      try {
        this.documentTypes = await listOrganizationDocumentTypes(this.organizationId);
        if (this.selectedTypeId !== null) {
          const selected = this.findType(this.selectedTypeId);
          if (selected) {
            this.applyType(selected);
            return;
          }
        }
        this.startCreate();
      } catch (error) {
        console.error("Failed to load organization OCR document types", error);
        this.error = this.errorMessage(error, "Could not load OCR document types.");
      } finally {
        this.loading = false;
      }
    },
    findType(id) {
      return this.documentTypes.find(function (documentType) {
        return Number(documentType.id) === Number(id);
      });
    },
    fieldCount(documentType) {
      return Array.isArray(documentType.fields) ? documentType.fields.length : 0;
    },
    startCreate() {
      this.selectedTypeId = null;
      this.form = emptyForm();
      this.error = "";
    },
    selectType(documentType) {
      this.selectedTypeId = Number(documentType.id);
      this.applyType(documentType);
      this.error = "";
    },
    applyType(documentType) {
      const fields = Array.isArray(documentType.fields) && documentType.fields.length
        ? documentType.fields.map(function (field) {
          return { name: field.name || field.label || field.key || "" };
        })
        : [emptyField()];
      this.form = {
        id: Number(documentType.id) || null,
        name: documentType.name || documentType.label || documentType.key || "",
        is_active: Boolean(documentType.is_active),
        fields,
      };
    },
    resetForm() {
      const selected = this.findType(this.selectedTypeId);
      if (selected) {
        this.applyType(selected);
        this.error = "";
        return;
      }
      this.startCreate();
    },
    addField() {
      this.form.fields.push(emptyField());
    },
    removeField(index) {
      if (this.form.fields.length > 1) this.form.fields.splice(index, 1);
    },
    buildPayload() {
      return {
        name: String(this.form.name || "").trim(),
        is_active: this.form.is_active ? 1 : 0,
        fields: this.form.fields.map(function (field) {
          return { name: String(field.name || "").trim() };
        }),
      };
    },
    async saveType() {
      const payload = this.buildPayload();
      if (!payload.name) {
        this.error = "Enter a document type name.";
        return;
      }
      if (payload.fields.some(function (field) {
        return !field.name;
      })) {
        this.error = "Enter a name for every extraction field.";
        return;
      }

      this.saving = true;
      this.error = "";
      try {
        const saved = this.form.id
          ? await updateOrganizationDocumentType(this.organizationId, this.form.id, payload)
          : await createOrganizationDocumentType(this.organizationId, payload);
        this.selectedTypeId = saved && saved.id ? Number(saved.id) : this.form.id;
        await this.loadDocumentTypes();
      } catch (error) {
        console.error("Failed to save organization OCR document type", error);
        this.error = this.errorMessage(error, "Could not save the OCR document type.");
      } finally {
        this.saving = false;
      }
    },
    async deleteType() {
      if (!this.form.id) return;
      this.deleting = true;
      this.deleteError = "";
      try {
        await deleteOrganizationDocumentType(this.organizationId, this.form.id);
        this.showDeleteConfirmation = false;
        this.selectedTypeId = null;
        await this.loadDocumentTypes();
      } catch (error) {
        console.error("Failed to delete organization OCR document type", error);
        this.deleteError = this.errorMessage(error, "Could not delete the OCR document type.");
      } finally {
        this.deleting = false;
      }
    },
    closeDeleteConfirmation() {
      if (!this.deleting) {
        this.showDeleteConfirmation = false;
        this.deleteError = "";
      }
    },
    errorMessage(error, fallback) {
      const data = error && error.response && error.response.data;
      return (data && (data.error || data.message)) || fallback;
    },
  },
};
</script>

<style scoped>
.ocr-settings {
  display: flex;
  min-height: 0;
  flex: 1;
  flex-direction: column;
  overflow: hidden;
}

.ocr-settings__error {
  margin: var(--spacing-md) var(--spacing-lg) 0;
  padding: var(--spacing-sm) var(--spacing-md);
  border-radius: var(--radius-sm);
  background: var(--color-badge-danger-bg);
  color: var(--color-badge-danger-text);
}

.ocr-settings__loading {
  padding: var(--spacing-xl);
}

.ocr-settings__layout {
  display: grid;
  min-height: 0;
  flex: 1;
  grid-template-columns: 260px minmax(0, 1fr);
}

.ocr-settings__sidebar {
  display: flex;
  min-height: 0;
  flex-direction: column;
  gap: var(--spacing-lg);
  padding: var(--spacing-lg);
  border-right: 1px solid var(--color-border);
  overflow-y: auto;
}

.ocr-settings__new {
  justify-content: center;
}

.ocr-settings__type-list,
.ocr-settings__type-copy,
.ocr-settings__editor,
.ocr-settings__scroll,
.ocr-settings__section,
.ocr-settings__fields,
.ocr-settings__field {
  display: flex;
  flex-direction: column;
}

.ocr-settings__type-list,
.ocr-settings__fields,
.ocr-settings__field {
  gap: var(--spacing-xs);
}

button.ocr-settings__type {
  width: 100%;
  min-height: auto;
  justify-content: space-between;
  text-align: left;
}

.ocr-settings__type-copy {
  min-width: 0;
}

.ocr-settings__type-copy strong,
.ocr-settings__type-copy small {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ocr-settings__status {
  width: var(--spacing-xs);
  height: var(--spacing-xs);
  flex: 0 0 auto;
  border-radius: var(--radius-pill);
  background: var(--color-text-muted);
}

.ocr-settings__status--active {
  background: var(--color-success);
}

.ocr-settings__empty {
  margin: 0;
  padding: var(--spacing-lg) var(--spacing-sm);
}

.ocr-settings__editor {
  min-width: 0;
  min-height: 0;
}

.ocr-settings__scroll {
  flex: 1;
  gap: var(--spacing-xl);
  padding: var(--spacing-xl);
  overflow-y: auto;
}

.ocr-settings__title,
.ocr-settings__intro,
.ocr-settings__section-heading h5,
.ocr-settings__section-heading p {
  margin: 0;
}

.ocr-settings__title {
  padding: 0;
  border: 0;
  font-size: var(--iz-fs-lg);
}

.ocr-settings__intro,
.ocr-settings__section-heading p {
  color: var(--color-text-secondary);
}

.ocr-settings__section {
  gap: var(--spacing-md);
}

.ocr-settings__section-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--spacing-md);
  padding-bottom: var(--spacing-sm);
  border-bottom: 1px solid var(--color-border);
}

.ocr-settings__section-heading h5 {
  padding: 0;
  border: 0;
  font-size: var(--iz-fs-md);
}

.ocr-settings__active {
  display: flex;
  align-items: center;
  gap: var(--spacing-xs);
  font-weight: 700;
  white-space: nowrap;
}

.ocr-settings__field-row {
  display: grid;
  align-items: end;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: var(--spacing-sm);
}

.ocr-settings__actions {
  justify-content: space-between;
}

.ocr-settings__actions-right {
  display: flex;
  gap: var(--spacing-sm);
  margin-left: auto;
}

@media (max-width: 760px) {
  .ocr-settings__layout {
    display: flex;
    flex-direction: column;
    overflow-y: auto;
  }

  .ocr-settings__sidebar {
    flex: 0 0 auto;
    border-right: 0;
    border-bottom: 1px solid var(--color-border);
    overflow: visible;
  }

  .ocr-settings__editor {
    overflow: visible;
  }

  .ocr-settings__scroll {
    flex: 0 0 auto;
    padding: var(--spacing-lg);
    overflow: visible;
  }

  .ocr-settings__field-row {
    grid-template-columns: 1fr;
  }

  .ocr-settings__actions {
    align-items: stretch;
    flex-direction: column;
  }

  .ocr-settings__actions-right {
    margin-left: 0;
  }

  .ocr-settings__actions-right .iz-btn {
    flex: 1;
  }
}
</style>
