<template>
  <section class="projects-panel iz-stack iz-stack--tight">
    <div class="projects-panel__filters">
      <input
        v-model="searchQuery"
        class="projects-panel__search iz-input"
        type="text"
        placeholder="Search projects…"
      />
    </div>

    <div v-if="filteredProjects.length === 0" class="iz-empty">
      No projects match your filters.
    </div>

    <div
      v-for="project in filteredProjects"
      :key="'proj-' + project.id"
      class="projects-panel__row iz-row--card iz-row--expandable"
      :class="{ 'projects-panel__row--expanded': isExpanded(project.id) }"
    >
      <div
        class="projects-panel__row-header iz-row__header"
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
          <div class="projects-panel__card iz-card">
            <div class="projects-panel__card-header">
              <h4 class="projects-panel__card-title">Project Overview</h4>
              <span
                v-if="project.statusLabel"
                class="iz-pill"
                :class="statusClass(project)"
              >{{ project.statusLabel }}</span>
            </div>
            <div class="projects-panel__info-grid">
              <div class="projects-panel__info-item">
                <span class="iz-label projects-panel__info-label">Project Name</span>
                <span class="projects-panel__info-value">{{ project.name }}</span>
              </div>
              <div v-if="project.number" class="projects-panel__info-item">
                <span class="iz-label projects-panel__info-label">Project Number</span>
                <span class="projects-panel__info-value">{{ project.number }}</span>
              </div>
              <div v-if="project.description" class="projects-panel__info-item">
                <span class="iz-label projects-panel__info-label">Description</span>
                <span class="projects-panel__info-value">{{ project.description }}</span>
              </div>
              <div
                v-if="project.location && project.location !== ','"
                class="projects-panel__info-item"
              >
                <span class="iz-label projects-panel__info-label">Project Address</span>
                <span class="projects-panel__info-value">{{ project.location }}</span>
              </div>
              <div class="projects-panel__info-item">
                <span class="iz-label projects-panel__info-label">Created</span>
                <span class="projects-panel__info-value">{{ formatDate(project.createdAt) }}</span>
              </div>
              <div class="projects-panel__info-item">
                <span class="iz-label projects-panel__info-label">Last Updated</span>
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

          <div class="projects-panel__card iz-card">
            <h4 class="projects-panel__card-title">Client &amp; Resources</h4>
            <div v-if="hasClientInfo(project)" class="projects-panel__info-grid">
              <div v-if="project.clientName" class="projects-panel__info-item">
                <span class="iz-label projects-panel__info-label">Client</span>
                <span class="projects-panel__info-value">{{ project.clientName }}</span>
              </div>
              <div v-if="project.clientEmail" class="projects-panel__info-item">
                <span class="iz-label projects-panel__info-label">Email</span>
                <a
                  :href="'mailto:' + project.clientEmail"
                  class="projects-panel__info-link"
                >{{ project.clientEmail }}</a>
              </div>
              <div v-if="project.clientPhone" class="projects-panel__info-item">
                <span class="iz-label projects-panel__info-label">Phone</span>
                <a
                  :href="'tel:' + project.clientPhone"
                  class="projects-panel__info-link"
                >{{ project.clientPhone }}</a>
              </div>
              <div
                v-if="project.clientAddress"
                class="projects-panel__info-item"
              >
                <span class="iz-label projects-panel__info-label">Client Address</span>
                <span class="projects-panel__info-value projects-panel__info-value--multiline">{{ project.clientAddress }}</span>
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

        <div class="projects-panel__map-row">
          <div class="projects-panel__card projects-panel__card--full">
            <div class="projects-panel__card-header">
              <h4 class="projects-panel__card-title">Project Location</h4>
            </div>
            <ProjectMap
              v-if="geocodeStatus[project.id] === 'loaded' && geocodeByProject[project.id]"
              :lat="Number(geocodeByProject[project.id].lat)"
              :lng="Number(geocodeByProject[project.id].lng)"
              :display-name="geocodeByProject[project.id].displayName || null"
            />
            <div
              v-else-if="geocodeStatus[project.id] === 'no_address'"
              class="projects-panel__map-state"
            >No address available for this project.</div>
            <div
              v-else-if="geocodeStatus[project.id] === 'not_found'"
              class="projects-panel__map-state"
            >Couldn't locate this address on the map.</div>
            <div
              v-else-if="geocodeStatus[project.id] === 'unavailable'"
              class="projects-panel__map-state projects-panel__map-state--error"
            >{{ geocodeError[project.id] || "Map unavailable." }}</div>
            <div
              v-else
              class="projects-panel__map-state"
            >Loading map…</div>
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
            <span class="projects-panel__section-title-text">
              Team Members
              <span class="projects-panel__section-count">
                {{ (project.members || []).length }}
              </span>
            </span>
            <button
              type="button"
              class="projects-panel__add-toggle"
              @click="toggleProjectAddPanel(project.id)"
            >
              <span aria-hidden="true">{{ (addPanelByProject[project.id] && addPanelByProject[project.id].open) ? "×" : "+" }}</span>
              {{ (addPanelByProject[project.id] && addPanelByProject[project.id].open) ? "Close" : "Add" }}
            </button>
          </h4>
          <div class="projects-panel__section-body">
            <div
              v-if="addPanelByProject[project.id] && addPanelByProject[project.id].open"
              class="projects-panel__add-form"
            >
              <input
                type="search"
                class="projects-panel__add-form-input"
                v-model="addPanelByProject[project.id].search"
                placeholder="Search org members…"
              />
              <div
                v-if="addPanelByProject[project.id].error"
                class="projects-panel__add-form-error"
              >{{ addPanelByProject[project.id].error }}</div>
              <ul
                v-if="availableMembersForProject(project).length"
                class="projects-panel__add-form-results"
              >
                <li
                  v-for="u in availableMembersForProject(project)"
                  :key="'avail-' + project.id + '-' + u.userId"
                  class="projects-panel__add-form-result"
                >
                  <div class="projects-panel__add-form-result-info">
                    <span class="projects-panel__add-form-result-name">
                      {{ u.displayName || u.userId }}
                    </span>
                    <span class="projects-panel__add-form-result-meta">
                      <template v-if="u.email">{{ u.email }} · </template>uid: {{ u.userId }}
                    </span>
                  </div>
                  <select
                    :value="rowRoleFor(project.id, u.userId)"
                    @change="setRowRole(project.id, u.userId, $event.target.value)"
                    class="projects-panel__add-form-role"
                    :aria-label="'DRASCI role for ' + (u.displayName || u.userId)"
                  >
                    <option value="">DRASCI role…</option>
                    <option value="driver">Driver</option>
                    <option value="responsible">Responsible</option>
                    <option value="accountable">Accountable</option>
                    <option value="supportive">Supportive</option>
                    <option value="consulted">Consulted</option>
                    <option value="informed">Informed</option>
                  </select>
                  <button
                    type="button"
                    class="projects-panel__add-form-add-btn"
                    :disabled="addPanelByProject[project.id].addingUid !== null || !rowRoleFor(project.id, u.userId)"
                    :title="rowRoleFor(project.id, u.userId) ? '' : 'Choose a DRASCI role first'"
                    :aria-label="'Add ' + (u.displayName || u.userId)"
                    @click="addProjectMember(project.id, u.userId)"
                  >
                    <span
                      v-if="addPanelByProject[project.id].addingUid === u.userId"
                      class="projects-panel__add-form-spinner"
                      aria-hidden="true"
                    ></span>
                    <span v-else aria-hidden="true">+</span>
                  </button>
                </li>
              </ul>
              <div
                v-else
                class="projects-panel__add-form-state"
              >
                <template v-if="addPanelByProject[project.id].search.trim()">
                  No matches.
                </template>
                <template v-else>
                  All organization members are already on this project.
                </template>
              </div>
            </div>

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
                <div class="projects-panel__member-badges">
                  <select
                    v-if="editingRoleKey === project.id + ':' + m.userId"
                    :value="drasciRoleFor(project.id, m.userId) || ''"
                    :disabled="savingRoleKey === project.id + ':' + m.userId"
                    class="projects-panel__member-drasci-edit"
                    @change="updateMemberRole(project.id, m.userId, $event.target.value)"
                    @blur="cancelEditRole"
                  >
                    <option value="">Unassigned</option>
                    <option value="driver">Driver</option>
                    <option value="responsible">Responsible</option>
                    <option value="accountable">Accountable</option>
                    <option value="supportive">Supportive</option>
                    <option value="consulted">Consulted</option>
                    <option value="informed">Informed</option>
                  </select>
                  <button
                    v-else-if="drasciLabelFor(project.id, m.userId)"
                    type="button"
                    class="projects-panel__member-drasci"
                    :class="'projects-panel__member-drasci--' + (drasciRoleFor(project.id, m.userId) || 'unassigned')"
                    :title="'Click to change DRASCI role'"
                    @click="startEditRole(project.id, m.userId)"
                  >
                    {{ drasciLabelFor(project.id, m.userId) }}
                  </button>
                  <span
                    class="iz-pill projects-panel__member-role"
                    :class="m.isOwner
                      ? 'iz-pill--accent'
                      : 'iz-pill--muted'"
                  >
                    <span v-if="m.isOwner" aria-hidden="true">★</span>
                    {{ m.isOwner ? 'Owner' : 'Member' }}
                  </span>
                </div>
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
import ProjectMap from "./ProjectMap.vue";

export default {
  name: "ProjectsPanel",
  components: { TimelineChart, ProjectTaskBrowser, ProjectMap },
  emits: ["reload"],
  props: {
    projects: {
      type: Array,
      default: () => [],
    },
    orgMembers: {
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
      // Per-project state for the inline "+ Add member" panel.
      // Shape: { [projectId]: { open, search, rowRoles, addingUid, error } }
      addPanelByProject: {},
      // DRASCI role labels per project member, fetched on project expand.
      // Our own backend's payload doesn't include drasciRole, so we ask
      // the projectcreatoraio API directly. Shape:
      // { [projectId]: { [userId]: { role, label } } }
      memberRolesByProject: {},
      // Geocode result per project, fetched on project expand. Mirrors
      // adminpage's per-project lookup but without the org scope check.
      geocodeByProject: {},   // { [projectId]: { lat, lng, displayName, source, fromCache } }
      geocodeStatus: {},      // 'loading' | 'loaded' | 'no_address' | 'not_found' | 'unavailable'
      geocodeError: {},
      // Inline edit state for member DRASCI roles. Single-edit-at-a-time:
      // both fields are "<projectId>:<userId>" strings, or null when idle.
      editingRoleKey: null,
      savingRoleKey: null,
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
        p.clientAddress
      );
    },
    // Project status -> In Zicht pill tone. A map (not string concat) so an
    // unrecognised status label degrades to the neutral tone rather than
    // emitting a class that doesn't exist.
    statusClass(p) {
      const label = (p.statusLabel || "").toLowerCase().replace(/ /g, "-");
      return (
        {
          active: "iz-pill--success",
          "waiting-on-customer": "iz-pill--warning",
          "on-hold": "iz-pill--danger",
          done: "iz-pill--accent",
        }[label] || "iz-pill--muted"
      );
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
        if (!this.memberRolesByProject[id]) {
          this.loadProjectMemberRoles(id);
        }
        if (!this.geocodeStatus[id]) {
          this.loadProjectGeocode(id);
        }
      } else {
        this.expandedIds.splice(i, 1);
      }
    },
    async loadProjectGeocode(projectId) {
      if (!projectId) return;
      if (this.geocodeStatus[projectId] === "loading") return;
      this.$set(this.geocodeStatus, projectId, "loading");
      this.$set(this.geocodeError, projectId, null);
      try {
        const res = await axios.get(
          generateUrl(
            "/apps/superadminpage/api/super/projects/" + projectId + "/geocode",
          ),
        );
        const d = (res && res.data) || {};
        if (d.lat != null && d.lng != null) {
          this.$set(this.geocodeByProject, projectId, d);
          this.$set(this.geocodeStatus, projectId, "loaded");
        } else {
          this.$set(this.geocodeStatus, projectId, "not_found");
        }
      } catch (e) {
        const code = (e && e.response && e.response.status) || 0;
        const reason =
          (e && e.response && e.response.data && e.response.data.reason) || "";
        if (code === 404 && reason === "no_address") {
          this.$set(this.geocodeStatus, projectId, "no_address");
        } else if (code === 404 && reason === "not_found") {
          this.$set(this.geocodeStatus, projectId, "not_found");
        } else if (code === 404 && reason === "no_project") {
          this.$set(this.geocodeStatus, projectId, "unavailable");
          this.$set(this.geocodeError, projectId, "Project no longer exists.");
        } else if (code === 503) {
          this.$set(this.geocodeStatus, projectId, "unavailable");
          this.$set(
            this.geocodeError,
            projectId,
            "Geocoding service unavailable.",
          );
        } else {
          this.$set(this.geocodeStatus, projectId, "unavailable");
          this.$set(this.geocodeError, projectId, "Couldn't load map.");
        }
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
    async loadProjectMemberRoles(projectId) {
      try {
        const res = await axios.get(
          generateUrl(
            "/apps/projectcreatoraio/api/v1/projects/" + projectId + "/members",
          ),
          {
            headers: {
              "OCS-APIRequest": "true",
              Accept: "application/json",
            },
          },
        );
        const members = (res.data && res.data.members) || [];
        const map = {};
        for (let i = 0; i < members.length; i++) {
          const m = members[i];
          // The projectcreatoraio API returns `id` for the user identifier;
          // our own backend uses `userId`. Index by both so lookups work
          // regardless of which side we have in hand.
          const uid = m.id || m.userId;
          if (!uid) continue;
          map[uid] = {
            role: m.drasciRole || null,
            label: m.drasciRoleLabel || "Unassigned",
          };
        }
        this.$set(this.memberRolesByProject, projectId, map);
      } catch (e) {
        // Silent: badge falls back to "Unassigned" until next reload.
        // Don't block the rest of the row from rendering.
      }
    },
    drasciLabelFor(projectId, userId) {
      const roles = this.memberRolesByProject[projectId];
      if (!roles) return null;
      const entry = roles[userId];
      if (!entry) return null;
      return entry.label || "Unassigned";
    },
    drasciRoleFor(projectId, userId) {
      const roles = this.memberRolesByProject[projectId];
      if (!roles) return null;
      const entry = roles[userId];
      return entry ? entry.role : null;
    },
    startEditRole(projectId, userId) {
      const key = projectId + ":" + userId;
      // Don't restart an edit that's currently saving.
      if (this.savingRoleKey === key) return;
      this.editingRoleKey = key;
    },
    cancelEditRole() {
      // Don't cancel mid-save — the @blur fires before we know the result.
      if (this.savingRoleKey) return;
      this.editingRoleKey = null;
    },
    async updateMemberRole(projectId, userId, newRole) {
      const key = projectId + ":" + userId;
      const current = this.drasciRoleFor(projectId, userId) || "";
      // Empty option ("Unassigned") is informational only — the API doesn't
      // accept "" as a valid DRASCI role (it returns 400). Just close.
      if (!newRole) {
        this.editingRoleKey = null;
        return;
      }
      // No-op if the role didn't change.
      if (newRole === current) {
        this.editingRoleKey = null;
        return;
      }
      this.savingRoleKey = key;
      try {
        await axios.put(
          generateUrl(
            "/apps/projectcreatoraio/api/v1/projects/" +
              projectId +
              "/members/" +
              encodeURIComponent(userId) +
              "/role",
          ),
          { drasciRole: newRole },
          {
            headers: {
              "OCS-APIRequest": "true",
              Accept: "application/json",
              "Content-Type": "application/json",
            },
          },
        );
        // Refresh role labels for this project so the new badge text/color
        // reflects the server-confirmed state (handles label capitalization
        // differences from the API).
        await this.loadProjectMemberRoles(projectId);
        this.editingRoleKey = null;
      } catch (e) {
        // Surface a transient error and leave the editor open so the admin
        // can try again. Window.alert is intentionally simple — a richer
        // inline error UI can come later if this hits often.
        const msg =
          (e && e.response && e.response.data && e.response.data.message) ||
          "Couldn't update DRASCI role. Try again.";
        // eslint-disable-next-line no-alert
        window.alert(msg);
      } finally {
        this.savingRoleKey = null;
      }
    },
    getAddPanelState(projectId) {
      // Lazily create the entry so Vue's reactivity tracks subsequent
      // mutations (via $set on first access).
      if (!this.addPanelByProject[projectId]) {
        this.$set(this.addPanelByProject, projectId, {
          open: false,
          search: "",
          rowRoles: {},
          addingUid: null,
          error: null,
        });
      }
      return this.addPanelByProject[projectId];
    },
    setRowRole(projectId, uid, role) {
      const s = this.getAddPanelState(projectId);
      this.$set(s.rowRoles, uid, role);
    },
    rowRoleFor(projectId, uid) {
      const s = this.addPanelByProject[projectId];
      return (s && s.rowRoles && s.rowRoles[uid]) || "";
    },
    toggleProjectAddPanel(projectId) {
      const s = this.getAddPanelState(projectId);
      if (s.open) {
        this.closeProjectAddPanel(projectId);
      } else {
        s.open = true;
        s.search = "";
        s.rowRoles = {};
        s.error = null;
      }
    },
    closeProjectAddPanel(projectId) {
      const s = this.getAddPanelState(projectId);
      s.open = false;
      s.search = "";
      s.rowRoles = {};
      s.error = null;
    },
    availableMembersForProject(project) {
      const existing = (project.members || []).reduce((acc, m) => {
        acc[m.userId] = true;
        return acc;
      }, {});
      const state = this.addPanelByProject[project.id];
      const q = state ? (state.search || "").trim().toLowerCase() : "";
      const out = [];
      for (let i = 0; i < this.orgMembers.length; i++) {
        const om = this.orgMembers[i];
        if (existing[om.userId]) continue;
        if (q) {
          const name = (om.displayName || "").toLowerCase();
          const uid = (om.userId || "").toLowerCase();
          const email = (om.email || "").toLowerCase();
          if (
            name.indexOf(q) === -1 &&
            uid.indexOf(q) === -1 &&
            email.indexOf(q) === -1
          ) {
            continue;
          }
        }
        out.push(om);
      }
      out.sort((a, b) =>
        (a.displayName || a.userId || "").localeCompare(
          b.displayName || b.userId || "",
        ),
      );
      return out;
    },
    async addProjectMember(projectId, uid) {
      const s = this.getAddPanelState(projectId);
      if (s.addingUid !== null) return;
      const role = (s.rowRoles && s.rowRoles[uid]) || "";
      if (!role) {
        // Should be unreachable — the [+] button is disabled when no role
        // is picked — but guard anyway so we never send an empty role.
        s.error = "Choose a DRASCI role before adding.";
        return;
      }
      s.addingUid = uid;
      s.error = null;
      try {
        // projectcreatoraio uses a plain app route (not OCS) but still
        // requires the OCS-APIRequest header per its controller attributes.
        await axios.post(
          generateUrl(
            "/apps/projectcreatoraio/api/v1/projects/" + projectId + "/members",
          ),
          { userId: uid, drasciRole: role },
          {
            headers: {
              "OCS-APIRequest": "true",
              Accept: "application/json",
              "Content-Type": "application/json",
            },
          },
        );
        // 201 (added) and 200 (alreadyMember) both end up here. Close and
        // reload; the new row will appear via the standard refresh chain.
        this.closeProjectAddPanel(projectId);
        // Refetch DRASCI role labels so the new member's badge shows even
        // before the full org reload completes. Fire-and-forget — failure
        // just leaves the badge as "Unassigned" until the reload lands.
        this.loadProjectMemberRoles(projectId);
        this.$emit("reload");
      } catch (e) {
        s.error = this.projectAddError(e);
        // 404 likely means the project itself is gone — surface the error
        // and still reload so the row disappears from the parent.
        if (e && e.response && e.response.status === 404) {
          this.$emit("reload");
        }
      } finally {
        s.addingUid = null;
      }
    },
    projectAddError(err) {
      if (!err) return "Couldn't add member. Try again.";
      if (!err.response) return "Couldn't reach the server. Try again.";
      const status = err.response.status;
      if (status === 403) return "User no longer in this organization.";
      if (status === 404) return "Project no longer exists.";
      if (status === 400) return "Invalid request.";
      if (status >= 500) return "Server error. Try again.";
      return "Couldn't add member (HTTP " + status + ").";
    },
  },
};
</script>

<style scoped>

.projects-panel__filters {
  margin-bottom: 4px;
}

/* Chrome comes from .iz-input; only the full-width stretch is local. */
.projects-panel__search {
  width: 100%;
}



/* Shell chrome comes from .iz-row--card .iz-row--expandable. */


.projects-panel__row--expanded {
  border-color: var(--iz-accent);
}

/* Padding/cursor come from .iz-row__header; this row needs a wider gap. */
.projects-panel__row-header {
  gap: 14px;
}


.projects-panel__chevron {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-muted, var(--color-text-muted));
  width: 12px;
  flex-shrink: 0;
  transition: transform 0.15s ease;
}

.projects-panel__chevron--open {
  transform: rotate(90deg);
  color: var(--accent);
}

.projects-panel__expanded {
  border-top: 1px solid var(--bg-subtle);
  padding: 16px;
  background: var(--bg-subtle);
  display: flex;
  flex-direction: column;
  gap: 14px;
  border-bottom-left-radius: 10px;
  border-bottom-right-radius: 10px;
}

.projects-panel__section {
  background: var(--bg-card);
  border: 1px solid var(--color-border, var(--color-border));
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.projects-panel__section-title {
  margin: 0;
  padding: 10px 14px;
  font-size: var(--iz-fs-sm);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--color-text-muted, var(--color-text-secondary));
  background: var(--bg-subtle);
  border-bottom: 1px solid var(--color-border, var(--bg-subtle));
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.projects-panel__section-title-text {
  display: inline-flex;
  align-items: center;
  gap: 6px;
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
  font-size: var(--iz-fs-md);
  font-weight: 600;
  color: var(--color-text-primary, var(--color-text-primary));
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.projects-panel__meta {
  display: flex;
  gap: 10px;
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted, var(--color-text-muted));
}

.projects-panel__meta-item--danger {
  color: var(--color-danger, var(--color-danger-text));
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
  background: var(--color-border);
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.projects-panel__fill {
  height: 100%;
  border-radius: var(--radius-sm);
  transition: width 0.4s ease;
}

.projects-panel__fill--high {
  background: var(--color-success);
}
.projects-panel__fill--mid {
  background: var(--color-warning-text);
}
.projects-panel__fill--low {
  background: var(--color-danger-text);
}

.projects-panel__pct {
  font-size: var(--iz-fs-sm);
  font-weight: 700;
  color: var(--color-text-primary, var(--color-text-primary));
  min-width: 36px;
  text-align: right;
}

.projects-panel__cards-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.projects-panel__map-row {
  margin-top: 14px;
}

/* Chrome comes from .iz-card; the internal stack is local. */
.projects-panel__card {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.projects-panel__card--full {
  width: 100%;
}

.projects-panel__map-state {
  height: 280px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text-muted, var(--color-text-muted));
  font-size: var(--iz-fs-md);
  background: var(--bg-subtle);
  border-radius: var(--radius-el);
  text-align: center;
  padding: 0 16px;
}

.projects-panel__map-state--error {
  color: var(--color-danger-text);
  background: var(--color-danger-bg);
}

.projects-panel__card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.projects-panel__card-title {
  margin: 0;
  font-size: var(--iz-fs-sm);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--color-text-muted, var(--color-text-secondary));
}

.projects-panel__card-title--sub {
  margin-top: 4px;
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

/* Eyebrow comes from .iz-label; these sit in a tight grid, no bottom margin. */
.projects-panel__info-label {
  margin-bottom: 0;
  letter-spacing: 0.4px;
}

.projects-panel__info-value {
  font-size: var(--iz-fs-md);
  color: var(--color-text-primary, var(--color-text-primary));
  word-break: break-word;
}

.projects-panel__info-value--multiline {
  white-space: pre-wrap;
}

.projects-panel__info-link {
  font-size: var(--iz-fs-md);
  color: var(--accent);
  text-decoration: none;
  word-break: break-all;
}

.projects-panel__info-link:hover {
  text-decoration: underline;
}

.projects-panel__no-client {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-muted, var(--color-text-muted));
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
  font-size: var(--iz-fs-sm);
  color: var(--color-text-muted, var(--color-text-secondary));
  font-weight: 600;
}

.projects-panel__completion-pct {
  color: var(--color-text-primary, var(--color-text-primary));
}

.projects-panel__completion-bar {
  height: 6px;
  background: var(--color-border);
  border-radius: var(--radius-sm);
  overflow: hidden;
}

.projects-panel__completion-fill {
  height: 100%;
  border-radius: var(--radius-sm);
  transition: width 0.4s ease;
}

.projects-panel__completion-detail {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted, var(--color-text-muted));
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
  background: var(--bg-subtle);
  border: 1px solid var(--color-border, var(--bg-subtle));
  border-radius: var(--radius-el);
  gap: 2px;
}

.projects-panel__resource-value {
  font-size: var(--iz-fs-lg);
  font-weight: 700;
  color: var(--color-text-primary, var(--color-text-primary));
}

.projects-panel__resource-label {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted, var(--color-text-secondary));
}

.projects-panel__kpi-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
}

.projects-panel__kpi-stat {
  background: var(--bg-card);
  border: 1px solid var(--color-border, var(--color-border));
  border-radius: var(--radius-lg);
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: flex-start;
}

.projects-panel__kpi-stat-value {
  font-size: var(--iz-fs-xl);
  font-weight: 700;
  color: var(--color-text-primary, var(--color-text-primary));
  line-height: 1.1;
}

.projects-panel__kpi-stat-label {
  font-size: var(--iz-fs-sm);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: var(--color-text-muted, var(--color-text-secondary));
}

.projects-panel__kpi-stat-sub {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted, var(--color-text-muted));
}

.projects-panel__kpi-bar {
  width: 100%;
  height: 4px;
  background: var(--color-border);
  border-radius: var(--radius-sm);
  overflow: hidden;
  margin-top: 2px;
}

.projects-panel__kpi-bar-fill {
  height: 100%;
  border-radius: var(--radius-sm);
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
  border-radius: var(--radius-pill);
  background: var(--bg-subtle);
  color: var(--color-text-secondary);
  font-size: var(--iz-fs-xs);
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
  padding: var(--iz-pad-row);
  border-bottom: 1px solid var(--iz-border);
  transition: background var(--iz-transition);
}
.projects-panel__member:last-child {
  border-bottom: 0;
}
.projects-panel__member:hover {
  background: var(--bg-subtle);
}
.projects-panel__member-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--iz-accent-bg);
  color: var(--accent);
  font-weight: 700;
  font-size: var(--iz-fs-md);
  letter-spacing: 0.5px;
}
.projects-panel__member-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.projects-panel__member-name {
  font-weight: 600;
  color: var(--color-text-primary);
  font-size: var(--iz-fs-md);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.projects-panel__member-email {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-decoration: none;
}
.projects-panel__member-email:hover {
  color: var(--accent);
  text-decoration: underline;
}
.projects-panel__member-email--muted {
  color: var(--color-text-muted);
  font-style: italic;
}
.projects-panel__member-badges {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-wrap: nowrap;
}

.projects-panel__member-drasci {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: var(--radius-pill);
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  white-space: nowrap;
  border: 0;
  cursor: pointer;
  transition: filter 0.15s, box-shadow 0.15s;
}

.projects-panel__member-drasci:hover {
  filter: brightness(0.92);
  box-shadow: 0 0 0 2px var(--iz-accent-bg);
}

.projects-panel__member-drasci-edit {
  font-size: var(--iz-fs-xs);
  font-weight: 600;
  padding: 3px 6px;
  border-radius: var(--radius-pill);
  border: 1px solid var(--accent);
  background: var(--bg-card);
  color: var(--color-text-primary);
  cursor: pointer;
  max-width: 140px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.projects-panel__member-drasci-edit:focus {
  outline: none;
  box-shadow: 0 0 0 2px var(--iz-accent-bg);
}

.projects-panel__member-drasci-edit:disabled {
  opacity: 0.6;
  cursor: progress;
}

/* DRASCI role color palette — distinct hues for quick scanning */
.projects-panel__member-drasci--driver {
  background: var(--iz-warning-bg);
  color: var(--color-warning-text);
}

.projects-panel__member-drasci--responsible {
  background: var(--iz-accent-bg);
  color: var(--accent-strong);
}

.projects-panel__member-drasci--accountable {
  background: var(--iz-danger-bg);
  color: var(--color-danger-text);
}

.projects-panel__member-drasci--supportive {
  background: var(--iz-success-bg);
  color: var(--color-success-text);
}

.projects-panel__member-drasci--consulted {
  background: rgba(139, 92, 246, 0.14);
  color: var(--chart-5);
}

.projects-panel__member-drasci--informed {
  background: var(--bg-subtle);
  color: var(--color-text-secondary);
}

.projects-panel__member-drasci--unassigned {
  background: var(--bg-subtle);
  color: var(--color-text-muted);
  font-style: italic;
}

.projects-panel__members-empty {
  padding: 14px 12px;
  text-align: center;
  color: var(--color-text-muted);
  font-size: var(--iz-fs-md);
  font-style: italic;
}

/* ── Inline "+ Add member" panel ────────────────────────────────────── */
.projects-panel__add-toggle {
  background: var(--bg-card);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  color: var(--accent);
  font-size: var(--iz-fs-xs);
  font-weight: 700;
  text-transform: none;
  letter-spacing: 0;
  padding: 3px 9px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}

.projects-panel__add-toggle:hover {
  background: var(--accent);
  border-color: var(--accent);
  color: #fff;
}

.projects-panel__add-form {
  background: var(--bg-subtle);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 12px 14px;
  margin-bottom: 12px;
}

.projects-panel__add-form-input {
  width: 100%;
  padding: 7px 12px;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-el);
  font-size: var(--iz-fs-md);
  color: var(--color-text-primary, var(--color-text-primary));
  background: var(--bg-card);
  outline: none;
  transition: border-color 0.15s;
  box-sizing: border-box;
}

.projects-panel__add-form-input:focus {
  border-color: var(--accent);
}

.projects-panel__add-form-role {
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--bg-card);
  color: var(--color-text-primary, var(--color-text-primary));
  font-size: var(--iz-fs-sm);
  padding: 5px 8px;
  cursor: pointer;
  max-width: 140px;
  flex-shrink: 0;
}

.projects-panel__add-form-role:focus {
  outline: none;
  border-color: var(--accent);
}

.projects-panel__add-form-error {
  margin-top: 8px;
  font-size: var(--iz-fs-sm);
  color: var(--color-danger-text);
  background: var(--color-danger-bg);
  border: 1px solid var(--color-danger-bg);
  border-radius: var(--radius-sm);
  padding: 6px 10px;
}

.projects-panel__add-form-state {
  margin-top: 8px;
  font-size: var(--iz-fs-sm);
  color: var(--color-text-muted, var(--color-text-muted));
  font-style: italic;
}

.projects-panel__add-form-results {
  list-style: none;
  margin: 8px 0 0;
  padding: 0;
  background: var(--bg-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-el);
  overflow: hidden;
  max-height: 220px;
  overflow-y: auto;
}

.projects-panel__add-form-result {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
  border-bottom: 1px solid var(--bg-subtle);
}

.projects-panel__add-form-result:last-child {
  border-bottom: none;
}

.projects-panel__add-form-result-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.projects-panel__add-form-result-name {
  font-size: var(--iz-fs-md);
  font-weight: 600;
  color: var(--color-text-primary, var(--color-text-primary));
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.projects-panel__add-form-result-meta {
  font-size: var(--iz-fs-xs);
  color: var(--color-text-muted, var(--color-text-muted));
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.projects-panel__add-form-add-btn {
  width: 28px;
  height: 28px;
  border-radius: var(--radius-el);
  border: 1px solid var(--accent);
  background: var(--bg-card);
  color: var(--accent);
  font-size: var(--iz-fs-lg);
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
  flex-shrink: 0;
}

.projects-panel__add-form-add-btn:hover:not(:disabled) {
  background: var(--accent);
  color: #fff;
}

.projects-panel__add-form-add-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.projects-panel__add-form-spinner {
  width: 12px;
  height: 12px;
  border: 2px solid var(--accent);
  border-top-color: transparent;
  border-radius: 50%;
  animation: projects-panel-spin 0.8s linear infinite;
  display: inline-block;
}

@keyframes projects-panel-spin {
  to { transform: rotate(360deg); }
}
</style>
