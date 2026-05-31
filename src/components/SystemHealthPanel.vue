<template>
  <section class="system-health">
    <header class="system-health__header">
      <h3 class="system-health__title">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
        </svg>
        System Health
      </h3>
      <div class="system-health__header-actions">
        <span
          v-if="snapshot"
          class="system-health__live"
          :class="{ 'system-health__live--paused': pollPaused }"
          :title="pollPaused ? 'Live updates paused (tab hidden)' : 'Live, updating every ' + (pollMs / 1000) + 's'"
        >
          <span class="system-health__live-dot"></span>
          {{ pollPaused ? "Paused" : "Live" }}
        </span>
        <button
          class="system-health__refresh"
          :disabled="loading"
          @click="manualRefresh"
        >
          <span
            class="system-health__refresh-icon"
            :class="{ 'system-health__refresh-icon--spinning': loading }"
          >↻</span>
          {{ loading ? "Refreshing…" : "Refresh" }}
        </button>
      </div>
    </header>

    <div v-if="error && !snapshot" class="system-health__error">
      {{ error }}
      <button class="system-health__retry" @click="fetch">Retry</button>
    </div>

    <template v-else>
      <div class="system-health__subhead system-health__subhead--first">Resources</div>
      <div class="system-health__grid">
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

      <template v-if="networkVisible">
        <div class="system-health__subhead">Network</div>
        <div class="system-health__grid">
          <div
            v-for="card in networkCards"
            :key="card.key"
            class="system-health__card"
          >
            <div class="system-health__card-title">{{ card.title }}</div>
            <div class="system-health__card-value">{{ card.valueLine }}</div>
            <div class="system-health__bar system-health__bar--empty"></div>
            <div v-if="card.subtitle" class="system-health__card-sub">
              {{ card.subtitle }}
            </div>
          </div>
        </div>
      </template>

      <template v-if="usersVisible">
        <div class="system-health__subhead">Active Users</div>
        <div class="system-health__grid">
          <div
            v-for="card in userCards"
            :key="card.key"
            class="system-health__card"
          >
            <div class="system-health__card-title">{{ card.title }}</div>
            <div class="system-health__card-value">{{ card.valueLine }}</div>
            <div class="system-health__bar system-health__bar--empty"></div>
            <div v-if="card.subtitle" class="system-health__card-sub">
              {{ card.subtitle }}
            </div>
          </div>
        </div>
      </template>

      <template v-if="servicesVisible || hpbHostVisible">
        <div class="system-health__subhead">Services</div>
        <div class="system-health__grid">
          <div
            v-for="card in serviceCards"
            :key="card.key"
            class="system-health__card"
          >
            <div class="system-health__card-title">{{ card.name }}</div>
            <div class="system-health__card-value">
              <span
                class="system-health__status-dot"
                :class="'system-health__status-dot--' + card.tone"
              ></span>
              {{ card.statusLabel }}
            </div>
            <div class="system-health__bar system-health__bar--empty"></div>
            <div v-if="card.subtitle" class="system-health__card-sub">
              {{ card.subtitle }}
            </div>
          </div>
          <div
            v-for="card in hpbCards"
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
      </template>
    </template>
  </section>
</template>

<script>
import axios from "@nextcloud/axios";
import { generateUrl } from "@nextcloud/router";

const GIB = 1024 ** 3;
const MIB = 1024 ** 2;
const POLL_MS = 3000;

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

function formatRate(bytesPerSec) {
  if (bytesPerSec === null || bytesPerSec === undefined || !Number.isFinite(bytesPerSec)) {
    return "—";
  }
  if (bytesPerSec >= GIB) return (bytesPerSec / GIB).toFixed(2) + " GB/s";
  if (bytesPerSec >= MIB) return (bytesPerSec / MIB).toFixed(2) + " MB/s";
  if (bytesPerSec >= 1024) return (bytesPerSec / 1024).toFixed(1) + " KB/s";
  return Math.round(bytesPerSec) + " B/s";
}

function formatCount(n) {
  if (n === null || n === undefined || !Number.isFinite(n)) return "—";
  return new Intl.NumberFormat().format(n);
}

export default {
  name: "SystemHealthPanel",
  data() {
    return {
      snapshot: null,
      loading: false,
      error: null,
      pollPaused: false,
      pollMs: POLL_MS,
      latched: { network: false, users: false, services: false, hpbHost: false },
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
    networkVisible() {
      if (this.latched.network) return true;
      return !!(this.snapshot && this.snapshot.network);
    },
    usersVisible() {
      if (this.latched.users) return true;
      return !!(this.snapshot && this.snapshot.users);
    },
    networkCards() {
      const n = this.snapshot && this.snapshot.network;
      return [
        {
          key: "host",
          title: "Host",
          valueLine: (n && n.hostname) ? n.hostname : "—",
          subtitle: "Hostname",
        },
        {
          key: "in",
          title: "Inbound",
          valueLine: formatRate(n && n.rxBytesPerSec),
          subtitle: "Receive rate",
        },
        {
          key: "out",
          title: "Outbound",
          valueLine: formatRate(n && n.txBytesPerSec),
          subtitle: "Transmit rate",
        },
        {
          key: "iface",
          title: "Interface",
          valueLine: (n && n.interface) ? n.interface : "—",
          subtitle: n
            ? "↓ " + formatBytes(n.rxBytesTotal) + " · ↑ " + formatBytes(n.txBytesTotal)
            : "",
        },
      ];
    },
    userCards() {
      const u = this.snapshot && this.snapshot.users;
      return [
        {
          key: "u5",
          title: "Last 5 min",
          valueLine: formatCount(u && u.last5min),
          subtitle: "Active sessions",
        },
        {
          key: "u1h",
          title: "Last hour",
          valueLine: formatCount(u && u.last1hour),
          subtitle: "Active sessions",
        },
        {
          key: "u1d",
          title: "Last 24 hours",
          valueLine: formatCount(u && u.last24hour),
          subtitle: "Active sessions",
        },
        {
          key: "uall",
          title: "Total",
          valueLine: formatCount(u && u.total),
          subtitle: "Accounts",
        },
      ];
    },
    servicesVisible() {
      if (this.latched.services) return true;
      const list = this.snapshot && this.snapshot.services;
      return Array.isArray(list) && list.length > 0;
    },
    serviceCards() {
      const list = (this.snapshot && this.snapshot.services) || [];
      return list.map((s) => {
        const statusLabel =
          s.status === "ok" ? "Healthy"
            : s.status === "degraded" ? "Degraded"
              : "Down";
        const host = (s.url || "").replace(/^https?:\/\//, "");
        const subtitle =
          (s.status !== "down" && s.latencyMs != null)
            ? s.latencyMs + " ms · " + host
            : host;
        return { key: s.key, name: s.name, statusLabel, tone: s.status, subtitle };
      });
    },
    hpbHostVisible() {
      if (this.latched.hpbHost) return true;
      return !!(this.snapshot && this.snapshot.hpbHost);
    },
    hpbCards() {
      const h = this.snapshot && this.snapshot.hpbHost;
      const unreachable = !h || h.status === "down";

      const mem = h && h.memory;
      const memoryCard = mem
        ? {
            key: "hpb-mem",
            title: "HPB Memory",
            valueLine: formatBytes(mem.usedBytes) + " / " + formatBytes(mem.totalBytes),
            percent: mem.percent,
            subtitle: (h.network && h.network.hostname) ? h.network.hostname : "HPB host",
          }
        : {
            key: "hpb-mem",
            title: "HPB Memory",
            valueLine: "—",
            percent: null,
            subtitle: unreachable ? "Monitor unreachable" : "HPB host",
          };

      const disk = h && h.disk;
      const diskCard = disk
        ? {
            key: "hpb-disk",
            title: "HPB Disk",
            valueLine: formatBytes(disk.usedBytes) + " / " + formatBytes(disk.totalBytes),
            percent: disk.percent,
            subtitle: disk.path || "/",
          }
        : {
            key: "hpb-disk",
            title: "HPB Disk",
            valueLine: "—",
            percent: null,
            subtitle: unreachable ? "Monitor unreachable" : "/",
          };

      const net = h && h.network;
      const networkCard = net
        ? {
            key: "hpb-net",
            title: "HPB Network",
            valueLine: "↓ " + formatRate(net.rxBytesPerSec) + " · ↑ " + formatRate(net.txBytesPerSec),
            percent: null,
            subtitle: (net.interface || "—")
              + " · ↓ " + formatBytes(net.rxBytesTotal)
              + " · ↑ " + formatBytes(net.txBytesTotal),
          }
        : {
            key: "hpb-net",
            title: "HPB Network",
            valueLine: "—",
            percent: null,
            subtitle: unreachable ? "Monitor unreachable" : "—",
          };

      return [memoryCard, diskCard, networkCard];
    },
  },
  mounted() {
    this.fetch();
    this.startPolling();
    this._onVisibility = () => {
      if (typeof document === "undefined") return;
      if (document.hidden) {
        this.pollPaused = true;
        this.stopPolling();
      } else {
        this.pollPaused = false;
        this.fetch({ silent: true });
        this.startPolling();
      }
    };
    if (typeof document !== "undefined") {
      document.addEventListener("visibilitychange", this._onVisibility);
    }
  },
  beforeDestroy() {
    this.stopPolling();
    if (typeof document !== "undefined" && this._onVisibility) {
      document.removeEventListener("visibilitychange", this._onVisibility);
    }
  },
  methods: {
    startPolling() {
      this.stopPolling();
      this._pollTimer = setInterval(() => {
        this.fetch({ silent: true });
      }, POLL_MS);
    },
    stopPolling() {
      if (this._pollTimer) {
        clearInterval(this._pollTimer);
        this._pollTimer = null;
      }
    },
    manualRefresh() {
      this.fetch();
    },
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
    async fetch({ silent = false } = {}) {
      if (this._fetching) return;
      this._fetching = true;
      if (!silent) {
        this.loading = true;
        this.error = null;
      }
      try {
        const res = await axios.get(
          generateUrl("/apps/superadminpage/api/super/system")
        );
        this.snapshot = res.data || null;
        if (this.snapshot && this.snapshot.network && !this.latched.network) {
          this.latched.network = true;
        }
        if (this.snapshot && this.snapshot.users && !this.latched.users) {
          this.latched.users = true;
        }
        if (this.snapshot && Array.isArray(this.snapshot.services)
            && this.snapshot.services.length > 0 && !this.latched.services) {
          this.latched.services = true;
        }
        if (this.snapshot && this.snapshot.hpbHost && !this.latched.hpbHost) {
          this.latched.hpbHost = true;
        }
        if (silent && this.error) this.error = null;
      } catch (e) {
        // Surface errors only on foreground fetches; silent polls keep the
        // previous values and quietly try again on the next tick.
        if (!silent) this.error = "Couldn't load system metrics.";
      } finally {
        this._fetching = false;
        if (!silent) this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.system-health {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-card, 12px);
  box-shadow: var(--shadow-card, 0 1px 3px rgba(0, 0, 0, 0.08));
  padding: var(--spacing-lg, 24px);
}

.system-health__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--spacing-md, 16px);
}

.system-health__title {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-secondary, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.system-health__title svg {
  color: #4a90d9;
}

.system-health__header-actions {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}

.system-health__live {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 600;
  color: #067a56;
  background: #ecfdf5;
  border: 1px solid #a6f4c5;
  padding: 3px 8px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  cursor: default;
}

.system-health__live--paused {
  color: #667085;
  background: #f2f4f7;
  border-color: #eaecf0;
}

.system-health__live-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6);
  animation: system-health-pulse 1.6s ease-out infinite;
}

.system-health__live--paused .system-health__live-dot {
  background: #98a2b3;
  animation: none;
  box-shadow: none;
}

@keyframes system-health-pulse {
  0%   { box-shadow: 0 0 0 0   rgba(16, 185, 129, 0.55); }
  70%  { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
  100% { box-shadow: 0 0 0 0   rgba(16, 185, 129, 0); }
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

.system-health__subhead {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-muted, #9ca3af);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 20px 0 10px;
  padding-bottom: 6px;
  border-bottom: 1px solid #f2f4f7;
}

.system-health__subhead--first {
  margin-top: 0;
}

.system-health__status-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 6px;
  vertical-align: middle;
  background: #98a2b3;
}

.system-health__status-dot--ok       { background: #10b981; }
.system-health__status-dot--degraded { background: #f59e0b; }
.system-health__status-dot--down     { background: #ef4444; }

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
