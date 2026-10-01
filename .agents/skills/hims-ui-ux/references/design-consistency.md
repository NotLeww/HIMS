# HIMS Design Consistency

Read this reference in full for every HIMS UI implementation or review. These are persistent product decisions recovered from the established HIMS UI direction; do not replace them with a generic design-system recommendation.

Verify component source and rendered behavior before relying on exact class names because repository contracts can evolve. Fix shared primitives when a rule is system-wide; keep a local exception when only one real consumer needs it.

## Visual Character

HIMS is a dense operational healthcare system, not a marketing site. Preserve:

- Inter typography, the clinical-blue `primary` scale, neutral surfaces, semantic success/warning/danger colors, restrained motion, and strong data readability;
- compact, scannable hierarchy with clear operational emphasis;
- consistent light and dark themes driven by shared tokens;
- one coherent icon family through `<x-ui.icon>`, never emojis as interface icons;
- useful visual polish without decorative cards, badges, banners, metadata, or animation that competes with work.

Every visible element must support a real task, communicate necessary state, prevent an error, or satisfy accessibility. Do not expose demo controls, fixture generators, debugging actions, or test shortcuts in production UI.

## Use the Available Screen

Authenticated operational workspaces, catalogs, dashboards, tables, inventory, and procurement screens are full-width by default through `<x-app-layout>` and `max-w-none`, with responsive gutters such as `px-4 sm:px-6 lg:px-8`.

- Do not leave large side gutters while forms, filters, queues, metrics, or tables are pushed below the fold.
- Use wide-screen space to place substantial companion panels side by side. Prefer equal `xl:grid-cols-2` for peers or intentional `minmax(0, ...)` tracks for asymmetric workspaces.
- Put `min-w-0` on grid/flex children containing text and use `items-start` unless equal height has a functional purpose.
- Split at `lg` or `xl` only when both panels remain independently usable. Keep sequential or width-hungry content stacked.
- Boxed widths are for login dialogs, narrow profile/security forms, and focused prose such as legal pages—not operational interfaces.
- Use horizontal dashboard grids (`sm:grid-cols-2`, `lg:grid-cols-4`, `xl:grid-cols-5`) to keep important metrics above the fold.
- Keep filters, search, selectors, and actions in cohesive wrapping toolbars rather than tall stacks when width allows.

When a user must scroll past vertically stacked content while wide desktop space is empty, recompose the layout horizontally.

## Responsive Acceptance Standard

Page-level horizontal scrolling is a layout failure. Reflow the same workflow across mobile, tablet, desktop, and wide desktop; do not create divergent workflows or shrink text below readable sizes.

- Verify at 375px, 768px, 1280px, 1536px when a two-panel layout activates, and 1920px.
- Use fluid widths and responsive grid steps. Avoid rigid pixel widths for content-bearing wrappers and controls.
- Inputs and controls are `w-full` on narrow screens and size naturally at larger breakpoints.
- Toolbars and action rows wrap or stack without hiding critical controls.
- Use `min-w-0` with `break-words`, or with `truncate` plus an accessible full-value mechanism such as `title`, for long unstructured values.
- Never truncate short controls, option labels, statuses, identifiers required to distinguish a row, or primary actions.
- Tables progressively hide secondary columns or use a mobile composition. Always preserve row identity, primary state, and required action.
- The shared table's `overflow-x-auto` is a safeguard for genuinely dense data, not the default responsive strategy.
- Modals must fit the viewport, preserve controls, and use a vertically scrollable content region when needed.
- Charts must resize without clipped plots, unreadable labels, or overflowing legends.

Confirm there is no unwanted horizontal scrollbar on the page, `main`, cards, modals, or table shells.

## Component Sizing and Text Clearance

Size controls for their real content plus buffer space. No letters, descenders, values, or labels may be clipped or covered by adjacent icons.

- Compact `<select>` controls use at least `pl-2.5 pr-8`; standard selects use at least `pl-3 pr-10`. Do not use symmetrical `px-*` when text can sit beneath the browser chevron.
- Do not force dynamic button, select, badge, or input content into narrow widths such as `w-14`, `w-16`, or arbitrary small `max-w-*` values.
- Use adequate horizontal padding and readable line height. Avoid fixed heights that clip `g`, `y`, `p`, `q`, `j`, or accented characters.
- Table cells and status labels need enough padding and width for complete operational values.
- Keep equivalent fields aligned and equally sized; component class composition must not accidentally remove `w-full`, padding, focus, or error styling.

## Cards and Operational KPI Metrics

Use `<x-ui.card>` for ordinary panels. Do not nest cards or create a card around one tiny control.

Primary operational KPI cards use a consistent three-zone structure:

1. **Identity:** bold uppercase category label plus a purposeful semantic icon tile.
2. **Value:** dominant, high-contrast, `tabular-nums` metric with a smaller baseline-aligned unit. Do not add a state pill beside it.
3. **Context:** plain concise text below a subtle divider. Keep this footer badge-free.

Reference structure:

```blade
<div class="flex flex-col justify-between rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs transition-all duration-150 dark:border-neutral-800 dark:bg-neutral-900/95">
    <div class="flex items-center justify-between gap-2">
        <p class="text-xs font-bold uppercase tracking-wider text-violet-700 sm:text-sm dark:text-violet-300">Predicted Demand</p>
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700 ring-1 ring-violet-200 dark:bg-violet-950/80 dark:text-violet-300 dark:ring-violet-800/50">
            <x-ui.icon name="sparkles" class="h-5 w-5" />
        </span>
    </div>
    <div class="mt-3 flex items-baseline gap-1.5">
        <span class="text-3xl font-black tracking-tight tabular-nums text-violet-700 sm:text-4xl lg:text-5xl dark:text-violet-300">1,181</span>
        <span class="text-sm font-bold text-violet-600/80 sm:text-base dark:text-violet-400/80">units</span>
    </div>
    <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
        <span class="truncate text-xs font-medium text-neutral-600 sm:text-sm dark:text-neutral-300">~39.4 units / day (30d horizon)</span>
    </div>
</div>
```

Use semantic tone consistently: neutral for stock/reference values, violet for AI/forecasting, primary blue for procurement/actions, rose for critical shortage/risk, and emerald for healthy/successful states. Verify actual contrast in both themes.

### Cursor-Following KPI Summary Tooltips

Use the shared delegated metric tooltip instead of page-local implementations. A card participates only when it supplies `summary`/`data-metric-summary` or actual `details`/`data-metric-details`; never generate a tooltip from the card's visible text.

- For count metrics, show the actual records behind the number (for example item name, SKU, and operational state). Limit long lists to the most relevant five records, then state how many remain and retain the card's route to the complete list.
- Use a concise explicit `summary` only for useful business context that is not already printed on the card. Never repeat the card label, value, or footer as the tooltip body.
- Keep async detail payloads synchronized with their displayed counts so live metrics do not expose stale records.
- Position the tooltip near the pointer with `position: fixed`, teleport it to `body`, flip it to the opposite side when needed, and clamp it inside an 8px viewport gutter.
- Update its position on pointer movement. Never animate `left` or `top`; that makes the tooltip fly from its old or initial position. Use only a restrained 100–150ms opacity/scale entrance and honor `prefers-reduced-motion`.
- Mouse click or pointer focus must preserve the current pointer position. Run the stable card-relative fallback only for genuine `:focus-visible` keyboard focus.
- Keep it non-interactive with `pointer-events: none`, hide it on pointer leave or focus out, and associate it through `role="tooltip"` and `aria-describedby`.
- Hover must not be the only way to obtain required information. Keep the primary metric and context visible, provide the same tooltip on keyboard focus, and retain a click/tap path to the detailed records when available.

## Badges and Icons

- Use `<x-ui.badge>` for essential entity lifecycle states such as Pending, Approved, Dispatched, Delivered, or Archived.
- Let the shared badge component map domain statuses to semantic colors. Add a missing system-wide status mapping there instead of coloring one call site.
- One state per field. Do not repeat a status already communicated by nearby text or turn counts, KPI labels, card footers, page headers, or actions into pills.
- Keep badge labels short and textual; color or a dot may reinforce but never replace the label.
- Use plain `tabular-nums` text for counts.
- Use `<x-ui.icon>` with a real icon name. Icon-only controls need an accessible name; icons beside visible text are normally decorative and hidden from assistive technology.
- Keep icon size, stroke, and outline/filled treatment consistent within the same hierarchy.

## Notifications, Alerts, and Confirmation

Authenticated `<x-app-layout>` pages use `layouts/partials/toast-notifications.blade.php` as the single transient feedback HUD for `status`, `success`, `error`, `warning`, and `info`.

- Never render an in-page success alert for a message already handled by the toast.
- Use `<x-ui.alert>` only for actionable validation/error summaries or persistent operational/compliance warnings.
- Do not add welcome, marketing, reassurance, celebratory, or decorative banners.
- Keep the page-header subtitle to one line of orientation.
- Do not add SweetAlert, browser `alert()`/`confirm()`, or page-local popup styles.
- Confirmation uses the shared decision-confirmation flow and `data-confirm-*` hooks.

The toast's established visual anatomy is a floating upper-right `rounded-2xl` card with a circular semantic status icon, uppercase category and relative timestamp, concise outcome/grounding copy, dismiss control, and a thin bottom countdown accent. If the system-wide appearance must change, update the shared partial rather than duplicating it.

Preserve this visual signature in the shared partial:

- HUD: `fixed top-16 right-0 z-[80] flex flex-col items-end gap-3 p-4 sm:p-6 sm:max-w-md pointer-events-none`;
- card: `pointer-events-auto relative w-full overflow-hidden rounded-2xl border border-neutral-200/90 bg-white/95 p-4 shadow-xl backdrop-blur-md dark:border-neutral-700 dark:bg-neutral-900/95`;
- icon tile: `mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-1`, using emerald for success, rose for error, amber for warning, and `primary` blue for info;
- category: `text-xs font-bold uppercase tracking-wider text-neutral-400 dark:text-neutral-500` with a small relative timestamp;
- message: `mt-0.5 text-xs font-medium leading-snug text-neutral-800 sm:text-sm dark:text-neutral-200`;
- countdown rail: `absolute inset-x-0 bottom-0 h-0.5 bg-neutral-100 dark:bg-neutral-800`, with a semantic fill that pauses while hovered.

Dispatch server feedback through Laravel flash keys and client feedback through the existing `toast` event contract. Preserve the supported success/error/warning/info types and let the shared renderer own presentation.

## Loading and Asynchronous Content

Show one loading indicator per action.

- Button-triggered form submissions use the established in-button loader and contextual `data-loading-text`; do not also show the central overlay.
- Reserve the central overlay for navigation, downloads/exports, or programmatic submission without a contextual submit button.
- Async content distinguishes loading, success, empty, and error. Never show an empty-state message while a request is still pending.
- Use lightweight CSS skeletons shaped like the final component, not generic repeated rectangles. Match its sections, count, approximate dimensions, responsive layout, and reserved space.
- Skeletons support light/dark themes, stop when loading ends, respect `prefers-reduced-motion`, and expose appropriate `aria-busy` or status semantics without repeatedly announcing animation.
- Do not generate fake numbers, fake chart data, fake forecasts, or temporary records during loading.
- Provide a useful error and Retry when appropriate; never expose raw framework/database errors.
- Prevent stale requests from overwriting a newer selection.
- An async modal opens at its intended size with a modal-specific skeleton and does not jump or resize unnecessarily when content arrives.

Compare loading and loaded states at each supported breakpoint. The real content should feel like it replaces the skeleton rather than an unrelated layout.

## Tables and Pagination

- Build tables with shared table components, complete identifiers, `tabular-nums` for numeric values, readable padding, and usable actions.
- All server pagination uses `resources/views/vendor/pagination/tailwind.blade.php` through `$collection->links()` or the appropriate fragment plus `onEachSide(1)` call.
- Do not create page-local pagination styling.
- Pagination must never render more than 20 page-number buttons. Preserve the same ceiling in client-side pagination.
- Keep the standard hierarchy: results summary on the left; grouped navigation on the right; high-contrast active page; subtle bordered inactive, previous, and next buttons; clear disabled styling.

Preserve the shared pagination signature:

- summary: `text-xs text-neutral-600 dark:text-neutral-400`, with `font-semibold text-neutral-900 dark:text-neutral-100` values;
- group: `flex items-center gap-1 text-xs`;
- active page: `inline-flex min-w-[2rem] items-center justify-center rounded-lg bg-neutral-900 px-2.5 py-1.5 text-xs font-semibold text-white shadow-2xs dark:bg-primary-600`;
- inactive page: `inline-flex min-w-[2rem] items-center justify-center rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700/80`;
- disabled previous/next: `cursor-not-allowed border-neutral-200 bg-neutral-100/60 text-neutral-400 dark:border-neutral-800 dark:bg-neutral-800/40 dark:text-neutral-600`.

## Forms and Dates

- Use visible labels, associated hints/errors, retained `old()` values, correct required/disabled semantics, named error bags where established, and server-side authorization/validation.
- Native `date`, `datetime-local`, `min`, and `max` are preferred when they fit the domain.
- State the precise chronology rule; do not confuse `after` with `after_or_equal`, or “not in the past” with “after today.”
- Recalculate dependent limits when the controlling date changes. Handle a now-invalid selected value only in a visible, expected way.
- Apply the same rule to query-string, database, master-data, and restored prefills as manual input.
- Build local calendar dates without UTC conversion drift; do not use `toISOString()` when it could move `YYYY-MM-DD` across a day boundary.
- Mirror client constraints in Laravel validation and in the owning service when non-HTTP callers can reach the rule.

## Accessibility and Theme Quality

Target WCAG 2.2 AA.

- Normal text contrast is at least 4.5:1; large text at least 3:1; meaningful non-text boundaries, focus indicators, and graphics at least 3:1 where applicable.
- Never rely on color alone for status, risk, validation, or selection.
- Preserve semantic headings, landmarks, tables, labels, buttons/links, document order, keyboard behavior, and visible focus.
- Keep hints/errors programmatically associated with fields.
- Dialogs need an accessible name, keyboard operation, correct focus behavior, and usable dismissal.
- Check default, hover, active, focus, disabled, error, and selected states independently in light and dark themes.
- Muted text must remain readable. Placeholders never replace visible labels.
- Charts keep readable labels/legends and use labels, markers, line styles, or patterns when color alone is insufficient.
- Sticky/fixed UI, overlays, and modals must not obscure keyboard focus.
- Motion is restrained, purposeful, and reduced or removed under `prefers-reduced-motion`.

## Visual Verification

For any visual or interaction change:

1. Render the actual authenticated/guest and permitted/forbidden state as relevant.
2. Inspect mobile, tablet, desktop, and wide desktop together in one bounded visual pass.
3. Check both themes, overflow, clipping, skeleton-to-content continuity, focus, control states, and primary actions.
4. Fix the observed issues in one batch and perform at most one confirmation pass, following `impeccable`'s bounded QA rule.
5. Run the nearest HIMS tests and `npm run build` when Vite inputs changed. Report only checks actually performed.
