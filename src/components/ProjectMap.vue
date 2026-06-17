<template>
  <div class="project-map">
    <div ref="mapRoot" class="project-map__container"></div>
    <a
      :href="osmLink"
      class="project-map__link"
      target="_blank"
      rel="noopener noreferrer"
    >Open in OpenStreetMap →</a>
  </div>
</template>

<script>
// Static imports — Leaflet is bundled into the main chunk that Nextcloud
// already serves correctly. We tried dynamic imports first but the production
// host couldn't serve the lazy chunk files (deploy pipeline didn't sync
// them), and even renaming the chunks didn't help. Static imports trade
// ~40kb gzipped on the main bundle for total deploy reliability.
import L from "leaflet";
import "leaflet/dist/leaflet.css";

// Inline SVG marker pin (Feather-style "map-pin"). Using L.divIcon avoids
// shipping marker-icon.png / marker-icon-2x.png / marker-shadow.png as
// separate webpack-emitted asset files, which would have the same
// "file-not-on-server" problem the lazy chunks had.
const MARKER_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="#b91c1c" stroke="#7f1d1d" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3" fill="#fff" stroke="#7f1d1d"/></svg>';

export default {
  name: "ProjectMap",
  props: {
    lat: { type: Number, required: true },
    lng: { type: Number, required: true },
    displayName: { type: String, default: null },
  },
  data() {
    return {
      _map: null,
    };
  },
  computed: {
    osmLink() {
      return (
        "https://www.openstreetmap.org/?mlat=" +
        this.lat +
        "&mlon=" +
        this.lng +
        "#map=16/" +
        this.lat +
        "/" +
        this.lng
      );
    },
  },
  mounted() {
    this._map = L.map(this.$refs.mapRoot, { scrollWheelZoom: true }).setView(
      [this.lat, this.lng],
      16
    );
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors',
    }).addTo(this._map);

    const icon = L.divIcon({
      className: "project-map__marker",
      html: MARKER_SVG,
      iconSize: [28, 28],
      iconAnchor: [14, 28],
      popupAnchor: [0, -24],
    });
    const marker = L.marker([this.lat, this.lng], { icon }).addTo(this._map);
    if (this.displayName) {
      marker.bindPopup(this.displayName);
    }
  },
  beforeDestroy() {
    if (this._map) {
      this._map.remove();
      this._map = null;
    }
  },
};
</script>

<style scoped>
.project-map {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.project-map__container {
  height: 280px;
  width: 100%;
  border-radius: 8px;
  overflow: hidden;
  background: #f0f1f5;
}
.project-map__link {
  font-size: 12px;
  color: #4a90d9;
  text-decoration: none;
  align-self: flex-end;
}
.project-map__link:hover {
  text-decoration: underline;
}
</style>

<style>
/* Unscoped: Leaflet generates the marker element inside a non-Vue subtree, so
   scoped class names don't reach it. We only need to drop the default
   background that Leaflet's default marker stylesheet sets. */
.project-map__marker {
  background: transparent !important;
  border: none !important;
}
</style>
