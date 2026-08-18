<template>
  <dialog ref="dialog" class="placement iz-app" aria-labelledby="placement-title" @cancel.prevent="requestClose">
    <header class="placement__header iz-panel">
      <div class="placement__heading">
        <button class="iz-btn iz-btn--icon" type="button" aria-label="Close signature placement" @click="requestClose">×</button>
        <div>
          <h2 id="placement-title" class="placement__title">Place signature fields</h2>
          <p class="placement__subtitle">{{ contract.displayName }}</p>
        </div>
      </div>
      <div class="placement__toolbar">
        <button class="iz-btn iz-btn--sm" type="button" :disabled="loading || rendering || scale <= 0.5" @click="setScale(scale - 0.1)">−</button>
        <span class="placement__zoom">{{ Math.round(scale * 100) }}%</span>
        <button class="iz-btn iz-btn--sm" type="button" :disabled="loading || rendering || scale >= 2" @click="setScale(scale + 0.1)">+</button>
        <button class="iz-btn" type="button" :disabled="saving" @click="requestClose">Cancel</button>
        <button class="iz-btn iz-btn--primary" type="button" :disabled="loading || saving || !dirty" @click="save">
          {{ saving ? "Saving…" : "Save placement" }}
        </button>
      </div>
    </header>

    <div class="placement__body">
      <aside class="placement__sidebar iz-panel">
        <div>
          <h3 class="placement__sidebar-title">Signers</h3>
          <p class="placement__help">Choose a signer, then click anywhere on the document.</p>
        </div>
        <button
          v-for="(signer, index) in signers"
          :key="signer.signRequestId"
          class="placement__signer iz-btn"
          :class="{ 'placement__signer--active': selectedSignerId === signer.signRequestId }"
          type="button"
          @click="selectSigner(signer.signRequestId)"
        >
          <span class="placement__swatch" :class="signerTone(index)" aria-hidden="true" />
          <span class="placement__signer-copy">
            <strong>{{ signer.displayName }}</strong>
            <span>{{ signerEmail(signer) }}</span>
          </span>
          <span class="iz-badge iz-badge--muted">{{ signerFieldCount(signer.signRequestId) }}</span>
        </button>
        <p v-if="selectedSigner" class="placement__tip">
          Click the PDF to place a field for {{ selectedSigner.displayName }}.
        </p>
        <p v-if="validationMessage" class="placement__tip placement__tip--warning" role="status">{{ validationMessage }}</p>
        <p v-if="saveError" class="placement__tip placement__tip--error" role="alert">{{ saveError }}</p>
      </aside>

      <main ref="workspace" class="placement__workspace" @click="clearSelection">
        <div v-if="loading" class="placement__state iz-empty">Loading document…</div>
        <div v-else-if="error" class="placement__state placement__tip--error" role="alert">
          <p>{{ error }}</p>
          <button class="iz-btn iz-btn--sm" type="button" @click="load">Retry</button>
        </div>
        <div v-else class="placement__pages">
          <article
            v-for="page in pages"
            :key="page.number"
            class="placement__page-wrap"
          >
            <div
              class="placement__page"
              :data-page="page.number"
              :style="pageStyle(page)"
              @click.stop="placeField($event, page)"
            >
              <canvas :ref="'pageCanvas' + page.number" class="placement__canvas" />
              <div
                v-for="field in fieldsForPage(page.number)"
                :key="field.localId"
                class="placement__field"
                :class="[signerTone(signerIndex(field.signRequestId)), { 'placement__field--selected': selectedFieldId === field.localId }]"
                :style="fieldStyle(field)"
                role="button"
                tabindex="0"
                :aria-label="'Signature field for ' + signerName(field.signRequestId)"
                @click.stop="selectedFieldId = field.localId"
                @keydown.delete.prevent="removeField(field.localId)"
                @keydown.backspace.prevent="removeField(field.localId)"
                @pointerdown.stop.prevent="startDrag($event, field)"
              >
                <span class="placement__field-icon" aria-hidden="true">✎</span>
                <span class="placement__field-name">{{ signerName(field.signRequestId) }}</span>
                <button
                  v-if="selectedFieldId === field.localId"
                  class="placement__delete iz-btn iz-btn--icon"
                  type="button"
                  aria-label="Delete signature field"
                  @pointerdown.stop
                  @click.stop="removeField(field.localId)"
                >×</button>
                <button
                  v-if="selectedFieldId === field.localId"
                  class="placement__resize"
                  type="button"
                  aria-label="Resize signature field"
                  @pointerdown.stop.prevent="startResize($event, field)"
                />
              </div>
            </div>
            <p class="placement__page-number">Page {{ page.number }} of {{ pages.length }}</p>
          </article>
        </div>
      </main>
    </div>
    <ConfirmDialog
      v-if="confirmClose"
      title="Discard placement changes?"
      message="Your unsaved signature fields will be lost."
      confirm-label="Discard changes"
      :danger="true"
      :busy="false"
      error=""
      @confirm="$emit('close')"
      @cancel="confirmClose = false"
    />
  </dialog>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateFilePath } from "@nextcloud/router";
import * as pdfjs from "pdfjs-dist";
import ConfirmDialog from "./ConfirmDialog.vue";
import { clamp, resizeField, serializeField } from "../lib/placementGeometry.mjs";

const emittedWorkerUrl = new URL("pdfjs-dist/build/pdf.worker.min.mjs", import.meta.url);
const workerFilename = emittedWorkerUrl.pathname.split("/").pop();
pdfjs.GlobalWorkerOptions.workerSrc = generateFilePath("superadminpage", "js", workerFilename);

const DEFAULT_WIDTH = 150;
const DEFAULT_HEIGHT = 50;
const MIN_WIDTH = 48;

export default {
  name: "ContractPlacementModal",
  components: { ConfirmDialog },
  props: {
    contract: { type: Object, required: true },
    request: { type: Object, required: true },
    dataUrl: { type: String, required: true },
    saveUrl: { type: String, required: true },
    headers: { type: Object, required: true },
  },
  data() {
    return {
      loading: true,
      saving: false,
      error: "",
      saveError: "",
      validationMessage: "",
      document: null,
      pdf: null,
      pages: [],
      signers: [],
      fields: [],
      selectedSignerId: null,
      selectedFieldId: null,
      scale: 1,
      dirty: false,
      nextLocalId: 1,
      interaction: null,
      renderGeneration: 0,
      rendering: false,
      renderTasks: {},
      previousBodyOverflow: "",
      confirmClose: false,
    };
  },
  computed: {
    selectedSigner() {
      return this.signers.find((signer) => signer.signRequestId === this.selectedSignerId) || null;
    },
  },
  mounted() {
    this.previousBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    window.addEventListener("keydown", this.onWindowKeydown);
    if (typeof this.$refs.dialog.showModal === "function") {
      this.$refs.dialog.showModal();
    } else {
      this.$refs.dialog.setAttribute("open", "");
    }
    this.load();
  },
  beforeDestroy() {
    document.body.style.overflow = this.previousBodyOverflow;
    window.removeEventListener("keydown", this.onWindowKeydown);
    this.stopInteraction();
    for (const task of Object.values(this.renderTasks)) {
      try {
        task.cancel();
      } catch (_) {
        // The task may already have completed.
      }
    }
    if (this.$refs.dialog && this.$refs.dialog.open) this.$refs.dialog.close();
    if (this.pdf) this.pdf.destroy();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = "";
      this.saveError = "";

      let data;
      try {
        const response = await axios.get(this.dataUrl, { params: { format: "json" }, headers: this.headers });
        data = response.data && response.data.ocs ? response.data.ocs.data : response.data;
        if (!data || !data.document || !data.document.contentUrl) throw new Error("The placement response did not include a PDF URL.");
      } catch (error) {
        this.failLoad("Could not load placement details.", error);
        return;
      }

      this.document = data.document;
      this.signers = (data.signers || []).map((signer) => ({ ...signer, signRequestId: Number(signer.signRequestId) }));
      this.fields = (data.elements || []).map((element) => this.normalizeField(element));
      this.nextLocalId = this.fields.length + 1;
      this.selectedSignerId = this.signers.length ? this.signers[0].signRequestId : null;

      let pdfBytes;
      try {
        const pdfResponse = await axios.get(this.document.contentUrl, { responseType: "arraybuffer" });
        const contentType = String(pdfResponse.headers && pdfResponse.headers["content-type"] || "").toLowerCase();
        pdfBytes = new Uint8Array(pdfResponse.data);
        const signature = new TextDecoder().decode(pdfBytes.slice(0, 5));
        if (!contentType.includes("application/pdf") || signature !== "%PDF-") {
          throw new Error("The document endpoint did not return a PDF file.");
        }
      } catch (error) {
        this.failLoad("Could not download the contract PDF.", error);
        return;
      }

      try {
        if (this.pdf) await this.pdf.destroy();
        this.pdf = await pdfjs.getDocument({ data: pdfBytes }).promise;
        this.pages = [];
        for (let number = 1; number <= this.pdf.numPages; number += 1) {
          const pdfPage = await this.pdf.getPage(number);
          const viewport = pdfPage.getViewport({ scale: 1 });
          const metadata = this.document.metadata && this.document.metadata.d && this.document.metadata.d[number - 1];
          this.pages.push({
            number,
            width: Number(metadata && metadata.w) || viewport.width,
            height: Number(metadata && metadata.h) || viewport.height,
            pdfPage,
          });
        }
        this.loading = false;
        this.dirty = false;
        await this.$nextTick();
        await this.renderPages();
      } catch (error) {
        this.failLoad("The contract PDF could not be displayed.", error);
      }
    },
    failLoad(message, error) {
      this.loading = false;
      this.error = message + " " + this.apiError(error, error && error.message ? error.message : "Please try again.");
    },
    normalizeField(element) {
      const coordinates = element.coordinates || {};
      return {
        localId: "field-" + this.nextLocalId++,
        elementId: element.elementId == null ? null : Number(element.elementId),
        fileId: Number(element.fileId),
        signRequestId: Number(element.signRequestId),
        type: "signature",
        page: Number(coordinates.page) || 1,
        x: Number(coordinates.left) || 0,
        y: Number(coordinates.top) || 0,
        width: Number(coordinates.width) || DEFAULT_WIDTH,
        height: Number(coordinates.height) || DEFAULT_HEIGHT,
      };
    },
    async renderPages() {
      const generation = ++this.renderGeneration;
      this.rendering = true;
      try {
        for (const task of Object.values(this.renderTasks)) task.cancel();
        this.renderTasks = {};
        for (const page of this.pages) {
          if (generation !== this.renderGeneration) return;
          const canvasRef = this.$refs["pageCanvas" + page.number];
          const canvas = Array.isArray(canvasRef) ? canvasRef[0] : canvasRef;
          if (!canvas) continue;
          const viewport = page.pdfPage.getViewport({ scale: this.scale });
          const pixelRatio = window.devicePixelRatio || 1;
          canvas.width = Math.floor(viewport.width * pixelRatio);
          canvas.height = Math.floor(viewport.height * pixelRatio);
          canvas.style.width = page.width * this.scale + "px";
          canvas.style.height = page.height * this.scale + "px";
          const context = canvas.getContext("2d");
          const transform = pixelRatio === 1 ? null : [pixelRatio, 0, 0, pixelRatio, 0, 0];
          const task = page.pdfPage.render({ canvasContext: context, viewport, transform });
          this.$set(this.renderTasks, page.number, task);
          try {
            await task.promise;
          } catch (error) {
            if (error && error.name !== "RenderingCancelledException") throw error;
          } finally {
            this.$delete(this.renderTasks, page.number);
          }
        }
      } finally {
        if (generation === this.renderGeneration) this.rendering = false;
      }
    },
    async setScale(value) {
      this.scale = Math.max(0.5, Math.min(2, Math.round(value * 10) / 10));
      await this.$nextTick();
      await this.renderPages();
    },
    pageStyle(page) {
      return { width: page.width * this.scale + "px", height: page.height * this.scale + "px" };
    },
    fieldStyle(field) {
      return {
        left: field.x * this.scale + "px",
        top: field.y * this.scale + "px",
        width: field.width * this.scale + "px",
        height: field.height * this.scale + "px",
      };
    },
    placeField(event, page) {
      if (!this.selectedSignerId || event.target.closest(".placement__field")) return;
      const rect = event.currentTarget.getBoundingClientRect();
      const width = Math.min(DEFAULT_WIDTH, page.width);
      const height = Math.min(DEFAULT_HEIGHT, page.height);
      const x = this.clamp((event.clientX - rect.left) / this.scale - width / 2, 0, page.width - width);
      const y = this.clamp((event.clientY - rect.top) / this.scale - height / 2, 0, page.height - height);
      const field = {
        localId: "field-" + this.nextLocalId++,
        elementId: null,
        fileId: Number(this.document.id),
        signRequestId: this.selectedSignerId,
        type: "signature",
        page: page.number,
        x,
        y,
        width,
        height,
      };
      this.fields.push(field);
      this.selectedFieldId = field.localId;
      this.dirty = true;
      this.validationMessage = "";
    },
    startDrag(event, field) {
      if (event.target.closest("button")) return;
      this.selectedFieldId = field.localId;
      const pageElement = event.currentTarget.closest(".placement__page");
      const fieldRect = event.currentTarget.getBoundingClientRect();
      this.interaction = {
        mode: "drag",
        field,
        pageElement,
        offsetX: event.clientX - fieldRect.left,
        offsetY: event.clientY - fieldRect.top,
      };
      this.bindInteraction();
    },
    startResize(event, field) {
      this.selectedFieldId = field.localId;
      this.interaction = {
        mode: "resize",
        field,
        startX: event.clientX,
        startWidth: field.width,
        startHeight: field.height,
        aspect: field.width / field.height,
      };
      this.bindInteraction();
    },
    bindInteraction() {
      window.addEventListener("pointermove", this.moveInteraction, { passive: false });
      window.addEventListener("pointerup", this.stopInteraction);
      window.addEventListener("pointercancel", this.stopInteraction);
    },
    moveInteraction(event) {
      if (!this.interaction) return;
      event.preventDefault();
      const { field } = this.interaction;
      if (this.interaction.mode === "resize") {
        const page = this.pages[field.page - 1];
        const size = resizeField(field, Math.max(MIN_WIDTH, this.interaction.startWidth + (event.clientX - this.interaction.startX) / this.scale), this.interaction.aspect, page);
        field.width = size.width;
        field.height = size.height;
      } else {
        const targetPageElement = document.elementsFromPoint(event.clientX, event.clientY).find((node) => node.classList && node.classList.contains("placement__page"));
        const pageElement = targetPageElement || this.interaction.pageElement;
        const pageNumber = Number(pageElement.dataset.page);
        const page = this.pages[pageNumber - 1];
        const rect = pageElement.getBoundingClientRect();
        field.page = pageNumber;
        field.x = this.clamp((event.clientX - rect.left - this.interaction.offsetX) / this.scale, 0, page.width - field.width);
        field.y = this.clamp((event.clientY - rect.top - this.interaction.offsetY) / this.scale, 0, page.height - field.height);
        this.interaction.pageElement = pageElement;
      }
      this.dirty = true;
    },
    stopInteraction() {
      window.removeEventListener("pointermove", this.moveInteraction);
      window.removeEventListener("pointerup", this.stopInteraction);
      window.removeEventListener("pointercancel", this.stopInteraction);
      this.interaction = null;
    },
    removeField(localId) {
      this.fields = this.fields.filter((field) => field.localId !== localId);
      if (this.selectedFieldId === localId) this.selectedFieldId = null;
      this.dirty = true;
    },
    fieldsForPage(page) {
      return this.fields.filter((field) => field.page === page);
    },
    selectSigner(id) {
      this.selectedSignerId = this.selectedSignerId === id ? null : id;
    },
    clearSelection() {
      this.selectedFieldId = null;
    },
    signerIndex(id) {
      return Math.max(0, this.signers.findIndex((signer) => signer.signRequestId === id));
    },
    signerName(id) {
      const signer = this.signers.find((item) => item.signRequestId === id);
      return signer ? signer.displayName : "Signer";
    },
    signerEmail(signer) {
      const method = (signer.identifyMethods || []).find((item) => item.method === "email");
      return method ? method.value : "External signer";
    },
    signerFieldCount(id) {
      const count = this.fields.filter((field) => field.signRequestId === id).length;
      return count + (count === 1 ? " field" : " fields");
    },
    signerTone(index) {
      return ["placement-tone--one", "placement-tone--two", "placement-tone--three", "placement-tone--four", "placement-tone--five"][index % 5];
    },
    validate() {
      const missing = this.signers.filter((signer) => !this.fields.some((field) => field.signRequestId === signer.signRequestId));
      this.validationMessage = missing.length ? "Place at least one field for " + missing.map((signer) => signer.displayName).join(", ") + "." : "";
      return missing.length === 0;
    },
    async save() {
      if (!this.validate()) return;
      this.saving = true;
      this.saveError = "";
      try {
        const elements = this.fields.map(serializeField);
        const response = await axios.put(this.saveUrl, { elements }, { params: { format: "json" }, headers: this.headers });
        const data = response.data && response.data.ocs ? response.data.ocs.data : response.data;
        this.fields = (data.elements || []).map((element) => this.normalizeField(element));
        this.dirty = false;
        this.$emit("saved");
        this.$emit("close");
      } catch (error) {
        this.saveError = this.apiError(error, "Could not save signature fields.");
      } finally {
        this.saving = false;
      }
    },
    requestClose() {
      if (this.saving) return;
      if (this.dirty) {
        this.confirmClose = true;
        return;
      }
      this.$emit("close");
    },
    onWindowKeydown(event) {
      if (event.key === "Escape") this.requestClose();
    },
    clamp(value, minimum, maximum) {
      return clamp(value, minimum, maximum);
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
.placement {
  position: fixed;
  inset: 0;
  z-index: 10000;
  width: 100vw;
  max-width: none;
  height: 100vh;
  max-height: none;
  margin: 0;
  padding: 0;
  border: 0;
  display: flex;
  flex-direction: column;
  background: var(--image-background);
  color: var(--color-text-primary);
}

.placement::backdrop {
  background: var(--image-background);
}

.placement__header {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-md);
  border-radius: 0;
}

.placement__heading,
.placement__toolbar,
.placement__signer,
.placement__field {
  display: flex;
  align-items: center;
}

.placement__heading,
.placement__toolbar {
  gap: var(--spacing-sm);
}

.placement__title,
.placement__subtitle,
.placement__sidebar-title,
.placement__help,
.placement__page-number,
.placement__tip {
  margin: 0;
}

.placement__title {
  font-size: var(--iz-fs-xl);
}

.placement__subtitle,
.placement__help,
.placement__page-number,
.placement__signer-copy span {
  color: var(--color-text-muted);
}

.placement__zoom {
  min-width: 4ch;
  text-align: center;
  font-family: var(--iz-font-mono);
}

.placement__tip--warning {
  color: var(--color-warning);
}

.placement__tip--error {
  color: var(--color-danger);
}

.placement__body {
  flex: 1 1 auto;
  min-height: 0;
  display: grid;
  grid-template-columns: minmax(240px, 300px) minmax(0, 1fr);
}

.placement__sidebar {
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
  border-radius: 0;
}

.placement__signer {
  width: 100%;
  justify-content: flex-start;
  gap: var(--spacing-sm);
  text-align: left;
}

.placement__signer--active {
  box-shadow: inset 0 0 0 2px var(--accent);
}

.placement__signer-copy {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.placement__signer-copy span {
  overflow: hidden;
  text-overflow: ellipsis;
}

.placement__swatch {
  width: var(--spacing-sm);
  height: var(--spacing-2xl);
  flex: 0 0 auto;
  border-radius: var(--radius-pill);
  background: var(--placement-color);
}

.placement__workspace {
  min-width: 0;
  overflow: auto;
  padding: var(--spacing-xl);
  background: var(--image-background);
}

.placement__pages {
  width: max-content;
  min-width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-xl);
}

.placement__page-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-sm);
}

.placement__page {
  position: relative;
  flex: 0 0 auto;
  overflow: hidden;
  box-shadow: var(--shadow-card-hover);
  background: var(--bg-card);
  touch-action: none;
}

.placement__canvas {
  position: absolute;
  inset: 0;
  display: block;
}

.placement__field {
  position: absolute;
  z-index: 2;
  box-sizing: border-box;
  justify-content: center;
  gap: var(--spacing-xs);
  padding: var(--spacing-xs);
  border: 2px solid var(--placement-color);
  border-radius: var(--radius-el);
  background: var(--placement-background);
  color: var(--placement-text);
  cursor: move;
  user-select: none;
  touch-action: none;
}

.placement__field--selected {
  box-shadow: 0 0 0 2px var(--bg-card), 0 0 0 4px var(--placement-color);
}

.placement__field-icon {
  font-weight: 700;
}

.placement__field-name {
  max-width: 75%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.placement__delete {
  position: absolute;
  top: calc(-1 * var(--spacing-xl));
  right: 0;
}

.placement__resize {
  position: absolute;
  right: calc(-1 * var(--spacing-sm));
  bottom: calc(-1 * var(--spacing-sm));
  width: var(--spacing-md);
  height: var(--spacing-md);
  min-height: 0;
  padding: 0;
  border: 2px solid var(--bg-card);
  border-radius: 50%;
  background: var(--placement-color);
  cursor: nwse-resize;
}

.placement__state {
  max-width: 520px;
  margin: var(--spacing-2xl) auto;
}

.placement-tone--one {
  --placement-color: var(--iz-cat-1);
  --placement-background: var(--iz-cat-1-bg);
  --placement-text: var(--iz-cat-1-text);
}

.placement-tone--two {
  --placement-color: var(--iz-cat-2);
  --placement-background: var(--iz-cat-2-bg);
  --placement-text: var(--iz-cat-2-text);
}

.placement-tone--three {
  --placement-color: var(--iz-cat-3);
  --placement-background: var(--iz-cat-3-bg);
  --placement-text: var(--iz-cat-3-text);
}

.placement-tone--four {
  --placement-color: var(--iz-cat-4);
  --placement-background: var(--iz-cat-4-bg);
  --placement-text: var(--iz-cat-4-text);
}

.placement-tone--five {
  --placement-color: var(--iz-cat-5);
  --placement-background: var(--iz-cat-5-bg);
  --placement-text: var(--iz-cat-5-text);
}

@media (max-width: 768px) {
  .placement__header {
    align-items: flex-start;
    flex-direction: column;
  }

  .placement__toolbar {
    width: 100%;
    overflow-x: auto;
  }

  .placement__body {
    grid-template-columns: 1fr;
    grid-template-rows: auto minmax(0, 1fr);
  }

  .placement__sidebar {
    max-height: 32vh;
  }

  .placement__workspace {
    padding: var(--spacing-md);
  }
}
</style>
