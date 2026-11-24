/*
 * Project Timeline — the shape behind ProjectTimeline.vue.
 *
 * VENDORED: identical copies live in adminpage, superadminpage and
 * employee_dashboard (src/lib/projectTimeline.mjs, with
 * src/components/ProjectTimeline.vue). Change all three in the same change;
 * see USING-THE-THEME.md §10.
 *
 * Input is projectcreatoraio's timeline summary — GET
 * /apps/projectcreatoraio/api/v1/projects/{id}/timeline/summary, or adminpage's
 * public proxy of it — the same object the Planning tab draws from. Nothing
 * here recalculates the plan: the dates, the float and the status are the
 * server's. This file only words them and places them.
 *
 * - timelineOf: the Overview's "5. Timeline" panel (Initiation, Execution,
 *   Delivery). A port of projectcreatoraio src/new/overview.js, same words.
 * - ganttOf: the Planning tab's Gantt (projectcreatoraio
 *   src/components/ProjectTimeline/GanttChart.vue and SystemPlanningRow.vue),
 *   read-only and with the same logic: the same range and padding, the System
 *   Planning row, each phase with its Deck cards, the custom timeline items,
 *   the guide lines, today, a status per row, delays and ghost bars, and the
 *   card dependencies. Geometry is in days from the range start; the component
 *   multiplies by its px-per-day zoom, as the Planning tab does.
 */

import { t, n, locale, formatNumber } from "./l10n.mjs";

const DAY_MS = 86400000;

// Month and weekday names in the user's locale ("Mar"/"mrt", "Fri"/"vr"),
// from Intl rather than a fixed English list. UTC, as every day here is.
const monthName = (m) =>
  new Intl.DateTimeFormat(locale(), { month: "short", timeZone: "UTC" }).format(
    new Date(Date.UTC(2000, m, 1))
  );
const weekdayName = (day) =>
  new Intl.DateTimeFormat(locale(), { weekday: "short", timeZone: "UTC" }).format(
    new Date(day * DAY_MS)
  );

// A YYYY-MM-DD date (or the date part of an ISO string) as a UTC day number,
// so differences are whole days in any time zone. Null for anything else.
export function dayOf(value) {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value || ""));
  return m ? Date.UTC(+m[1], +m[2] - 1, +m[3]) / DAY_MS : null;
}

function partsOf(day) {
  const d = new Date(day * DAY_MS);
  return { y: d.getUTCFullYear(), m: d.getUTCMonth(), d: d.getUTCDate() };
}

export function dateLabel(value) {
  const day = dayOf(value);
  if (day === null) return t("Not set");
  const p = partsOf(day);
  return t("{day} {month} {year}", { day: p.d, month: monthName(p.m), year: p.y });
}

// The Planning tab's two date formats: dd/mm/yy in the Gantt (formatDate),
// dd/mm/yyyy on the System Planning markers.
const pad2 = (n) => String(n).padStart(2, "0");
export function shortDate(value) {
  const day = dayOf(value);
  if (day === null) return t("-");
  const p = partsOf(day);
  return t("{dd}/{mm}/{yy}", { dd: pad2(p.d), mm: pad2(p.m + 1), yy: String(p.y).slice(-2) });
}
export function fullDate(value) {
  const day = dayOf(value);
  if (day === null) return "";
  const p = partsOf(day);
  return t("{dd}/{mm}/{yyyy}", { dd: pad2(p.d), mm: pad2(p.m + 1), yyyy: p.y });
}

const weeksCount = (weeks) => n("%n week", "%n weeks", weeks);
const signedWeeks = (weeks) => `${weeks > 0 ? "+" : ""}${weeksCount(weeks)}`;

// ── Overview: Initiation / Execution / Delivery ───────────────────────────

// Keyed by projectcreatoraio's planningStatus code; the label is words only.
const PLANNING_STATUS = {
  on_track: ["success", () => t("On track")],
  attention_needed: ["warning", () => t("Attention needed")],
  behind_at_risk: ["danger", () => t("Behind / at risk")],
};

export function timelineOf(summary, now = Date.now()) {
  const s = summary || {};
  const process = s.processCompleted || {};
  const total = Math.max(0, Number(process.totalRequired) || 0);
  const done = Math.min(Math.max(0, Number(process.doneCount) || 0), total);
  const configured = total > 0 && !["error", "not_configured"].includes(process.status);
  const stepsDone = configured && process.status === "complete";
  const desired = s.desiredStartDate || null;
  const actual = s.actualStartDate || null;
  const handover = s.actualHandoverDate || null;
  const ended = s.actualEndDate || null;
  const floatDays = Number.isFinite(s.overallFloatDays) ? s.overallFloatDays : null;
  const floatWeeks = Number.isFinite(s.overallFloatWeeks) ? s.overallFloatWeeks : null;
  const [tone, labelOf] = PLANNING_STATUS[s.planningStatus] || PLANNING_STATUS.on_track;
  const label = labelOf();
  let text = t("No desired start yet, so there is no float to check.");
  if (desired && floatDays !== null) {
    if (floatDays < 0)
      text = t("Desired start is {days} before the earliest possible start · float {weeks}.", {
        days: n("%n day", "%n days", -floatDays),
        weeks: signedWeeks(floatWeeks ?? Math.round((floatDays / 7) * 10) / 10),
      });
    else if (floatDays === 0) text = t("Desired start is the earliest possible start · no float.");
    else
      text = t("Float of {weeks} before the desired start.", {
        weeks: signedWeeks(floatWeeks ?? Math.round((floatDays / 7) * 10) / 10),
      });
  }
  const pendingWeeks = Number.isFinite(s.coordinationPendingPeriod?.weeks)
    ? s.coordinationPendingPeriod.weeks
    : null;
  const missing = Array.isArray(process.missingTitles) ? process.missingTitles.length : 0;
  let stepsNote = "";
  if (!configured) stepsNote = t("No process steps configured");
  else if (stepsDone)
    stepsNote = process.date
      ? t("Completed {date}", { date: dateLabel(process.date) })
      : t("All steps done");
  else if (missing)
    stepsNote = n(
      "%n required card missing from the board",
      "%n required cards missing from the board",
      missing
    );
  else if (pendingWeeks !== null)
    stepsNote = t("{weeks} since the request", { weeks: weeksCount(pendingWeeks) });
  const weeks = Number.isFinite(s.executionWeeks) ? s.executionWeeks : null;
  const planned = !ended && s.plannedEndDate ? s.plannedEndDate : null;
  let endNote = t("Set the actual start in Planning to plan it");
  if (ended) endNote = t("Work on site finished");
  else if (planned)
    endNote = t("{weeks} on site from the actual start", { weeks: weeksCount(weeks ?? 0) });
  else if (actual) endNote = t("Set weeks on site in Planning to plan it");
  const warnings = Array.isArray(s.handoverWarnings) ? s.handoverWarnings : [];
  const handoverNote = warnings.length
    ? warnings.join(" · ")
    : handover
    ? t("Handed to the execution team")
    : t("After the process steps");
  const preparation = Math.max(0, Number(s.requiredPreparationWeeks) || 0);
  return {
    status: { tone, label: desired ? label : t("{status} (no target)", { status: label }), text },
    // Which group the project is in: 0 Initiation until the handover, 1 Execution
    // until the actual start, 2 Delivery on site, 3 ended.
    stage: ended ? 3 : actual ? 2 : handover ? 1 : 0,
    initiation: {
      requestDate: s.requestDate || null,
      done,
      total,
      configured,
      percent: configured ? Math.round((done / total) * 100) : 0,
      note: stepsNote,
      warn: configured && !stepsDone && missing > 0,
      handover,
      handoverNote,
      handoverWarn: warnings.length > 0,
    },
    execution: {
      minimumStart: s.minimumStartDate || null,
      note: preparation
        ? t("{weeks} preparation after the process steps", { weeks: weeksCount(preparation) })
        : t("Right after the process steps"),
      desired,
      actual,
    },
    delivery: { end: ended || planned, planned: !!planned, note: endNote, weeks },
    overall: overallOf(s.requestDate || null, ended, planned, todayOf(s, now)),
  };
}

// How far along the whole timeline is: the share of the time from the request
// date to the end (recorded, else planned) that has passed. Time, not work, so
// without an end date there is nothing to measure against.
function overallOf(requestDate, ended, planned, today) {
  const end = ended || planned;
  const from = dayOf(requestDate),
    to = dayOf(end);
  if (from === null) return { percent: null, text: t("of the timeline · no request date yet") };
  if (to === null)
    return {
      percent: null,
      text: t(
        "of the timeline · no end date planned yet, so there is nothing to measure against"
      ),
    };
  const percent = ended
    ? 100
    : to <= from
    ? 0
    : Math.min(100, Math.max(0, Math.round(((today - from) / (to - from)) * 100)));
  return {
    percent,
    text: t("of the timeline · {from} → {label} {end}", {
      from: dateLabel(requestDate),
      label: ended ? t("ended") : t("planned end"),
      end: dateLabel(end),
    }),
  };
}

// The server's today when the summary carries one, so the chart and the
// figures agree with the Planning tab whatever the viewer's clock says.
function todayOf(summary, now) {
  return dayOf(summary?.kpis?.today) ?? dayOf(new Date(now).toISOString());
}

// ── Planning: the Gantt ───────────────────────────────────────────────────

// A colour is projectcreatoraio data; only a plain hex reaches CSS.
const safeColor = (value) => (/^#[0-9a-f]{3,8}$/i.test(String(value || "")) ? value : null);
const isoOf = (day) => new Date(day * DAY_MS).toISOString().slice(0, 10);
// Monday 0 … Sunday 6
const sinceMonday = (day) => (new Date(day * DAY_MS).getUTCDay() + 6) % 7;

function isoWeekOf(day) {
  const d = new Date(day * DAY_MS);
  d.setUTCDate(d.getUTCDate() + 3 - ((d.getUTCDay() + 6) % 7));
  const isoYear = d.getUTCFullYear();
  const firstThursday = new Date(Date.UTC(isoYear, 0, 4));
  firstThursday.setUTCDate(firstThursday.getUTCDate() + 3 - ((firstThursday.getUTCDay() + 6) % 7));
  return { isoWeek: 1 + Math.round((d - firstThursday) / (7 * DAY_MS)), isoYear };
}

// "3w", "4.5w", or "5d" under a week — formatPhaseDuration / formatWeeks.
function weeksBadge(days) {
  const weeks = days / 7;
  if (weeks < 1) return t("{n}d", { n: days });
  return t("{n}w", {
    n: formatNumber(Math.abs(weeks - Math.round(weeks)) < 1e-9 ? Math.round(weeks) : Number(weeks.toFixed(1))),
  });
}
function weeksText(days) {
  const weeks = days / 7;
  const fixed =
    Math.abs(weeks - Math.round(weeks)) < 1e-9 ? String(Math.round(weeks)) : weeks.toFixed(1);
  // n() prints %n from the number, so hand it the rounded figure.
  return n("%n week", "%n weeks", Number(fixed));
}
// A card's slip: whole weeks once it is a week or more — formatDelayBadge.
function delayBadge(days) {
  const w = Math.round(days / 7);
  return w >= 1 ? t("{n}w", { n: w }) : t("{n}d", { n: days });
}

// The Status column's words and tone — getStatusLabel / getStatusClass. The
// tone is the stable key callers style and test on; the label is words only.
const STATUS = {
  behind_at_risk: [() => t("At risk"), "at-risk"],
  attention_needed: [() => t("Attention"), "attention"],
  completed: [() => t("Done"), "completed"],
  not_started: [() => t("Not started"), "not-started"],
  on_track: [() => t("On track"), "on-track"],
};
function statusOf(status, isDone = false) {
  if (isDone) return { label: t("Done"), tone: "completed" };
  const [labelOf, tone] = STATUS[status] || STATUS.on_track;
  return { label: labelOf(), tone };
}

// System items the System Planning row already draws, and the Planning tab
// therefore hides from the item rows.
const LEGACY_SYSTEM_KEYS = ["request_date", "process_completed", "prep_time", "deck_schedule"];

// Row heights, px — the Planning tab's, which the dependency arrows rely on.
export const ROW_HEIGHT = { system: 76, phase: 56, task: 44, item: 52 };

// The date header — years (only across years), months, ISO weeks — for a
// range of UTC day numbers, as the Planning tab heads its chart.
function headerOf(start, end) {
  const off = (day) => day - start;
  const first = partsOf(start);
  const last = partsOf(end);
  const multiYear = first.y !== last.y;
  const months = [];
  for (let y = first.y, m = first.m; ; m++) {
    const from = Date.UTC(y, m, 1) / DAY_MS;
    if (from > end) break;
    const to = Math.min(end, Date.UTC(y, m + 1, 0) / DAY_MS);
    const p = partsOf(from);
    months.push({
      key: `${p.y}-${p.m}`,
      label: multiYear ? monthName(p.m) : t("{month} {year}", { month: monthName(p.m), year: p.y }),
      offset: off(from),
      days: to - from + 1,
    });
  }
  const years = [];
  if (multiYear) {
    for (let y = first.y; y <= last.y; y++) {
      const from = Math.max(start, Date.UTC(y, 0, 1) / DAY_MS);
      const to = Math.min(end, Date.UTC(y, 11, 31) / DAY_MS);
      years.push({ key: `year-${y}`, label: String(y), offset: off(from), days: to - from + 1 });
    }
  }
  const weeks = [];
  for (let cursor = start; cursor <= end; ) {
    const { isoWeek, isoYear } = isoWeekOf(cursor);
    const span = Math.min(7 - sinceMonday(cursor), end - cursor + 1);
    const monday = cursor - sinceMonday(cursor);
    const sunday = monday + 6;
    const pm = partsOf(monday);
    const ps = partsOf(sunday);
    weeks.push({
      key: `${isoYear}-W${isoWeek}`,
      label: t("W{week}", { week: isoWeek }),
      tooltip: t("Week {week} · {startDay} {startMonth} – {endDay} {endMonth} {year}", {
        week: isoWeek,
        startDay: pm.d,
        startMonth: monthName(pm.m),
        endDay: ps.d,
        endMonth: monthName(ps.m),
        year: ps.y,
      }),
      offset: off(cursor),
      days: span,
    });
    cursor += span;
  }
  return {
    start: isoOf(start),
    totalDays: end - start,
    // Monday-aligned week lines start this many days into the range.
    firstMondayOffset: (7 - sinceMonday(start)) % 7,
    years,
    months,
    weeks,
  };
}

export function ganttOf(summary, items = [], now = Date.now()) {
  const s = summary || {};
  const sp = s.systemPlanning || null;
  const phases = Array.isArray(s.phases) ? s.phases : [];
  const today = todayOf(s, now);
  const endDate = sp ? sp.actualEnd?.date || sp.execution?.endDate || null : null;

  // Items as the Planning tab lists them: no schedule overrides, no system
  // items the System Planning row covers, no card a phase already lists.
  const phaseCards = new Set();
  phases.forEach((phase) =>
    (phase.tasks || []).forEach((task) => {
      if (task.deckCardId) phaseCards.add(Number(task.deckCardId));
    })
  );
  const shownItems = (Array.isArray(items) ? items : [])
    .filter((item) => {
      const key = String(item.systemKey || "").trim();
      if (item.itemType === "schedule_override" || key.startsWith("schedule_override:"))
        return false;
      if (sp && LEGACY_SYSTEM_KEYS.includes(key)) return false;
      if (key === "process_completed") return false;
      if (item.deckCardId && phaseCards.has(Number(item.deckCardId))) return false;
      return dayOf(item.startDate) !== null;
    })
    .sort((a, b) => (Number(a.orderIndex) || 0) - (Number(b.orderIndex) || 0));

  const hasContent = phases.length > 0 || shownItems.length > 0 || !!sp;
  if (!hasContent) return { hasContent: false };

  // The range — timelineRange: every drawn date and today, from the first of
  // the month before the first through the end of the month after the last.
  const isMilestone = (item) => String(item.itemType || "phase") === "milestone";
  const itemEnd = (item, start) => {
    if (isMilestone(item)) return start;
    const end = dayOf(item.endDate);
    return end !== null ? end : Math.max(start, today);
  };
  let min = null;
  let max = null;
  const addMin = (d) => {
    if (d !== null && (min === null || d < min)) min = d;
  };
  const addMax = (d) => {
    if (d !== null && (max === null || d > max)) max = d;
  };
  const addBoth = (d) => {
    addMin(d);
    addMax(d);
  };
  shownItems.forEach((item) => {
    const start = dayOf(item.startDate);
    addMin(start);
    addMax(itemEnd(item, start));
  });
  if (sp) {
    [
      sp.requestDate,
      sp.deckTasks?.startDate,
      sp.deckTasks?.endDate,
      sp.minimumStart?.date,
      sp.desiredStart?.date,
      sp.actualHandover?.date,
      endDate,
    ]
      .map(dayOf)
      .forEach(addBoth);
  }
  phases.forEach((phase) => {
    addMin(dayOf(phase.startDate));
    addMax(dayOf(phase.endDate));
    addMax(dayOf(phase.milestone?.date));
    (phase.tasks || []).forEach((task) => {
      addMin(dayOf(task.startDate));
      addMax(dayOf(task.endDate));
    });
  });
  let start;
  let end;
  if (min === null || max === null) {
    const now = partsOf(today);
    start = Date.UTC(now.y, now.m, 1) / DAY_MS;
    end = Date.UTC(now.y, now.m + 6, 0) / DAY_MS;
  } else {
    min = Math.min(min, today);
    max = Math.max(max, today);
    const a = partsOf(min);
    const b = partsOf(max);
    start = Date.UTC(a.y, a.m - 1, 1) / DAY_MS;
    end = Date.UTC(b.y, b.m + 2, 0) / DAY_MS;
  }
  const totalDays = end - start;
  const off = (day) => day - start;
  // A bar through its end day, at least one day — the phase, card and item bars.
  const through = (a, b) => ({ offset: off(a), days: Math.max(1, b - a + 1) });

  const header = headerOf(start, end);

  // 1. System Planning — SystemPlanningRow.
  let system = null;
  if (sp) {
    const deckWeeks = Number(sp.deckTasks?.weeks) || 0;
    const prepWeeks = Number(sp.preparation?.weeks) || 0;
    // The System Planning bars run up to their end day, at least one day.
    const upTo = (a, b) => ({ offset: off(a), days: Math.max(1, b - a) });
    const bars = [];
    const deckFrom = dayOf(sp.deckTasks?.startDate);
    const deckTo = dayOf(sp.deckTasks?.endDate);
    if (deckFrom !== null && deckTo !== null) {
      bars.push({
        key: "deck",
        tone: "deck",
        label: t("Deck tasks ({n} weeks)", { n: sp.deckTasks.weeks }),
        title: t("Deck tasks ({n} weeks): {start} - {end}", {
          n: sp.deckTasks.weeks,
          start: fullDate(sp.deckTasks.startDate),
          end: fullDate(sp.deckTasks.endDate),
        }),
        ...upTo(deckFrom, deckTo),
      });
    }
    const prepFrom = dayOf(sp.preparation?.startDate);
    const prepTo = dayOf(sp.preparation?.endDate);
    if (prepFrom !== null && prepTo !== null) {
      bars.push({
        key: "prep",
        tone: "prep",
        label: t("Preparation ({n} weeks)", { n: sp.preparation.weeks }),
        title: t("Preparation ({n} weeks): {start} - {end}", {
          n: sp.preparation.weeks,
          start: fullDate(sp.preparation.startDate),
          end: fullDate(sp.preparation.endDate),
        }),
        ...upTo(prepFrom, prepTo),
      });
    }
    const minimum = dayOf(sp.minimumStart?.date);
    const desired = dayOf(sp.desiredStart?.date);
    if (minimum !== null && desired !== null && desired !== minimum) {
      const floatDays = sp.float?.days;
      const positive = floatDays === null || floatDays === undefined || floatDays >= 0;
      const fw = sp.float?.weeks;
      const known = fw !== null && fw !== undefined;
      bars.push({
        key: "float",
        tone: positive ? "float" : "behind",
        label: !known
          ? ""
          : positive
          ? t("Float ({n}w)", { n: fw })
          : t("Behind ({n}w)", { n: Math.abs(fw) }),
        title: !known
          ? ""
          : positive
          ? t("Available Float: +{n} week(s)", { n: fw })
          : t("Negative Float: {n} week(s) behind schedule", { n: fw }),
        offset: off(positive ? minimum : desired),
        days: Math.abs(desired - minimum),
      });
    }
    const markers = [
      ["min-start", t("Minimum start"), sp.minimumStart?.date],
      ["desired-start", t("Desired start"), sp.desiredStart?.date],
      ["actual-start", t("Actual start"), sp.actualStart?.date],
    ]
      .filter(([, , date]) => dayOf(date) !== null)
      .map(([key, label, date]) => ({
        key,
        label,
        date: fullDate(date),
        offset: off(dayOf(date)),
      }));
    system = {
      status: statusOf(sp.float?.status || "on_track"),
      duration: t("{n}w", { n: deckWeeks + prepWeeks }),
      dates: t("{start} – {end}", {
        start: shortDate(sp.deckTasks?.startDate || sp.requestDate),
        end: shortDate(sp.desiredStart?.date || sp.minimumStart?.date),
      }),
      bars,
      markers,
      legend: {
        desired: dayOf(sp.desiredStart?.date) !== null,
        actual: dayOf(sp.actualStart?.date) !== null,
      },
      // The mini legend sits under the bars, from the Deck tasks bar on.
      legendOffset: deckFrom !== null ? off(deckFrom) : 0,
    };
  }

  // 2…n. The phases and their Deck cards.
  const rows = phases.map((phase) => {
    const from = dayOf(phase.startDate);
    const to = dayOf(phase.endDate);
    const scheduled = from !== null && to !== null;
    const milestoneDay = phase.milestone ? dayOf(phase.milestone.date) ?? to : null;
    const tasks = (phase.tasks || [])
      .map((task) => {
        const a = dayOf(task.startDate);
        const b = dayOf(task.endDate);
        if (a === null || b === null) return null;
        const done = !!task.isDone;
        const delayDays = Math.max(0, Number(task.delayDays) || 0);
        const status = statusOf(task.status, done);
        const plannedEnd = dayOf(task.plannedEndDate);
        const dates = t("{start} – {end}", {
          start: shortDate(task.startDate),
          end: shortDate(task.endDate),
        });
        return {
          id: String(task.id),
          deckCardId: task.deckCardId ? String(task.deckCardId) : null,
          label: String(task.label || ""),
          dates,
          durationDays: task.durationDays,
          done,
          delayed: !!task.isDelayed,
          delayDays,
          delayBadge: delayDays > 0 ? delayBadge(delayDays) : "",
          atRisk: task.status === "behind_at_risk" || !!task.isDelayed,
          status,
          ghost:
            plannedEnd !== null && task.plannedEndDate !== task.endDate
              ? through(a, plannedEnd)
              : null,
          title: t("{label}: {dates} ({n}d) • {status}{delay}", {
            label: task.label,
            dates,
            n: task.durationDays,
            status: status.label,
            delay:
              delayDays > 0
                ? " " + t("• Delayed by +{delay}", { delay: delayBadge(delayDays) })
                : "",
          }),
          ...through(a, b),
        };
      })
      .filter(Boolean);
    return {
      key: String(phase.category || phase.id),
      order: phase.order ?? "",
      name: String(phase.name || ""),
      color: safeColor(phase.color) || "#3b82f6",
      status: statusOf(phase.status),
      scheduled,
      duration: scheduled ? weeksBadge(Math.max(1, to - from + 1)) : "",
      dates: scheduled
        ? t("{start} – {end}", { start: shortDate(phase.startDate), end: shortDate(phase.endDate) })
        : t("No tasks scheduled"),
      bar: scheduled ? through(from, to) : null,
      milestone:
        milestoneDay !== null
          ? {
              label: String(phase.milestone.label || ""),
              title: t("Milestone: {label} ({date})", {
                label: phase.milestone.label,
                date: shortDate(isoOf(milestoneDay)),
              }),
              offset: off(milestoneDay),
            }
          : null,
      tasks,
    };
  });

  // The custom timeline items, under the phases.
  const itemRows = shownItems.map((item) => {
    const a = dayOf(item.startDate);
    const milestone = isMilestone(item);
    const ongoing = !milestone && !String(item.endDate || "").trim();
    const b = itemEnd(item, a);
    const days = milestone ? 1 : Math.max(1, b - a + 1);
    const untilStart = a - today;
    const hint = untilStart > 0 ? t("Starts in {weeks}", { weeks: weeksText(untilStart) }) : "";
    const startText = shortDate(item.startDate);
    const endText = ongoing ? t("Ongoing") : shortDate(item.endDate);
    const span = milestone ? startText : t("{start} – {end}", { start: startText, end: endText });
    const daysText = n("{weeks} (%n day)", "{weeks} (%n days)", days, { weeks: weeksText(days) });
    const vars = { label: item.label, start: startText, end: endText, duration: daysText, hint };
    let title;
    if (milestone)
      title = hint
        ? t("{label}: {start} ({hint})", vars)
        : t("{label}: {date}", { label: item.label, date: startText });
    else
      title = hint
        ? t("{label}: {start} - {end} ({duration}) ({hint})", vars)
        : t("{label}: {start} - {end} ({duration})", vars);
    return {
      id: String(item.id),
      label: String(item.label || ""),
      milestone,
      ongoing,
      color: safeColor(item.color) || (milestone ? "#0f172a" : "#3b82f6"),
      badge: milestone ? t("Milestone") : weeksBadge(days),
      dates: hint ? t("{span} • {hint}", { span, hint }) : span,
      title,
      status: statusOf(item.status || "on_track"),
      offset: off(a),
      days,
    };
  });

  // Full-height guide lines for the System Planning dates — guideMarkers.
  const guides = sp
    ? [
        ["min-start", t("Min start"), sp.minimumStart?.date],
        ["desired-start", t("Desired start"), sp.desiredStart?.date],
        ["actual-start", t("Actual start"), sp.actualStart?.date],
        ["handover", t("Handover"), sp.actualHandover?.date],
        ["end", sp.actualEnd?.date ? t("End") : t("End (planned)"), endDate],
      ]
        .filter(([, , date]) => dayOf(date) !== null)
        .map(([key, label, date]) => ({
          key,
          label,
          date: shortDate(date),
          offset: off(dayOf(date)),
        }))
        .sort((a, b) => a.offset - b.offset)
    : [];

  return {
    hasContent: true,
    ...header,
    system,
    rows,
    items: itemRows,
    guides,
    hasActiveDelays: !!s.delayAnalysis?.hasActiveDelays,
    today: { offset: off(today), iso: isoOf(today) },
  };
}

// ── Several projects on one chart ─────────────────────────────────────────

/**
 * Projects side by side on one date range — the Planning tab's System Planning
 * row and phase rows for each, the way its Gantt draws one project. Each
 * project's chart is built by ganttOf and then moved onto the shared range, so
 * every rule above holds per project. `entries` is [{ id, name, summary }].
 */
export function portfolioGanttOf(entries, now = Date.now()) {
  const built = (entries || [])
    .map((entry) => ({ entry, g: ganttOf(entry.summary, [], now) }))
    .filter(({ g }) => g.hasContent);
  if (!built.length) return { hasContent: false, projects: [] };
  const today = todayOf((built[0].entry || {}).summary, now);
  let start = null;
  let end = null;
  built.forEach(({ g }) => {
    const from = dayOf(g.start);
    if (start === null || from < start) start = from;
    if (end === null || from + g.totalDays > end) end = from + g.totalDays;
  });
  const header = headerOf(start, end);
  const projects = built.map(({ entry, g }) => {
    const shift = dayOf(g.start) - start;
    const move = (thing) => (thing ? { ...thing, offset: thing.offset + shift } : thing);
    return {
      id: entry.id,
      name: String(entry.name || ""),
      status: g.system ? g.system.status : statusOf(entry.summary && entry.summary.planningStatus),
      system: g.system
        ? {
            ...g.system,
            bars: g.system.bars.map(move),
            markers: g.system.markers.map(move),
            legendOffset: g.system.legendOffset + shift,
          }
        : null,
      rows: g.rows.map((row) => ({
        ...row,
        bar: move(row.bar),
        milestone: move(row.milestone),
        tasks: [],
      })),
      guides: g.guides.map(move),
      hasActiveDelays: g.hasActiveDelays,
    };
  });
  return {
    hasContent: true,
    ...header,
    projects,
    today: { offset: today - start, iso: isoOf(today) },
  };
}

// Stack the guide flags so none overlaps its left neighbour — guideMarkers.
// Positions and width in px.
export function stackFlags(guides, dayWidth, flagWidth = 150) {
  const levelEnds = [];
  return (guides || []).map((guide) => {
    const left = guide.offset * dayWidth;
    let level = levelEnds.findIndex((endsAt) => endsAt <= left);
    if (level === -1) level = levelEnds.length;
    levelEnds[level] = left + flagWidth;
    return { ...guide, left, level };
  });
}

// The card dependency arrows — visibleDependencyPaths. `rows` is the visible
// row list in order ({ type, task? }), as the component lays it out.
export function dependencyPaths(dependencies, rows, { dayWidth, hasSystem }) {
  if (!Array.isArray(dependencies) || !dependencies.length) return [];
  const byId = {};
  let y = hasSystem ? ROW_HEIGHT.system : 0;
  rows.forEach((row) => {
    const height = ROW_HEIGHT[row.type];
    if (row.type === "task") {
      const task = row.task;
      const xStart = task.offset * dayWidth;
      const place = { xStart, xEnd: xStart + task.days * dayWidth, yMid: y + height / 2, task };
      byId[task.id] = place;
      if (task.deckCardId) byId[task.deckCardId] = place;
    }
    y += height;
  });
  const paths = [];
  dependencies.forEach((dep) => {
    const pred = byId[String(dep.predecessorId)];
    const succ = byId[String(dep.successorId)];
    if (!pred || !succ) return;
    const x1 = pred.xEnd;
    const y1 = pred.yMid;
    const x2 = succ.xStart;
    const y2 = succ.yMid;
    let d;
    if (x2 >= x1 + 10) {
      const midX = x1 + Math.max(6, Math.min(16, (x2 - x1) / 2));
      d = `M ${x1} ${y1} L ${midX} ${y1} L ${midX} ${y2} L ${x2} ${y2}`;
    } else {
      const exitX = x1 + 10;
      const entryX = Math.max(0, x2 - 10);
      const midY = (y1 + y2) / 2;
      d = `M ${x1} ${y1} L ${exitX} ${y1} L ${exitX} ${midY} L ${entryX} ${midY} L ${entryX} ${y2} L ${x2} ${y2}`;
    }
    paths.push({
      key: `dep-${dep.predecessorId}-${dep.successorId}`,
      d,
      active: !pred.task.done,
      delayed: pred.task.delayed,
    });
  });
  return paths;
}

// "Today", "in 3w 2d", "5d ago" — the hover guide's relative date.
export function relativeDays(days) {
  if (days === 0) return t("Today");
  const abs = Math.abs(days);
  const w = Math.floor(abs / 7);
  const rest = abs % 7;
  const parts = [];
  if (w) parts.push(t("{n}w", { n: w }));
  if (rest || !w) parts.push(t("{n}d", { n: rest }));
  const duration = parts.join(" ");
  return days > 0 ? t("in {duration}", { duration }) : t("{duration} ago", { duration });
}

// The hover guide's chip: "Fri, 23 Oct 2026".
export function hoverDateLabel(day) {
  const d = new Date(day * DAY_MS);
  return t("{weekday}, {day} {month} {year}", {
    weekday: weekdayName(day),
    day: d.getUTCDate(),
    month: monthName(d.getUTCMonth()),
    year: d.getUTCFullYear(),
  });
}

export { isoWeekOf };
