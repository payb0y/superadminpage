<template>
  <section class="iz-panel iz-panel--flush contracts-panel">
    <header class="contracts-panel__header">
      <div>
        <h3 class="contracts-panel__title">Contract files</h3>
        <p class="contracts-panel__subtitle">Private PDF contracts for this organization.</p>
      </div>
      <button class="iz-btn iz-btn--primary" type="button" @click="openUpload">Upload contract</button>
    </header>

    <p v-if="loadError" class="contracts-panel__message contracts-panel__message--error" role="alert">
      {{ loadError }}
      <button class="iz-btn iz-btn--sm" type="button" @click="loadContracts">Retry</button>
    </p>
    <div v-else-if="loading" class="iz-empty contracts-panel__empty">Loading contracts…</div>
    <div v-else-if="contracts.length === 0" class="iz-empty contracts-panel__empty">
      <p class="contracts-panel__empty-title">No contracts uploaded</p>
      <p class="contracts-panel__subtitle">Upload the first PDF contract for this organization.</p>
    </div>
    <div v-else class="iz-table-wrap">
      <table class="iz-table contracts-panel__table">
        <thead>
          <tr>
            <th>Contract</th>
            <th>File</th>
            <th>Size</th>
            <th>Uploaded by</th>
            <th>Updated</th>
            <th><span class="contracts-panel__sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <template v-for="contract in contracts">
          <tr :key="'contract-' + contract.id">
            <td class="contracts-panel__name">{{ contract.displayName }}</td>
            <td class="contracts-panel__filename" :title="contract.originalFilename">{{ contract.originalFilename }}</td>
            <td>{{ formatBytes(contract.fileSize) }}</td>
            <td>{{ contract.uploadedByUid }}</td>
            <td>{{ formatDate(contract.updatedAt) }}</td>
            <td>
              <div class="contracts-panel__actions">
                <a class="iz-btn iz-btn--sm" :href="downloadUrl(contract)" download>Download</a>
                <button class="iz-btn iz-btn--sm" type="button" @click="openRename(contract)">Rename</button>
                <button class="iz-btn iz-btn--sm" type="button" @click="openReplace(contract)">Replace</button>
                <button class="iz-btn iz-btn--sm iz-btn--primary" type="button" @click="openSignatureRequest(contract)">Request signatures</button>
                <button class="iz-btn iz-btn--sm iz-btn--danger" type="button" @click="confirmDelete(contract)">Delete</button>
              </div>
            </td>
          </tr>
          <tr v-for="request in signingRequests[contract.id] || []" :key="'sign-' + request.id" class="contracts-panel__request-row">
            <td colspan="6">
              <div class="contracts-panel__request">
                <span class="iz-badge" :class="requestTone(request.status)">{{ request.status }}</span>
                <span>Version checksum {{ request.sourceChecksum.slice(0, 10) }}…</span>
                <span>{{ signerSummary(request.signers) }}</span>
                <div class="contracts-panel__actions">
                  <button v-if="request.status === 'draft'" class="iz-btn iz-btn--sm" type="button" @click="resumePlacement(contract, request)">Place fields</button>
                  <button v-if="request.status === 'draft'" class="iz-btn iz-btn--sm iz-btn--primary" type="button" :disabled="sendingRequestId === request.id" @click="sendRequest(contract, request)">{{ sendingRequestId === request.id ? "Sending…" : "Send invitations" }}</button>
                  <a v-if="request.hasSignedCopy" class="iz-btn iz-btn--sm iz-btn--primary" :href="signedDownloadUrl(contract, request)" download>Download signed PDF</a>
                </div>
              </div>
            </td>
          </tr>
          </template>
        </tbody>
      </table>
    </div>

    <ContractModal
      v-if="modalMode"
      :mode="modalMode"
      :display-name="formName"
      :busy="saving"
      :error="saveError"
      @update:displayName="formName = $event"
      @file-change="formFile = $event"
      @submit="save"
      @close="closeModal"
    />

    <ConfirmDialog
      v-if="deleteTarget"
      title="Delete contract?"
      :message="'Permanently delete “' + deleteTarget.displayName + '” and its stored PDF?'"
      confirm-label="Delete contract"
      busy-label="Deleting…"
      :danger="true"
      :busy="deleting"
      :error="deleteError"
      @confirm="deleteContract"
      @cancel="closeDelete"
    />

    <SignatureRequestModal
      v-if="signatureContract"
      :signers="signatureSigners"
      :busy="creatingSignature"
      :error="signatureError"
      @submit="createSignatureRequest"
      @close="signatureContract = null"
    />
  </section>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateOcsUrl, generateUrl } from "@nextcloud/router";
import ConfirmDialog from "./ConfirmDialog.vue";
import ContractModal from "./ContractModal.vue";
import SignatureRequestModal from "./SignatureRequestModal.vue";

const OCS_HEADERS = { "OCS-APIRequest": "true", Accept: "application/json" };

function unwrapOcs(response) {
  return response && response.data && response.data.ocs ? response.data.ocs.data : response.data;
}

export default {
  name: "ContractsPanel",
  components: { ConfirmDialog, ContractModal, SignatureRequestModal },
  props: {
    orgId: { type: Number, required: true },
  },
  data() {
    return {
      contracts: [],
      loading: true,
      loadError: "",
      modalMode: "",
      selectedContract: null,
      formName: "",
      formFile: null,
      saving: false,
      saveError: "",
      deleteTarget: null,
      deleting: false,
      deleteError: "",
      signingRequests: {},
      signatureContract: null,
      signatureSigners: [],
      creatingSignature: false,
      signatureError: "",
      sendingRequestId: null,
    };
  },
  mounted() {
    this.loadContracts();
  },
  methods: {
    endpoint(suffix) {
      return generateOcsUrl("/apps/organization/organizations/" + this.orgId + "/contracts" + (suffix || ""));
    },
    async loadContracts() {
      this.loading = true;
      this.loadError = "";
      try {
        const response = await axios.get(this.endpoint(), { params: { format: "json" }, headers: OCS_HEADERS });
        this.contracts = unwrapOcs(response).contracts || [];
        await Promise.all(this.contracts.map((contract) => this.loadSignatureRequests(contract.id)));
      } catch (error) {
        this.loadError = this.apiError(error, "Could not load contracts.");
      } finally {
        this.loading = false;
      }
    },
    async loadSignatureRequests(contractId) {
      try {
        const response = await axios.get(this.endpoint("/" + contractId + "/signature-requests"), { params: { format: "json" }, headers: OCS_HEADERS });
        this.$set(this.signingRequests, contractId, unwrapOcs(response).requests || []);
      } catch (error) {
        this.$set(this.signingRequests, contractId, []);
      }
    },
    openSignatureRequest(contract) {
      this.signatureContract = contract;
      this.signatureSigners = [{ displayName: "", email: "" }];
      this.signatureError = "";
    },
    async createSignatureRequest() {
      this.creatingSignature = true;
      this.signatureError = "";
      try {
        const response = await axios.post(this.endpoint("/" + this.signatureContract.id + "/signature-requests"), { signers: this.signatureSigners }, { params: { format: "json" }, headers: OCS_HEADERS });
        const data = unwrapOcs(response);
        window.location.assign(data.placementUrl);
      } catch (error) {
        this.signatureError = this.apiError(error, "Could not create the signing draft.");
        this.creatingSignature = false;
      }
    },
    async resumePlacement(contract, request) {
      try {
        const response = await axios.get(this.endpoint("/" + contract.id + "/signature-requests/" + request.id + "/placement"), { params: { format: "json" }, headers: OCS_HEADERS });
        window.location.assign(unwrapOcs(response).placementUrl);
      } catch (error) {
        this.loadError = this.apiError(error, "Could not open signature placement.");
      }
    },
    async sendRequest(contract, request) {
      this.sendingRequestId = request.id;
      try {
        await axios.post(this.endpoint("/" + contract.id + "/signature-requests/" + request.id + "/send"), {}, { params: { format: "json" }, headers: OCS_HEADERS });
        await this.loadSignatureRequests(contract.id);
      } catch (error) {
        this.loadError = this.apiError(error, "Could not send signature invitations.");
      } finally {
        this.sendingRequestId = null;
      }
    },
    requestTone(status) {
      return { draft: "iz-badge--muted", pending: "iz-badge--warning", completed: "iz-badge--success", cancelled: "iz-badge--danger" }[status] || "iz-badge--muted";
    },
    signerSummary(signers) {
      return (signers || []).map((signer) => signer.displayName + " <" + signer.email + ">" + (signer.status ? " · " + signer.status : "")).join(", ");
    },
    openUpload() {
      this.openModal("upload", null, "");
    },
    openRename(contract) {
      this.openModal("rename", contract, contract.displayName);
    },
    openReplace(contract) {
      this.openModal("replace", contract, "");
    },
    openModal(mode, contract, name) {
      this.modalMode = mode;
      this.selectedContract = contract;
      this.formName = name;
      this.formFile = null;
      this.saveError = "";
    },
    closeModal() {
      if (this.saving) return;
      this.modalMode = "";
      this.selectedContract = null;
    },
    async save() {
      if (this.saving) return;
      if (this.modalMode !== "rename" && !this.formFile) {
        this.saveError = "Choose a PDF file.";
        return;
      }
      if (this.modalMode !== "replace" && !this.formName.trim()) {
        this.saveError = "Enter a contract name.";
        return;
      }
      this.saving = true;
      this.saveError = "";
      try {
        if (this.modalMode === "rename") {
          await axios.put(this.endpoint("/" + this.selectedContract.id), { displayName: this.formName.trim() }, { params: { format: "json" }, headers: OCS_HEADERS });
        } else {
          const form = new FormData();
          form.append("file", this.formFile);
          if (this.modalMode === "upload") form.append("displayName", this.formName.trim());
          const suffix = this.modalMode === "replace" ? "/" + this.selectedContract.id + "/file" : "";
          await axios.post(this.endpoint(suffix), form, { params: { format: "json" }, headers: OCS_HEADERS });
        }
        this.closeModalAfterSave();
        await this.loadContracts();
      } catch (error) {
        this.saveError = this.apiError(error, "Could not save the contract.");
      } finally {
        this.saving = false;
      }
    },
    closeModalAfterSave() {
      this.modalMode = "";
      this.selectedContract = null;
    },
    confirmDelete(contract) {
      this.deleteTarget = contract;
      this.deleteError = "";
    },
    closeDelete() {
      if (!this.deleting) this.deleteTarget = null;
    },
    async deleteContract() {
      this.deleting = true;
      this.deleteError = "";
      try {
        await axios.delete(this.endpoint("/" + this.deleteTarget.id), { params: { format: "json" }, headers: OCS_HEADERS });
        this.deleteTarget = null;
        await this.loadContracts();
      } catch (error) {
        this.deleteError = this.apiError(error, "Could not delete the contract.");
      } finally {
        this.deleting = false;
      }
    },
    downloadUrl(contract) {
      return generateUrl("/apps/organization/organizations/" + this.orgId + "/contracts/" + contract.id + "/download");
    },
    signedDownloadUrl(contract, request) {
      return generateUrl("/apps/organization/organizations/" + this.orgId + "/contracts/" + contract.id + "/signature-requests/" + request.id + "/download");
    },
    formatBytes(value) {
      const bytes = Number(value) || 0;
      if (bytes < 1024) return bytes + " B";
      if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " KB";
      return (bytes / 1048576).toFixed(1) + " MB";
    },
    formatDate(value) {
      if (!value) return "—";
      const date = new Date(value.replace(" ", "T") + "Z");
      return isNaN(date.getTime()) ? value : date.toLocaleString("en-GB", { dateStyle: "medium", timeStyle: "short" });
    },
    apiError(error, fallback) {
      const body = error && error.response && error.response.data;
      const meta = body && body.ocs && body.ocs.meta;
      return (meta && meta.message) || (body && (body.message || body.error)) || fallback;
    },
  },
};
</script>

<style scoped>
.contracts-panel {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
}

.contracts-panel__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--spacing-md);
}

.contracts-panel__title,
.contracts-panel__subtitle,
.contracts-panel__empty-title,
.contracts-panel__message {
  margin: 0;
}

.contracts-panel__title {
  font-size: var(--iz-fs-lg);
}

.contracts-panel__subtitle,
.contracts-panel__filename {
  color: var(--color-text-muted);
}

.contracts-panel__empty {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
  text-align: center;
}

.contracts-panel__message {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-md);
}

.contracts-panel__message--error {
  color: var(--color-badge-danger-text);
}

.contracts-panel__table {
  width: 100%;
}

.contracts-panel__name {
  font-weight: 600;
}

.contracts-panel__filename {
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.contracts-panel__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--spacing-xs);
  flex-wrap: wrap;
}

.contracts-panel__request-row td {
  padding-top: 0;
}

.contracts-panel__request {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  padding: var(--spacing-sm) var(--spacing-md);
  color: var(--color-text-secondary);
  flex-wrap: wrap;
}

.contracts-panel__request .contracts-panel__actions {
  margin-left: auto;
}

.contracts-panel__sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

@media (max-width: 768px) {
  .contracts-panel__header {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
