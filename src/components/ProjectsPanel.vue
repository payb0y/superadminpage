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
      :class="{ 'projects-panel__row--expanded': isExpanded(project.id) }"
    >
      <div
        class="projects-panel__row-header"
        role="button"
        tabindex="0"
        :aria-expanded="isExpanded(project.id) ? 'true' : 'false'"
        @click="toggleExpand(project.id)"
        @keydown.enter.prevent="toggleExpand(project.id)"
        @keydown.space.prevent="toggleExpand(project.id)"
      >
        <span
          class="projects-panel__chevron"
          :class="{ 'projects-panel__chevron--open': isExpanded(project.id) }"
          aria-hidden="true"
        >▸</span>

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

      <div
        v-if="isExpanded(project.id)"
        class="projects-panel__timeline"
      >
        <TimelineChart :timeline="project.timeline || []" />
      </div>

      <div
        v-if="isExpanded(project.id)"
        class="projects-panel__tasks"
      >
        <ProjectTaskBrowser
          :tasks="tasksByProject[project.id] || []"
          :loading="!!tasksLoadingByProject[project.id]"
          :error="tasksErrorByProject[project.id] || null"
        />
      </div>
    </div>
  </section>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";
import TimelineChart from "./TimelineChart.vue";
import ProjectTaskBrowser from "./ProjectTaskBrowser.vue";

export default {
  name: "ProjectsPanel",
  components: { TimelineChart, ProjectTaskBrowser },
  props: {
    projects: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      searchQuery: "",
      expandedIds: [],
      tasksByProject: {},
      tasksLoadingByProject: {},
      tasksErrorByProject: {},
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
    isExpanded(id) {
      return this.expandedIds.indexOf(id) !== -1;
    },
    toggleExpand(id) {
      const i = this.expandedIds.indexOf(id);
      if (i === -1) {
        this.expandedIds.push(id);
        if (
          !this.tasksByProject[id] &&
          !this.tasksLoadingByProject[id]
        ) {
          this.loadTasks(id);
        }
      } else {
        this.expandedIds.splice(i, 1);
      }
    },
    async loadTasks(projectId) {
      this.$set(this.tasksLoadingByProject, projectId, true);
      this.$set(this.tasksErrorByProject, projectId, null);
      try {
        const res = await axios.get(
          generateUrl(
            "/apps/superadminpage/api/super/projects/" + projectId + "/tasks",
          ),
        );
        this.$set(
          this.tasksByProject,
          projectId,
          (res.data && res.data.tasks) || [],
        );
      } catch (e) {
        this.$set(
          this.tasksErrorByProject,
          projectId,
          (e && e.message) || "Failed to load tasks",
        );
      } finally {
        this.$set(this.tasksLoadingByProject, projectId, false);
      }
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
  display: flex;
  flex-direction: column;
  transition: border-color 0.15s;
}

.projects-panel__row:hover {
  border-color: #e0e3e9;
}

.projects-panel__row--expanded {
  border-color: #e0e3e9;
}

.projects-panel__row-header {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  cursor: pointer;
  user-select: none;
  outline: none;
}

.projects-panel__row-header:focus-visible {
  box-shadow: inset 0 0 0 2px #4a90d9;
  border-radius: 10px;
}

.projects-panel__chevron {
  font-size: 12px;
  color: var(--color-text-muted, #9ca3af);
  width: 12px;
  flex-shrink: 0;
  transition: transform 0.15s ease;
}

.projects-panel__chevron--open {
  transform: rotate(90deg);
  color: #4a90d9;
}

.projects-panel__timeline {
  border-top: 1px solid #eef1f5;
  padding: 16px;
  background: #fafbfd;
}

.projects-panel__tasks {
  border-top: 1px solid #eef1f5;
  padding: 16px;
  background: #fafbfd;
  border-bottom-left-radius: 10px;
  border-bottom-right-radius: 10px;
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
  .projects-panel__row-header {
    flex-direction: column;
    align-items: stretch;
  }
  .projects-panel__progress {
    min-width: 0;
  }
}
</style>
