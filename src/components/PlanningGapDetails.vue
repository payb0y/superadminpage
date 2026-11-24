<template>
  <section class="gap-details iz-card" :aria-label="t('Planning gap details')">
    <header class="gap-details__header">
      <div>
        <strong>{{ gap.type === 'internal' ? t('Inside project') : t('Between projects') }}</strong>
        <p>{{ gap.startDate }} – {{ gap.endDate }} · {{ gap.duration }}</p>
      </div>
      <button type="button" class="iz-btn iz-btn--quiet iz-btn--sm" :aria-label="t('Close gap details')" @click="$emit('close')">{{ t('Close') }}</button>
    </header>
    <p v-if="gap.teamName">{{ t('Team: {team}', { team: gap.teamName }) }}</p>
    <div class="gap-details__anchors">
      <div v-for="(anchor, index) in [gap.before, gap.after]" :key="index">
        <small>{{ index === 0 ? t('Before') : t('After') }}</small>
        <button v-if="gap.type === 'between'" type="button" class="iz-btn iz-btn--quiet iz-btn--sm" @click="openProject(anchor)">{{ t('{project} → Project timeline', { project: anchor.name }) }}</button>
        <span v-else>{{ anchor.title }}</span>
      </div>
    </div>
    <button v-if="gap.type === 'internal'" type="button" class="iz-btn iz-btn--quiet iz-btn--sm" @click="openProject({ id: gap.projectIds[0], name: gap.name })">{{ t('Open project timeline →') }}</button>
  </section>
</template>

<script>
export default {
  name: 'PlanningGapDetails',
  props: { gap: { type: Object, required: true } },
  methods: {
    openProject(project) {
      this.$emit('open-project', {
        ...project,
        planningGap: { hasGap: true, display: this.gap.duration },
      });
    },
  },
};
</script>

<style scoped>
.gap-details { padding: 16px; display: grid; gap: 10px; }
.gap-details__header { display: flex; justify-content: space-between; align-items: start; gap: 16px; }
.gap-details__header p { margin: 4px 0 0; }
.gap-details__anchors { display: flex; flex-wrap: wrap; gap: 20px; }
.gap-details__anchors > div { display: grid; gap: 4px; }
.gap-details__anchors small { color: var(--iz-text-secondary); }
</style>
