import Vue from "vue";
import Dashboard from "./components/Dashboard.vue";
import { t, n, tc } from "./lib/l10n.mjs";

// t(), n() and tc() in every template, the same functions .js/.mjs files
// import from ./lib/l10n.mjs. Nextcloud registers l10n/<lang>.js before this
// bundle, so a Dutch-speaking user sees Dutch and everyone else the msgid.
Vue.mixin({
  methods: { t, n, tc },
});

const mountEl = document.getElementById("superadminpage-root");

if (mountEl) {
  new Vue({
    el: mountEl,
    render: (h) => h(Dashboard),
  });
}
