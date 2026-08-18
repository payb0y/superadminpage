# Contract Placement Modal

## Goal

Provide signature-field placement inside Super Admin without redirecting to the
signing app. The signing app remains authoritative for drafts, signers, visible
elements, invitations, and signed PDFs.

The initial scope is PDF signature fields only. Placement coordinates and
interaction behavior must remain compatible with the existing signing editor.

## Data Contract

`GET .../signature-requests/{requestId}/placement-data` returns:

- `document`: signing-app file ID, name, size, MIME type, PDF metadata, and an
  authenticated `contentUrl`.
- `signers`: signing-app signer records including `signRequestId`.
- `elements`: persisted visible signature fields.

`PUT .../signature-requests/{requestId}/elements` accepts the complete desired
set of fields. Each field contains `elementId` when persisted, `fileId`,
`signRequestId`, `type: "signature"`, and one-based page coordinates using
top-left `left`, `top`, `width`, and `height` values in unscaled PDF units.

## Phases

- [x] Phase 1: Define the API and progress tracker.
- [x] Phase 2: Add secured placement data, PDF, and element persistence APIs.
- [x] Phase 3: Build the full-screen Vue 2 modal and PDF renderer.
- [x] Phase 4: Match click placement, drag, resize, delete, and zoom behavior.
- [x] Phase 5: Integrate draft creation, reopening, saving, and request refresh.
- [x] Phase 6: Add focused tests and complete available build verification.

## Acceptance Rules

- All placement endpoints require a current global administrator.
- Only the administrator who created a draft can edit it.
- Only draft requests can be edited.
- Files, signers, and persisted elements must belong to the request.
- Stored coordinates do not change when the viewer zoom changes.
- Every signer must have at least one field before invitations can be sent.
- The existing redirect remains available temporarily as a fallback.

## Progress Notes

### 2026-08-18

- Replaced the shared-package direction with a native Super Admin modal.
- Added the placement API boundary between Organization and the signing app.
- Kept the existing redirect endpoint for rollback during initial validation.
- Added native PDF.js rendering and Vue 2 field placement in Super Admin.
- Connected draft creation and reopening to the native modal.
- Added coordinate, bounds, resize, and serialization tests.
- Live Nextcloud verification remains pending because the documented containers
  are not available on this machine.
- Moved the editor into a native modal dialog so it renders in the browser top
  layer above Nextcloud chrome.
- Switched PDF delivery to a same-origin relative URL and added distinct
  metadata, download, and rendering failure messages.
- Configured a stable `superadminpage-pdf.worker.min.js` build asset so local
  build-and-push deployments do not lose a content-hashed PDF.js worker and
  production static-file rules can serve it as JavaScript.
- Resolve the worker with Nextcloud's app webroot so installations mounted at
  `/custom_apps` do not incorrectly request it from `/apps`.
