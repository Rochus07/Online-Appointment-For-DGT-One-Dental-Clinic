/* ============================================================
   Tiny dependency-free SVG charts: donut + horizontal bars + grouped bars.
   window.donutChart(segments, opts)        -> HTML string
   window.barChart(items, opts)             -> HTML string (horizontal)
   window.groupedBarChart(categories, series, opts) -> HTML string (clustered vertical bars)
   ============================================================ */
(function () {
  'use strict';

  /* Shared floating tooltip for chart hit-targets (`.donut-arc`, `.grouped-bar`),
     built once and reused for every chart on the page — these render functions
     only return markup, they never run script, so the hover behaviour has to
     live here as one document-level delegated listener rather than being
     wired per chart. */
  const TIP_SEL = '.donut-arc, .grouped-bar, .line-node';
  let tipEl = null;
  function tip() {
    if (!tipEl) {
      tipEl = document.createElement('div');
      tipEl.className = 'pointer-events-none fixed z-[999] hidden -translate-x-1/2 -translate-y-[calc(100%+10px)] ' +
        'whitespace-nowrap rounded-md bg-dgt-blue-900 px-2.5 py-1.5 text-[12px] font-medium text-white shadow-lg dark:bg-dgt-ink-700';
      document.body.appendChild(tipEl);
    }
    return tipEl;
  }
  function showTip(el, x, y) {
    const t = tip();
    t.innerHTML = el.dataset.tip || '';
    t.style.transform = '';   // reset to the class-defined centred-above position before measuring
    t.style.left = x + 'px';
    t.style.top = y + 'px';
    t.classList.remove('hidden');
    // Clamp inside the viewport — the tooltip is centred horizontally and
    // sits entirely above (x, y) by default (the `-translate-x-1/2
    // -translate-y-[calc(100%+10px)]` classes above), which overflows and
    // gets visually clipped whenever the hovered/clicked point sits near the
    // chart's top or right edge — a real, reported case: the most recent
    // date on a trend line is always the rightmost point, and a revenue
    // spike often sits near the top of the plot, so the two combine on
    // exactly the kind of point someone is most likely to click. Only nudges
    // when actually needed (dx/dy stay 0 for an ordinary, non-edge point), so
    // this never changes the tooltip's normal centred-above position.
    const pad = 8;
    const r = t.getBoundingClientRect();
    let dx = 0, dy = 0;
    if (r.right > window.innerWidth - pad) dx = (window.innerWidth - pad) - r.right;
    if (r.left + dx < pad) dx = pad - r.left;
    if (r.top < pad) dy = pad - r.top;
    if (dx || dy) {
      t.style.transform = 'translate(calc(-50% + ' + dx + 'px), calc(-100% - 10px + ' + dy + 'px))';
    }
  }
  function hideTip() { if (tipEl) tipEl.classList.add('hidden'); }

  document.addEventListener('pointermove', (e) => {
    const el = e.target.closest && e.target.closest(TIP_SEL);
    if (el) showTip(el, e.clientX, e.clientY);
  });
  document.addEventListener('pointerout', (e) => {
    if (e.target.closest && e.target.closest(TIP_SEL)) hideTip();
  });
  document.addEventListener('focusin', (e) => {
    const el = e.target.closest && e.target.closest(TIP_SEL);
    if (!el) return;
    const r = el.getBoundingClientRect();
    showTip(el, r.left + r.width / 2, r.top + r.height / 2);
  });
  document.addEventListener('focusout', (e) => {
    if (e.target.closest && e.target.closest(TIP_SEL)) hideTip();
  });

  // DGT categorical palette — brand blue in slot 1, then a CVD-validated hue order
  // (validate_palette.js: all adjacent-pair checks pass on a white surface; the
  //  sub-3:1 hues are always shown with a legend + value label). Assign by entity
  //  in a fixed order, never cycle past 8 — fold the tail into "Other".
  const PALETTE = ['#006cc0', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];
  const OTHER_HUE = '#b4c1cb';   // dgt-ink-300 — the "Other" bucket / empty state

  /* Tailwind utility strings (replace the old style.css .chart-* / .bar-* rules). */
  const C_WRAP   = 'relative flex flex-col items-center gap-2.5 [&>svg]:block';
  const C_CENTRE = 'pointer-events-none absolute left-1/2 top-0 -translate-x-1/2';
  const C_LEGEND = 'flex w-full list-none flex-wrap items-center justify-center gap-x-4 gap-y-1.5 mt-3 sm:mt-4';
  const C_DOT    = 'inline-block h-2.5 w-2.5 shrink-0 rounded-sm mr-2';
  const C_ITEM   = 'inline-flex items-center text-[13px] text-dgt-ink-500 dark:text-dgt-ink-400';
  const B_CHART  = 'flex flex-col gap-[9px] pt-1';
  const B_ROW    = 'grid grid-cols-[120px_1fr_46px] items-center gap-2.5 text-xs';
  const B_LABEL  = 'overflow-hidden text-ellipsis whitespace-nowrap text-dgt-ink-500 dark:text-dgt-ink-400';
  const B_TRACK  = 'block h-3 overflow-hidden rounded-full bg-dgt-ink-100 dark:bg-dgt-ink-700';
  const B_FILL   = 'block h-full rounded-full';
  const B_VAL    = 'text-right font-semibold text-dgt-blue-900 dark:text-dgt-ink-50 [font-variant-numeric:tabular-nums]';
  const L_WRAP   = 'block [&_svg]:block [&_svg]:overflow-visible';

  window.donutChart = function (segments, opts) {
    opts = opts || {};
    const size = opts.size || 168;
    const stroke = opts.thickness || 24;
    const r = (size - stroke) / 2;
    const cx = size / 2;
    const c = 2 * Math.PI * r;
    const total = segments.reduce((s, x) => s + (+x.value || 0), 0);

    const money = opts.money !== false;
    let offset = 0;
    const arcs = total === 0
      ? '<circle cx="' + cx + '" cy="' + cx + '" r="' + r + '" fill="none" stroke="var(--chart-track)" stroke-width="' + stroke + '"/>'
      : segments.map((seg, i) => {
          const frac = (+seg.value || 0) / total;
          const dash = frac * c;
          const val = +seg.value || 0;
          const tip = esc(seg.label) + ' &middot; ' + (money ? esc(peso(val)) : val) + ' (' + Math.round(frac * 100) + '%)';
          const el = '<circle class="donut-arc" tabindex="0" role="img" aria-label="' + tip + '" data-tip="' + tip + '" ' +
            'cx="' + cx + '" cy="' + cx + '" r="' + r + '" fill="none" ' +
            'stroke="' + (seg.color || PALETTE[i % PALETTE.length]) + '" stroke-width="' + stroke + '" ' +
            'stroke-dasharray="' + dash + ' ' + (c - dash) + '" stroke-dashoffset="' + (-offset) + '"/>';
          offset += dash;
          return el;
        }).join('');

    const centre = opts.centerLabel
      ? '<text x="' + cx + '" y="' + (cx - 4) + '" text-anchor="middle" font-size="20" font-weight="600" fill="var(--chart-center)">' +
        esc(String(opts.centerLabel)) + '</text>' +
        (opts.centerSub ? '<text x="' + cx + '" y="' + (cx + 14) + '" text-anchor="middle" font-size="10" fill="var(--chart-center-sub)">' + esc(String(opts.centerSub)) + '</text>' : '')
      : '';

    const legend = (opts.legend === false) ? '' :
      '<ul class="' + C_LEGEND + '">' + segments.map((seg, i) =>
        '<li class="' + C_ITEM + '"><span class="' + C_DOT + '" style="background:' + (seg.color || PALETTE[i % PALETTE.length]) + '"></span>' +
        esc(seg.label) + '</li>').join('') + '</ul>';

    return '<div class="' + C_WRAP + '"><svg viewBox="0 0 ' + size + ' ' + size + '" width="' + size + '" height="' + size + '" ' +
      'style="transform:rotate(-90deg)">' + arcs + '</svg>' +
      (centre ? '<svg viewBox="0 0 ' + size + ' ' + size + '" width="' + size + '" height="' + size + '" class="donut-centre ' + C_CENTRE + '">' + centre + '</svg>' : '') +
      legend.replace('<ul class="', '<ul class="donut-legend ') + '</div>';
  };

  /* Entrance animation for a donutChart() render — a segment "sweep" (each
     arc's stroke-dasharray tweens from 0 up to its real length, staggered so
     the ring draws itself clockwise), then the centre total and legend items
     fade/rise in just after. donutChart() only returns a markup string (the
     caller does el.innerHTML = ...), so this can't be baked into the render
     itself — call window.playDonutIntro(container) once right after inserting
     it. Not automatic on every render on purpose: the admin dashboard's 30s
     auto-refresh only touches the operational cards, never this chart, so the
     one explicit call in loadRevenue() already means "the chart just entered
     the page," not "a chart re-rendered mid-session." Skips entirely under
     prefers-reduced-motion. */
  window.playDonutIntro = function (container) {
    if (!container || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const arcs = container.querySelectorAll('.donut-arc');
    if (!arcs.length) return;
    const EASE = 'cubic-bezier(.16,1,.3,1)';

    arcs.forEach((arc, i) => {
      const parts = (arc.getAttribute('stroke-dasharray') || '0 0').split(' ').map(Number);
      const dash = parts[0], gap = parts[1], total = dash + gap;
      arc.animate(
        [{ strokeDasharray: '0 ' + total }, { strokeDasharray: dash + ' ' + gap }],
        { duration: 750, delay: i * 90, easing: EASE, fill: 'backwards' }
      );
    });

    // The centre <svg> is horizontally centred via the Tailwind utility
    // `-translate-x-1/2` — the animation's own `transform` keyframes must
    // repeat that (a WAAPI transform replaces the element's CSS transform
    // outright for the animation's duration, it doesn't compose with it),
    // or the total would visibly jump sideways while it scales in.
    const centre = container.querySelector('.donut-centre');
    if (centre) {
      centre.animate(
        [{ opacity: 0, transform: 'translateX(-50%) scale(.85)' }, { opacity: 1, transform: 'translateX(-50%) scale(1)' }],
        { duration: 450, delay: 250, easing: EASE, fill: 'backwards' }
      );
    }

    container.querySelectorAll('.donut-legend > li').forEach((li, i) => {
      li.animate(
        [{ opacity: 0, transform: 'translateY(6px)' }, { opacity: 1, transform: 'translateY(0)' }],
        { duration: 350, delay: 500 + i * 60, easing: EASE, fill: 'backwards' }
      );
    });
  };

  window.barChart = function (items, opts) {
    opts = opts || {};
    const max = Math.max(1, ...items.map((x) => +x.value || 0));
    const money = !!opts.money;
    return '<div class="' + B_CHART + '">' + items.map((x, i) => {
      const pct = ((+x.value || 0) / max) * 100;
      const val = money ? peso(x.value) : (+x.value || 0);
      return '<div class="' + B_ROW + '"><span class="' + B_LABEL + '">' + esc(x.label) + '</span>' +
        '<span class="' + B_TRACK + '"><span class="' + B_FILL + '" style="width:' + pct.toFixed(1) + '%;background:' +
        (x.color || PALETTE[i % PALETTE.length]) + '"></span></span>' +
        '<span class="' + B_VAL + '">' + val + '</span></div>';
    }).join('') + '</div>';
  };

  /* Clustered/grouped vertical bar chart — one category (X-axis tick) per
     date or month, and one thin bar per data series (e.g. per service)
     rendered side by side within that category's group, so volumes across
     several series can be compared both within a period and across periods
     at a glance. Y-axis is a numeric axis gridded in fixed increments. A
     colour-coded legend renders above the chart naming every series.
     `categories`: [{key, label}, ...] — one per X-axis group.
     `series`: [{label, color?, data: [value per category, same order]}, ...].
     Distinct from barChart() above (a horizontal label+track bar meant for a
     label column, not an axis) and from donutChart() (part-to-whole, not a
     trend). */
  window.groupedBarChart = function (categories, series, opts) {
    opts = opts || {};
    const nCat = Math.max(1, categories.length);
    const nSer = Math.max(1, series.length);
    const barW = opts.barWidth || 8;
    const gapBar = opts.gapBar || 2;
    const gapGroupMin = opts.gapGroup || 18;
    // opts.topPerCategory caps how many bars render inside EACH category's own
    // cluster, to whichever series have the highest values in THAT category —
    // not a single fixed top-N for the whole chart. Every category can (and
    // usually does) show a different subset once the catalogue is bigger than
    // the cap; the legend still lists every series so a colour is always
    // identifiable even on a day it didn't make that day's top N. Group width
    // is sized off the capped count, not the full series length, so a busy
    // catalogue doesn't force a wide, mostly-empty cluster per category.
    const capN = opts.topPerCategory ? Math.max(1, Math.min(nSer, opts.topPerCategory)) : nSer;
    const groupW = capN * barW + (capN - 1) * gapBar;
    const pad = { l: 34, r: 14, t: 14, b: 34 };
    const minInnerW = nCat * groupW + (nCat - 1) * gapGroupMin;
    const minW = Math.max(opts.minWidth || 520, minInnerW + pad.l + pad.r);
    // Fluid width: a caller that measures its own container's real rendered
    // width (opts.containerWidth) gets the chart expanded to fill it exactly
    // — the extra room is spent widening the gap between category groups, so
    // every bar genuinely spreads out to use the space rather than leaving a
    // dead margin to the right of a chart drawn at its bare minimum width.
    // This only ever widens (never narrows below minW, which stays legible at
    // any category/series count) and needs no preserveAspectRatio trickery —
    // every coordinate below is computed directly against the final W, so
    // there is no post-hoc SVG stretch and therefore no risk of the axis-text
    // distortion that a viewBox/rendered-size mismatch used to cause (see
    // lineChart()'s own history of the same bug). On an ordinary desktop
    // width minW usually already meets or exceeds the container, so this is a
    // no-op there; it matters on a wide/projected/high-resolution display
    // where the card has real extra width to give the chart.
    const W = Math.max(minW, opts.containerWidth || 0);
    const gapGroup = nCat > 1 ? gapGroupMin + Math.max(0, (W - minW) / (nCat - 1)) : gapGroupMin;
    const H = opts.height || 300;
    const innerH = H - pad.t - pad.b;
    const step = opts.step || 5;
    const maxVal = Math.max(0, ...series.flatMap((s) => (s.data || []).map((v) => +v || 0)));
    const niceMax = Math.max(opts.minScale || (step * 5), Math.ceil(maxVal / step) * step || step);
    const baseY = pad.t + innerH;
    const y = (v) => pad.t + (1 - v / niceMax) * innerH;
    const unit = opts.unit || 'Patient';

    let grid = '';
    for (let v = 0; v <= niceMax; v += step) {
      const gy = y(v);
      grid += '<line x1="' + pad.l + '" y1="' + gy.toFixed(1) + '" x2="' + (W - pad.r) + '" y2="' + gy.toFixed(1) +
        '" stroke="var(--chart-track)"/>' +
        '<text x="' + (pad.l - 6) + '" y="' + (gy + 3).toFixed(1) + '" text-anchor="end" font-size="12" fill="var(--chart-axis)">' +
        Math.round(v) + '</text>';
    }

    let bars = '', labels = '';
    categories.forEach((cat, ci) => {
      const groupX = pad.l + ci * (groupW + gapGroup);
      // Rank this category's own series by its own value, then keep only the
      // top capN — a plain series.forEach (rendering every series at its
      // fixed array index) would defeat the point: the cluster would still
      // reserve a slot per series, just with most of them at zero height.
      let shown = series.map((s, si) => ({ s, si, val: +(s.data && s.data[ci]) || 0 }));
      if (opts.topPerCategory) {
        shown = shown.slice().sort((a, b) => b.val - a.val).slice(0, capN);
      }
      shown.forEach((entry, slot) => {
        const { s, si, val } = entry;
        const bx = groupX + slot * (barW + gapBar);
        const barY = y(val);
        const barH = Math.max(0, baseY - barY);
        const color = s.color || PALETTE[si % PALETTE.length];
        const tipTxt = esc(s.label) + ' &middot; ' + esc(cat.label) + ': ' + val + ' ' + unit + (val === 1 ? '' : 's');
        // service_id/bucket key are optional passthroughs a caller can attach
        // to each series/category so a click on one bar can be routed to a
        // per-(service, bucket) drill-down — same idea as a donut arc or a
        // metric tile's data-drill, just addressed by two coordinates instead
        // of one key.
        const dataAttrs = (s.service_id != null ? ' data-service-id="' + s.service_id + '"' : '') +
          (cat.key != null ? ' data-bucket="' + esc(String(cat.key)) + '"' : '');
        bars += '<rect class="grouped-bar" tabindex="0" role="img" aria-label="' + tipTxt + '" data-tip="' + tipTxt + '"' + dataAttrs + ' ' +
          'x="' + bx.toFixed(1) + '" y="' + barY.toFixed(1) + '" width="' + barW.toFixed(1) + '" height="' + barH.toFixed(1) +
          '" rx="' + Math.min(2.5, barW / 2).toFixed(1) + '" fill="' + color + '"/>';
      });
      const catCx = groupX + groupW / 2;
      // A category/date label is its own click target when the caller wants
      // one (data-bucket + a cursor-pointer class) — the chart-owning page
      // wires the click to open a full breakdown for that one date, since
      // the bars themselves only ever show the capped top-N here.
      const textEl = '<text x="' + catCx.toFixed(1) + '" y="' + (baseY + 18) + '"' +
        ' text-anchor="middle" font-size="12" fill="var(--chart-axis)">' + esc(cat.label) + '</text>';
      if (opts.categoryClick && cat.key != null) {
        // SVG <text> only receives a click on its painted glyphs, so a click
        // that lands between letters or just beside the label silently did
        // nothing ("sometimes it doesn't open"). Wrap it in a <g> with a
        // transparent rect covering the whole label slot so the entire area
        // under the date is one reliable click target.
        const hitHalf = Math.max(groupW / 2, 24);
        labels += '<g class="grp-cat-label cursor-pointer" data-bucket="' + esc(String(cat.key)) + '" tabindex="0" role="button">' +
          '<rect x="' + (catCx - hitHalf).toFixed(1) + '" y="' + (baseY + 2) + '" width="' + (hitHalf * 2).toFixed(1) + '" height="30" fill="transparent"/>' +
          textEl + '</g>';
      } else {
        labels += textEl;
      }
    });

    // opts.legendTop = N: the legend lists only the N series with the most
    // total volume (busiest first) plus a "+ X more" toggle; opts.legendExpanded
    // lists every series again with a "Show top N" toggle. The caller owns the
    // expanded state and re-renders on a [data-legend-toggle] click.
    let legendItems = series.map((s, i) => ({ s, i }));
    let legendToggle = '';
    if (opts.legendTop && series.length > opts.legendTop) {
      if (!opts.legendExpanded) {
        legendItems = legendItems
          .map((o) => Object.assign(o, { total: (o.s.data || []).reduce((a, v) => a + (+v || 0), 0) }))
          .sort((a, b) => (b.total - a.total) || (a.i - b.i))
          .slice(0, opts.legendTop);
      }
      legendToggle = '<li><button type="button" data-legend-toggle class="tap-sm cursor-pointer rounded-full border border-dgt-ink-200 px-2.5 py-0.5 text-[12px] font-semibold text-dgt-blue-700 hover:bg-dgt-ink-50 dark:border-white/15 dark:text-dgt-blue-300 dark:hover:bg-white/5">' +
        (opts.legendExpanded ? 'Show top ' + opts.legendTop : '+ ' + (series.length - opts.legendTop) + ' more') + '</button></li>';
    }
    const legend = (opts.legend === false) ? '' :
      '<ul class="' + C_LEGEND + ' mt-0">' + legendItems.map(({ s, i }) =>
        '<li class="' + C_ITEM + '"><span class="' + C_DOT + '" style="background:' + (s.color || PALETTE[i % PALETTE.length]) + '"></span>' +
        esc(s.label) + '</li>').join('') + legendToggle + '</ul>';

    // Always a literal pixel width (never width="100%" + preserveAspectRatio)
    // — W above is already the exact final size (natural minimum, or the
    // caller's measured container width when that's larger), so the viewBox
    // and the rendered box always match 1:1. No stretch, no distortion, and
    // axis text renders at its true, specified font-size at any resolution.
    const svgAttrs = 'width="' + W + '" height="' + H + '" class="mx-auto block" style="min-width:' + Math.min(W, 720) + 'px"';

    return '<div class="flex w-full flex-col items-center gap-2">' + legend +
      '<div class="w-full overflow-x-auto"><svg viewBox="0 0 ' + W + ' ' + H + '" ' + svgAttrs + '>' +
      grid + '<line x1="' + pad.l + '" y1="' + baseY.toFixed(1) + '" x2="' + (W - pad.r) + '" y2="' + baseY.toFixed(1) + '" stroke="var(--chart-axis)"/>' +
      bars + labels + '</svg></div></div>';
  };

  /* Entrance animation for a groupedBarChart() render — the bar-chart
     equivalent of playDonutIntro() above: each bar grows up from the shared
     baseline instead of the donut's stroke-dasharray sweep, then the legend
     items fade/rise in just after. groupedBarChart() only returns a markup
     string (the caller does el.innerHTML = ...), so this can't be baked into
     the render itself — call window.playGroupedBarChartIntro(container) once
     right after inserting it. Every `.grouped-bar` rect's own bounding box
     already touches the baseline at its bottom edge (y + height === baseY for
     every bar, zero-value bars included), so animating a plain CSS
     `transform: scaleY()` with `transform-box: fill-box; transform-origin:
     bottom` (set permanently in tailwind.php's `.grouped-bar` rule, inert
     until a transform is actually applied) grows every bar from that shared
     line with no per-bar coordinate math needed here.
     Staggering is by *category* (the `data-bucket` attribute added in
     groupedBarChart() above), not by individual bar — a caller can cluster
     up to ~10 series under each of up to 31 categories, and staggering
     ~300 individual bars one at a time would take far too long to finish
     drawing in; every bar sharing a bucket animates together, and each
     bucket's own start is staggered a little so the chart still visibly
     draws in left to right. Skips entirely under prefers-reduced-motion. */
  window.playGroupedBarChartIntro = function (container) {
    if (!container || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const bars = container.querySelectorAll('.grouped-bar');
    if (!bars.length) return;
    const EASE = 'cubic-bezier(.16,1,.3,1)';

    const groupIndex = new Map();
    bars.forEach((b) => {
      const key = b.dataset.bucket != null ? b.dataset.bucket : b;   // no bucket -> every bar its own group
      if (!groupIndex.has(key)) groupIndex.set(key, groupIndex.size);
    });
    const groups = groupIndex.size;
    const stagger = Math.min(45, Math.max(10, Math.floor(420 / Math.max(1, groups))));

    bars.forEach((b) => {
      const key = b.dataset.bucket != null ? b.dataset.bucket : b;
      b.animate(
        [{ transform: 'scaleY(0)' }, { transform: 'scaleY(1)' }],
        { duration: 420, delay: groupIndex.get(key) * stagger, easing: EASE, fill: 'backwards' }
      );
    });

    container.querySelectorAll('ul > li').forEach((li, i) => {
      li.animate(
        [{ opacity: 0, transform: 'translateY(6px)' }, { opacity: 1, transform: 'translateY(0)' }],
        { duration: 350, delay: groups * stagger + i * 40, easing: EASE, fill: 'backwards' }
      );
    });
  };

  // Whole-peso formatting for this chart only (axis ticks + node tooltips) —
  // deliberately not window.peso()'s always-2-decimal convention used
  // everywhere else in the app (invoices, tiles, …). A trend line's job is
  // the shape/direction at a glance; centavos are clutter on an axis label
  // and weren't in the reference tooltip format ("Sep 21, 2026: ₱5,800").
  const pesoWhole = (v) => '₱' + Math.round(+v || 0).toLocaleString('en-PH');
  let lineGradientSeq = 0;

  // Monotone cubic Hermite interpolation (Fritsch-Carlson) — smooths a
  // polyline into one curve that is mathematically guaranteed to never
  // over/undershoot past a segment's own two endpoints. A plain Catmull-Rom
  // spline (tried first) can bulge past a local peak or dip below a local
  // valley when neighbouring values swing sharply in alternating directions
  // (e.g. a low day sitting right next to a spike) — on a revenue line that
  // reads as the curve looping back on itself or dipping below the axis
  // between two points, a real rendering artifact, not just a style
  // preference. Monotone-cubic is the standard fix (this is the same
  // technique behind D3's curveMonotoneX / Chart.js's "monotone" tension
  // mode) and costs nothing extra at render time. Fewer than 3 points can't
  // bend, so those fall back to a plain straight segment.
  function smoothPath(pts) {
    const n = pts.length;
    if (!n) return '';
    if (n < 3) {
      return pts.map((p, i) => (i ? 'L' : 'M') + p.x.toFixed(1) + ',' + p.y.toFixed(1)).join(' ');
    }
    const dx = [], slope = [];
    for (let i = 0; i < n - 1; i++) {
      dx[i] = pts[i + 1].x - pts[i].x;
      slope[i] = dx[i] ? (pts[i + 1].y - pts[i].y) / dx[i] : 0;
    }
    const t = new Array(n);
    t[0] = slope[0];
    t[n - 1] = slope[n - 2];
    for (let i = 1; i < n - 1; i++) {
      // Opposite-signed (or flat) neighbouring slopes mean this point is a
      // local peak/valley — force the tangent to 0 there so the curve
      // actually turns instead of overshooting through it.
      if (slope[i - 1] === 0 || slope[i] === 0 || (slope[i - 1] > 0) !== (slope[i] > 0)) {
        t[i] = 0;
      } else {
        const w1 = 2 * dx[i] + dx[i - 1], w2 = dx[i] + 2 * dx[i - 1];
        t[i] = (w1 + w2) / (w1 / slope[i - 1] + w2 / slope[i]);
      }
    }
    let d = 'M' + pts[0].x.toFixed(1) + ',' + pts[0].y.toFixed(1);
    for (let i = 0; i < n - 1; i++) {
      const h = dx[i] / 3;
      const c1x = pts[i].x + h, c1y = pts[i].y + t[i] * h;
      const c2x = pts[i + 1].x - h, c2y = pts[i + 1].y - t[i + 1] * h;
      d += ' C' + c1x.toFixed(1) + ',' + c1y.toFixed(1) + ' ' + c2x.toFixed(1) + ',' + c2y.toFixed(1) + ' ' + pts[i + 1].x.toFixed(1) + ',' + pts[i + 1].y.toFixed(1);
    }
    return d;
  }

  // Plain straight-segment polyline — the default line style (a sharp,
  // jagged trajectory, day-to-day precision over a smoothed-over shape).
  // smoothPath() above stays available via opts.smooth for a caller that
  // wants the eased curve instead.
  function straightPath(pts) {
    if (!pts.length) return '';
    return pts.map((p, i) => (i ? 'L' : 'M') + p.x.toFixed(1) + ',' + p.y.toFixed(1)).join(' ');
  }

  /* Single-series trend line — "stock market trajectory" styling (advisor
     feedback, 2026-09, admin dashboard's Revenue Trend): a straight-segment
     line by default (opts.smooth opts into the monotone-cubic curve instead)
     with an interactive node at every point instead of the plain small dots
     this used to draw. Each node is a `.line-node` (added to the shared
     TIP_SEL floating-tooltip delegate above) carrying data-tip so hovering/
     focusing it shows the exact date/month and formatted value
     ("Sep 21, 2026: ₱5,800") — the reason there is no on-chart value label or
     legend box: a single series needs neither (the card title already names
     it), and the tooltip already carries the exact number. **Markers are
     invisible at rest (r="0")** and only drawn at r=6 for whichever point the
     pointer is currently nearest — window.wireLineChartHover() below is what
     actually grows/shrinks a node's `r`, since a zero-radius circle has no
     area of its own to receive a real mouse `:hover`; `.line-node:focus-
     visible` in tailwind.php still grows a tabbed-to node via plain CSS,
     since that pseudo-class matches the exact focused element regardless of
     its geometry.
     `points`: [{key, label, tip_label?, value}, ...] — `label` is the short
     axis tick text, `tip_label` (falls back to `label`) is the fuller string
     used in the tooltip alongside the formatted value. */
  window.lineChart = function (points, opts) {
    opts = opts || {};
    const H = opts.height || 190;
    // Trimmed to the minimum room the axis labels actually need (2026-09) —
    // pad.l/pad.r previously carried more slack than groupedBarChart's own
    // (32/14), so the two full-width stacked cards' plotted content didn't
    // line up at the same left/right edges even though their outer card
    // padding is identical; a peso y-axis label ("₱27,300") is wider than a
    // bar chart's plain integer count, so pad.l still can't drop all the way
    // to 32, but it no longer carries extra padding beyond that. pad.r keeps
    // just enough margin for the last point's own centered axis-label text
    // (e.g. "Sep 2026") to sit fully inside the plot without its right half
    // clipping past the SVG's edge — a bar chart's last category never has
    // this problem (its label centers on a whole bar group, well short of
    // the true edge), but a line's last point sits exactly at the plot's
    // right boundary, so it still needs a little more than the bar chart's
    // own 14.
    const pad = { l: opts.money !== false ? 50 : 36, r: 22, t: 14, b: 30 };
    // Left margin grows to fit the widest y-axis value ("₱311,500" no longer
    // loses its ₱ off the left edge on a narrow card).
    {
      const m0 = Math.max(1, ...points.map((p) => +p.value || 0));
      const top0 = Math.ceil(m0 / 4) * 4 || 4;
      const txt0 = opts.money !== false ? pesoWhole(top0) : String(Math.round(top0));
      pad.l = Math.max(pad.l, Math.ceil(txt0.length * 7.2) + 12);
    }
    // W is always at least the *natural*, content-driven minimum — exactly
    // like groupedBarChart()'s own minW. A caller that measures its real
    // container width (opts.containerWidth) gets W expanded to match it
    // exactly when that's larger, and since xAt()/yAt() below always compute
    // coordinates directly against the final W (never against a separate,
    // possibly-mismatched viewBox), every point genuinely spreads out to fill
    // the extra room — there is no post-hoc SVG stretch involved at all.
    // This replaces an earlier `opts.fill` mode that rendered at a flat
    // natural width and then stretched it to 100% via `preserveAspectRatio=
    // "none"`: that scales the viewBox's x-axis by whatever ratio separates
    // it from the SVG's real rendered width, and that ratio applies to
    // *everything* inside — grid-line positions, padding, and critically the
    // glyph outlines of every <text> element, which visibly stretch
    // horizontally the more undersized the viewBox is relative to the real
    // container (measurably fattened axis-label text was reported directly
    // from a screenshot the first time this chart tried it). Computing
    // coordinates straight against the true final width sidesteps that whole
    // class of bug — the rendered box and the viewBox are always the same
    // size, so there is nothing to stretch and axis text always renders at
    // its literal specified font-size. Bumping pointW's default here (90,
    // versus the 48 a purely scrollable natural-width chart would want on its
    // own) keeps Monthly's fixed 12-point natural minimum close to a typical
    // full-width card's own pixel width, so in practice it rarely needs
    // containerWidth to expand it at all on an ordinary desktop.
    const n = Math.max(1, points.length);
    const pointW = opts.pointW || 90;
    const minW = Math.max(520, (n - 1) * pointW + pad.l + pad.r);
    // opts.fill (the Revenue Trend Monthly view's own flag) is a hard "never
    // scroll" mode: it caps W to the caller's measured containerWidth exactly,
    // even when that's narrower than the natural minW — a fixed 12-point
    // series stays legible compressed a little, and a caller that wants this
    // guarantee is explicitly trading a small amount of per-point spacing for
    // zero horizontal scrollbar, ever, regardless of how narrow the card gets
    // (a laptop-width window, sidebar expanded, …). Without this flag W only
    // ever widens to fill extra room (see the comment below) and can still
    // legitimately exceed a container narrower than the natural minimum,
    // which is correct for a Daily view that's expected to scroll.
    const W = (opts.fill && opts.containerWidth)
      ? Math.max(1, opts.containerWidth)
      : Math.max(minW, opts.containerWidth || 0);
    const color = opts.color || PALETTE[0];
    const max = Math.max(1, ...points.map((p) => +p.value || 0));
    const niceMax = Math.ceil(max / 4) * 4 || 4;
    // Narrow card (an iPad's Monthly view): "Nov 2025"-style labels don't fit
    // side by side, so every month shows as a short name ("Nov", "Dec") with
    // the year on a second line only where it starts or changes. Only if even
    // that can't fit do labels thin out to every k-th one (below).
    const spacing0 = points.length > 1 ? (W - pad.l - pad.r) / (points.length - 1) : W;
    const longest = Math.max(1, ...points.map((p) => String(p.label || '').length));
    const monthYear = points.length > 1 && points.every((p) => /^[A-Za-z]{3} \d{4}$/.test(String(p.label || '')));
    const shortMode = monthYear && (longest * 6.8 + 12) > spacing0 && (3 * 6.8 + 10) <= spacing0;
    if (shortMode) pad.b += 12;
    const innerW = W - pad.l - pad.r;
    const innerH = H - pad.t - pad.b;
    const baseline = pad.t + innerH;
    const xAt = (i) => pad.l + (points.length <= 1 ? innerW / 2 : (i / (points.length - 1)) * innerW);
    const yAt = (v) => pad.t + (1 - v / niceMax) * innerH;
    const coords = points.map((p, i) => ({ x: xAt(i), y: yAt(+p.value || 0) }));

    const linePath = opts.smooth ? smoothPath(coords) : straightPath(coords);
    const areaPath = coords.length
      ? linePath + ' L' + coords[coords.length - 1].x.toFixed(1) + ',' + baseline.toFixed(1) +
        ' L' + coords[0].x.toFixed(1) + ',' + baseline.toFixed(1) + ' Z'
      : '';

    let grid = '';
    for (let g = 0; g <= 4; g++) {
      const gy = pad.t + (g / 4) * innerH;
      const gv = niceMax - (g / 4) * niceMax;
      grid += '<line x1="' + pad.l + '" y1="' + gy.toFixed(1) + '" x2="' + (W - pad.r) + '" y2="' + gy.toFixed(1) +
        '" stroke="var(--chart-track)"/>' +
        '<text x="' + (pad.l - 8) + '" y="' + (gy + 3).toFixed(1) + '" text-anchor="end" font-size="12" fill="var(--chart-axis)">' +
        (opts.money !== false ? pesoWhole(gv) : Math.round(gv)) + '</text>';
    }
    // The first/last labels anchor toward the inside of the plot (start/end)
    // instead of centering on their own point — a centered label's right (or
    // left) half extends past the point's own x-coordinate, and the first/
    // last point sits exactly at the plot's outer edge, so a long label
    // there ("Sep 2026") could clip past the SVG's boundary regardless of
    // pad.r/pad.l (reported directly from a screenshot: the "6" in "Sep
    // 2026" was cut off on the Monthly view's last point). Anchoring
    // start/end grows the text inward, toward the plot, instead — every
    // interior label still centers normally, since it has real neighbours on
    // both sides and the existing padding already covers it.
    // On a narrow card (an iPad's fixed-width Monthly view) the labels can't
    // all fit side by side: show every k-th one (first + last always kept,
    // dropping any that would crowd the last).
    const spacing = points.length > 1 ? innerW / (points.length - 1) : innerW;
    const step = shortMode ? 1 : Math.max(1, Math.ceil((longest * 6.8 + 12) / spacing));
    const lastI = points.length - 1;
    const showLabel = (i) => i === lastI || (i % step === 0 && lastI - i >= step);
    const axisLabels = points.map((p, i) => {
      if (!showLabel(i)) return '';
      const anchor = i === 0 ? 'start' : (i === points.length - 1 ? 'end' : 'middle');
      const x = coords[i].x.toFixed(1);
      const t = (y, txt) => '<text x="' + x + '" y="' + y + '" text-anchor="' + anchor + '" font-size="12" fill="var(--chart-axis)">' + esc(txt) + '</text>';
      if (shortMode) {
        const [mon, yr] = String(p.label).split(' ');
        const prevYr = i > 0 ? String(points[i - 1].label).split(' ')[1] : null;
        return t(H - 21, mon) + (yr !== prevYr ? t(H - 7, yr) : '');
      }
      return t(H - 9, p.label);
    }).join('');

    const nodes = points.map((p, i) => {
      const tipTxt = esc(p.tip_label || p.label) + ': ' + (opts.money !== false ? pesoWhole(p.value) : (+p.value || 0));
      // data-key (the bucket this point represents, e.g. "2026-09-21" or
      // "2026-09") is an optional passthrough, same idea as groupedBarChart()'s
      // own data-service-id/data-bucket — it's what lets a caller wire a click
      // drill-down (window.wireLineChartHover()'s onPick param below) back to
      // a specific point with no extra state needed.
      return '<circle class="line-node" tabindex="0" role="img" aria-label="' + tipTxt + '" data-tip="' + tipTxt + '" ' +
        (p.key != null ? 'data-key="' + esc(String(p.key)) + '" ' : '') +
        'cx="' + coords[i].x.toFixed(1) + '" cy="' + coords[i].y.toFixed(1) + '" r="0" fill="' + color + '"/>';
    }).join('');

    // A unique gradient id per render — this chart can now appear more than
    // once on a page (the admin dashboard's own Revenue Trend is the first
    // real caller), and a shared hardcoded id would make every instance's
    // <path fill="url(#id)"> resolve to whichever <defs> the browser
    // happens to paint last.
    const gid = 'lg-line-' + (++lineGradientSeq);
    // The crosshair is a single vertical dashed line, initially hidden
    // (opacity 0) — window.wireLineChartHover() below moves it to the
    // nearest node's own x and reveals it on pointermove anywhere over the
    // chart, and hides it again on pointerleave. Its y-span (pad.t..baseline)
    // matches the grid's own plot-area bounds exactly.
    const crosshair = '<line class="line-crosshair" x1="' + coords[0].x.toFixed(1) + '" x2="' + coords[0].x.toFixed(1) +
      '" y1="' + pad.t + '" y2="' + baseline.toFixed(1) + '" stroke="var(--chart-axis)" stroke-width="1" stroke-dasharray="4 4" opacity="0"/>';
    // Always a literal pixel width — W above is already the exact final size
    // (natural minimum, or the caller's measured container width when
    // that's larger), so the viewBox and the rendered box always match 1:1.
    const svgAttrs = 'width="' + W + '" height="' + H + '" class="mx-auto block" style="min-width:' + Math.min(W, 720) + 'px"';
    // opts.fill's wrapper is overflow-hidden (never overflow-x-auto) so there
    // is no scroll TRACK to ever render either, not just no overflow — a
    // belt-and-braces match for W being capped to containerWidth just above.
    const wrapCls = opts.fill ? 'w-full overflow-hidden' : 'w-full overflow-x-auto';
    return '<div class="' + L_WRAP + ' w-full"><div class="' + wrapCls + '"><svg viewBox="0 0 ' + W + ' ' + H + '" ' + svgAttrs + '>' +
      '<defs><linearGradient id="' + gid + '" x1="0" y1="0" x2="0" y2="1">' +
      '<stop offset="0" stop-color="' + color + '" stop-opacity="0.45"/>' +
      '<stop offset="1" stop-color="' + color + '" stop-opacity="0.05"/></linearGradient></defs>' +
      grid + axisLabels +
      (areaPath ? '<path class="line-area" d="' + areaPath + '" fill="url(#' + gid + ')"/>' : '') +
      '<path class="line-path" d="' + linePath + '" fill="none" stroke="' + color + '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>' +
      crosshair + nodes +
      '</svg></div></div>';
  };

  /* Companion to lineChart() — a full-width interactive crosshair, the one
     piece that genuinely can't be baked into a returned markup string. On
     pointermove anywhere over the chart (not just precisely on a node —
     nodes render at r="0" by design, no area of their own to be "over" at
     all), it snaps a vertical dashed guideline to the nearest data point's x,
     grows *that one node's* `r` from 0 to 6 (shrinking whichever node was
     previously active back to 0), and shows the shared floating tooltip for
     it — matching the "hover the whole chart, not just the dots" feel of a
     typical interactive trend/stock chart, and the "markers hidden until
     hovered" look the admin dashboard's Revenue Summary chart asked for.
     Call once right after inserting lineChart()'s markup (and after any
     entrance animation call — order between the two doesn't matter, they
     touch different elements). A no-op if the chart has no nodes (e.g. an
     empty dataset never got this far).
     `onPick(key)` is optional — when given, it wires this chart as a
     click-to-drill panel like every other one on the admin dashboard (a bar,
     a donut arc, a metric tile). Mouse: a real click landing precisely on a
     r="0" node is effectively impossible, so a click anywhere on the whole
     `<svg>` resolves to the *nearest* point via the same `nearest()` used for
     hover, exactly mirroring how hover itself works on this chart. Keyboard:
     each node is individually focusable (`tabindex="0"`) regardless of its
     zero visible radius, so Tab lands on the exact point and Enter/Space
     reads `e.target.dataset.key` directly — no nearest-point search needed
     there, since the event target already *is* the intended point. */
  window.wireLineChartHover = function (container, onPick) {
    if (!container) return;
    const svg = container.querySelector('svg');
    const crosshair = container.querySelector('.line-crosshair');
    const nodes = Array.prototype.slice.call(container.querySelectorAll('.line-node'));
    if (!svg || !crosshair || !nodes.length) return;
    let active = null;

    function nearest(clientX) {
      let best = nodes[0], bestDist = Infinity;
      nodes.forEach((n) => {
        const rect = n.getBoundingClientRect();
        const d = Math.abs((rect.left + rect.width / 2) - clientX);
        if (d < bestDist) { bestDist = d; best = n; }
      });
      return best;
    }
    function move(e) {
      const n = nearest(e.clientX);
      if (n !== active) {
        if (active) active.setAttribute('r', '0');
        n.setAttribute('r', '6');
        active = n;
      }
      const cx = n.getAttribute('cx');
      crosshair.setAttribute('x1', cx);
      crosshair.setAttribute('x2', cx);
      crosshair.setAttribute('opacity', '1');
      showTip(n, e.clientX, e.clientY);
    }
    function leave() {
      if (active) { active.setAttribute('r', '0'); active = null; }
      crosshair.setAttribute('opacity', '0');
      hideTip();
    }
    svg.addEventListener('pointermove', move);
    svg.addEventListener('pointerleave', leave);

    if (typeof onPick === 'function') {
      svg.style.cursor = 'pointer';
      svg.addEventListener('click', (e) => {
        const n = nearest(e.clientX);
        if (n && n.dataset.key != null) onPick(n.dataset.key);
      });
      nodes.forEach((n) => {
        n.addEventListener('keydown', (e) => {
          if (e.key !== 'Enter' && e.key !== ' ') return;
          if (n.dataset.key == null) return;
          e.preventDefault();
          onPick(n.dataset.key);
        });
      });
    }
  };

  /* Entrance animation for a lineChart() render — the line-chart counterpart
     of playDonutIntro()/playGroupedBarChartIntro(): the path draws itself in
     (a classic stroke-dashoffset sweep, using the path's own real length via
     getTotalLength() so it works regardless of the chart's data or width),
     the area fill fades in alongside it. lineChart() only returns a markup
     string, so this can't be baked into the render itself — call
     window.playLineChartIntro(container) once right after inserting it.
     Skips entirely under prefers-reduced-motion. There is no per-node pop-in
     step (there was, until markers went to r="0" at rest — animating the
     scale of a permanently zero-radius circle has nothing to show); a node
     only ever becomes visible via window.wireLineChartHover()'s own hover
     logic, never on entrance. */
  window.playLineChartIntro = function (container) {
    if (!container || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const path = container.querySelector('.line-path');
    const area = container.querySelector('.line-area');
    const EASE = 'cubic-bezier(.16,1,.3,1)';
    const DRAW_MS = 700;

    if (path) {
      const len = path.getTotalLength();
      path.style.strokeDasharray = String(len);
      path.animate(
        [{ strokeDashoffset: len }, { strokeDashoffset: 0 }],
        { duration: DRAW_MS, easing: EASE, fill: 'backwards' }
      );
      // The inline dasharray is only scaffolding for the sweep — clear it
      // once done so a later hover/focus restyle of the path never has to
      // fight a leftover dash pattern.
      setTimeout(() => { path.style.strokeDasharray = ''; }, DRAW_MS + 30);
    }
    if (area) {
      area.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 500, delay: 150, easing: EASE, fill: 'backwards' });
    }
  };

  window.CHART_PALETTE = PALETTE;
  window.CHART_OTHER   = OTHER_HUE;
})();
