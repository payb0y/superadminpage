/**
 * Leaflet's own control texts in the user's language.
 *
 * The zoom buttons ("Zoom in" / "Zoom out") and the tooltip on the "Leaflet"
 * credit link are hardcoded English in the library. They are defaults on the
 * control classes, so setting them before L.map() is called is enough; the
 * credit link itself and the flag next to it stay as Leaflet draws them.
 * Calling this again (another map on the page) only sets the same values.
 */
import { t } from "./l10n.mjs";

function escapeAttribute(value) {
  return String(value).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;");
}

export function localizeLeaflet(L) {
  const zoom = L.Control.Zoom.prototype.options;
  zoom.zoomInTitle = t("Zoom in");
  zoom.zoomOutTitle = t("Zoom out");
  const attribution = L.Control.Attribution.prototype.options;
  if (typeof attribution.prefix === "string") {
    attribution.prefix = attribution.prefix.replace(
      /title="[^"]*"/,
      'title="' + escapeAttribute(t("A JavaScript library for interactive maps")) + '"'
    );
  }
}
