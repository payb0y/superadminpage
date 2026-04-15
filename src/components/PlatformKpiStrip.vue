<template>
  <div class="platform-kpi-strip">
    <KpiCard
      title="Organizations"
      icon-color="#4a90d9"
      :metrics="[
        { value: kpis.orgs.total, label: 'total' },
        { value: kpis.orgs.active, label: 'active' },
        { value: kpis.orgs.paused, label: 'paused' },
      ]"
    />
    <KpiCard
      title="Financial"
      icon-color="#2e9e5a"
      :metrics="[
        { value: mrrDisplay, label: 'MRR' },
        { value: kpis.orgs.active, label: 'paying' },
      ]"
    />
    <KpiCard
      title="Resources"
      icon-color="#8b5cf6"
      :metrics="[
        { value: kpis.members.total, label: 'members' },
        { value: kpis.projects.active, label: 'projects' },
      ]"
    />
    <KpiCard
      title="Tasks"
      icon-color="#f59e0b"
      :metrics="[
        { value: kpis.projects.total, label: 'projects all-time' },
        { value: kpis.orgs.cancelled, label: 'churned' },
      ]"
    />
  </div>
</template>

<script>
import KpiCard from "./KpiCard.vue";

export default {
  name: "PlatformKpiStrip",
  components: { KpiCard },
  props: {
    kpis: {
      type: Object,
      required: true,
    },
  },
  computed: {
    mrrDisplay() {
      const v = Math.round(this.kpis.mrr.value || 0);
      const sym =
        this.kpis.mrr.currency === "EUR"
          ? "€"
          : this.kpis.mrr.currency === "USD"
            ? "$"
            : this.kpis.mrr.currency + " ";
      const prefix = this.kpis.mrr.multiCurrency ? "~" : "";
      return prefix + sym + v.toLocaleString();
    },
  },
};
</script>

<style scoped>
.platform-kpi-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--spacing-md, 16px);
}

@media (max-width: 1200px) {
  .platform-kpi-strip {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .platform-kpi-strip {
    grid-template-columns: 1fr;
  }
}
</style>
