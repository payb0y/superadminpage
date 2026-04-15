<template>
  <section class="projects-panel">
    <div class="projects-panel__filters">
      <input
        v-model="searchQuery"
        class="projects-panel__search"
        type="text"
        placeholder="Search projects…"
      />
    </div>

    <div v-if="filteredProjects.length === 0" class="projects-panel__empty">
      No projects match your filters.
    </div>

    <div
      v-for="project in filteredProjects"
      :key="'proj-' + project.id"
      class="projects-panel__row"
    >
      <div class="projects-panel__main">
        <span class="projects-panel__name">{{ project.name }}</span>
        <div class="projects-panel__meta">
          <span class="projects-panel__meta-item">
            {{ project.done }}/{{ project.total }} tasks
          </span>
          <span
            v-if="project.overdue > 0"
            class="projects-panel__meta-item projects-panel__meta-item--danger"
          >
            {{ project.overdue }} overdue
          </span>
        </div>
      </div>

      <div class="projects-panel__progress">
        <div class="projects-panel__bar">
          <div
            class="projects-panel__fill"
            :class="fillClass(project)"
            :style="{ width: project.progress + '%' }"
          ></div>
        </div>
        <span class="projects-panel__pct">{{ project.progress }}%</span>
      </div>
    </div>
  </section>
</template>

<script>
export default {
  name: "ProjectsPanel",
  props: {
    projects: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      searchQuery: "",
    };
  },
  computed: {
    filteredProjects() {
      const q = (this.searchQuery || "").toLowerCase();
      if (!q) return this.projects;
      return this.projects.filter(
        (p) => (p.name || "").toLowerCase().indexOf(q) !== -1,
      );
    },
  },
  methods: {
    fillClass(p) {
      if (p.progress >= 75) return "projects-panel__fill--high";
      if (p.progress >= 40) return "projects-panel__fill--mid";
      return "projects-panel__fill--low";
    },
  },
};
</script>

<style scoped>
.projects-panel {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.projects-panel__filters {
  margin-bottom: 4px;
}

.projects-panel__search {
  width: 100%;
  padding: 8px 14px;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 8px;
  font-size: 13px;
  color: var(--color-text-primary, #1a1a2e);
  background: #fff;
  outline: none;
  transition: border-color 0.15s;
}

.projects-panel__search:focus {
  border-color: #4a90d9;
}

.projects-panel__empty {
  padding: 24px;
  text-align: center;
  color: var(--color-text-muted, #9ca3af);
  font-size: 13px;
}

.projects-panel__row {
  background: #fff;
  border: 1px solid #f3f4f6;
  border-radius: 10px;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  gap: 20px;
  transition: border-color 0.15s;
}

.projects-panel__row:hover {
  border-color: #e0e3e9;
}

.projects-panel__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.projects-panel__name {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.projects-panel__meta {
  display: flex;
  gap: 10px;
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
}

.projects-panel__meta-item--danger {
  color: var(--color-danger, #d94040);
  font-weight: 600;
}

.projects-panel__progress {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 180px;
}

.projects-panel__bar {
  flex: 1;
  height: 6px;
  background: #e5e7eb;
  border-radius: 3px;
  overflow: hidden;
}

.projects-panel__fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.4s ease;
}

.projects-panel__fill--high {
  background: #2e9e5a;
}
.projects-panel__fill--mid {
  background: #f4a261;
}
.projects-panel__fill--low {
  background: #e63946;
}

.projects-panel__pct {
  font-size: 12px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  min-width: 36px;
  text-align: right;
}

@media (max-width: 600px) {
  .projects-panel__row {
    flex-direction: column;
    align-items: stretch;
  }
  .projects-panel__progress {
    min-width: 0;
  }
}
</style>
