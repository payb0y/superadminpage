<template>
  <div :class="['backups-panel', 'iz-panel', { 'iz-panel--flush': embedded }]">
    <!-- Empty state (no jobs at all) -->
    <div v-if="!jobs || jobs.length === 0" class="iz-empty backups-panel__empty">
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
            class="iz-chip"
            :class="[
              chipTone(s.value),
              { 'iz-chip--active': statusFilter === s.value },
            ]"
            @click="statusFilter = s.value"
          >{{ s.label }}</span>
        </div>

        <!-- Type filter -->
        <div class="backups-panel__filter-group">
          <span
            v-for="t in typeOptions"
            :key="'tf-' + t.value"
            class="iz-chip"
            :class="[
              chipTone(t.value),
              { 'iz-chip--active': typeFilter === t.value },
            ]"
            @click="typeFilter = t.value"
          >{{ t.label }}</span>
        </div>

        <!-- Trigger filter -->
        <div class="backups-panel__filter-group">
          <span
            v-for="tr in triggerOptions"
            :key="'trf-' + tr.value"
            class="iz-chip"
            :class="[
              chipTone(tr.value),
              { 'iz-chip--active': triggerFilter === tr.value },
            ]"
            @click="triggerFilter = tr.value"
          >{{ tr.label }}</span>
        </div>
      </div>

      <!-- No results after filtering -->
      <div v-if="filteredJobs.length === 0" class="iz-empty backups-panel__empty">
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
              v-for="job in filteredJobs"
              :key="job.jobId"
              class="backups-panel__row"
            >
              <!-- Status badge -->
              <td>
                <span
                  class="iz-badge"
                  :class="badgeTone(job.status)"
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
                <span v-else-if="job.status === 'running'" class="iz-badge iz-badge--accent">running</span>
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
  },
  methods: {
    // Backup job status -> shared badge tone. Explicit map with a neutral
    // fallback so an unknown status can't emit a class that doesn't exist.
    badgeTone(status) {
      return (
        {
          completed: "iz-badge--success",
          failed: "iz-badge--danger",
          error: "iz-badge--danger",
          running: "iz-badge--accent",
          pending: "iz-badge--accent",
          queued: "iz-badge--accent",
          expired: "iz-badge--muted",
        }[status] || "iz-badge--muted"
      );
    },
    // Filter-chip tone. "" is the "All" option and stays neutral.
    chipTone(value) {
      return (
        {
          completed: "iz-chip--success",
          failed: "iz-chip--danger",
          expired: "iz-chip--muted",
          running: "iz-chip--accent",
          full: "iz-chip--accent",
          incremental: "iz-chip--warning",
          scheduled: "iz-chip--muted",
          manual: "iz-chip--cat-5",
        }[value] || "iz-chip--muted"
      );
    },
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


/* ─── Empty State ─── */
.backups-panel__empty {
  text-align: center;
  padding: var(--spacing-lg, 24px) 0;
}

.backups-panel__empty-text {
  font-size: var(--iz-fs-md);
  color: var(--color-text-muted, var(--color-text-muted));
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
  border-right: 1px solid var(--color-border);
}

.backups-panel__filter-group:last-child {
  border-right: none;
  padding-right: 0;
}




/* Status-specific filter colors */




/* Type-specific filter colors */


/* Trigger-specific filter colors */


/* ─── Table ─── */
.backups-panel__table-wrap {
  overflow-x: auto;
}

.backups-panel__table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--iz-fs-sm);
}

.backups-panel__table th {
  font-size: var(--iz-fs-micro);
  font-weight: 600;
  color: var(--color-text-muted, var(--color-text-muted));
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0 10px 10px;
  text-align: left;
  white-space: nowrap;
  border-bottom: 1px solid var(--bg-subtle);
}

.backups-panel__row {
  transition: background 0.12s;
}

.backups-panel__row:hover {
  background: var(--bg-subtle);
}

.backups-panel__row td {
  padding: 10px;
  border-bottom: 1px solid var(--bg-subtle);
  vertical-align: middle;
  white-space: nowrap;
}

.backups-panel__row:last-child td {
  border-bottom: none;
}

/* ─── Status Badge ─── */





/* ─── Type Pill ─── */
.backups-panel__type {
  display: inline-block;
  font-size: var(--iz-fs-micro);
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--radius-el);
  text-transform: capitalize;
}

.backups-panel__type--full {
  background: var(--accent-bg);
  color: var(--accent-strong);
}

.backups-panel__type--incremental {
  background: var(--color-warning-bg);
  color: var(--color-warning-text);
}

/* ─── Trigger ─── */
.backups-panel__trigger {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary, var(--color-text-secondary));
  text-transform: capitalize;
}

.backups-panel__trigger svg {
  opacity: 0.6;
}

/* ─── Artifact ─── */
.backups-panel__artifact {
  font-size: var(--iz-fs-xs);
  font-family: 'SF Mono', Monaco, 'Cascadia Code', Consolas, monospace;
  color: var(--color-text-secondary, var(--color-text-secondary));
}

/* ─── Size ─── */
.backups-panel__size {
  font-size: var(--iz-fs-sm);
  font-weight: 600;
  color: var(--color-text-primary, var(--color-text-primary));
}

/* ─── Dates ─── */
.backups-panel__date {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary, var(--color-text-secondary));
}

.backups-panel__date--expiring {
  color: var(--color-badge-warning-text, var(--color-warning-text));
  font-weight: 600;
}

.backups-panel__date--expired {
  color: var(--color-text-muted, var(--color-text-muted));
  text-decoration: line-through;
}

/* ─── Duration ─── */
.backups-panel__duration {
  font-size: var(--iz-fs-sm);
  color: var(--color-text-secondary, var(--color-text-secondary));
}

/* ─── Muted placeholder ─── */
.backups-panel__muted {
  color: var(--color-text-muted, var(--color-text-muted));
  font-size: var(--iz-fs-sm);
}

</style>
