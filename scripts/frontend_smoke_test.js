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

if (!apiSource.includes("sms-query-lines") || !apiSource.includes("sms-check-deliveries")) {
  console.error("api.php must expose sms-query-lines and sms-check-deliveries");
  process.exit(1);
}

if (!apiSource.includes("'desk-assignments' => 'desk_assignments'")) {
  console.error("api.php must map desk-assignments to desk_assignments for CRUD");
  process.exit(1);
}

console.log("Frontend smoke tests passed");
