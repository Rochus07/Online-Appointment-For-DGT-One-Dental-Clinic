---
name: taste-skill
description: >-
  Frontend design-taste pass for the DGT One Dental Center PUBLIC pages only —
  index.php now, and login/register/reset/terms if they later get a visual pass.
  NOT for the app shell or any modules/** screen (dashboards, tables, calendars,
  wizards, CRUD, billing). Use it when building or reworking a public page's
  look: it forces a one-line design read, sets VARIANCE/MOTION/DENSITY dials, and
  blocks the ~40 "AI slop" tells. Adapted from github.com/Leonxlnx/taste-skill
  (MIT); /DESIGN.md, /CLAUDE.md and pinned memories override it on every conflict.
---

# taste-skill — DGT adapter

The full anti-slop rule set lives in [`reference.md`](reference.md) (vendored
verbatim from the upstream project). **This file scopes and overrides it for this
codebase.** Read this first, then pull from `reference.md` only what the sections
below say to keep.

## 1. When this skill applies

- **Only public-facing pages:** `index.php` today; later `login.php`,
  `register.php`, `reset_password.php`, `terms.php` if they get a visual pass.
- **Never** the app shell (`includes/layout.php`, `sidebar.php`, `header.php`) or
  any `modules/**` page — admin dashboard, role dashboards, tables, calendars,
  booking wizard, billing invoice, CRUD forms. The upstream text says it itself:
  *"Not dashboards, not data tables, not multi-step product UI."* Those follow
  `DESIGN.md`'s `stat-tile` / `elevation` specs and nothing here.
- "Modernize / polish the dashboard" requests are governed by the
  `modern-elevated-aesthetic` memory + `DESIGN.md`, **not** this skill.

## 2. Authority order (when rules collide)

1. A direct instruction in the current chat.
2. `/DESIGN.md` + `/CLAUDE.md` + pinned memories (`dgt-design-system`,
   `no-cursor-driven-motion`, `no-page-subtitles`, `styling-tailwind-migration`,
   `homepage-services-carousel`, `landing-page-iteration-style`, …).
3. `reference.md` (the upstream rules).

`reference.md` never overrides 1 or 2.

## 3. Stack overrides — this is NOT the upstream stack

Upstream assumes React/Next/RSC + Tailwind v4 + Motion/GSAP + npm packages. This
project is **PHP + vanilla JS** (one IIFE per page, `nav.js` SPA swap),
**no build step, no npm, no framework**, **Tailwind v3 via the Play CDN**
(`includes/tailwind.php`, inline config).

**Ignore entirely:** reference.md §2.A (installing `@fluentui` / `@carbon` /
`shadcn` / etc.), §3.A (React/Next/RSC/`"use client"`), §3.B (`useMotionValue`),
§3.C (npm icon libraries), §3.F (`package.json` checks), §5.A/§5.B/§5.C
(GSAP / Motion-React code skeletons), §12 (block-library files), Appendices A–C
(package install commands).

**Translate their intent to the house patterns instead:**

- **Icons:** inline hand-authored `<svg>` is the house style and is *correct*
  here — this overrides §3.C ("never hand-roll SVG icons" / Phosphor-Tabler
  mandate). Keep one consistent stroke width per page.
- **Motion:** CSS transitions/keyframes + `IntersectionObserver` (the existing
  `.reveal` pattern). Scroll-linked effects use the existing **rAF-batched
  `scroll` listener** pattern already in `index.php` — this overrides
  reference.md's "never `window.addEventListener('scroll')`" (that rule targets
  React re-render cost, which does not exist here). Always honour
  `prefers-reduced-motion` (already wired site-wide).
- **No pointer/cursor-driven motion at all** (memory `no-cursor-driven-motion`):
  slow autonomous CSS loops only — stricter than upstream's "magnetic
  micro-physics when MOTION > 5".
- **Fonts:** keep the current system-font stack. Do **not** add Geist / Outfit /
  Satoshi / Cabinet Grotesk or any web font unless the user explicitly asks
  (overrides §4.1's "avoid Inter, prefer Geist…").
- Third-party JS stays **vendored**, never from a CDN (only CSS/Tailwind loads
  from a CDN here).

## 4. Visual overrides — `DESIGN.md` wins

- **Accent lock is already set:** DGT Blue `#006CC0` (primary), DGT Teal
  `#0086A0` (CTA + secondary), DGT Ink grey scale, navy `#102F51` for headings
  and the sidebar. Use only `dgt-*` Tailwind tokens. This *is* §4.2's
  "one accent, locked" rule — already satisfied, keep it.
- **No gradients as fill.** Hard project rule — overrides every reference.md
  mention of "restrained gradients", bento gradient cells, aurora/mesh, gradient
  hero art, or §4.7's "Bento Background Diversity → brand-appropriate gradient".
  The only tolerated case already in the code is the navy→teal photo *fallback*
  behind hero / service images; add no new ones.
- **DGT Blue and DGT Teal must never be two adjacent chart/category colours**
  (colour-blind separation floor — memory `dgt-design-system`).
- Off-black / off-white only — already true (`dgt-ink-50` canvas, navy text).
  This part of §4.2 aligns; keep it.
- Cards: on `index.php` follow what is already there (white, hairline,
  `border-t` accent on the MVW cards, ripple-fill hovers). The elevated
  `rounded-2xl` / `shadow-md` treatment is a documented **admin-dashboard-only**
  exception, not a public-page pattern.

## 5. Em-dash / separator rule

- Upstream §9.G bans `—` in all user-facing copy. Apply that to **new
  marketing / body copy you write for a public page**: use commas, periods,
  colons, parentheses.
- Do **not** run a codebase-wide find-and-replace. The middle-dot `·` separator
  used throughout the UI is fine (it is not an em-dash). Prose in `CLAUDE.md` /
  `DESIGN.md` / code comments is out of scope.

## 6. What to actively use from `reference.md`

- **§0 — Brief inference + a one-line "Design Read"** before any visual work on a
  public page. State it in chat.
- **§1 — the three dials.** State the values you are using. The DGT public-site
  read is: *local dental-clinic marketing site, trust-first, calm* → start near
  **`DESIGN_VARIANCE 5 / MOTION_INTENSITY 3 / VISUAL_DENSITY 3`** (the calm,
  light, white-canvas feel in `DESIGN.md`), not the upstream 8/6/4.
- **§4.3 + §4.7 — layout diversification and the hard layout rules:** hero fits
  the viewport, nav on one line ≤80px, **max 1 eyebrow per 3 sections** (the user
  had eyebrows removed — keep them gone), ≤2 consecutive image+text zigzag
  sections, bento cell count == content count, no section-layout family twice,
  **max 1 marquee** (`index.php` already has exactly one — the Services
  wordmark).
- **§4.5 — interactive states:** real loading / empty / error states; `:active`
  press feedback (already global in `tailwind.php`).
- **§4.9 — content density + the copy self-audit:** reinforces the
  `no-page-subtitles` memory — no descriptive paragraph under a heading; cut
  ruthlessly; re-read every visible string for AI-hallucinated cute copy.
- **§4.10 — quotes/testimonials:** ≤3 lines, real attribution, curly quotes.
- **§9 — AI TELLS (the whole blocklist):** no "Jane Doe" / fake avatar rows, no
  "Quietly trusted by" copy, no fake version numbers or locale strips in the
  footer, no `<div>`-based fake product screenshots, no AI-purple glow, no
  three-identical-feature-cards, no oversaturated accent.
- **§11 — Redesign protocol:** audit existing brand tokens / IA / copy first;
  never silently change nav labels, URL structure, form field `name`s, or
  legal / T&C copy (matches `CLAUDE.md`).
- **§14 — pre-flight check:** run it before calling a public page done, skipping
  the React/stack-specific items.

## 7. Provenance

`reference.md` is `skills/taste-skill/SKILL.md` from
<https://github.com/Leonxlnx/taste-skill> @ `main`, MIT-licensed
((c) 2026 Leonxlnx — see `LICENSE`), unmodified except for a vendoring header.
This `SKILL.md` is the DGT-specific adapter and is the file the skill loader uses.
