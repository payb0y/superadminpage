import Vue from "vue";
import Dashboard from "./components/Dashboard.vue";

Vue.mixin({
  methods: {
    t: (app, text) => text,
  },
});

const mountEl = document.getElementById("superadminpage-root");

if (mountEl) {
  new Vue({
    el: mountEl,
    render: (h) => h(Dashboard),
  });
}
