<template>
  <section class="system-health">
    <header class="system-health__header">
      <h3 class="system-health__title">System health</h3>
      <button
        class="system-health__refresh"
        :disabled="loading"
        @click="fetch"
      >
        <span
          class="system-health__refresh-icon"
          :class="{ 'system-health__refresh-icon--spinning': loading }"
        >↻</span>
        {{ loading ? "Refreshing…" : "Refresh" }}
      </button>
    </header>

    <div v-if="error && !snapshot" class="system-health__error">
      {{ error }}
      <button class="system-health__retry" @click="fetch">Retry</button>
    </div>

    <div v-else class="system-health__grid">
      <div
        v-for="card in cards"
        :key="card.key"
        class="system-health__card"
      >
        <div class="system-health__card-title">{{ card.title }}</div>
        <div class="system-health__card-value">{{ card.valueLine }}</div>
        <div
          v-if="card.percent !== null"
          class="system-health__bar"
        >
          <div
            class="system-health__bar-fill"
            :class="'system-health__bar-fill--' + toneFor(card.percent)"
            :style="{ width: clamp(card.percent) + '%' }"
          ></div>
          <span class="system-health__bar-percent">{{ card.percent }}%</span>
        </div>
        <div v-else class="system-health__bar system-health__bar--empty"></div>
        <div v-if="card.subtitle" class="system-health__card-sub">
          {{ card.subtitle }}
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";

const GIB = 1024 ** 3;
const MIB = 1024 ** 2;

function formatBytes(n) {
  if (n === null || n === undefined || !Number.isFinite(n)) return "—";
  if (n >= GIB) return (n / GIB).toFixed(1) + " GB";
  if (n >= MIB) return (n / MIB).toFixed(0) + " MB";
  return (n / 1024).toFixed(0) + " KB";
}

function formatDuration(seconds) {
  if (!Number.isFinite(seconds) || seconds <= 0) return "—";
  const days = Math.floor(seconds / 86400);
  const hours = Math.floor((seconds % 86400) / 3600);
  const mins = Math.floor((seconds % 3600) / 60);
  if (days > 0) return days + "d " + hours + "h " + mins + "m";
  if (hours > 0) return hours + "h " + mins + "m";
  return mins + "m";
}

export default {
  name: "SystemHealthPanel",
  data() {
    return {
      snapshot: null,
      loading: false,
      error: null,
    };
  },
  computed: {
    cards() {
      return [
        this.cpuCard(),
        this.memoryCard(),
        this.diskCard(),
        this.uptimeCard(),
      ];
    },
  },
  mounted() {
    this.fetch();
  },
  methods: {
    toneFor(percent) {
      if (percent === null || percent === undefined) return "neutral";
      if (percent >= 90) return "danger";
      if (percent >= 70) return "warn";
      return "ok";
    },
    clamp(percent) {
      if (!Number.isFinite(percent)) return 0;
      return Math.max(0, Math.min(100, percent));
    },
    cpuCard() {
      const c = this.snapshot && this.snapshot.cpu;
      if (!c) {
        return { key: "cpu", title: "CPU load", valueLine: "—", percent: null, subtitle: "" };
      }
      const cores = c.cores;
      const valueLine = cores
        ? c.load1.toFixed(2) + " / " + cores
        : c.load1.toFixed(2);
      const percent = cores
        ? Math.min(100, Math.round((c.load1 / cores) * 100))
        : null;
      const subtitle =
        "1m " + c.load1.toFixed(2) +
        " · 5m " + c.load5.toFixed(2) +
        " · 15m " + c.load15.toFixed(2);
      return { key: "cpu", title: "CPU load", valueLine, percent, subtitle };
    },
    memoryCard() {
      const m = this.snapshot && this.snapshot.memory;
      const s = this.snapshot && this.snapshot.swap;
      if (!m) {
        return { key: "memory", title: "Memory", valueLine: "—", percent: null, subtitle: "" };
      }
      return {
        key: "memory",
        title: "Memory",
        valueLine: formatBytes(m.usedBytes) + " / " + formatBytes(m.totalBytes),
        percent: m.percent,
        subtitle: s
          ? "swap " + formatBytes(s.usedBytes) + " / " + formatBytes(s.totalBytes)
          : "",
      };
    },
    diskCard() {
      const d = this.snapshot && this.snapshot.disk;
      if (!d) {
        return { key: "disk", title: "Disk", valueLine: "—", percent: null, subtitle: "" };
      }
      if (d.totalBytes === null) {
        return {
          key: "disk",
          title: "Disk",
          valueLine: "—",
          percent: null,
          subtitle: d.path || "",
        };
      }
      return {
        key: "disk",
        title: "Disk",
        valueLine: formatBytes(d.usedBytes) + " / " + formatBytes(d.totalBytes),
        percent: d.percent,
        subtitle: "data partition",
      };
    },
    uptimeCard() {
      const u = this.snapshot && this.snapshot.uptime;
      const v = this.snapshot && this.snapshot.nextcloudVersion;
      const subtitle = v ? "NC " + v : "since boot";
      if (!u) {
        return { key: "uptime", title: "Uptime", valueLine: "—", percent: null, subtitle };
      }
      return {
        key: "uptime",
        title: "Uptime",
        valueLine: formatDuration(u.seconds),
        percent: null,
        subtitle,
      };
    },
    async fetch() {
      this.loading = true;
      this.error = null;
      try {
        const res = await axios.get(
          generateUrl("/apps/superadminpage/api/super/system")
        );
        this.snapshot = res.data || null;
      } catch (e) {
        this.error = "Couldn't load system metrics.";
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.system-health {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  padding: 20px;
}

.system-health__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.system-health__title {
  font-size: 15px;
  font-weight: 600;
  color: #1d2939;
  margin: 0;
}

.system-health__refresh {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: transparent;
  border: 1px solid #d0d5dd;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 500;
  color: #344054;
  border-radius: 6px;
  cursor: pointer;
}

.system-health__refresh:hover:not(:disabled) {
  background: #f9fafb;
}

.system-health__refresh:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.system-health__refresh-icon {
  display: inline-block;
  font-size: 14px;
  line-height: 1;
}

.system-health__refresh-icon--spinning {
  animation: system-health-spin 0.9s linear infinite;
}

@keyframes system-health-spin {
  to { transform: rotate(360deg); }
}

.system-health__error {
  font-size: 13px;
  color: #b42318;
  background: #fef3f2;
  border: 1px solid #fecdca;
  border-radius: 8px;
  padding: 10px 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.system-health__retry {
  margin-left: auto;
  background: transparent;
  border: 1px solid #fecdca;
  color: #b42318;
  font-size: 12px;
  font-weight: 500;
  padding: 3px 10px;
  border-radius: 6px;
  cursor: pointer;
}

.system-health__retry:hover {
  background: #fee4e2;
}

.system-health__grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.system-health__card {
  border: 1px solid #eaecf0;
  border-radius: 10px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.system-health__card-title {
  font-size: 11px;
  font-weight: 600;
  color: #667085;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.system-health__card-value {
  font-size: 18px;
  font-weight: 600;
  color: #1d2939;
  line-height: 1.2;
  word-break: break-word;
}

.system-health__bar {
  position: relative;
  height: 6px;
  border-radius: 999px;
  background: #f2f4f7;
  overflow: visible;
}

.system-health__bar--empty {
  background: transparent;
  height: 6px;
}

.system-health__bar-fill {
  position: absolute;
  inset: 0 auto 0 0;
  height: 100%;
  border-radius: 999px;
  transition: width 0.2s ease, background-color 0.2s ease;
}

.system-health__bar-fill--ok      { background: #10b981; }
.system-health__bar-fill--warn    { background: #f59e0b; }
.system-health__bar-fill--danger  { background: #ef4444; }
.system-health__bar-fill--neutral { background: #98a2b3; }

.system-health__bar-percent {
  position: absolute;
  right: 0;
  top: 10px;
  font-size: 11px;
  font-weight: 600;
  color: #475467;
}

.system-health__card-sub {
  font-size: 11px;
  color: #667085;
  margin-top: 14px;
  word-break: break-word;
}

@media (max-width: 1200px) {
  .system-health__grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .system-health__grid {
    grid-template-columns: 1fr;
  }
}
</style>
