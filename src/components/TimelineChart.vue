<template>
  <div class="timeline-chart">
    <div v-if="items.length === 0" class="timeline-chart__empty">
      No timeline data available
    </div>
    <div v-else class="timeline-chart__container">
      <!-- Y-axis labels -->
      <div class="timeline-chart__labels">
        <div
          v-for="(item, i) in items"
          :key="'lbl-' + i"
          class="timeline-chart__label"
        >
          <span
            class="timeline-chart__dot"
            :style="{ backgroundColor: item.color || '#94a3b8' }"
          ></span>
          <span class="timeline-chart__label-text" :title="item.label">{{
            item.label
          }}</span>
          <span
            v-if="item.systemKey"
            class="timeline-chart__type-badge timeline-chart__type-badge--system"
            >SYS</span
          >
        </div>
      </div>
      <!-- Bars area -->
      <div class="timeline-chart__bars">
        <!-- Month header -->
        <div class="timeline-chart__months">
          <div
            v-for="(m, i) in months"
            :key="'m-' + i"
            class="timeline-chart__month"
            :style="{ width: m.widthPct + '%', left: m.leftPct + '%' }"
          >
            {{ m.label }}
          </div>
        </div>
        <!-- Grid lines -->
        <div class="timeline-chart__grid">
          <div
            v-for="(m, i) in months"
            :key="'g-' + i"
            class="timeline-chart__grid-line"
            :style="{ left: m.leftPct + '%' }"
          ></div>
        </div>
        <!-- Today marker -->
        <div
          v-if="todayPct >= 0 && todayPct <= 100"
          class="timeline-chart__today"
          :style="{ left: todayPct + '%' }"
          title="Today"
        ></div>
        <!-- Bar rows -->
        <div
          v-for="(item, i) in items"
          :key="'bar-' + i"
          class="timeline-chart__bar-row"
        >
          <div
            class="timeline-chart__bar"
            :style="{
              left: item.leftPct + '%',
              width: item.widthPct + '%',
              backgroundColor: item.color || '#94a3b8',
            }"
            :title="
              item.label +
              ': ' +
              formatDateShort(item.startDate) +
              ' → ' +
              formatDateShort(item.endDate) +
              ' (' +
              item.duration +
              ')'
            "
          >
            <span v-if="item.widthPct > 12" class="timeline-chart__bar-text">
              {{ item.duration }}
            </span>
          </div>
          <!-- Weeks elapsed indicator (from start date to today) -->
          <span
            v-if="item.weeksElapsed !== null"
            class="timeline-chart__weeks-badge"
            :style="{ left: todayBadgeLeft(item) + '%' }"
            :title="
              item.weeksElapsed +
              ' week' +
              (item.weeksElapsed !== 1 ? 's' : '') +
              ' since ' +
              formatDateShort(item.startDate)
            "
          >
            {{ item.weeksElapsed }}w
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "TimelineChart",
  props: {
    timeline: {
      type: Array,
      default: function () {
        return [];
      },
    },
  },
  computed: {
    parsedItems: function () {
      var self = this;
      return this.timeline
        .map(function (t) {
          var s = t.startDate ? new Date(t.startDate) : null;
          var e = t.endDate ? new Date(t.endDate) : null;
          if (!s || !e || isNaN(s.getTime()) || isNaN(e.getTime())) return null;
          return {
            label: t.label,
            systemKey: t.systemKey,
            color: t.color || "#94a3b8",
            startDate: s,
            endDate: e,
            duration: self.calcDuration(s, e),
          };
        })
        .filter(function (x) {
          return x !== null;
        });
    },
    rangeStart: function () {
      if (this.parsedItems.length === 0) return new Date();
      var min = this.parsedItems[0].startDate;
      for (var i = 1; i < this.parsedItems.length; i++) {
        if (this.parsedItems[i].startDate < min) {
          min = this.parsedItems[i].startDate;
        }
      }
      // Pad to first of that month
      return new Date(min.getFullYear(), min.getMonth(), 1);
    },
    rangeEnd: function () {
      if (this.parsedItems.length === 0) return new Date();
      var max = this.parsedItems[0].endDate;
      for (var i = 1; i < this.parsedItems.length; i++) {
        if (this.parsedItems[i].endDate > max) {
          max = this.parsedItems[i].endDate;
        }
      }
      // Pad to end of that month
      return new Date(max.getFullYear(), max.getMonth() + 1, 0);
    },
    totalDays: function () {
      var diff = this.rangeEnd.getTime() - this.rangeStart.getTime();
      return Math.max(1, Math.ceil(diff / (1000 * 60 * 60 * 24)));
    },
    items: function () {
      var self = this;
      var now = new Date();
      return this.parsedItems.map(function (item) {
        var startOff =
          (item.startDate.getTime() - self.rangeStart.getTime()) /
          (1000 * 60 * 60 * 24);
        var dur =
          (item.endDate.getTime() - item.startDate.getTime()) /
          (1000 * 60 * 60 * 24);
        // Calculate weeks elapsed from start date to today
        var daysSinceStart = Math.floor(
          (now.getTime() - item.startDate.getTime()) / (1000 * 60 * 60 * 24),
        );
        var weeksElapsed =
          daysSinceStart > 0 ? Math.round(daysSinceStart / 7) : null;
        return {
          label: item.label,
          systemKey: item.systemKey,
          color: item.color,
          startDate: item.startDate,
          endDate: item.endDate,
          duration: item.duration,
          leftPct: (startOff / self.totalDays) * 100,
          widthPct: Math.max(0.5, (dur / self.totalDays) * 100),
          weeksElapsed: weeksElapsed,
        };
      });
    },
    months: function () {
      var result = [];
      var d = new Date(this.rangeStart);
      while (d <= this.rangeEnd) {
        var monthStart = new Date(d.getFullYear(), d.getMonth(), 1);
        var monthEnd = new Date(d.getFullYear(), d.getMonth() + 1, 0);
        if (monthEnd > this.rangeEnd) monthEnd = this.rangeEnd;

        var startOff =
          (monthStart.getTime() - this.rangeStart.getTime()) /
          (1000 * 60 * 60 * 24);
        var dur =
          (monthEnd.getTime() - monthStart.getTime()) / (1000 * 60 * 60 * 24);

        var monthNames = [
          "Jan",
          "Feb",
          "Mar",
          "Apr",
          "May",
          "Jun",
          "Jul",
          "Aug",
          "Sep",
          "Oct",
          "Nov",
          "Dec",
        ];

        result.push({
          label:
            monthNames[monthStart.getMonth()] + " " + monthStart.getFullYear(),
          leftPct: (startOff / this.totalDays) * 100,
          widthPct: (dur / this.totalDays) * 100,
        });

        d = new Date(d.getFullYear(), d.getMonth() + 1, 1);
      }
      return result;
    },
    todayPct: function () {
      var now = new Date();
      var off =
        (now.getTime() - this.rangeStart.getTime()) / (1000 * 60 * 60 * 24);
      return (off / this.totalDays) * 100;
    },
  },
  methods: {
    calcDuration: function (s, e) {
      var days = Math.round((e - s) / (1000 * 60 * 60 * 24));
      if (days < 1) return "1d";
      if (days < 7) return days + "d";
      var weeks = Math.round(days / 7);
      if (weeks < 5) return weeks + "w";
      var months = Math.round(days / 30);
      return months + "mo";
    },
    formatDateShort: function (d) {
      if (!d) return "—";
      var monthNames = [
        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "May",
        "Jun",
        "Jul",
        "Aug",
        "Sep",
        "Oct",
        "Nov",
        "Dec",
      ];
      return d.getDate() + " " + monthNames[d.getMonth()];
    },
    todayBadgeLeft: function (item) {
      // Position the weeks badge at the today marker, clamped to bar bounds
      var pct = this.todayPct;
      var barEnd = item.leftPct + item.widthPct;
      if (pct < item.leftPct) return item.leftPct;
      if (pct > barEnd) return barEnd;
      return pct;
    },
  },
};
</script>

<style scoped>
.timeline-chart__empty {
  text-align: center;
  font-size: 13px;
  color: var(--color-text-muted, #9ca3af);
  padding: 24px;
}

.timeline-chart__container {
  display: flex;
  gap: 0;
  overflow-x: auto;
}

.timeline-chart__labels {
  flex-shrink: 0;
  width: 160px;
  padding-top: 28px;
}

.timeline-chart__label {
  height: 36px;
  display: flex;
  align-items: center;
  gap: 6px;
  padding-right: 12px;
}

.timeline-chart__dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.timeline-chart__label-text {
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text-primary, #1a1a2e);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
}

.timeline-chart__type-badge {
  font-size: 9px;
  font-weight: 700;
  padding: 1px 4px;
  border-radius: 3px;
  flex-shrink: 0;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.timeline-chart__type-badge--system {
  background: #e0f2fe;
  color: #0369a1;
}

.timeline-chart__bars {
  flex: 1;
  position: relative;
  min-width: 300px;
  border-left: 1px solid var(--color-border, #e5e7eb);
}

.timeline-chart__months {
  position: relative;
  height: 28px;
  border-bottom: 1px solid var(--color-border, #e5e7eb);
}

.timeline-chart__month {
  position: absolute;
  top: 0;
  height: 28px;
  display: flex;
  align-items: center;
  padding-left: 8px;
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-muted, #9ca3af);
  text-transform: uppercase;
  letter-spacing: 0.3px;
  white-space: nowrap;
  overflow: hidden;
  box-sizing: border-box;
  border-right: 1px solid #f3f4f6;
}

.timeline-chart__grid {
  position: absolute;
  top: 28px;
  bottom: 0;
  left: 0;
  right: 0;
  pointer-events: none;
}

.timeline-chart__grid-line {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 1px;
  background: #f3f4f6;
}

.timeline-chart__today {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #ef4444;
  z-index: 2;
  opacity: 0.6;
}

.timeline-chart__today::before {
  content: "Today";
  position: absolute;
  top: 4px;
  left: 4px;
  font-size: 9px;
  font-weight: 700;
  color: #ef4444;
  white-space: nowrap;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.timeline-chart__bar-row {
  height: 36px;
  position: relative;
}

.timeline-chart__bar-row:nth-child(even) {
  background: #fafbfd;
}

.timeline-chart__bar {
  position: absolute;
  top: 6px;
  height: 24px;
  border-radius: 6px;
  opacity: 0.85;
  cursor: default;
  transition: opacity 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 4px;
}

.timeline-chart__bar:hover {
  opacity: 1;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.timeline-chart__bar-text {
  font-size: 10px;
  font-weight: 700;
  color: #fff;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
  white-space: nowrap;
}

.timeline-chart__weeks-badge {
  position: absolute;
  top: 0;
  transform: translateX(-50%);
  font-size: 9px;
  font-weight: 700;
  color: #6366f1;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  border-radius: 4px;
  padding: 0 4px;
  line-height: 16px;
  white-space: nowrap;
  z-index: 3;
  pointer-events: none;
}
</style>
