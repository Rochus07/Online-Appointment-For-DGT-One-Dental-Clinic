---
name: DGT-One-Dental-design-system
version: 0.1
description: >
  The visual system for the DGT One Dental Center management app. A calm,
  professional, light-theme clinic tool built on the clinic logo's palette:
  DGT Blue (#006CC0) as the primary accent and navy heading/sidebar colour,
  DGT Teal (#0086A0) for calls-to-action and secondary accents, and a cool
  metallic-grey neutral scale for surfaces, borders and text. White canvas,
  12px cards, hairline borders, subtle shadows, generous whitespace.
  Restraint over decoration — saturated colour is for small marks and accents,
  never large blocks.

# Tokens live in includes/tailwind.php (Tailwind Play CDN config). Reference them
# by their Tailwind class name — e.g. text-dgt-blue-900, border-dgt-ink-200.

colors:
  # --- DGT Blue (from the blue figure + top-left dot in the logo) ---
  # Primary accent, links, focus rings, single-series charts, and — at 900 —
  # the navy used for every heading and the sidebar.
  dgt-blue:
    50:  "#e9f2fb"
    100: "#cee3f6"
    200: "#a0c8ec"
    300: "#66a7e0"
    400: "#2683d2"
    500: "#006cc0"   # the logo blue
    600: "#0056a0"   # link / focus / hover
    700: "#0a457e"
    800: "#0d3a66"
    900: "#102f51"   # headings, sidebar, chart center numbers

  # --- DGT Teal (the teal figure + centre dot + top-right dot) ---
  # Primary CTA buttons, secondary badges, the patient-facing hero banner.
  dgt-teal:
    50:  "#e4f3f5"
    100: "#c1e5ea"
    200: "#8ad0d9"
    300: "#49b4c1"
    400: "#1897a8"
    500: "#0086a0"   # the logo teal — accents, borders, badges
    600: "#016f87"   # CTA button rest
    700: "#075b6f"   # CTA button hover / active
    800: "#0b4a5a"
    900: "#0d3b49"

  # --- DGT Ink (cool metallic grey, tuned toward the logo's silver ring) ---
  dgt-ink:
    50:  "#f5f7f9"   # app canvas / sub-card fill
    100: "#e9edf1"   # table header fill, gridlines, faint dividers
    200: "#d6dee4"   # default 1px border on cards, inputs, tiles
    300: "#b4c1cb"   # "Other" chart bucket, disabled marks
    400: "#8a98a4"   # tertiary text, captions, axis labels
    500: "#647380"   # secondary text, field labels (== the logo grey)
    600: "#4a5661"
    700: "#37424b"   # body text
    800: "#262e36"
    900: "#161d24"   # max ink (rarely needed — headings use dgt-blue-900)

  roles:
    canvas:          "dgt-ink-50"     # page background
    surface:         "#ffffff"        # cards
    surface-sunken:  "dgt-ink-50"     # nested sub-cards, chart wells
    border:          "dgt-ink-200"    # 1px hairline on everything
    divider:         "dgt-ink-100"    # section rules inside a card
    heading:         "dgt-blue-900"   # h1 / h2 / h3, card titles
    text:            "dgt-ink-700"    # body
    text-muted:      "dgt-ink-500"    # labels, secondary
    text-subtle:     "dgt-ink-400"    # captions, empty states
    link:            "dgt-blue-600"
    focus-ring:      "dgt-blue-500"
    accent:          "dgt-blue-500"   # primary accent mark
    cta:             "dgt-teal-600"   # primary action button
    cta-hover:       "dgt-teal-700"

  # Functional status — reserved for good/bad meaning, never used as a series
  # colour. Ship with an icon + label, never colour alone.
  status:
    good:      "#0ca30c"   # ok / paid / positive delta        soft: "#e4f4ec"
    warning:   "#c98500"   # pending / partial / attention      soft: "#fbeed6"
    critical:  "#d03b3b"   # error / unpaid / cancelled         soft: "#f9e4e4"
    info:      "dgt-blue-600"  # approved / neutral notice      soft: "dgt-blue-50"

  # Categorical chart palette — DGT blue in slot 1, then a CVD-validated hue
  # order (checked with the dataviz validator on a white surface: every
  # adjacent pair clears the colourblind + normal-vision floors). Assign by
  # entity in this fixed order; never cycle past 8 — fold the tail into "Other"
  # (dgt-ink-300). Lives in assets/js/charts.js as PALETTE.
  chart-categorical:
    - "#006cc0"   # 1  DGT blue
    - "#eb6834"   # 2  orange
    - "#1baf7a"   # 3  aqua-green
    - "#eda100"   # 4  amber
    - "#e87ba4"   # 5  pink
    - "#008300"   # 6  green
    - "#4a3aa7"   # 7  violet
    - "#e34948"   # 8  red
  chart-single-series: "#006cc0"   # every line / single-metric bar
  chart-other:         "#b4c1cb"

typography:
  # One family — the app's existing system sans. No display or serif face.
  family: "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
  h1:         { size: 22px, weight: 600, color: heading }
  h2:         { size: 15px, weight: 600, color: heading }   # card titles
  section-label: { size: 11px, weight: 600, transform: uppercase, tracking: 0.04em, color: text-muted }  # sub-card headers, tile labels
  body:       { size: 13px, weight: 400, color: text }
  body-sm:    { size: 12px, weight: 400, color: text-muted }
  stat-value: { size: 24px, weight: 600, color: heading }   # proportional figures, NOT tabular
  table-cell: { size: 13px, weight: 400 }
  table-head: { size: 11px, weight: 600, transform: uppercase, tracking: 0.03em, color: text-muted }
  button:     { size: 13px, weight: 600 }
  # tabular-nums ONLY in columns that align vertically (table money cells,
  # axis ticks) — never on a large standalone number.

rounded:
  sm:   4px    # chips, status badges, chart dots
  md:   6px    # buttons, inputs, selects, small controls
  lg:   8px    # sub-cards, stat tiles, list wells
  xl:   12px   # content cards (the main card radius)
  pill: 9999px # tab toggles, count chips, avatars

spacing:
  # 4px base. Card padding 20px (p-5). Card→card gap 16px (gap-4 / mt-4).
  # Grid gutters 16px. Tile/sub-card padding 14–16px. Section rule pb 12px.
  card-padding: 20px
  card-gap: 16px
  tile-padding: "14px 16px"

elevation:
  # Depth comes from the surface ladder + hairline borders, not heavy shadow.
  flat:      "no border, no shadow — body text, page background"
  card:      "bg #fff, 1px dgt-ink-200 border, shadow-sm (0 1px 2px rgba(16,47,81,.06))"
  sunken:    "bg dgt-ink-50, 1px dgt-ink-100 border — nested wells, chart areas"
  focus:     "2px dgt-blue-500 ring at ~40% — focused input / button"
  # No neumorphism, no glassmorphism, no gradient fills on large blocks.

components:
  button-cta:
    bg: dgt-teal-600
    hover: dgt-teal-700
    text: "#ffffff"
    rounded: md
    padding: "9px 15px"
    font: button
  button-primary:        # blue variant, for primary nav-level actions
    bg: dgt-blue-600
    hover: dgt-blue-700
    text: "#ffffff"
    rounded: md
  button-ghost:
    bg: transparent
    border: "1px dgt-ink-200"
    text: dgt-blue-800
    hover-bg: dgt-ink-50
    rounded: md
  button-danger:
    bg: status.critical
    text: "#ffffff"
    rounded: md
  card:
    bg: "#ffffff"
    border: "1px dgt-ink-200"
    rounded: xl
    padding: 20px
    shadow: sm
  card-head:
    layout: "flex items-center justify-between, border-b 1px dgt-ink-100, pb 12px, mb 16px"
    title: h2
  sub-card:
    bg: dgt-ink-50
    border: "1px dgt-ink-100"
    rounded: lg
    padding: 16px
  stat-tile:
    bg: "a ~12% flat tint of the accent hue (never a gradient)"
    border: "1px dgt-ink-200, plus a 4px left edge in the accent hue"
    rounded: lg
    padding: "14px 16px, pr-11 to clear the icon chip"
    icon-chip: "absolute top-right, 32px, rounded-lg, ~19% accent tint bg, accent-stroke icon"
    label: section-label
    value: "stat-value, in dgt-blue-900 (or a status hue when the number itself means good/bad, e.g. an outstanding balance in amber / red)"
    # optional: sub (body-sm, text-subtle) and a signed delta vs a named period.
    # Flat tint only, never a gradient. Accent = the entity's chart hue, or a
    # status hue for a good/bad number. Matches the patient Treatment & Billing
    # and admin History Logs metric tiles.
  table:
    head-row: "bg dgt-ink-50, th = table-head, 1px dgt-ink-100 bottom border, whitespace-nowrap"
    cell: "table-cell, 1px dgt-ink-100 bottom border, px 12px py 10px, align middle"
    wrapper: "overflow-x-auto"
    capped-list: "add max-h + overflow-y-auto + sticky th + a count chip"
  input:
    bg: "#ffffff"
    border: "1px dgt-ink-200"
    rounded: md
    padding: "8px 10px"
    font: body
    focus: "border dgt-blue-500"
    invalid: "border status.critical, bg status.critical-soft (.is-invalid marker class)"
  field:
    label: section-label   # (sentence case is also fine for form labels)
    error: ".field-err — status.critical, 11px, 600"
  badge:
    rounded: pill
    padding: "3px 10px"
    font: "11px / 700 / capitalize"
    tones:   # standardized appointment-status set (migration 027, 2026-09)
      pending:     "yellow  — bg status.warning-soft, text status.warning"
      approved:    "blue    — bg status.info-soft, text status.info  (was 'scheduled')"
      # no 'ongoing' badge — removed 2026-09; a checked-in visit stays 'approved' until completed
      completed:   "green   — bg status.good-soft, text status.good"
      paid:        "green   — bg status.good-soft, text status.good"
      rescheduled: "gray    — bg steel-100, text steel-500  (was teal)"
      cancelled:   "red     — bg status.critical-soft, text status.critical"
  tab:
    rest: "bg #fff, 1px dgt-ink-200 border, text dgt-ink-500, rounded pill, px 16 py 8"
    active: "bg dgt-blue-900, border dgt-blue-900, text #fff"
  sidebar:
    bg: dgt-blue-800                              # lifted off near-black so it reads "blue", not "ink"
    brand-cap: "bg dgt-teal-600"                  # the logo/brand block at the top is a teal band
    text: "rgba(255,255,255,.70)"
    item-hover: "rgba(255,255,255,.09)"
    item-active: "bg dgt-blue-500, text #fff"     # both logo hues present: teal cap + blue active marker
    footer: "border-t rgba(255,255,255,.10); avatar tile bg rgba(255,255,255,.10)"
  brand-mark:
    treatment: "the dgt_logo.jpg image; when set as type, a blue→teal gradient on 'DGT'"
  chart:
    surface: "#ffffff (or sub-card dgt-ink-50)"
    gridline: dgt-ink-100
    axis-label: dgt-ink-400
    line: "2px, dgt-blue-500, soft same-hue area fill fading to 0"
    marks: "thin; 4px rounded data-ends; ≥8px markers; 2px surface gap between adjacent fills"
    legend: "always present for ≥2 series; ≤4 series also direct-labelled"
    part-to-whole: "donut, ≤6 coloured segments + an 'Other' fold; a lone 100% category still renders as a full ring (the user wants the circle kept); only a genuinely empty period falls back to a text line"
---

## Overview

The DGT app is a **light-theme management tool** — dense tables, forms, modals,
a sidebar, dashboards, an invoice. The identity is the **clinic logo**: two
figures (one DGT Blue, one DGT Teal) forming a tooth, circled by a silver ring.
Those three colours are the whole palette.

- **DGT Blue `#006CC0`** — the primary accent. Links, focus rings, active nav
  markers, single-series charts, the first categorical chart hue. Its darkest
  step **`dgt-blue-900` `#102F51`** is the navy used for every heading and the
  sidebar — so headings read as "brand", not just "dark".
- **DGT Teal `#0086A0`** — calls-to-action (`dgt-teal-600` for AA contrast on
  the button), secondary badges, the patient "Our Services" hero banner.
- **DGT Ink** — a cool metallic-grey scale for canvas, surfaces, borders and
  text. `dgt-ink-50` canvas, white cards, `dgt-ink-200` hairline borders,
  `dgt-ink-700` body text, `dgt-ink-500` labels (that step is the logo's grey).

**Blue and teal never sit next to each other as two chart categories** — they
fail the normal-vision separation floor (ΔE 10, need ≥15). The brand reads
through the chrome (navy headings, blue nav, teal CTA, the tile accent edges),
not through pairing the two hues in a plot.

### Feel

Calm, clinical, trustworthy. White canvas, 12px cards, 1px hairline borders,
`shadow-sm` only. Saturated colour appears as **small marks and accents** — a
3px tile edge, a chart line, a badge — never as a large filled block. No
gradients on panels, no glassmorphism. Generous whitespace; every card has one
title and clear hierarchy.

## Colours

Use the Tailwind classes: `bg-dgt-blue-500`, `text-dgt-blue-900`,
`border-dgt-ink-200`, `bg-dgt-ink-50`, `text-dgt-ink-500`, `text-dgt-ink-700`.

| Job | Token |
|---|---|
| Page background | `bg-dgt-ink-50` |
| Card | `bg-white border border-dgt-ink-200` |
| Nested well / chart area | `bg-dgt-ink-50 border border-dgt-ink-100` |
| Heading (all levels) | `text-dgt-blue-900` |
| Body text | `text-dgt-ink-700` |
| Label / secondary | `text-dgt-ink-500` |
| Caption / empty state | `text-dgt-ink-400` |
| Link / focus | `text-dgt-blue-600` / `focus:border-dgt-blue-500` |
| Primary action button | `bg-dgt-teal-600 hover:bg-dgt-teal-700 text-white` |
| Ghost button | `border border-dgt-ink-200 text-dgt-blue-800 hover:bg-dgt-ink-50` |

Status colours stay functional and carry an icon + label:
`good #0ca30c`, `warning #c98500`, `critical #d03b3b`, `info` = `dgt-blue-600`.

### Charts

`assets/js/charts.js` `PALETTE` is the validated categorical set. Pass a stable
`color` per segment keyed on the **entity** (role, payment method, service) so a
filter never repaints the survivors. Single-series line/bar → `#006cc0`
everywhere. A part-to-whole panel with one non-zero category renders as a
labelled line, not a one-slice donut.

## Typography

One family (the system sans). Headings 600 in `dgt-blue-900`. Section labels and
table headers are 11px 600 uppercase with slight tracking in `dgt-ink-500`.
Stat-tile values are 24px 600 with **proportional** figures. `tabular-nums` only
in aligned columns (money cells, axis ticks).

## Visual hierarchy & craft

Distilled from the framework-agnostic parts of Dammyjay93's `interface-design`
skill (`github.com/Dammyjay93/interface-design`, MIT) — a craft / hierarchy guide
for **app screens** (dashboards, tables, forms, settings), the inverse of
`taste-skill`'s public-page scope. Its React / shadcn / CVA / Radix / Tailwind-v4
/ dark-mode machinery does **not** apply to this PHP + vanilla-JS + Tailwind-v3
stack; only the principles below were kept, rewritten against the real building
blocks (`tile()`, `openModal()`, the per-file `$class`-string + JS `const`
pattern, `window.UI`). The upstream skill was **not** vendored — same treatment
as the `ui-ux-pro-max` repo above.

**Intent before pixels.** Before building or reworking a screen, state in one
line: *who* opens it (a receptionist mid-rush, an admin reviewing the day),
*what verb* they're there for (approve, bill, find), and *how it should feel*
(calm, dense, fast). Every layout / weight / spacing choice traces back to that.
"It's common" or "it works" is not a reason.

**One focal point per view.** Each screen has one thing the user came to do —
that element wins through size, contrast, or the whitespace around it; everything
else is deliberately demoted. A screen where every card, number and button
competes equally reads as generated. Name the focal element before building
(the Proceed-to-Billing button on the cashier queue; the `<h1>` + primary action
on a CRUD page).

**Weight + colour beat size.** Three tiers from one 14px size: `value` 600 /
`dgt-blue-900`, `label` 500 / `dgt-ink-500`, `meta` 400 / `dgt-ink-400` — already
the `tile()` and table convention. Reach for the weight / opacity lever before
bumping font-size.

**Type scale is a ratio (~1.25), not per-item feel:** `caption 11 · body 13 ·
label 12 · card-title 15–17 · h1 22`. The app keeps its **system font stack** by
project decision — the hierarchy work is done through weight + colour, which is
the stronger lever anyway. No display face.

**Density is one decision, held everywhere.** This app is a *workbench* tool, not
a brochure: `p-5` cards, `px-3 py-2.5` table cells, `gap-3`/`gap-4`. Pick the
number once and repeat it — mixed paddings and gaps are the clearest "no system"
tell.

**Breathe unevenly.** Group tightly-related things, then put real air between
groups. Same card size + same gap + same density in every region is the sound of
no one deciding — vary the rhythm on purpose (a dense filter row over open
content; a wide hero beside compact tiles).

**Proportions state a relationship.** The 244px sidebar vs full-bleed content
says "nav serves content". If you can't say what a width or ratio is
communicating, it isn't.

**~60 / 30 / 10.** Most of a screen is neutral structure (white / `dgt-ink-50` /
hairlines); a secondary tone; ~10 % saturated accent — and that accent
*communicates* (status, the one primary action, an entity hue), never
decoration. One accent used with intent beats three used without.

**The checks — run before showing a screen:**
- **Swap test** — swap the layout for a plain template: would anything feel
  lost? Where it wouldn't, you defaulted.
- **Squint test** — blur your eyes: hierarchy still readable, nothing harsh
  jumping out.
- **Signature test** — point to specific elements that make this *this clinic's*
  tool, not any admin panel.
- **Token test** — the classes / tokens read as this product's, not `gray-200`
  soup sprayed on every element.

## Icons

Icons are **hand-authored inline `<svg>`** — the house style (this overrides
`taste-skill`'s "never hand-roll SVG icons"). No icon font, no icon package, no
AI-generated icons. Conventions, mostly already followed (e.g. the dashboard
`DASH_ICON` set):

- `viewBox="0 0 24 24"`; draw at 24, check at 16 and 20.
- `stroke="currentColor"` (or `fill="currentColor"`) so colour inherits — never
  a hardcoded hex on an icon path.
- One stroke width per visual layer (`1.9`–`2` for line icons); outlined icons
  use `stroke-linecap="round" stroke-linejoin="round"`.
- One style per hierarchy level — don't mix filled and outlined at the same
  level.
- Decorative icon next to visible text → `aria-hidden="true"`. Meaningful
  standalone icon (icon-only button) → an `aria-label` or `sr-only` text (see
  *Accessibility*).
- 🦷 as a **fallback** for a missing service image is a deliberate, documented
  exception — not a licence to use emoji as UI icons anywhere else.
- The **sidebar** (`includes/sidebar.php`) is the one place that uses raster
  icons: a small set of filled Lucide-style PNGs in `assets/icons/`, rendered
  `<img … class="brightness-0 invert">` white on the navy rail. Everywhere else
  is inline `<svg>`. There is no `icon()` helper or shared `.svg` set — the old
  one was removed 2026-09 (unreferenced).

## Layout & shape

- Card radius **`rounded-xl` (12px)**; sub-cards / tiles **`rounded-lg` (8px)**;
  controls **`rounded-md` (6px)**; badges / tabs **pill**.
- Card padding **20px** (`p-5`); card→card gap **16px** (`mt-4` / `gap-4`).
- `$PAGE_FULL = true` on every module page (full-bleed content column).
- **Growing lists** (blocked dates, upcoming appointments, discount list): cap
  with `max-h-[…]` + `overflow-y-auto` + sticky `<th>` + a count chip, and keep
  the list alone in its own grid column. Never a full-width card below an
  uneven `items-start` grid.
- **Concentric radius** — a rounded child inside a rounded parent gets
  `child radius ≈ parent radius − padding`. A `rounded-xl` card with `p-4` holds
  `rounded-md` / `rounded-lg` inner tiles, not another `rounded-xl`. Matching
  radii on parent and child is the most common thing that makes a card look
  slightly off.
- **Don't spray the same long class string.** On the second real reuse, hoist it
  to a per-file `$var` (PHP) mirrored as a JS `const`, or onto `window.UI` if
  it's cross-page — the established pattern. The same 14-class string
  copy-pasted onto nine elements is a missing token, not styling.

## Elevation

Surface ladder + hairlines, not shadow. `card` = white + `dgt-ink-200` border +
`shadow-sm`. `sunken` = `dgt-ink-50` + `dgt-ink-100` border. Focus = a
`dgt-blue-500` ring.

**The ladder is whisper-quiet.** Each step up (page → card → popover) is a small
shift you feel rather than see: `dgt-ink-50` canvas → white card → a dropdown /
popover **one visible step above** its parent (a slightly stronger shadow or a
ring — never blending into it). Inputs read as *inset* — a `dgt-ink-200` hairline
on white; they receive content, they don't lift. Border intensity is a
progression matched to what the boundary is worth: `dgt-ink-100` for a faint
in-card divider, `dgt-ink-200` for a card / input edge, the `dgt-blue-500` ring
for focus. If a border is the first thing you notice, it's too strong — reach for
whitespace and a tonal shift before a line.

**The navy sidebar is a deliberate exception** to "a sidebar shares the canvas
background" — it is `dgt-blue-800` because the brand lives in the chrome (navy
rail + teal cap). Everywhere else, don't tint a region a different hue to
separate it; shift lightness and let a hairline do the work.

**Exception — the admin dashboard** (`modules/admin/dashboard.php`) runs a more
elevated treatment the user asked for: `rounded-2xl`, `shadow-md` at rest,
`hover:-translate-y-1 hover:shadow-xl` on interactive cards. See the migration
note below. Don't propagate that to other pages without the user asking.

## Design tokens & component state

Distilled from the same repo's `design-system` skill
(`github.com/nextlevelbuilder/ui-ux-pro-max-skill`, MIT). Its CSS-variable token
generator, shadcn/HSL Tailwind config and slide-deck system were not taken — the
project deliberately runs an **inline hex Tailwind config with no `:root`
custom-property layer** (the leftover `:root` block in `tailwind.php` is dead
weight kept only for cheapness). What carries over is the vocabulary and the
state discipline.

**Three token layers — this project already has all three, just not as CSS vars:**

| Layer | Where it lives here | Change it when |
|---|---|---|
| **Primitive** — raw values | the `dgt.blue` / `dgt.teal` / `dgt.ink` numeric scales in `includes/tailwind.php` | almost never (foundational) |
| **Semantic** — purpose aliases | the `roles:` map in this file's frontmatter (`canvas`, `surface`, `border`, `divider`, `heading`, `text`, `text-muted`, `link`, `focus-ring`, `cta`) | a palette-wide rethink |
| **Component** — per-component specs | the `components:` map in this file's frontmatter (`button-cta`, `card`, `card-head`, `sub-card`, `stat-tile`, `table`, `input`, `badge`, `tab`, `sidebar`, `chart`) | one component needs to deviate |

Reach for the **semantic role or the component spec first**; drop to a raw
`dgt-*` step only when neither fits, and **never a raw hex in markup or
JS-built HTML**. Two standing exceptions, both already documented: a chart
segment's **entity hue** (assigned by entity from `charts.js` `PALETTE`, not a
token) and the admin dashboard's **8-digit-hex tile tint** (`accent + '1f'` —
the accent there is an arbitrary entity hue, not a Tailwind colour).

**Interactive state — order of precedence** when more than one applies:

```
disabled  >  loading  >  active  >  focus  >  hover  >  default
```

- `disabled`: the real `disabled` attribute (+ `aria-disabled` on a non-form
  control), ~0.4 opacity, `cursor-not-allowed`, `pointer-events: none`.
- `loading`: `aria-busy="true"` + an `sr-only` "Loading…", the trigger
  non-interactive, contents at ~0.7 opacity or swapped for a spinner.
- `error` (fields): red border + `aria-invalid` + a `role="alert"` message
  below, cleared on a valid value — what `wireRefCheck()` / `wirePhoneField()`
  already do, plus the ARIA (see *Accessibility*).

**Speccing a new component:** write it as a Property × state matrix
(Background / Text / Border / Shadow across Default / Hover / Active / Disabled)
and add it to the `components:` map — that is the house format, as the existing
`stat-tile` and `tab` entries show.

**Reference implementation:** the shared `openModal()` / `closeModal()` /
`toast()` in `app.js` and `#toastRoot` in `layout.php` carry the modal-focus and
`aria-live` parts of the three sections above — focus moved into the dialog and
returned to the trigger on close, a Tab trap, `aria-labelledby` from the `<h3>`,
`role="status"`/`alert` on toasts, `#toastRoot` an `aria-live` region. (A wider
2026-09 hardening pass on `modules/admin/dashboard.php` itself — `aria-hidden`
SVGs, fraction-bar/donut text equivalents, CLS `min-height`s, `aria-busy` — was
reverted with that page's restyle; re-apply those patterns from the sections
above when reworking other pages.)
The dashboard `HUE` metric-accent map (one hue per operational metric) is part
of the same "entity hue, not a token" exception as the chart palette.

## Accessibility

WCAG **AA** is the floor — 4.5:1 for body text, 3:1 for large text and UI marks
(the palette in the frontmatter is already tuned to clear it). The rules below
keep the shared helpers accessible as pages are reworked. (Distilled from the
framework-agnostic parts of the `ui-styling` skill —
`github.com/nextlevelbuilder/ui-ux-pro-max-skill`, MIT; its shadcn/React/
build-step machinery does not apply to this PHP + vanilla-JS stack, so only
these principles were kept.)

**Keyboard & focus**

- Never remove a focus outline without replacing it. Use `focus-visible:` for
  ring styles (a mouse click shouldn't flash a ring, a Tab should); the global
  `focus: 2px dgt-blue-500 ring` is the default.
- Anything clickable must be Tab-reachable in a logical order. A clickable
  non-button — a drill-down card, a click-to-flip status badge — needs
  `role="button"`, `tabindex="0"` and an Enter/Space handler. The admin
  dashboard `tile()` and the Settings promo badges are the reference.
- `openModal()` traps focus: on open move focus into the dialog (first field, or
  the dialog itself), keep Tab within it, close on `Esc`, and **return focus to
  the trigger** on `closeModal()`. The dialog carries `role="dialog"`
  `aria-modal="true"` and an `aria-labelledby`/`aria-label` from its title.
- The patient notification bell and any dropdown: `Esc` closes and restores
  focus to the trigger; the trigger carries `aria-expanded`.
- `.tab` rows are a tablist — `role="tablist"` on the row, `role="tab"` +
  `aria-selected` on each, arrow keys move between them.

**Screen readers**

- Icon-only controls (`.modal-close` X, the bell, an icon-only row action) need
  an `aria-label` or an `sr-only` `<span>`. A button with visible text does not.
- `toast()` renders into an `aria-live` region (`polite`, or `assertive` for an
  error) — set once on `#toastRoot` in `layout.php` so every toast is announced.
- Semantic elements only — `<button>`, `<nav>`, `<table>` with `<th scope>` —
  never a click-handled `<div>`.
- Add a "Skip to content" link (`sr-only` until focused → `#app-content`) when
  `layout.php` / `header.php` get their design pass.

**Forms**

- Every input has a `<label for>` or an `aria-label`. The `REQ` marker
  (`<span class="text-danger"> *</span>`) is followed by an `sr-only`
  "(required)".
- When `wireRefCheck()` / `wirePhoneField()` / the booking + billing validators
  add `.is-invalid` and un-hide a `.field-err`, they also set
  `aria-invalid="true"` on the field and `aria-describedby` → the `.field-err`
  id, so the error is announced, not only shown in red.
- Group related fields (a Contact Information block, the split-payment amounts)
  in a `<fieldset>` with a `<legend>`.

**Motion**

- Every transition / animation stays behind the site-wide
  `prefers-reduced-motion` off-switch in `tailwind.php`. A raw `@keyframes` loop
  with no reduced-motion guard is a regression.

## Interaction & performance

Web/desktop rules distilled from the same repo's `ui-ux-pro-max` skill
(`github.com/nextlevelbuilder/ui-ux-pro-max-skill`, MIT) — its native-mobile
material (safe areas, haptics, bottom-nav, 44 pt touch targets, per-OS gesture
conventions) does not apply to this desktop clinic tool, and the skill's own
Python search database was not vendored; only the stack-agnostic web rules below
were kept. They are targets to hold as pages are reworked, not a claim the app
already complies everywhere.

**Pointer & state**

- Interactive elements get `cursor-pointer` and a visible `:hover` **and**
  `:focus-visible` state — never hover as the only affordance for something
  clickable (a pure reveal like the `index.php` service cards, which aren't
  themselves links, is fine).
- Pointer targets stay ≥ 24×24 CSS px (WCAG 2.2) — the dense table row-action
  buttons already clear this through padding; keep it when shrinking a control.
- Disabled controls carry the real `disabled` attribute, ~0.4 opacity and
  `cursor-not-allowed` — never a live-looking button that does nothing.
- Read-only ≠ disabled: a `readonly` field (the `receipt.php` invoice) stays
  full-contrast, visually distinct from a greyed-out disabled one.

**Async & layout stability**

- Every async action shows feedback: disable the trigger + a spinner / `Loading…`
  for a fetch, a skeleton for anything past ~1s. The dashboard cards and drill
  modals are the reference.
- Reserve space so content doesn't jump (CLS): give every `<img>` (service
  photos, avatars) a `width`/`height` or an `aspect-[…]` box, and keep a
  min-height on a table / well that swaps between `Loading…` and rows.
- `loading="lazy"` on below-the-fold images (service grids, records lists).
- Debounce / throttle high-frequency handlers — the 400 ms `wireRefCheck()` and
  the rAF-batched `index.php` scroll listener are the pattern.

**Motion**

- Animate `transform` / `opacity` only — never `width` / `height` / `top` /
  `left` (they reflow). A one-shot width-fill bar is tolerable; a loop is not.
  `transition: all` is banned — name the exact properties.
- Exit faster than enter (~60–70 % of the enter duration) so dismissal feels
  responsive; the `openModal()` / `closeModal()` pair already leans this way.
- One duration copied onto every transition is a smell — pick timing by the
  distance and weight of what's moving. Stagger a list / grid entrance
  ~30–50 ms per item (already done on `index.php`).
- **Should it animate at all?** A control the user hits dozens of times a shift
  (a table sort, a `.tab`) gets no transition or a near-instant one — animation
  there just reads as lag. Modals, drawers, toasts and the `nav.js` swap get
  real motion.
- **Durations stay under ~300 ms for UI:** press feedback 100–160 ms, tooltip /
  popover 125–200 ms, dropdown 150–250 ms, modal / drawer 200–300 ms. A 180 ms
  dropdown feels more responsive than a 350 ms one.
- **Custom ease-out, never `ease-in`.** `cubic-bezier(.23,1,.32,1)` for anything
  entering or responding to a click; `ease-in` holds the first frame — the
  moment the user is watching — and feels sluggish.
- **Nothing appears from nothing** — start a fade-in at `scale(.95)` +
  `opacity 0`, never `scale(0)`. A popover scales from its trigger
  (`transform-origin`); a modal stays centred.
- **`:active` press** — `transform: scale(.97)` (never below `.95`); the global
  `button` rule in `tailwind.php` already does a lighter version.

**SPA navigation (`nav.js`)**

- After a partial swap, move focus to the `#app-content` region so a keyboard /
  screen-reader user lands in the new page, not back at `<body>`.
- `window.reloadPage()` after a mutation, never `location.reload()` — it keeps
  scroll and in-page state (already the rule in `CLAUDE.md`).

**Tables & charts**

- A `.sortable` header carries `aria-sort` (`ascending` / `descending` /
  `none`) matching its current `.sorted` state.
- Truncated cells (`.cell-clip`, `truncate`) carry a `title=` with the full
  value; prefer wrapping to truncation where the column can afford it.
- A chart card carries a short `aria-label` summarising its takeaway, and the
  data is always also reachable as a table (the dashboard donut → its drill-down
  is the model). Gridlines stay low-contrast (`dgt-ink-100`); never encode a
  series by colour alone (status hues already ship with an icon + label).

## Do / Don't

**Do**
- Name the one focal element of a screen before building it, and make it win.
- Anchor every heading and the sidebar on `dgt-blue-900`.
- Use `dgt-teal-600` for the one primary action on a screen.
- Keep saturated colour to small marks: tile edges, chart lines, badges, dots.
- Keep the donut visuals — a lone 100% category renders as a full ring, not a
  text summary. Only an empty period drops to a line.
- Give a stat tile a label + value; add a delta only when there's a real
  comparison period.
- Give every icon-only button an `aria-label`; trap focus in `openModal()` and
  return it to the trigger on close; keep `aria-invalid` + `aria-describedby` in
  sync with `.is-invalid` / `.field-err`.
- Reserve layout space for images and `Loading…`→rows swaps (CLS); animate only
  `transform` / `opacity`; give `.sortable` headers an `aria-sort`.
- Bind markup to a semantic role or a component spec, not a raw `dgt-*` step;
  respect the state precedence (`disabled > loading > active > focus > hover`).

**Don't**
- Don't put DGT Blue and DGT Teal next to each other as two chart series.
- Don't hardcode a hex value in markup or JS-built HTML — use a `dgt-*` utility,
  bar the two documented exceptions (chart entity hues, the dashboard tile tint).
- Don't ship a click-handled `<div>`, an unlabelled icon button, or a
  `focus:outline-none` with no `focus-visible` replacement.
- Don't animate `width` / `height` / `top` / `left`, rely on `:hover` as the
  only affordance for something clickable, or leave a disabled-looking control
  that isn't actually `disabled`.
- Don't use gradient-filled KPI blocks. A stat tile carries a flat ~12% tint
  of its accent hue (see `stat-tile`) — that's the ceiling; no full-strength
  saturated card fills.
- Don't reuse a status colour (green/amber/red) as a chart series hue.
- Don't add a descriptive subtitle paragraph under a page heading, or a
  breadcrumb row. A small `N total · N active` stat line is fine.
- Don't introduce a second type family or a display/serif face.
- Don't ship a flat screen (every card / number / button the same size and
  weight, no focal point) or a monotone layout (same card size + gap + density
  everywhere). Vary the rhythm; separate tiers by weight + colour, not size
  alone.
- Don't mix depth strategies on one screen — hairline borders are the house
  default; the admin dashboard's `shadow-md` lift is a documented per-page
  exception, not a licence to blend the two elsewhere.
- Don't match a child's border-radius to its parent's (concentric radius), and
  don't leave a control missing a state (hover / focus / disabled / loading /
  empty / error).

## Migration status

Rolling out group-by-group, like the earlier Tailwind migration. New/edited
pages use `dgt-*` tokens; the legacy `navy` / `steel` / `accent` / `teal` tokens
in `tailwind.php` stay until their pages are converted, then retire.

- **Done — `modules/admin/dashboard.php`** (first page): DGT tokens, navy
  headings, tinted stat tiles (flat ~12% accent tint, 4px accent edge, icon
  chip top-right — matching the patient Treatment & Billing / History Logs
  metric tiles), entity-stable chart colours, the `distro()` one-category
  guard. `assets/js/charts.js`
  PALETTE + chrome colours are now DGT-wide (any other `charts.js` consumer
  picks them up). Stripped down 2026-09 at the user's request (advisor wanted a
  transactions-focused board): the whole **User Analytics** card is gone
  (role donut + per-role tiles + Account-Growth line), so are the Expenses /
  Net-Profit tiles (finance is gross-revenue-only now), the Service-Usage bar
  chart, and every Services-Analytics tile (Total Services / Most Popular / Avg
  Cost). Refactored again 2026-09 on panel feedback (focus purely on gross
  revenue accumulation + revenue by service): the Monthly Revenue Trend line
  and the Payment Methods donut were removed, and the generic period `<select>`
  was replaced by three fixed operational-period tiles. What's left:
  **Revenue Summary** (three gross-revenue-to-date tiles — Today's Revenue,
  Weekly Revenue, This Month's Revenue; named "Revenue Analytics" until 2026-09)
  and **Services Analytics** (the
  all-time Revenue by Service donut only). A **Clinic Operations** card was then
  added *above* Revenue Summary (2026-09) — seven tinted DGT stat tiles for
  real-time clinic-floor state (Today's Schedule, Ongoing Patients, Pending
  Schedule, Rescheduled, Doctors on Duty, Assistants on Duty, Total Active
  Accounts) on a 30 s auto-refresh toggle; every tile is clickable and opens a
  drill-down modal (appointment tiles → an appointment table → `appointmentModal()`;
  staff tiles → a people table). This is the **one** part of the dashboard that
  polls — the analytics section below it still does not. The Revenue Summary
  tiles and the Revenue-by-Service donut are likewise clickable (transaction
  list / full per-service breakdown) — every panel on the dashboard drills down.
  **Relaid out to an elevated modern "bento" layout 2026-09** (the user supplied
  SaaS-dashboard references and asked for "high-impact, polished" depth): the
  three big container cards are gone.
  The `<h1>` is **"Dashboard Overview"** (auto-refresh toggle beside it). The
  seven operational cards sit in one `#opsCards` grid
  (`grid-cols-2 lg:grid-cols-4`); **Today's Schedule** is the navy hero and
  spans two columns. Below them the lower row is
  **two equal-height cards** (`lg:grid-cols-2 lg:items-stretch`) — **Revenue
  Summary** (`#revenueCards`, a `grid-cols-2 auto-rows-fr` that fills its card,
  This Month's Revenue the double-wide navy hero)
  on the left and the **Revenue by Service** donut card (`distro()` → `donutChart`
  `size 220`, vertically centred in a `flex-1` body) on the right. The page content is a
  `min-h-[calc(100vh-150px)]` flex column and that row is `flex-1`, so the two
  cards **stretch to the bottom of the viewport** — the tiles and donut grow
  rather than leaving dead canvas below. Per the clinic advisor the dashboard
  carries **only** these panels: fill vertical space by expanding them, never by
  adding a new section. (An earlier pass put a read-only
  Upcoming Appointments feed in the right column — `api/dashboard.php?action=upcoming`,
  still live but no longer rendered — the user replaced it with the donut.)
  **Card style for this page only** (deviates from the `elevation` /
  `stat-tile` specs above, which still govern every other page): `rounded-2xl`,
  `shadow-md` at rest → `hover:-translate-y-1 hover:shadow-xl transition-all
  duration-200` when it drills, `border-dgt-ink-100`, `p-5` on section cards /
  `p-4` on metric cards (tightened in the compaction pass). Metric card =
  **top-left icon chip** (accent ~12% tint) + a bottom-aligned bold value
  (`~28px`, hero `~34px`) + label (`~13.5px`) — bumped up 2026-09 at the user's
  request ("hard to read"); section headers `~17px`, drill-down tables `~14px`.
  **No accent bar and no decorative pills** —
  the user had every micro-badge removed 2026-09 (the `+/-%` prior-period
  deltas, the `Live` / `Needs approval` status pills, and — with that whole
  card's later removal, see below — the one surviving `Online now` teal pill
  on Total Active Accounts). The `today_prev` /
  `week_prev` / `month_prev` figures still come back from the APIs, just unused.
  **A Revenue Trend line chart was added under the three tiles (2026-09,
  advisor feedback)** — a second sub-section inside the same Revenue Summary
  card, **un-headed** (a "Revenue Trend" `<h3>` label here was removed the
  same day at the user's request — "rename the card header to Revenue
  Summary" — since the outer card already has exactly that `<h2>` directly
  above; a second, differently-worded title would just be confusing), with
  its own Daily/Monthly `.tab` toggle and a calendar-month `<select>`
  (Daily-only, hidden while Monthly is active, offering "Last 7 Days" or any
  of the last 12 months). Styled as a "stock market trajectory chart":
  `window.lineChart()` (existed in `charts.js` since the Tailwind migration
  but had no callers until now) draws a **sharp, straight-segment line** (a
  new `straightPath()` helper — a smoothed monotone-cubic curve was tried
  first, and stays available as an opt-in for a future caller, but the user
  asked for the jagged look instead), an interactive `.line-node` circle at
  every point wired into the shared donut/bar floating-tooltip delegate, and
  a soft gradient fill under the line — no on-chart value label, no legend
  box, since a single series needs neither and the card's own heading already
  names it. **Markers are invisible at rest (`r="0"`) and only ever one
  visible at a time, wherever the pointer currently is** — hovering anywhere
  across the chart (not just precisely on a node, which has no area of its
  own to land on at `r="0"`) shows a **vertical dashed crosshair** snapped to
  the nearest date, grows that one node to `r="6"`, and shows the exact
  figure ("Sep 21, 2026: ₱5,800") via `window.wireLineChartHover()`, a
  companion function called once after render. `window.playLineChartIntro()`
  draws the line in via a stroke-dashoffset sweep on every render (page
  entry, refresh, toggle, or month pick), the same "just entered" language as
  the donut's/bar chart's own entrance animations — its old per-node pop-in
  step was removed once markers went hover-only, since animating a
  permanently zero-radius circle has nothing to show.
  **Every point is also click-to-drill (2026-09)**, closing the one gap in
  this card's "every panel is interactive" story — `wireLineChartHover()`
  took a second optional `onPick(key)` parameter (a click anywhere on the
  chart resolves to the nearest point, same lookup hover already uses; a
  tabbed-to node's own Enter/Space reads its `data-key` directly) and opens a
  modal of the individual fully-paid transactions behind that day/month's
  figure, via the new `api/dashboard.php?action=revenue_detail` — the same
  revenue-recognition rule as the summary aggregate itself, so the rows
  always foot to the number the point showed. A row opens the shared
  `appointmentModal()`, same as every other drill-down table on this page.
  Full detail in `CLAUDE.md`.
  **The three revenue tiles were removed outright, and the chart's own
  windows aligned to Service Usage & Demand's exactly (2026-09, later
  same-topic pass).** `#revenueCards` (the today/week/month tiles, backed by
  the now-deleted `api/reports.php?action=analytics`) and their own
  `action=revenue_list` drill-down are gone — the Revenue Summary card is now
  just its header row (`<h2>Revenue Summary</h2>` + the Daily/Monthly toggle,
  laid out exactly like Service Usage & Demand's own header) and the chart,
  nothing above it. The calendar-month `<select>` next to the toggle is gone
  too: **Daily now always means "the current calendar month, day 1 through
  its real last day"** (e.g. Sep 1–30 in September — no "Last 7 Days"
  default, nothing to pick), and **Monthly is the rolling 12 calendar
  months** — identical, bucket-for-bucket, to Service Usage & Demand's own
  Daily/Monthly windows, just plotted as revenue instead of patient counts.
  `#chartRevTrend` carries the same `flex flex-1 min-h-[300px]` sizing as
  `#chartSvcUsage`. **`window.lineChart()` gained the same natural-width-vs-
  stretch split `groupedBarChart()` already had (2026-09, second pass)** —
  matching the box height wasn't enough on its own: the chart's old always-
  `width:100%` rendering squeezed all 30 Daily points into one fixed-width
  card and the date labels overlapped into unreadable mush, screenshotted
  and flagged directly ("its still not like service usage"). `lineChart()`
  now takes an `opts.fill` flag, defaulting to `false`: **Daily** renders
  inside a `w-full overflow-x-auto` wrapper — `groupedBarChart()`'s own
  non-fill behaviour — so a full month of dates gets real spacing and
  scrolls horizontally just like Service Usage & Demand's own Daily view;
  **Monthly** passes `{ fill: true, height: 420 }`, stretching full-width at
  the same taller height as Service Usage's own Monthly.
  **A third pass fixed the real root cause behind the leftover left/right
  gutter mismatch** — the user pointed at it again from a fresh screenshot.
  `lineChart()`'s viewBox width was hardcoded to a flat `520` whenever
  `opts.fill` was set, so `preserveAspectRatio="none"` had to stretch it
  ~2x to fill a real ~1000-1100px card — a horizontal-only scale that
  inflates *everything* inside proportionally, padding **and the glyph
  shapes of every axis-label `<text>` element**, which measurably fattened
  the labels (confirmed: the same "Sep 2026" label rendered ~1.7x wider in
  the line chart than the bar chart). `groupedBarChart()` never hits this
  because its own natural-width formula, driven by up to 10 clustered bars
  per category, already lands close to a card's real width regardless of
  `opts.fill` — its own stretch ratio is negligible. `lineChart()` now
  follows the identical rule: its viewBox width is **always** the natural,
  content-driven width, never hardcoded for fill; only its default
  per-point spacing changed (48 → 90) so Monthly's fixed 12 points'
  natural width lands close enough to a real card's width that the stretch
  is nearly a no-op. `pad.l`/`pad.r` were trimmed too (from `58`/`28` to
  `46`/`32` money/non-money and `20`) — closer to `groupedBarChart()`'s own
  `32`/`14` (a peso label needs more room than a plain count, so `pad.l`
  can't drop all the way to match), re-verified with real
  `getBoundingClientRect()` measurements that the last point's own
  axis-label text still doesn't clip at the tighter margin.
  **Total Active Accounts removed / Revenue-by-Service donut replaced with a
  Service Usage & Demand bar graph (2026-09).** The operational row is now six
  cards, not seven (`grid-cols-2 lg:grid-cols-4` for the first four, a plain
  `grid-cols-2` pair for Doctors/Assistants on Duty) — the login-presence tile
  and its `staff_list&key=online` drill-down are gone outright, not hidden.
  The right-hand lower card is now **Service Usage & Demand**: a Daily/Monthly
  `.tab` toggle (no date/month picker — see below) over
  `window.groupedBarChart()` (new in `charts.js`, alongside the existing
  `donutChart`/`barChart`/`lineChart` — a **clustered/grouped** vertical bar
  chart: dates or months on the X-axis, one thin bar per service clustered
  under each, a 5-count-gridded Y-axis, and a colour-coded legend above the
  chart) plotting every active service's patient count for
  `api/dashboard.php?action=service_usage`, reusing the donut's shared
  floating tooltip via a `.donut-arc, .grouped-bar` selector. **A stronger
  hover/focus highlight than the donut's plain brightness bump (2026-09)** —
  at only ~6px wide with up to ~10 bars clustered per date/month, a
  `.donut-arc`-style brightness-only bump was too subtle to tell which bar
  the pointer was over ("hard to see because the bars are small"); `.grouped-
  bar` in `tailwind.php` adds a `1.5px` `stroke: var(--chart-center)` (the
  same token `.donut-arc:focus-visible`'s outline uses, so it holds up in
  both themes) on top of the brightness bump, on `:hover` **and**
  `:focus-visible` alike — matches this file's Pointer & state rule that
  hover is never the only affordance. **Individual bars are clickable
  drill-downs (2026-09)** — the card as a whole can't be (it hosts the
  Daily/Monthly toggle), but every other panel on this dashboard drills down
  on click, so each bar now does too: `groupedBarChart()` stamps
  `data-service-id`/`data-bucket` on every `<rect>` when the caller's data
  carries them, and clicking or Enter/Space-activating one opens a
  `{wide:true}` modal (`api/dashboard.php?action=service_usage_detail`) listing
  the completed visits behind that one (service, day-or-month) count, each row
  `data-view` into the shared `appointmentModal()` — the same click-to-detail
  pattern as every operational/revenue tile and the donut used to have.
  **The bars draw in on entry (2026-09)** — `window.playGroupedBarChartIntro()`
  in `charts.js`, the bar-chart counterpart of `playDonutIntro()`: each bar
  grows from `scaleY(0)` off the shared baseline (`transform-box: fill-box;
  transform-origin: bottom` on `.grouped-bar`, permanent and inert until a
  transform is applied), staggered by category bucket rather than by
  individual bar (an important distinction once ~10 services cluster under up
  to 31 categories — per-bar staggering would take far too long to finish),
  with the legend fading in after. Runs on every render of this chart, not
  gated behind a "first load only" flag, since it has no polling to guard
  against. **This chart type was corrected same-day** — a first pass put services on the
  X-axis with a single-date/single-month selector (a single-series
  `window.vBarChart()`, since deleted); the user flagged that this couldn't
  compare demand across time, so it was rebuilt as Daily = last 7 days /
  Monthly = last 6 months (both fixed rolling windows anchored to the DB
  clock, not a user-picked point), with every service as its own clustered
  series instead. **The Daily window churned through several shapes on
  2026-09, all the same day, before settling on the simplest one:** widened
  outright to a rolling 31 days (Monthly to 12 calendar months) → replaced
  with a "Last 7 Days / pick a calendar month" `<select>` beside the
  Daily/Monthly toggle → the *default* changed from 7 days to the current
  month → **the dropdown removed entirely**. Final state: Daily has **no
  control besides the Daily/Monthly toggle itself** and always means "the
  current calendar month so far" — day 1 through its real last day (28–31
  categories depending on the month, e.g. Sep 1–30 in September), resolved
  from the DB clock (`SELECT CURDATE()`), never a client-picked or
  client-computed date. Monthly is unaffected throughout — still the fixed
  rolling last 12 calendar months, no picker of its own. Every intermediate
  shape's plumbing (`$_GET['range']`/`month`/`year`, `month_options`,
  `fillSvcMonthOptions()`, `#svcDayRange`) was deleted outright once
  superseded, not left dead. **Colour and sizing**: both views share the same
  tighter `groupedBarChart()` bar/gap sizing (`barWidth:6, gapBar:1,
  gapGroup:12`, vs. the library's 8/2/18 default); **Monthly additionally
  renders with `{ fill: true, height: 420 }`**, stretching to the card's full
  width with a taller canvas. This churned back and forth the same day
  (2026-09) before settling here: flagged as "too compact" from a screenshot
  → `groupedBarChart()` gained `opts.fill` (`width="100%"
  preserveAspectRatio="none"` on the `<svg>`, viewBox unchanged, mapping the
  coordinate system directly onto the container's real box) → reverted after
  the independent X/Y rescaling was reported as visibly fattening and
  unevenly gapping the deliberately thin, evenly-spaced bars ("the months too
  stretch") → the taller-but-unstretched middle ground reverted too once the
  user saw it live ("change it back") → **`opts.fill` reinstated and the
  stretch restored** at the user's explicit final request ("make it back
  where the months are stretch"). `opts.fill` stays opt-in on
  `groupedBarChart()` — a caller wanting undistorted natural sizing still can
  — so Daily (up to ~310 bars for a full calendar month, still needing real
  horizontal scroll to stay legible) is unaffected and keeps its natural,
  non-stretched width. Chosen over the donut because the donut only ever showed
  the top few services by all-time revenue — this shows demand across the
  **whole** catalogue, over time, which is what "which service is busiest,
  and when" actually needs. Colour note: the shared, CVD-validated
  `CHART_PALETTE` only has 8 hues for a ~10-service catalogue, so this card
  extends it **locally** with two more (a cyan, and a slate-grey for
  Restorative Dentistry — lightened from an initial `#475569` to `#94a3b8`
  2026-09 after the user reported the darker slate was hard to see in dark
  mode, where it nearly vanished into the dark card background; the lighter
  step reads clearly in both themes), chosen by eye for hue-wheel separation,
  not run through the `dataviz` skill's validator — re-check before reusing
  elsewhere. The card is no longer
  itself a click-to-drill button (it now hosts the toggle); `distro()`/
  `foldSegments()`/`svcRevTable()` and the `service_revenue_list` /
  `revenue_by_service` endpoints were deleted with the donut.
  **`opts.fill`'s `preserveAspectRatio="none"` stretch was removed outright
  (2026-09) and replaced with true container-width-aware sizing**, on both
  this chart and Revenue Summary's `lineChart()` — a request phrased in
  ApexCharts terms ("optimize for presentation scaling and high-resolution
  displays," `redrawOnWindowResize`/`redrawOnParentResize`) translated
  silently into this vanilla-SVG library. Both chart functions now accept
  `opts.containerWidth` (the caller's measured real pixel width) and compute
  every coordinate directly against `W = Math.max(minNaturalWidth,
  containerWidth)` — the SVG's `width` attribute is always that literal
  number, never `"100%"`/`preserveAspectRatio`, so viewBox and rendered box
  always match 1:1 and there is no stretch-driven text distortion at *any*
  width, including a genuinely wide projector/4K display where the old
  stretch ratio would have grown well past 1.0x. `groupedBarChart()` spends
  the extra room widening `gapGroup`; `lineChart()`'s point-spacing was
  already proportional to `W`. Axis/grid text bumped from `font-size="9"`/
  `"10"` to a flat `"12"` on both charts (padding grew slightly to fit it) so
  it renders at a true, literal 12px at any resolution now that nothing
  stretches it. `redrawOnWindowResize`/`redrawOnParentResize` became one
  debounced `ResizeObserver` on both chart containers in
  `modules/admin/dashboard.php` (not a `window.resize` listener — the
  sidebar-collapse toggle changes these cards' width with no resize event of
  its own) that re-renders from a small local cache of the last fetch, not a
  refetch, and skips the draw-in entrance animation on a resize-triggered
  redraw so resizing the window never replays it.
  **Un-paired from Revenue Summary into its own full-width row (2026-09).**
  The two cards used to share the "two equal-height cards" row described above
  (`lg:grid-cols-2 lg:items-stretch`, each `col-span-6`) — fine for a donut,
  but at half the row's width `window.groupedBarChart()`'s SVG (7 or 6 date/
  month groups × up to 10 clustered bars, ~840px wide) overflowed its card and
  fell back to its own `overflow-x-auto` horizontal scrollbar, which the user
  flagged directly from a screenshot and asked to have removed. Both cards are
  now `col-span-12`, stacked — Revenue Summary on top, Service Usage & Demand
  directly below it — each spanning the full page width (`#revenueCards`
  itself, along with the three tiles it held, was removed entirely in the
  later pass described above — this wider row is what gave the chart room to
  match Service Usage & Demand's own width and height once the tiles were
  gone). The `min-h-[calc(100vh-150px)] flex-1`
  viewport-stretch treatment (described above, from the original bento pass)
  was dropped along with the two-column split — with two full-width stacked
  cards there's no shared row height left to stretch. At a normal desktop
  width the chart's card now has 1000px+ to work with, comfortably fitting
  the ~840px SVG with no scrollbar.
  **Two "hero" cards** — Today's Schedule and This Month's Revenue — are
  **solid `dgt-blue-900` fill**, white text, `col-span-2`, with a big faint
  watermark of their icon (`text-white/[0.06]`). **Still no gradients** (the
  user chose to keep that rule); "high-impact" comes from the solid navy fill,
  size, elevation and bold type. **The white metric cards additionally have a
  Uiverse-style hover flood (2026-09, user request):** a `.dash-card` `::before`
  circle parked outside the top-right corner scales up on `:hover` /
  `:focus-visible` to flood the card in **its own icon-accent hue** (inline
  `--flood`, deepened with `color-mix(… 84%, #0a1a2e)` for white-text contrast),
  text + chip cross-fading white. Adapted from
  `uiverse.io/SouravBandyopadhyay/giant-squid-24` as a **solid colour, not the
  original gradient**; `prefers-reduced-motion` drops it; navy hero cards are
  excluded. Full CSS lives in a `<style>` block atop `dashboard.php`'s
  `$PAGE_CONTENT`. (A hover-scale + press-tilt from
  `uiverse.io/SteveBloX/dangerous-warthog-85` was briefly layered on top and then
  removed at the user's request.) **A later 2026-09 restyle / regroup pass —
  white header band, group dividers, accent caps, fraction bars, ranked service
  list, an a11y hardening layer — was reverted at the user's request; they did
  not like the look.** Only the shared, cross-cutting part was kept: `app.js`
  `openModal()` moves focus into the dialog + adds a Tab trap + `aria-labelledby`,
  `closeModal()` returns focus to the trigger, `toast()` sets
  `role="status"`/`alert`, and `#toastRoot` (in `layout.php`) is an `aria-live`
  region. The interactive-dashboard-builder skill was **not** used on this page —
  it is scoped to standalone hand-off files only.
- **In progress — app shell:** `includes/sidebar.php` converted (`dgt-blue-800`
  body, `dgt-teal-600` brand cap, `dgt-blue-500` active nav item, white-alpha
  hover / borders). `includes/header.php` and `includes/layout.php` (body/main
  canvas → `dgt-ink-50`) still legacy — next.
- **Done — `index.php`:** **Rebuilt from scratch 2026-09 (third pass) in the
  `floria-landing-page.vercel.app` idiom.** User choices, confirmed by
  `AskUserQuestion`: keep the **DGT cool palette** (borrow only Floria's
  layout — navy `dgt-blue-900` headings, DGT teal/blue accent, NOT Floria's
  warm-stone + emerald); **Services rebuilt as a Floria staggered card grid**
  (the old slider is gone); **add the Geist typeface** (+ Geist Mono) with
  **serif-italic accent words** (`.ser` = `Georgia, italic`, wraps the last word
  of each heading). Geist loads from Google Fonts, scoped to this page via
  `body.floria` (no `tailwind.php` change). This page **deliberately deviates
  from the specs below** — big radii (`rounded-[1.75rem]`/`[2rem]`,
  `rounded-full` pills), full-bleed **navy `dgt-blue-900`** dark sections
  (Process, closing CTA, footer), Geist not the system stack. The no-gradient-
  fill rule is kept (the hero photo scrim + card gradient fallback are the only
  gradients). Section order: fixed pill nav (slides in) → dark Hero
  (`min-h-[100svh]`, one `hero-kb` Ken Burns) → light **Our services.**
  (staggered `md:grid-cols-3` card grid, per-card offset + aspect-ratio cycle,
  image zoom + caption-panel slide-up on hover) → dark **How it works.**
  (Floria "Architecture of Nature": sticky left heading + right `border-l`
  steps with `.mono` `01/02/03` badges) → light **About us.** (editorial split +
  `<dl>` facts, then a Mission/Vision/Promise 3-col row) → dark **Ready when you
  are.** CTA → navy footer. **Motion copied from Floria's framer-motion feel:**
  directional scroll-reveal variants (`.rv` up / `.rv-l` from left / `.rv-r`
  from right / `.rv-s` scale-up) eased `.8s`, once, one IntersectionObserver,
  per-item stagger via inline `transition-delay`; the pill nav ports Floria's
  `<Navbar>` 1:1 (spring drop-in `@keyframes nav-drop`, transparent→frosted
  capsule on `scrollY>50`); the hero photo (a dark dental still-life, Floria's
  "one lit object on dark" role) brightens on `#home:hover`
  (`brightness(.88)→(1.08)`, Floria's accent hover); card images zoom
  `scale(1.1)` on hover. A floating **`#toTop` "Back to Top" button** (navy
  rounded square → `dgt-teal-600` labelled pill on hover, arrow slides out;
  `uiverse.io/vinodjangid07` recoloured to the DGT palette) fades in past ~600px
  of scroll. All reduced-motion gated. SEO block (meta description, canonical, OG,
  `Dentist` JSON-LD) in `<head>`. No testimonials/newsletter (no real quotes).
  The other
  public/auth pages (`login`, `register`, `reset_password`, `terms`) are still
  legacy.
- **Done — `modules/patient/appointments.php` (first per-role module page,
  2026-09):** a visual-polish-only pass (the user explicitly scoped it that way
  via `AskUserQuestion` — no layout/behaviour change) converting the page off
  the legacy `navy`/`steel`/`accent` tokens onto `dgt-*`. `$tabCls` now follows
  the `tab` component spec (rest: white / `dgt-ink-200` border / `dgt-ink-500`
  text; active: `dgt-blue-900` pill); the status tablist (`#apptTabs`) gained
  `role="tablist"` / `role="tab"` / `aria-selected` + arrow-key roving focus per
  the Accessibility section. The **"+ Request Appointment"** button and the
  wizard's **Continue to Payment** / **Submit Request** buttons are now
  `dgt-teal-600` (the `button-cta` spec — they're the one primary action per
  screen); the ghost **Back** / **View Details** / **Reschedule** buttons are
  `border-dgt-ink-200 text-dgt-blue-800`. `includes/patient_booking_style.php`
  (the wizard, payment-method picker, "Submitted" status card and the
  `.appt-c` card grid — a large hand-written `<style>` partial, kept as one
  per the styling-tailwind-migration convention) had every neutral/brand hex
  remapped to its DGT equivalent (`#c7d2df`→`#d6dee4` `dgt-ink-200`,
  `#0f1c2e`→`#102f51` `dgt-blue-900`, `#2f80b4`→`#006cc0` `dgt-blue-500` for
  the wizard's current-step / selected-payment-method accent, etc.); the
  **status** colours (pending/approved/completed/rescheduled/cancelled
  amber/blue/green/gray/red) were deliberately left on the legacy
  `ok`/`warn`/`danger`/`info` hexes — those are the same colours `BADGE_C` in
  `app.js` still uses everywhere else, so this page's badges stay visually
  identical to every other role's until `app.js` itself is converted. Card
  radius bumped `10px`→`12px` (`rounded-xl`, the `card` spec) and the wizard's
  sub-boxes (`.pay-summary`, `.pm-card`, `.pm-box`, `.rcpt-drop`, …) `6px`→`8px`
  (`rounded-lg`, concentric under the 12px card); `.appt-c`'s shadow swapped to
  the `elevation.card` spec (`0 1px 2px rgba(16,47,81,.06)`) instead of the
  old heavier navy-tinted one. `assets/js/app.js` (`badgeHTML`, `openModal`,
  `toast`, …) is **not** touched by this pass — it stays legacy-toned until its
  own migration group, same precedent as the admin dashboard conversion.
  (A follow-up richer-card pass — a 4px status-accent edge, a bigger bold
  service name, and a combined icon-chip date/time block — was tried and then
  reverted at the user's request; the card stays in its plain polished form
  above.)
- **Then:** the other admin pages, then the rest of the per-role module pages,
  then the rest of the public/auth pages, then shared components in `app.js`.
