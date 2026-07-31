#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");

const appPath = path.join(__dirname, "..", "assets", "app.js");
const apiPath = path.join(__dirname, "..", "api.php");
const source = fs.readFileSync(appPath, "utf8");
const apiSource = fs.readFileSync(apiPath, "utf8");

const requiredSymbols = [
  "entityTypeLabels",
  "entityBadge",
  "teamActiveBadge",
  "usageLabels",
  "crudResourceKey",
  "isEditableResource",
];

for (const symbol of requiredSymbols) {
  if (!new RegExp(`\\b${symbol}\\b`).test(source)) {
    console.error(`Missing symbol in app.js: ${symbol}`);
    process.exit(1);
  }
}

const entityTypeIndex = source.indexOf("const entityTypeLabels");
const entityBadgeIndex = source.indexOf("const entityBadge");
if (entityTypeIndex < 0 || entityBadgeIndex < 0 || entityTypeIndex > entityBadgeIndex) {
  console.error("entityTypeLabels must be defined before entityBadge");
  process.exit(1);
}

if (!/const entityTypeLabels\s*=\s*\{[^}]*team:\s*"تیم"[^}]*\}/.test(source)) {
  console.error("entityTypeLabels must include team/company/student labels");
  process.exit(1);
}

if (!/function isEditableResource|const isEditableResource/.test(source)) {
  console.error("isEditableResource helper is missing");
  process.exit(1);
}

if (!source.includes('crudResourceKey(resource)') || !source.includes('.replace(/-/g, "_")')) {
  console.error("crudResourceKey must normalize kebab-case resources");
  process.exit(1);
}

if (!source.includes("buildRecipientFilterBar")) {
  console.error("app.js must expose buildRecipientFilterBar for member/sms filters");
  process.exit(1);
}

if (!fs.existsSync(path.join(__dirname, "..", "assets", "sms-editor.js"))) {
  console.error("sms-editor.js is missing");
  process.exit(1);
}

if (!fs.existsSync(path.join(__dirname, "..", "sms-settings.php"))) {
  console.error("sms-settings.php is missing");
  process.exit(1);
}

const indexSource = fs.readFileSync(path.join(__dirname, "..", "index.php"), "utf8");
if (!indexSource.includes('id="sms-settings"')) {
  console.error("index.php must include sms-settings section inside admin panel");
  process.exit(1);
}

if (!apiSource.includes("sms-query-lines") || !apiSource.includes("sms-check-deliveries")) {
  console.error("api.php must expose sms-query-lines and sms-check-deliveries");
  process.exit(1);
}

if (!apiSource.includes("sms-charge-debtors") || !apiSource.includes("sms-send-charge-reminders")) {
  console.error("api.php must expose sms-charge-debtors and sms-send-charge-reminders");
  process.exit(1);
}

if (!indexSource.includes('id="smsChargeReminderPanel"')) {
  console.error("index.php must include sms charge reminder panel");
  process.exit(1);
}

if (!apiSource.includes("'desk-assignments' => 'desk_assignments'")) {
  console.error("api.php must map desk-assignments to desk_assignments for CRUD");
  process.exit(1);
}

const initReportBuilderIndex = source.indexOf("const initReportBuilder");
const activateSectionIndex = source.indexOf("const activateSection");
const bootActivateIndex = source.indexOf("activateSection(initialSection)");
if (initReportBuilderIndex < 0 || activateSectionIndex < 0 || bootActivateIndex < 0) {
  console.error("report builder / activateSection boot symbols missing");
  process.exit(1);
}
if (!(initReportBuilderIndex < activateSectionIndex && activateSectionIndex < bootActivateIndex)) {
  console.error("initReportBuilder must be defined before activateSection, and activateSection before boot call");
  process.exit(1);
}
if (!apiSource.includes("report-catalog") || !apiSource.includes("resource === 'reports'")) {
  console.error("api.php must expose report-catalog and reports resources");
  process.exit(1);
}
if (!source.includes("formatKpiValue") || !source.includes("formatReportCell") || !source.includes("columnKinds")) {
  console.error("report preview must format KPI/cells by kind (not all numbers as money)");
  process.exit(1);
}
if (!/text-align:\s*center/.test(fs.readFileSync(path.join(__dirname, "..", "assets", "styles.css"), "utf8"))) {
  console.error("styles.css should center-align table cells");
  process.exit(1);
}

if (!indexSource.includes('id="accountMenu"') || !indexSource.includes('id="accountMenuTrigger"')) {
  console.error("index.php must include account menu dropdown in the topbar");
  process.exit(1);
}

if (!source.includes('window.addEventListener("hashchange"') && !source.includes("window.addEventListener('hashchange'")) {
  console.error("app.js must listen for hashchange to sync section routing");
  process.exit(1);
}

if (!source.includes('closest(".section.active")')) {
  console.error("data-table must lazy-load only when parent section is active");
  process.exit(1);
}

if (!apiSource.includes("resource === 'health'") && !apiSource.includes('$resource === \'health\'')) {
  console.error("api.php must expose health resource");
  process.exit(1);
}

const rangePath = path.join(__dirname, "..", "assets", "room-range.js");
const rangeSource = fs.readFileSync(rangePath, "utf8");
if (!rangeSource.includes("resolveRange") || !rangeSource.includes("MechinnoRoomRange")) {
  console.error("room-range.js must expose MechinnoRoomRange.resolveRange");
  process.exit(1);
}
// Evaluate helper and assert exclusive-end 2-hour selection (10:00 → 12:00).
global.window = global;
require(rangePath);
const Range = global.MechinnoRoomRange;
const freeSlots = ["10:00", "10:30", "11:00", "11:30", "12:00"].map((time) => ({
  time,
  end: Range.minutesToTime(Range.timeToMinutes(time) + 30),
  status: "free",
}));
const twoHours = Range.resolveRange({
  anchor: "10:00",
  clicked: "12:00",
  slotMinutes: 30,
  maxHours: 2,
  slots: freeSlots,
});
if (!twoHours.ok || twoHours.end !== "12:00" || twoHours.minutes !== 120) {
  console.error("room-range must treat second click as exclusive end (10:00–12:00 = 2h)", twoHours);
  process.exit(1);
}
if (!Range.inRangeDisplay("12:00", "10:00", "12:00") || Range.inRange("12:00", "10:00", "12:00")) {
  console.error("display range must include end button; booking range must exclude it");
  process.exit(1);
}
const endVisual = Range.slotPresentation({
  time: "12:00",
  status: "free",
  selectedStart: "10:00",
  selectedEnd: "12:00",
});
if (!endVisual.isEnd || !String(endVisual.label).includes("پایان")) {
  console.error("completed range must label exclusive end as پایان", endVisual);
  process.exit(1);
}
const tooLong = Range.resolveRange({
  anchor: "10:00",
  clicked: "12:30",
  slotMinutes: 30,
  maxHours: 2,
  slots: freeSlots.concat([{ time: "12:30", end: "13:00", status: "free" }]),
});
if (tooLong.ok) {
  console.error("room-range must reject ranges above maxHours");
  process.exit(1);
}

const bookingJs = fs.readFileSync(path.join(__dirname, "..", "assets", "room-booking.js"), "utf8");
const publicJs = fs.readFileSync(path.join(__dirname, "..", "assets", "room-public.js"), "utf8");
if (!bookingJs.includes("MechinnoRoomRange") || !publicJs.includes("MechinnoRoomRange")) {
  console.error("admin/team and public booking must use shared MechinnoRoomRange");
  process.exit(1);
}

console.log("Frontend smoke tests passed");
