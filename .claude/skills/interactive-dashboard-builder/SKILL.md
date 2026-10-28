---
name: interactive-dashboard-builder
description: >-
  Build a self-contained, standalone HTML dashboard (Chart.js, dropdown/date
  filters, sortable table, KPI cards) as a one-off file to hand off or share —
  NOT for editing dgt_dental_clinic's own admin dashboard or any modules/**
  page. Use it when asked for a shareable dashboard/report file that works
  without a server. Adapted from
  github.com/w95/awesome-claude-corporate-skills (MIT); /CLAUDE.md and pinned
  memories override it on every conflict.
---

# interactive-dashboard-builder — DGT adapter

The full pattern library (base HTML template, KPI-card markup, Chart.js
line/bar/doughnut chart builders, filter + sortable-table wiring, the CSS
design tokens, and the large-dataset guidance) lives in
[`reference.md`](reference.md), vendored verbatim from the upstream skill.
**This file scopes it for this codebase — read this first.**

## 1. When this applies

Use this skill only when the ask is for a **standalone, shareable HTML
dashboard file** — a one-off analytics/report artifact the user will open
directly in a browser or hand to someone else (email attachment, USB drive,
a link), independent of the running app.

**Never** use it to build or edit anything inside the actual app:

- **Not** `modules/admin/dashboard.php` (the real admin dashboard) or any
  other `modules/**` page. That dashboard already has its own chart system —
  `assets/js/charts.js` (dependency-free inline-SVG `donutChart` /
  `barChart` / `lineChart`, no Chart.js), its own DGT-palette tokens, and its
  own bento card layout documented in `CLAUDE.md`'s design-system section and
  `DESIGN.md`. If asked to change the in-app dashboard, edit that page and its
  existing chart library directly — do not introduce Chart.js or this skill's
  markup into the running app.
- **Not** a chart destined for a Claude Artifact. An Artifact has its own CDN
  allowlist, native Mermaid support, and a required design pass — use the
  `dataviz` and `artifact-design` skills for that instead, not this one.

If a request is ambiguous ("make me a dashboard for X"), ask whether the
output is a standalone file to hand off, or a change to a page in the app —
the two have completely different implementations here.

## 2. The one real stack conflict — CDN scripts

This project's standing rule (`CLAUDE.md` → Conventions) is that third-party
JS shipped *as part of the app* must be vendored under `assets/js/vendor/`,
never pulled from a CDN. `reference.md`'s base template loads Chart.js from
`cdn.jsdelivr.net`.

**That's fine here** — it does not violate the project rule, because a
dashboard built with this skill is explicitly *not* part of the app's asset
pipeline; it's a standalone file that stands entirely on its own outside
`dgt_dental_clinic/`. Do not vendor Chart.js into `assets/js/vendor/` for
this, and do not add a `<script>` tag to any real app page.

## 3. Style notes

- The reference CSS uses a generic dark-header / blue-accent palette
  (`--color-1: #4C72B0`, etc.). Unless the user asks for the DGT clinic
  palette specifically, keep the upstream neutral palette — this is a
  general-purpose artifact, not a DGT-branded page. If the user does want it
  branded, swap in DGT Blue `#006CC0` / DGT Teal `#0086A0` / navy
  `#102F51` per the pinned `dgt-design-system` memory, but that's an explicit
  ask, not the default.
- Currency formatting in the reference (`formatValue()`) defaults to `$`.
  Swap to `₱` (peso) when the data is clinic revenue/billing, matching the
  app's own `peso()` convention.

## 4. Provenance

`reference.md` is `10-data-analytics/interactive-dashboard-builder/SKILL.md`
from <https://github.com/w95/awesome-claude-corporate-skills> @ `main`,
MIT-licensed ((c) 2026 Enrike — see `LICENSE`), unmodified except for a
vendoring header. This `SKILL.md` is the DGT-specific adapter and is the file
the skill loader uses.
