// VENDORED with src/lib/projectTimeline.mjs — identical in adminpage,
// superadminpage and employee_dashboard. The timelineOf cases are
// projectcreatoraio's own (src/new/overview.test.js), so the words stay its.
import test from "node:test";
import assert from "node:assert/strict";
import {
  dayOf,
  dateLabel,
  shortDate,
  fullDate,
  timelineOf,
  ganttOf,
  portfolioGanttOf,
  stackFlags,
  dependencyPaths,
  relativeDays,
  isoWeekOf,
} from "../src/lib/projectTimeline.mjs";

const fresh = {
  requestDate: "2026-08-24",
  requiredPreparationWeeks: 0,
  processCompleted: {
    status: "incomplete",
    date: null,
    doneCount: 0,
    totalRequired: 10,
    missingTitles: [],
  },
  coordinationPendingPeriod: { weeks: 7 },
  minimumStartDate: "2028-05-29",
  desiredStartDate: null,
  actualStartDate: null,
  overallFloatWeeks: null,
  overallFloatDays: null,
  planningStatus: "on_track",
  actualHandoverDate: null,
  actualEndDate: null,
  executionWeeks: null,
  plannedEndDate: null,
  handoverWarnings: [],
};

test("dates are whole days and read the same in every time zone", () => {
  assert.equal(dayOf("2026-03-02") - dayOf("2026-03-01"), 1);
  assert.equal(dayOf("2026-03-01T23:59:00+05:00"), dayOf("2026-03-01"));
  assert.equal(dayOf(null), null);
  assert.equal(dateLabel("2026-03-04"), "4 Mar 2026");
  assert.equal(dateLabel("nonsense"), "Not set");
  // The Planning tab's formats: dd/mm/yy in the Gantt, dd/mm/yyyy on markers.
  assert.equal(shortDate("2026-03-04"), "04/03/26");
  assert.equal(shortDate(null), "-");
  assert.equal(fullDate("2028-05-29"), "29/05/2028");
  assert.deepEqual(isoWeekOf(dayOf("2026-10-23")), { isoWeek: 43, isoYear: 2026 });
  assert.deepEqual(isoWeekOf(dayOf("2027-01-01")), { isoWeek: 53, isoYear: 2026 });
  assert.equal(relativeDays(0), "Today");
  assert.equal(relativeDays(23), "in 3w 2d");
  assert.equal(relativeDays(-5), "5d ago");
});

test("a fresh project sits in Initiation, on track without a target", () => {
  const t = timelineOf(fresh);
  assert.deepEqual(t.status, {
    tone: "success",
    label: "On track (no target)",
    text: "No desired start yet, so there is no float to check.",
  });
  assert.equal(t.stage, 0);
  assert.deepEqual(t.initiation, {
    requestDate: "2026-08-24",
    done: 0,
    total: 10,
    configured: true,
    percent: 0,
    note: "7 weeks since the request",
    warn: false,
    handover: null,
    handoverNote: "After the process steps",
    handoverWarn: false,
  });
  assert.equal(t.execution.note, "Right after the process steps");
  assert.deepEqual(t.delivery, {
    end: null,
    planned: false,
    note: "Set the actual start in Planning to plan it",
    weeks: null,
  });
});

test("the status and float are the server's, in words", () => {
  assert.deepEqual(
    timelineOf({
      ...fresh,
      desiredStartDate: "2026-10-01",
      overallFloatDays: -4,
      overallFloatWeeks: -0.6,
      planningStatus: "behind_at_risk",
    }).status,
    {
      tone: "danger",
      label: "Behind / at risk",
      text: "Desired start is 4 days before the earliest possible start · float -0.6 weeks.",
    }
  );
  assert.equal(
    timelineOf({
      ...fresh,
      desiredStartDate: "2026-10-05",
      overallFloatDays: 0,
      overallFloatWeeks: 0,
      planningStatus: "attention_needed",
    }).status.label,
    "Attention needed"
  );
  assert.equal(
    timelineOf({
      ...fresh,
      desiredStartDate: "2026-11-02",
      overallFloatDays: 28,
      overallFloatWeeks: 4,
    }).status.text,
    "Float of +4 weeks before the desired start."
  );
});

test("the stage follows handover, actual start and end, in that order", () => {
  const steps = {
    ...fresh,
    processCompleted: { status: "complete", date: "2026-09-14", doneCount: 10, totalRequired: 10 },
    requiredPreparationWeeks: 3,
  };
  assert.equal(timelineOf(steps).stage, 0);
  assert.equal(timelineOf(steps).initiation.note, "Completed 14 Sep 2026");
  assert.equal(timelineOf(steps).execution.note, "3 weeks preparation after the process steps");
  const handedOver = { ...steps, actualHandoverDate: "2026-09-28" };
  assert.equal(timelineOf(handedOver).stage, 1);
  assert.equal(timelineOf(handedOver).initiation.handoverNote, "Handed to the execution team");
  const started = { ...handedOver, actualStartDate: "2026-10-12" };
  assert.equal(timelineOf(started).stage, 2);
  assert.equal(timelineOf(started).delivery.note, "Set weeks on site in Planning to plan it");
  const planned = { ...started, executionWeeks: 6, plannedEndDate: "2026-11-23" };
  assert.deepEqual(timelineOf(planned).delivery, {
    end: "2026-11-23",
    planned: true,
    note: "6 weeks on site from the actual start",
    weeks: 6,
  });
  const over = { ...planned, actualEndDate: "2026-11-20", plannedEndDate: "2026-11-20" };
  assert.equal(timelineOf(over).stage, 3);
  assert.deepEqual(timelineOf(over).delivery, {
    end: "2026-11-20",
    planned: false,
    note: "Work on site finished",
    weeks: 6,
  });
});

test("handover warnings and missing steps are said, not counted as progress", () => {
  const warned = timelineOf({
    ...fresh,
    actualHandoverDate: "2026-10-19",
    handoverWarnings: ["Handover after the actual start"],
  });
  assert.equal(warned.initiation.handoverNote, "Handover after the actual start");
  assert.equal(warned.initiation.handoverWarn, true);
  const missing = timelineOf({
    ...fresh,
    processCompleted: {
      status: "missing_cards",
      doneCount: 1,
      totalRequired: 4,
      missingTitles: ["AVP", "VO"],
    },
  });
  assert.equal(missing.initiation.note, "2 required cards missing from the board");
  assert.equal(missing.initiation.warn, true);
  const none = timelineOf({
    ...fresh,
    processCompleted: { status: "not_configured", doneCount: 0, totalRequired: 0 },
  });
  assert.equal(none.initiation.configured, false);
  assert.equal(none.initiation.note, "No process steps configured");
  assert.equal(timelineOf(null).stage, 0);
});

test("the whole timeline's percentage is the time passed from request to end", () => {
  const site = {
    ...fresh,
    requestDate: "2026-06-01",
    actualStartDate: "2026-10-12",
    executionWeeks: 6,
    plannedEndDate: "2026-11-23",
  };
  // 1 Jun to 23 Nov is 175 days; 19 Oct is day 140.
  assert.deepEqual(timelineOf({ ...site, kpis: { today: "2026-10-19" } }).overall, {
    percent: 80,
    text: "of the timeline · 1 Jun 2026 → planned end 23 Nov 2026",
  });
  assert.equal(timelineOf({ ...site, kpis: { today: "2026-05-20" } }).overall.percent, 0);
  assert.equal(timelineOf({ ...site, kpis: { today: "2026-12-10" } }).overall.percent, 100);
  assert.equal(timelineOf(site, Date.UTC(2026, 9, 19, 23, 30)).overall.percent, 80);
  assert.equal(
    timelineOf({ ...site, actualEndDate: "2026-11-20", kpis: { today: "2026-11-01" } }).overall
      .percent,
    100
  );
  assert.equal(timelineOf({ ...fresh, kpis: { today: "2026-10-19" } }).overall.percent, null);
  assert.equal(
    timelineOf({ ...fresh, requestDate: null, plannedEndDate: "2026-11-23" }).overall.text,
    "of the timeline · no request date yet"
  );
});

// A project on site: initiation done, handed over, started, planned to end.
const onSite = {
  ...fresh,
  requestDate: "2026-03-04",
  kpis: { today: "2026-10-23" },
  systemPlanning: {
    requestDate: "2026-03-04",
    deckTasks: { startDate: "2026-03-04", endDate: "2026-06-02", weeks: 13 },
    preparation: { startDate: "2026-06-02", endDate: "2026-07-14", weeks: 6 },
    minimumStart: { date: "2026-07-14" },
    desiredStart: { date: "2026-08-01" },
    actualStart: { date: "2026-08-03" },
    float: { days: 18, weeks: 2.6, status: "on_track" },
    actualHandover: { date: "2026-06-09" },
    execution: { startDate: "2026-08-03", endDate: "2027-02-01", weeks: 26 },
    actualEnd: { date: null },
  },
  phases: [
    {
      id: 5,
      order: 2,
      name: "Initiation Phase",
      category: "initiation",
      color: "#10b981",
      startDate: "2026-03-04",
      endDate: "2026-06-02",
      milestone: { label: "Initiation ready", date: "2026-06-02" },
      tasks: [
        {
          id: 414,
          deckCardId: 414,
          label: "Intakeformulier",
          startDate: "2026-03-04",
          endDate: "2026-03-18",
          durationDays: 15,
          delayDays: 0,
          isDone: true,
          status: "on_track",
        },
        {
          id: 415,
          deckCardId: 415,
          label: "VO",
          startDate: "2026-05-04",
          endDate: "2026-05-27",
          durationDays: 24,
          delayDays: 5,
          plannedEndDate: "2026-05-22",
          isDelayed: true,
          isDone: false,
          status: "behind_at_risk",
        },
        { id: 416, label: "No dates", startDate: null, endDate: null },
      ],
    },
    {
      id: 8,
      order: 3,
      name: "Handover Phase",
      category: "handover",
      color: "url(x)",
      status: "not_started",
      startDate: null,
      endDate: null,
      milestone: null,
      tasks: [],
    },
  ],
};

test("the range is the Planning tab's: a month before the first date to the end of the month after the last", () => {
  const g = ganttOf(onSite);
  assert.equal(g.hasContent, true);
  // 1 Feb 2026 – 31 Mar 2027; the Planning tab counts end - start days.
  assert.equal(g.start, "2026-02-01");
  assert.equal(g.totalDays, 423);
  assert.equal(g.months.length, 14);
  assert.deepEqual(g.months[0], { key: "2026-1", label: "Feb", offset: 0, days: 28 });
  assert.deepEqual(
    g.years.map((y) => [y.label, y.offset, y.days]),
    [
      ["2026", 0, 334],
      ["2027", 334, 90],
    ]
  );
  // 1 Feb 2026 is a Sunday: a one-day W5, then W6 from Monday 2 Feb.
  assert.deepEqual(g.weeks[0], {
    key: "2026-W5",
    label: "W5",
    tooltip: "Week 5 · 26 Jan – 1 Feb 2026",
    offset: 0,
    days: 1,
  });
  assert.equal(g.weeks[1].label, "W6");
  assert.equal(g.weeks[1].days, 7);
  assert.equal(g.firstMondayOffset, 1);
  assert.deepEqual(g.today, { offset: at("2026-10-23"), iso: "2026-10-23" });
});

test("within one year the months carry the year and there is no year row", () => {
  const short = structuredClone(onSite);
  short.kpis.today = "2026-05-01";
  short.systemPlanning.execution.endDate = "2026-09-30";
  const g = ganttOf(short);
  assert.equal(g.years.length, 0);
  assert.equal(g.months[0].label, "Feb 2026");
});

test("the System Planning row carries the server's bars, markers and status", () => {
  const { system } = ganttOf(onSite);
  assert.deepEqual(system.status, { label: "On track", tone: "on-track" });
  assert.equal(system.duration, "19w");
  assert.equal(system.dates, "04/03/26 – 01/08/26");
  assert.deepEqual(
    system.bars.map((b) => [b.key, b.tone, b.label, b.offset, b.days]),
    [
      ["deck", "deck", "Deck tasks (13 weeks)", at("2026-03-04"), 90],
      ["prep", "prep", "Preparation (6 weeks)", at("2026-06-02"), 42],
      ["float", "float", "Float (2.6w)", at("2026-07-14"), 18],
    ]
  );
  assert.equal(system.bars[0].title, "Deck tasks (13 weeks): 04/03/2026 - 02/06/2026");
  assert.equal(system.bars[2].title, "Available Float: +2.6 week(s)");
  assert.deepEqual(
    system.markers.map((m) => [m.key, m.label, m.date]),
    [
      ["min-start", "Minimum start", "14/07/2026"],
      ["desired-start", "Desired start", "01/08/2026"],
      ["actual-start", "Actual start", "03/08/2026"],
    ]
  );
  assert.deepEqual(system.legend, { desired: true, actual: true });
});

test("negative float runs from the desired start to the minimum start", () => {
  const late = structuredClone(onSite);
  late.systemPlanning.desiredStart.date = "2026-07-01";
  late.systemPlanning.float = { days: -13, weeks: -1.9, status: "behind_at_risk" };
  const g = ganttOf(late);
  const float = g.system.bars.find((b) => b.key === "float");
  assert.equal(float.tone, "behind");
  assert.equal(float.label, "Behind (1.9w)");
  assert.equal(float.offset, at("2026-07-01"));
  assert.deepEqual(g.system.status, { label: "At risk", tone: "at-risk" });
});

test("a same-day preparation still draws its one-day bar, as the Planning tab does", () => {
  const none = structuredClone(onSite);
  none.systemPlanning.preparation = { startDate: "2026-06-02", endDate: "2026-06-02", weeks: 0 };
  const prep = ganttOf(none).system.bars.find((b) => b.key === "prep");
  assert.equal(prep.days, 1);
  assert.equal(prep.label, "Preparation (0 weeks)");
});

test("phases carry their cards with status, delay and ghost bar; unscheduled phases say so", () => {
  const [initiation, handover] = ganttOf(onSite).rows;
  assert.equal(initiation.key, "initiation");
  assert.equal(initiation.color, "#10b981");
  assert.deepEqual(initiation.status, { label: "On track", tone: "on-track" });
  assert.equal(initiation.duration, "13w");
  assert.equal(initiation.dates, "04/03/26 – 02/06/26");
  assert.deepEqual(initiation.bar, { offset: at("2026-03-04"), days: 91 });
  assert.deepEqual(initiation.milestone, {
    label: "Initiation ready",
    title: "Milestone: Initiation ready (02/06/26)",
    offset: at("2026-06-02"),
  });
  const [intake, vo] = initiation.tasks;
  assert.deepEqual(
    [
      intake.label,
      intake.dates,
      intake.durationDays,
      intake.done,
      intake.status.label,
      intake.ghost,
    ],
    ["Intakeformulier", "04/03/26 – 18/03/26", 15, true, "Done", null]
  );
  assert.equal(intake.title, "Intakeformulier: 04/03/26 – 18/03/26 (15d) • Done");
  assert.deepEqual(
    [vo.delayed, vo.delayDays, vo.delayBadge, vo.atRisk, vo.status.label],
    [true, 5, "1w", true, "At risk"]
  );
  assert.deepEqual(vo.ghost, { offset: at("2026-05-04"), days: 19 });
  assert.equal(vo.title, "VO: 04/05/26 – 27/05/26 (24d) • At risk • Delayed by +1w");
  assert.equal(initiation.tasks.length, 2, "a card without dates is not drawn");
  assert.equal(handover.scheduled, false);
  assert.equal(handover.bar, null);
  assert.equal(handover.dates, "No tasks scheduled");
  assert.equal(handover.milestone, null);
  assert.equal(
    handover.color,
    "#3b82f6",
    "an unusable colour falls back to the Planning tab's blue"
  );
  assert.deepEqual(handover.status, { label: "Not started", tone: "not-started" });
});

test("items are listed as the Planning tab lists them", () => {
  const items = [
    {
      id: 1,
      label: "Request Date",
      itemType: "milestone",
      startDate: "2026-03-04",
      systemKey: "request_date",
    },
    {
      id: 2,
      label: "Done",
      itemType: "phase",
      startDate: "2026-03-04",
      systemKey: "process_completed",
    },
    {
      id: 3,
      label: "Overlap",
      itemType: "schedule_override",
      startDate: "2026-03-04",
      systemKey: "schedule_override:overlap:A",
    },
    {
      id: 4,
      label: "Intake card",
      itemType: "phase",
      startDate: "2026-03-04",
      endDate: "2026-03-18",
      deckCardId: 414,
    },
    {
      id: 5,
      label: "Site visit",
      itemType: "milestone",
      startDate: "2026-11-06",
      color: "#e11d48",
      orderIndex: 2,
    },
    {
      id: 6,
      label: "Permit",
      itemType: "phase",
      startDate: "2026-09-01",
      orderIndex: 1,
      status: "attention_needed",
    },
  ];
  const g = ganttOf(onSite, items);
  assert.deepEqual(
    g.items.map((i) => i.label),
    ["Permit", "Site visit"]
  );
  const [permit, visit] = g.items;
  // No end date: ongoing, drawn up to today.
  assert.deepEqual([permit.ongoing, permit.days, permit.badge], [true, 53, "7.6w"]);
  assert.equal(permit.dates, "01/09/26 – Ongoing");
  assert.equal(permit.title, "Permit: 01/09/26 - Ongoing (7.6 weeks (53 days))");
  assert.deepEqual(permit.status, { label: "Attention", tone: "attention" });
  assert.deepEqual([visit.milestone, visit.badge, visit.color], [true, "Milestone", "#e11d48"]);
  assert.equal(visit.dates, "06/11/26 • Starts in 2 weeks");
  // Without System Planning the system items are listed too.
  const bare = { ...onSite, systemPlanning: null };
  assert.deepEqual(
    ganttOf(bare, items).items.map((i) => i.label),
    ["Request Date", "Permit", "Site visit"]
  );
});

test("guide lines run in date order and their flags stack only when they would overlap", () => {
  const g = ganttOf(onSite);
  assert.deepEqual(
    g.guides.map((m) => [m.label, m.date]),
    [
      ["Handover", "09/06/26"],
      ["Min start", "14/07/26"],
      ["Desired start", "01/08/26"],
      ["Actual start", "03/08/26"],
      ["End (planned)", "01/02/27"],
    ]
  );
  // At 4px a day: 9 Jun is 512px in, 14 Jul 652, 1 Aug 724, 3 Aug 732; a
  // flag is 150px wide.
  assert.deepEqual(
    stackFlags(g.guides, 4).map((m) => [m.left, m.level]),
    [
      [512, 0],
      [652, 1],
      [724, 0],
      [732, 2],
      [1460, 0],
    ]
  );
});

test("dependency arrows join a card's end to its successor's start", () => {
  const g = ganttOf(onSite);
  const [initiation] = g.rows;
  const rows = [{ type: "phase" }, ...initiation.tasks.map((task) => ({ type: "task", task }))];
  const deps = [
    { predecessorId: 414, successorId: 415 },
    { predecessorId: 415, successorId: 999 },
  ];
  const [path, ...rest] = dependencyPaths(deps, rows, { dayWidth: 4, hasSystem: true });
  assert.equal(rest.length, 0, "a link to a card not on screen is not drawn");
  // Intake ends at (31 + 15) * 4 = 184px, VO starts at 92 * 4 = 368px;
  // rows: system 76, phase 56, then 44 each — midpoints 154 and 198.
  assert.equal(path.d, "M 184 154 L 200 154 L 200 198 L 368 198");
  assert.deepEqual([path.active, path.delayed], [false, false]);
});

test("nothing to draw without a plan", () => {
  assert.deepEqual(ganttOf(null), { hasContent: false });
  assert.deepEqual(ganttOf({ phases: [] }), { hasContent: false });
  const empty = ganttOf({
    phases: [{ id: 1, name: "Empty", tasks: [] }],
    kpis: { today: "2026-10-23" },
  });
  assert.equal(empty.hasContent, true);
  assert.equal(empty.start, "2026-10-01", "no dates: this month and the five after");
  assert.equal(empty.totalDays, 181);
});

test("several projects share one range, each placed as its own chart would place it", () => {
  // The second project's dates run a year later than onSite's.
  const later = structuredClone(onSite);
  const shiftYear = (v) =>
    typeof v === "string" && /^2026-/.test(v)
      ? v.replace(/^2026-/, "2027-")
      : typeof v === "string" && /^2027-/.test(v)
      ? v.replace(/^2027-/, "2028-")
      : v;
  const walk = (o) => {
    for (const k of Object.keys(o)) {
      if (o[k] && typeof o[k] === "object") walk(o[k]);
      else if (k !== "today") o[k] = shiftYear(o[k]);
    }
  };
  walk(later.systemPlanning);
  later.phases.forEach(walk);
  const p = portfolioGanttOf(
    [
      { id: 1, name: "Now", summary: onSite },
      { id: 2, name: "Later", summary: later },
      { id: 3, name: "Empty", summary: { phases: [] } },
    ],
    Date.UTC(2026, 9, 23)
  );
  assert.equal(p.hasContent, true);
  assert.deepEqual(
    p.projects.map((x) => x.name),
    ["Now", "Later"],
    "a project with nothing to draw is left out"
  );
  // onSite's own chart starts 1 Feb 2026; the later one's range takes in
  // today (Oct 2026) too, so it starts on 1 Sep 2026.
  assert.equal(p.start, "2026-02-01");
  const own = ganttOf(onSite);
  const ownLater = ganttOf(later);
  const shift = dayOf(ownLater.start) - dayOf(own.start);
  assert.equal(ownLater.start, "2026-09-01");
  assert.equal(shift, 212);
  assert.equal(p.totalDays, shift + ownLater.totalDays);
  assert.equal(p.projects[0].system.bars[0].offset, own.system.bars[0].offset);
  assert.equal(p.projects[1].system.bars[0].offset, ownLater.system.bars[0].offset + shift);
  assert.equal(p.projects[1].rows[0].bar.offset, ownLater.rows[0].bar.offset + shift);
  assert.equal(p.projects[1].rows[0].milestone.offset, ownLater.rows[0].milestone.offset + shift);
  assert.equal(p.projects[1].system.legendOffset, ownLater.system.legendOffset + shift);
  assert.deepEqual(p.projects[0].rows[0].tasks, [], "no card rows in the overview");
  assert.deepEqual(p.projects[0].status, { label: "On track", tone: "on-track" });
  assert.deepEqual(p.today, { offset: at("2026-10-23"), iso: "2026-10-23" });
  assert.equal(p.months[0].label, "Feb");
  assert.deepEqual(portfolioGanttOf([]), { hasContent: false, projects: [] });
});

function at(date) {
  return dayOf(date) - dayOf("2026-02-01");
}
