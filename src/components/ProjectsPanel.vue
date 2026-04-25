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
        class="projects-panel__expanded"
      >
        <div class="projects-panel__cards-row">
          <div class="projects-panel__card">
            <div class="projects-panel__card-header">
              <h4 class="projects-panel__card-title">Project Overview</h4>
              <span
                v-if="project.statusLabel"
                class="projects-panel__badge"
                :class="statusClass(project)"
              >{{ project.statusLabel }}</span>
            </div>
            <div class="projects-panel__info-grid">
              <div class="projects-panel__info-item">
                <span class="projects-panel__info-label">Project Name</span>
                <span class="projects-panel__info-value">{{ project.name }}</span>
              </div>
              <div v-if="project.number" class="projects-panel__info-item">
                <span class="projects-panel__info-label">Project Number</span>
                <span class="projects-panel__info-value">{{ project.number }}</span>
              </div>
              <div v-if="project.description" class="projects-panel__info-item">
                <span class="projects-panel__info-label">Description</span>
                <span class="projects-panel__info-value">{{ project.description }}</span>
              </div>
              <div class="projects-panel__info-item">
                <span class="projects-panel__info-label">Created</span>
                <span class="projects-panel__info-value">{{ formatDate(project.createdAt) }}</span>
              </div>
              <div class="projects-panel__info-item">
                <span class="projects-panel__info-label">Last Updated</span>
                <span class="projects-panel__info-value">{{ formatDate(project.updatedAt) }}</span>
              </div>
            </div>
            <div class="projects-panel__completion">
              <div class="projects-panel__completion-header">
                <span class="projects-panel__completion-label">Task Completion</span>
                <span class="projects-panel__completion-pct">{{ project.progress }}%</span>
              </div>
              <div class="projects-panel__completion-bar">
                <div
                  class="projects-panel__completion-fill"
                  :class="fillClass(project)"
                  :style="{ width: project.progress + '%' }"
                ></div>
              </div>
              <span class="projects-panel__completion-detail">
                {{ project.done }} of {{ project.total }} tasks completed
              </span>
            </div>
          </div>

          <div class="projects-panel__card">
            <h4 class="projects-panel__card-title">Client &amp; Resources</h4>
            <div v-if="hasClientInfo(project)" class="projects-panel__info-grid">
              <div v-if="project.clientName" class="projects-panel__info-item">
                <span class="projects-panel__info-label">Client</span>
                <span class="projects-panel__info-value">{{ project.clientName }}</span>
              </div>
              <div v-if="project.clientEmail" class="projects-panel__info-item">
                <span class="projects-panel__info-label">Email</span>
                <a
                  :href="'mailto:' + project.clientEmail"
                  class="projects-panel__info-link"
                >{{ project.clientEmail }}</a>
              </div>
              <div v-if="project.clientPhone" class="projects-panel__info-item">
                <span class="projects-panel__info-label">Phone</span>
                <a
                  :href="'tel:' + project.clientPhone"
                  class="projects-panel__info-link"
                >{{ project.clientPhone }}</a>
              </div>
              <div
                v-if="project.location && project.location !== ','"
                class="projects-panel__info-item"
              >
                <span class="projects-panel__info-label">Location</span>
                <span class="projects-panel__info-value">{{ project.location }}</span>
              </div>
            </div>
            <div v-else class="projects-panel__no-client">
              No client information
            </div>
            <h4 class="projects-panel__card-title projects-panel__card-title--sub">Resources</h4>
            <div class="projects-panel__resources">
              <div class="projects-panel__resource-item">
                <span class="projects-panel__resource-value">{{ (project.resources && project.resources.files) || 0 }}</span>
                <span class="projects-panel__resource-label">Files</span>
              </div>
              <div class="projects-panel__resource-item">
                <span class="projects-panel__resource-value">{{ (project.resources && project.resources.whiteboards) || 0 }}</span>
                <span class="projects-panel__resource-label">Whiteboards</span>
              </div>
              <div class="projects-panel__resource-item">
                <span class="projects-panel__resource-value">{{ (project.resources && project.resources.notes) || 0 }}</span>
                <span class="projects-panel__resource-label">Notes</span>
              </div>
            </div>
          </div>
        </div>

        <div class="projects-panel__kpi-row">
          <div class="projects-panel__kpi-stat">
            <span class="projects-panel__kpi-stat-value">{{ project.progress }}%</span>
            <span class="projects-panel__kpi-stat-label">Completion Rate</span>
            <div class="projects-panel__kpi-bar">
              <div
                class="projects-panel__kpi-bar-fill"
                :class="fillClass(project)"
                :style="{ width: project.progress + '%' }"
              ></div>
            </div>
            <span class="projects-panel__kpi-stat-sub">{{ project.done }}/{{ project.total }} tasks</span>
          </div>
          <div class="projects-panel__kpi-stat">
            <span class="projects-panel__kpi-stat-value">{{ coordinationPending(project) }}</span>
            <span class="projects-panel__kpi-stat-label">Coordination Pending</span>
            <span class="projects-panel__kpi-stat-sub">Weeks since Request Date</span>
          </div>
          <div class="projects-panel__kpi-stat">
            <span class="projects-panel__kpi-stat-value">{{ requiredPrepTime(project) }}</span>
            <span class="projects-panel__kpi-stat-label">Required Prep Time</span>
            <span class="projects-panel__kpi-stat-sub">Planned preparation period</span>
          </div>
        </div>

        <section class="projects-panel__section">
          <h4 class="projects-panel__section-title">
            Team Members
            <span class="projects-panel__section-count">
              {{ (project.members || []).length }}
            </span>
          </h4>
          <div class="projects-panel__section-body">
            <ul
              v-if="(project.members || []).length"
              class="projects-panel__members"
            >
              <li
                v-for="m in project.members"
                :key="m.userId"
                class="projects-panel__member"
              >
                <span class="projects-panel__member-avatar">{{ initials(m.displayName) }}</span>
                <div class="projects-panel__member-info">
                  <span class="projects-panel__member-name">{{ m.displayName }}</span>
                  <a
                    v-if="m.email"
                    :href="'mailto:' + m.email"
                    class="projects-panel__member-email"
                  >{{ m.email }}</a>
                  <span
                    v-else
                    class="projects-panel__member-email projects-panel__member-email--muted"
                  >No email</span>
                </div>
                <span
                  class="projects-panel__member-role"
                  :class="m.isOwner
                    ? 'projects-panel__member-role--owner'
                    : 'projects-panel__member-role--member'"
                >
                  <span v-if="m.isOwner" aria-hidden="true">★</span>
                  {{ m.isOwner ? 'Owner' : 'Member' }}
                </span>
              </li>
            </ul>
            <div
              v-else
              class="projects-panel__members-empty"
            >
              No team members assigned to this project.
            </div>
          </div>
        </section>

        <section class="projects-panel__section">
          <h4 class="projects-panel__section-title">Timeline</h4>
          <div class="projects-panel__section-body">
            <TimelineChart :timeline="project.timeline || []" />
          </div>
        </section>

        <section class="projects-panel__section">
          <h4 class="projects-panel__section-title">Tasks</h4>
          <div class="projects-panel__section-body">
            <ProjectTaskBrowser
              :tasks="tasksByProject[project.id] || []"
              :loading="!!tasksLoadingByProject[project.id]"
              :error="tasksErrorByProject[project.id] || null"
            />
          </div>
        </section>
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
    formatDate(s) {
      if (!s) return "—";
      const d = new Date(s);
      if (isNaN(d.getTime())) return "—";
      return d.toLocaleDateString();
    },
    initials(name) {
      if (!name) return "?";
      const parts = name.trim().split(/\s+/).filter(Boolean);
      const first = parts[0] ? parts[0][0] : "";
      const last = parts.length > 1 ? parts[parts.length - 1][0] : "";
      return (first + last).toUpperCase() || name[0].toUpperCase();
    },
    hasClientInfo(p) {
      return !!(
        p.clientName ||
        p.clientEmail ||
        p.clientPhone ||
        (p.location && p.location !== ",")
      );
    },
    statusClass(p) {
      const label = (p.statusLabel || "").toLowerCase().replace(/ /g, "-");
      return "projects-panel__badge--" + label;
    },
    findTimelineItem(p, systemKey) {
      const tl = (p && p.timeline) || [];
      for (let i = 0; i < tl.length; i++) {
        if (tl[i].systemKey === systemKey) return tl[i];
      }
      return null;
    },
    coordinationPending(p) {
      const item = this.findTimelineItem(p, "request_date");
      if (!item || !item.startDate) return "—";
      const start = new Date(item.startDate);
      if (isNaN(start.getTime())) return "—";
      const days = Math.floor(
        (Date.now() - start.getTime()) / (1000 * 60 * 60 * 24),
      );
      const weeks = Math.round(days / 7);
      return weeks + " wk" + (weeks !== 1 ? "s" : "");
    },
    requiredPrepTime(p) {
      const item = this.findTimelineItem(p, "required_preparation");
      if (!item || !item.startDate || !item.endDate) return "—";
      const s = new Date(item.startDate);
      const e = new Date(item.endDate);
      if (isNaN(s.getTime()) || isNaN(e.getTime())) return "—";
      const days = Math.floor(
        (e.getTime() - s.getTime()) / (1000 * 60 * 60 * 24),
      );
      const weeks = Math.round(days / 7);
      return weeks + " wk" + (weeks !== 1 ? "s" : "");
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

.projects-panel__expanded {
  border-top: 1px solid #eef1f5;
  padding: 16px;
  background: #fafbfd;
  display: flex;
  flex-direction: column;
  gap: 14px;
  border-bottom-left-radius: 10px;
  border-bottom-right-radius: 10px;
}

.projects-panel__section {
  background: #fff;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  overflow: hidden;
}

.projects-panel__section-title {
  margin: 0;
  padding: 10px 14px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--color-text-muted, #6b7280);
  background: #fafbfd;
  border-bottom: 1px solid var(--color-border, #eef1f5);
}

.projects-panel__section-body {
  padding: 14px;
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

.projects-panel__cards-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.projects-panel__card {
  background: #fff;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.projects-panel__card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.projects-panel__card-title {
  margin: 0;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--color-text-muted, #6b7280);
}

.projects-panel__card-title--sub {
  margin-top: 4px;
}

.projects-panel__badge {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 999px;
  background: #eef1f5;
  color: #4b5563;
  white-space: nowrap;
}

.projects-panel__badge--active {
  background: #e6f4ea;
  color: #1f7a3e;
}
.projects-panel__badge--waiting-on-customer {
  background: #fff4e0;
  color: #a1620b;
}
.projects-panel__badge--on-hold {
  background: #fde8e8;
  color: #a32222;
}
.projects-panel__badge--done {
  background: #e8efff;
  color: #2b57c7;
}

.projects-panel__info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px 16px;
}

.projects-panel__info-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.projects-panel__info-label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: var(--color-text-muted, #9ca3af);
  font-weight: 600;
}

.projects-panel__info-value {
  font-size: 13px;
  color: var(--color-text-primary, #1a1a2e);
  word-break: break-word;
}

.projects-panel__info-link {
  font-size: 13px;
  color: #4a90d9;
  text-decoration: none;
  word-break: break-all;
}

.projects-panel__info-link:hover {
  text-decoration: underline;
}

.projects-panel__no-client {
  font-size: 12px;
  color: var(--color-text-muted, #9ca3af);
  font-style: italic;
}

.projects-panel__completion {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.projects-panel__completion-header {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: var(--color-text-muted, #6b7280);
  font-weight: 600;
}

.projects-panel__completion-pct {
  color: var(--color-text-primary, #1a1a2e);
}

.projects-panel__completion-bar {
  height: 6px;
  background: #e5e7eb;
  border-radius: 3px;
  overflow: hidden;
}

.projects-panel__completion-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.4s ease;
}

.projects-panel__completion-detail {
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
}

.projects-panel__resources {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.projects-panel__resource-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 10px 6px;
  background: #fafbfd;
  border: 1px solid var(--color-border, #eef1f5);
  border-radius: 8px;
  gap: 2px;
}

.projects-panel__resource-value {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
}

.projects-panel__resource-label {
  font-size: 11px;
  color: var(--color-text-muted, #6b7280);
}

.projects-panel__kpi-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
}

.projects-panel__kpi-stat {
  background: #fff;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: flex-start;
}

.projects-panel__kpi-stat-value {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-text-primary, #1a1a2e);
  line-height: 1.1;
}

.projects-panel__kpi-stat-label {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: var(--color-text-muted, #6b7280);
}

.projects-panel__kpi-stat-sub {
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
}

.projects-panel__kpi-bar {
  width: 100%;
  height: 4px;
  background: #e5e7eb;
  border-radius: 2px;
  overflow: hidden;
  margin-top: 2px;
}

.projects-panel__kpi-bar-fill {
  height: 100%;
  border-radius: 2px;
  transition: width 0.4s ease;
}

@media (max-width: 600px) {
  .projects-panel__row-header {
    flex-direction: column;
    align-items: stretch;
  }
  .projects-panel__progress {
    min-width: 0;
  }
  .projects-panel__cards-row,
  .projects-panel__kpi-row {
    grid-template-columns: 1fr;
  }
  .projects-panel__info-grid {
    grid-template-columns: 1fr;
  }
}

.projects-panel__section-count {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 8px;
  border-radius: 999px;
  background: #eef2f7;
  color: #6b7280;
  font-size: 11px;
  font-weight: 600;
}
.projects-panel__members {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}
.projects-panel__member {
  display: grid;
  grid-template-columns: 36px 1fr auto;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-bottom: 1px solid #eef0f3;
  transition: background 120ms ease;
}
.projects-panel__member:last-child {
  border-bottom: 0;
}
.projects-panel__member:hover {
  background: #f7f9fc;
}
.projects-panel__member-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(74, 144, 217, 0.12);
  color: #4a90d9;
  font-weight: 700;
  font-size: 13px;
  letter-spacing: 0.5px;
}
.projects-panel__member-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.projects-panel__member-name {
  font-weight: 600;
  color: #1f2937;
  font-size: 14px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.projects-panel__member-email {
  font-size: 12px;
  color: #6b7280;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-decoration: none;
}
.projects-panel__member-email:hover {
  color: #4a90d9;
  text-decoration: underline;
}
.projects-panel__member-email--muted {
  color: #9ca3af;
  font-style: italic;
}
.projects-panel__member-role {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.projects-panel__member-role--owner {
  background: rgba(74, 144, 217, 0.12);
  color: #4a90d9;
}
.projects-panel__member-role--member {
  background: #eef2f7;
  color: #6b7280;
}
.projects-panel__members-empty {
  padding: 14px 12px;
  text-align: center;
  color: #9ca3af;
  font-size: 13px;
  font-style: italic;
}
</style>
