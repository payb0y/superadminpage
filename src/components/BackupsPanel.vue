<template>
  <div :class="['backups-panel', { 'backups-panel--embedded': embedded }]">
    <!-- Empty state (no jobs at all) -->
    <div v-if="!jobs || jobs.length === 0" class="backups-panel__empty">
      <p class="backups-panel__empty-text">No backup jobs found.</p>
    </div>

    <template v-else>
      <!-- ── Filters ── -->
      <div class="backups-panel__filters">
        <!-- Status filter -->
        <div class="backups-panel__filter-group">
          <span
            v-for="s in statusOptions"
            :key="'sf-' + s.value"
            class="backups-panel__filter-badge"
            :class="[
              'backups-panel__filter-badge--' + (s.value || 'all'),
              { 'backups-panel__filter-badge--active': statusFilter === s.value },
            ]"
            @click="statusFilter = s.value; currentPage = 1"
          >{{ s.label }}</span>
        </div>

        <!-- Type filter -->
        <div class="backups-panel__filter-group">
          <span
            v-for="t in typeOptions"
            :key="'tf-' + t.value"
            class="backups-panel__filter-badge"
            :class="[
              'backups-panel__filter-badge--' + (t.value || 'all'),
              { 'backups-panel__filter-badge--active': typeFilter === t.value },
            ]"
            @click="typeFilter = t.value; currentPage = 1"
          >{{ t.label }}</span>
        </div>

        <!-- Trigger filter -->
        <div class="backups-panel__filter-group">
          <span
            v-for="tr in triggerOptions"
            :key="'trf-' + tr.value"
            class="backups-panel__filter-badge"
            :class="[
              'backups-panel__filter-badge--' + (tr.value || 'all'),
              { 'backups-panel__filter-badge--active': triggerFilter === tr.value },
            ]"
            @click="triggerFilter = tr.value; currentPage = 1"
          >{{ tr.label }}</span>
        </div>
      </div>

      <!-- No results after filtering -->
      <div v-if="filteredJobs.length === 0" class="backups-panel__empty">
        <p class="backups-panel__empty-text">No backup jobs match your filters.</p>
      </div>

      <!-- Backup jobs table -->
      <div v-else class="backups-panel__table-wrap">
        <table class="backups-panel__table">
          <thead>
            <tr>
              <th>Status</th>
              <th>Type</th>
              <th>Trigger</th>
              <th>Artifact</th>
              <th>Size</th>
              <th>Created</th>
              <th>Duration</th>
              <th>Expires</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="job in paginatedJobs"
              :key="job.jobId"
              class="backups-panel__row"
            >
              <!-- Status badge -->
              <td>
                <span
                  class="backups-panel__badge"
                  :class="'backups-panel__badge--' + job.status"
                >{{ job.status }}</span>
              </td>

              <!-- Backup type -->
              <td>
                <span
                  class="backups-panel__type"
                  :class="'backups-panel__type--' + job.backupType"
                >{{ job.backupType }}</span>
              </td>

              <!-- Trigger source -->
              <td>
                <span class="backups-panel__trigger">
                  <svg
                    v-if="job.triggerSource === 'scheduled'"
                    xmlns="http://www.w3.org/2000/svg"
                    width="13"
                    height="13"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                  </svg>
                  <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    width="13"
                    height="13"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                  </svg>
                  {{ job.triggerSource }}
                </span>
              </td>

              <!-- Artifact name -->
              <td>
                <span
                  v-if="job.artifactName"
                  class="backups-panel__artifact"
                  :title="job.artifactName"
                >{{ truncateArtifact(job.artifactName) }}</span>
                <span v-else class="backups-panel__muted">&mdash;</span>
              </td>

              <!-- Size -->
              <td>
                <span v-if="job.artifactSize" class="backups-panel__size">{{ formatSize(job.artifactSize) }}</span>
                <span v-else class="backups-panel__muted">&mdash;</span>
              </td>

              <!-- Created -->
              <td>
                <span class="backups-panel__date">{{ formatDate(job.createdAt) }}</span>
              </td>

              <!-- Duration -->
              <td>
                <span v-if="job.startedAt && job.finishedAt" class="backups-panel__duration">{{ formatDuration(job.startedAt, job.finishedAt) }}</span>
                <span v-else-if="job.status === 'running'" class="backups-panel__badge backups-panel__badge--running">running</span>
                <span v-else class="backups-panel__muted">&mdash;</span>
              </td>

              <!-- Expires -->
              <td>
                <span
                  v-if="job.expiresAt"
                  class="backups-panel__date"
                  :class="{ 'backups-panel__date--expiring': isExpiringSoon(job.expiresAt), 'backups-panel__date--expired': isExpired(job.expiresAt) }"
                >{{ formatDate(job.expiresAt) }}</span>
                <span v-else class="backups-panel__muted">&mdash;</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="backups-panel__pagination">
        <button
          class="backups-panel__page-btn"
          :disabled="currentPage <= 1"
          @click="currentPage--"
        >&#8249;</button>
        <span class="backups-panel__page-info">{{ currentPage }} / {{ totalPages }}</span>
        <button
          class="backups-panel__page-btn"
          :disabled="currentPage >= totalPages"
          @click="currentPage++"
        >&#8250;</button>
      </div>
      <div v-if="filteredJobs.length > 0" class="backups-panel__showing-hint">
        Showing {{ paginatedJobs.length }} of {{ filteredJobs.length }} backups
      </div>
    </template>
  </div>
</template>

<script>
export default {
  name: 'BackupsPanel',
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
    jobs: {
      type: Array,
      default: function () {
        return [];
      },
    },
  },
  data: function () {
    return {
      statusFilter: '',
      typeFilter: '',
      triggerFilter: '',
      currentPage: 1,
      pageSize: 5,
    };
  },
  computed: {
    statusOptions: function () {
      return [
        { label: 'All', value: '' },
        { label: 'Completed', value: 'completed' },
        { label: 'Expired', value: 'expired' },
        { label: 'Running', value: 'running' },
        { label: 'Failed', value: 'failed' },
      ];
    },
    typeOptions: function () {
      return [
        { label: 'All types', value: '' },
        { label: 'Full', value: 'full' },
        { label: 'Incremental', value: 'incremental' },
      ];
    },
    triggerOptions: function () {
      return [
        { label: 'All triggers', value: '' },
        { label: 'Scheduled', value: 'scheduled' },
        { label: 'Manual', value: 'manual' },
      ];
    },
    filteredJobs: function () {
      var status = this.statusFilter;
      var type = this.typeFilter;
      var trigger = this.triggerFilter;
      var result = [];
      for (var i = 0; i < this.jobs.length; i++) {
        var job = this.jobs[i];
        if (status && job.status !== status) continue;
        if (type && job.backupType !== type) continue;
        if (trigger && job.triggerSource !== trigger) continue;
        result.push(job);
      }
      return result;
    },
    totalPages: function () {
      return Math.max(1, Math.ceil(this.filteredJobs.length / this.pageSize));
    },
    paginatedJobs: function () {
      var start = (this.currentPage - 1) * this.pageSize;
      return this.filteredJobs.slice(start, start + this.pageSize);
    },
  },
  watch: {
    filteredJobs: function () {
      if (this.currentPage > this.totalPages) {
        this.currentPage = this.totalPages;
      }
    },
  },
  methods: {
    formatSize: function (bytes) {
      if (!bytes || bytes === 0) return '0 B';
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
      if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
      return (bytes / 1073741824).toFixed(2) + ' GB';
    },
    formatDate: function (dateStr) {
      if (!dateStr) return '';
      var d = new Date(dateStr.replace(' ', 'T'));
      if (isNaN(d.getTime())) return dateStr;
      var month = d.toLocaleString('en', { month: 'short' });
      var day = d.getDate();
      var hours = String(d.getHours()).padStart(2, '0');
      var mins = String(d.getMinutes()).padStart(2, '0');
      return month + ' ' + day + ', ' + hours + ':' + mins;
    },
    formatDuration: function (startStr, endStr) {
      var start = new Date(startStr.replace(' ', 'T'));
      var end = new Date(endStr.replace(' ', 'T'));
      var diffSec = Math.round((end - start) / 1000);
      if (diffSec < 0) return '\u2014';
      if (diffSec < 60) return diffSec + 's';
      var mins = Math.floor(diffSec / 60);
      var secs = diffSec % 60;
      if (mins < 60) return mins + 'm ' + secs + 's';
      var hrs = Math.floor(mins / 60);
      mins = mins % 60;
      return hrs + 'h ' + mins + 'm';
    },
    truncateArtifact: function (name) {
      if (!name) return '';
      if (name.length <= 30) return name;
      return name.substring(0, 14) + '...' + name.substring(name.length - 13);
    },
    isExpiringSoon: function (expiresAt) {
      if (!expiresAt) return false;
      var exp = new Date(expiresAt.replace(' ', 'T'));
      var now = new Date();
      var hoursLeft = (exp - now) / (1000 * 60 * 60);
      return hoursLeft >= 0 && hoursLeft < 24;
    },
    isExpired: function (expiresAt) {
      if (!expiresAt) return false;
      var exp = new Date(expiresAt.replace(' ', 'T'));
      return exp < new Date();
    },
  },
};
</script>

<style scoped>
.backups-panel {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  padding: var(--spacing-lg, 24px);
}

.backups-panel--embedded {
  background: none;
  border-radius: 0;
  box-shadow: none;
  padding: 0;
}

/* ─── Empty State ─── */
.backups-panel__empty {
  text-align: center;
  padding: var(--spacing-lg, 24px) 0;
}

.backups-panel__empty-text {
  font-size: 13px;
  color: var(--color-text-muted, #9ca3af);
  margin: 0;
}

/* ─── Filters ─── */
.backups-panel__filters {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.backups-panel__filter-group {
  display: flex;
  gap: 5px;
  padding-right: 12px;
  border-right: 1px solid #e5e7eb;
}

.backups-panel__filter-group:last-child {
  border-right: none;
  padding-right: 0;
}

.backups-panel__filter-badge {
  font-size: 11px;
  font-weight: 500;
  padding: 3px 10px;
  border-radius: 12px;
  cursor: pointer;
  background: #f0f1f5;
  color: #6b7280;
  transition: all 0.15s ease;
  user-select: none;
  border: 1.5px solid transparent;
}

.backups-panel__filter-badge:hover {
  background: #e5e7eb;
}

.backups-panel__filter-badge--active {
  font-weight: 600;
  border-color: currentColor;
}

/* Status-specific filter colors */
.backups-panel__filter-badge--completed {
  color: #166534;
}
.backups-panel__filter-badge--completed.backups-panel__filter-badge--active {
  background: #d4edda;
}

.backups-panel__filter-badge--expired {
  color: #6b7280;
}
.backups-panel__filter-badge--expired.backups-panel__filter-badge--active {
  background: #e5e7eb;
}

.backups-panel__filter-badge--running {
  color: #1e4a8a;
}
.backups-panel__filter-badge--running.backups-panel__filter-badge--active {
  background: #e8f0fe;
}

.backups-panel__filter-badge--failed {
  color: #b91c1c;
}
.backups-panel__filter-badge--failed.backups-panel__filter-badge--active {
  background: #fde8e8;
}

/* Type-specific filter colors */
.backups-panel__filter-badge--full {
  color: #1e4a8a;
}
.backups-panel__filter-badge--full.backups-panel__filter-badge--active {
  background: #e8f0fe;
}

.backups-panel__filter-badge--incremental {
  color: #92400e;
}
.backups-panel__filter-badge--incremental.backups-panel__filter-badge--active {
  background: #fef3cd;
}

/* Trigger-specific filter colors */
.backups-panel__filter-badge--scheduled {
  color: #475569;
}
.backups-panel__filter-badge--scheduled.backups-panel__filter-badge--active {
  background: #e2e8f0;
}

.backups-panel__filter-badge--manual {
  color: #6b21a8;
}
.backups-panel__filter-badge--manual.backups-panel__filter-badge--active {
  background: #f3e8ff;
}

/* ─── Table ─── */
.backups-panel__table-wrap {
  overflow-x: auto;
}

.backups-panel__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.backups-panel__table th {
  font-size: 10px;
  font-weight: 600;
  color: var(--color-text-muted, #9ca3af);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0 10px 10px;
  text-align: left;
  white-space: nowrap;
  border-bottom: 1px solid #eef1f5;
}

.backups-panel__row {
  transition: background 0.12s;
}

.backups-panel__row:hover {
  background: #fafbfd;
}

.backups-panel__row td {
  padding: 10px;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
  white-space: nowrap;
}

.backups-panel__row:last-child td {
  border-bottom: none;
}

/* ─── Status Badge ─── */
.backups-panel__badge {
  display: inline-block;
  font-size: 10px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 8px;
  text-transform: capitalize;
}

.backups-panel__badge--completed {
  background: var(--color-badge-success-bg, #d4edda);
  color: var(--color-badge-success-text, #166534);
}

.backups-panel__badge--failed,
.backups-panel__badge--error {
  background: var(--color-badge-danger-bg, #fde8e8);
  color: var(--color-badge-danger-text, #b91c1c);
}

.backups-panel__badge--running,
.backups-panel__badge--pending,
.backups-panel__badge--queued {
  background: #e8f0fe;
  color: #1e4a8a;
}

.backups-panel__badge--expired {
  background: #f0f1f5;
  color: var(--color-text-muted, #9ca3af);
}

/* ─── Type Pill ─── */
.backups-panel__type {
  display: inline-block;
  font-size: 10px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 8px;
  text-transform: capitalize;
}

.backups-panel__type--full {
  background: #e8f0fe;
  color: #1e4a8a;
}

.backups-panel__type--incremental {
  background: #fef3cd;
  color: #92400e;
}

/* ─── Trigger ─── */
.backups-panel__trigger {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
  text-transform: capitalize;
}

.backups-panel__trigger svg {
  opacity: 0.6;
}

/* ─── Artifact ─── */
.backups-panel__artifact {
  font-size: 11px;
  font-family: 'SF Mono', Monaco, 'Cascadia Code', Consolas, monospace;
  color: var(--color-text-secondary, #6b7280);
}

/* ─── Size ─── */
.backups-panel__size {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-primary, #1a1a2e);
}

/* ─── Dates ─── */
.backups-panel__date {
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
}

.backups-panel__date--expiring {
  color: var(--color-badge-warning-text, #92400e);
  font-weight: 600;
}

.backups-panel__date--expired {
  color: var(--color-text-muted, #9ca3af);
  text-decoration: line-through;
}

/* ─── Duration ─── */
.backups-panel__duration {
  font-size: 12px;
  color: var(--color-text-secondary, #6b7280);
}

/* ─── Muted placeholder ─── */
.backups-panel__muted {
  color: var(--color-text-muted, #9ca3af);
  font-size: 12px;
}

/* ─── Pagination ─── */
.backups-panel__pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-top: 12px;
}

.backups-panel__page-btn {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  font-size: 16px;
  font-weight: 600;
  color: #4a90d9;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
}

.backups-panel__page-btn:hover:not(:disabled) {
  background: #e8f0fe;
  border-color: #4a90d9;
}

.backups-panel__page-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.backups-panel__page-info {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
}

.backups-panel__showing-hint {
  text-align: center;
  font-size: 11px;
  color: var(--color-text-muted, #9ca3af);
  margin-top: 6px;
}
</style>
