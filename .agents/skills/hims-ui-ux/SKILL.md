---
name: hims-ui-ux
description: Applies HIMS-specific UI implementation and design-consistency rules for Blade, Tailwind CSS, Alpine.js, shared components, layouts, themes, permissions, and interaction contracts. Use when adding, changing, reviewing, polishing, or testing any HIMS page, form, table, modal, dashboard, workflow, or frontend behavior. Always use it together with `ui-ux-pro-max` and `impeccable`; HIMS rules remain the repository-specific source of truth.
---

# HIMS UI/UX

Use this skill as the project-specific implementation layer for HIMS interfaces. It defines what must remain consistent with this repository; it is not a general UI/UX handbook.

## Mandatory Companion Stack

For every HIMS UI/UX task, load and apply all three skills. This is the default workflow, not an optional enhancement:

1. `hims-ui-ux` owns HIMS architecture, persistent visual identity, component contracts, operational density, themes, workflows, and repository-specific invariants. Read [references/design-consistency.md](references/design-consistency.md) in full before reviewing or editing HIMS UI.
2. `ui-ux-pro-max` supplies general design intelligence, UX/accessibility guidance, and Laravel/Tailwind implementation guidance. Follow its query contract using the smallest applicable search: design-system search for a new or system-wide direction, or one focused domain/stack search for a local concern.
3. `impeccable` supplies product context, surface mode, craft floor, and bounded visual QA. Run its context command once per session, load the applicable playbook, and read its craft-floor reference immediately before a UI edit.

Do not skip either companion because a task appears small; apply only the portions relevant to the requested surface. Do not let companion guidance silently redesign an established HIMS surface during a focused change.

Apply precedence in this order:

1. The user's explicit brief and verified product/domain behavior.
2. HIMS repository truth and the design invariants in this skill.
3. Existing surface identity and shared component contracts.
4. Applicable `impeccable` and `ui-ux-pro-max` recommendations.

When general guidance conflicts with an intentional HIMS convention, preserve the HIMS convention unless the user explicitly requests a redesign. Add the relevant HIMS security, database, audit, Laravel, or testing skill when the interface touches those domains. This skill does not replace server-side validation or authorization.

## Current HIMS Baseline

Verify the actual files before relying on these conventions because component contracts may evolve.

- HIMS is server-rendered Laravel Blade with Tailwind CSS, Alpine.js, Vite, and shared components under `resources/views/components/ui`.
- Authenticated pages use `layouts/app.blade.php` and its sidebar, topbar, toast notifications, decision confirmation, loading, and session-warning partials.
- Authentication pages use `layouts/guest.blade.php` and shared auth components. Landing and legal pages have standalone shells.
- Admin and Super Admin screens are view namespaces, not separate design systems. Preserve panel-aware routes, guards, permissions, navigation, and Gate-controlled visibility.
- The visual language uses Inter, the `primary` clinical-blue scale, semantic `success`/`warning`/`danger` colors, neutral surfaces, restrained motion, light/dark themes, and high operational readability.

## Reusable Workflow for Every HIMS UI Change

1. Render and inspect the current page before editing. Trace its route, controller or server response, layout, Blade partials/components, Alpine or JavaScript hooks, authorization checks, and nearest tests.
2. Find the closest existing HIMS screen or component pattern and reuse it. Inspect the component source before passing props or changing its behavior.
3. Define the smallest interface change that preserves existing functionality, data, permissions, redirects, validation, loading, error handling, and theme behavior.
4. Implement every relevant state: normal, validation or operation failure, loading or duplicate-submit protection, success feedback, empty data, and disabled/read-only/unauthorized behavior when applicable.
5. Verify the rendered result at mobile, tablet, and desktop widths, in light and dark themes where supported, then run proportional tests and frontend builds.

Do not turn a focused request into a page redesign, new framework, new component system, dependency change, or unrelated cleanup. Preserve user work and existing public contracts.

## Reuse Shared HIMS Components

Prefer an existing `<x-ui.*>` component when its contract fits:

- `alert`: `variant`, `title`, `message`, `dismissible`
- `badge`: `status`, `variant`, `dot`
- `button`: `variant`, `size`, `href`, `type`, `icon`
- `card`: `title`, `subtitle`, `padding`, with optional `header`, `actions`, and `footer` slots
- `field`: `name`, `label`, `type`, `value`, `hint`, `required`, `disabled`, `placeholder`, `options`, `rows`
- `loader`: `label`, `size`
- `modal`: `name`, `title`, `maxWidth`
- `nav-item`: `href`, `icon`, `active`, `badge`, `disabled`, `sub`
- `page-header`: `title`, `subtitle`, `breadcrumbs`, with optional `actions`
- `stat`: `label`, `value`, `icon`, `tone`, `hint`, `summary`, `href`, `compact`
- `table`: `stickyHeader`, `zebra`, plus `table.head`, `table.row`, `table.th`, `table.td`, and `table.empty`

Do not invent props. Extend a shared component only when multiple real consumers benefit and existing uses remain compatible; otherwise keep a local exception in the page.

## HIMS Layout and Density

### Use the Available Screen

- Authenticated operational pages are full-width by default through `<x-app-layout>` and `max-w-none`, with responsive gutters such as `px-4 sm:px-6 lg:px-8`.
- Avoid large empty side gutters while forms, filters, tables, queues, or metrics are pushed below the fold. Use available horizontal space to bring related work into view.
- Put substantial companion panels side by side at `lg` or `xl` only when both remain independently usable. Use `minmax(0, ...)`, `min-w-0`, `items-start`, and stack them below the chosen breakpoint.
- Boxed widths are appropriate for login dialogs, narrow security/profile forms, and focused prose such as legal pages—not operational dashboards, catalogs, or tables.
- Do not add oversized empty hero sections, decorative spacer blocks, or fixed heights that waste working space.

### Responsive Behavior and Overflow

- Reflow the same content across mobile, tablet, desktop, and wide desktop; do not create divergent mobile and desktop workflows.
- Page-level horizontal scrolling is not acceptable. Use fluid widths, responsive grids, wrapping toolbars, and `min-w-0` on text-bearing flex/grid children.
- Tables may use the shared `overflow-x-auto` shell as a safeguard, but first progressively hide secondary columns or use a mobile composition. Always preserve the row identifier, primary state, and required action.
- Inputs and controls should be `w-full` on narrow screens and size naturally at larger breakpoints. Avoid rigid widths for dynamic labels or values.
- Long unstructured strings may use `truncate` only with `min-w-0` and an accessible `title`; do not truncate short controls, status labels, or operational values.
- Verify no horizontal overflow at 375px, 768px, 1280px, and 1920px, including nested cards, modals, and table shells.

## HIMS Interface Conventions

### Forms and Buttons

- Use visible labels, associated hints/errors, retained `old()` values, correct required/disabled semantics, and the existing validation bags.
- Keep equivalent fields aligned and equally sized. Do not let component class composition accidentally drop `w-full`, padding, or error/focus styles.
- Every `<select>` needs arrow clearance: compact selects use at least `pl-2.5 pr-8`; standard selects use `pl-3 pr-10`. Do not use symmetrical `px-*` where text can sit under the browser chevron.
- Use native date/datetime constraints when appropriate, update dependent limits, and mirror all chronology rules in Laravel validation. Client constraints are UX, not persistence authority.
- Use `<x-ui.button>` and its established variants/sizes. Preserve visible focus, disabled behavior, and `data-loading-text` integration.

### Cards, Dashboards, and Metrics

- Use `<x-ui.card>` for ordinary panels. Do not nest cards inside cards or create a card for a single tiny control.
- Primary operational KPI cards use the existing three-zone pattern: identity and icon, dominant tabular value, then a plain contextual footer separated by a subtle divider.
- Keep KPI footers badge-free and concise. Use semantic tones only when they convey real operational meaning.
- Give KPI cards the shared cursor-following tooltip only when they have useful context or underlying records; follow the design-consistency reference.
- Prefer compact, scannable dashboard grids over promotional copy or decorative surfaces.

### Tables and Pagination

- Build tables from the shared table components and retain readable cell padding, complete identifiers, tabular numbers, and usable actions at every breakpoint.
- Use `$collection->links()` with `resources/views/vendor/pagination/tailwind.blade.php`; do not create page-local pagination styles.
- Pagination must never render more than 20 page buttons. Prefer `->onEachSide(1)` and retain the same ceiling for any client-side paginator.

### Modals and Secondary Actions

- Use the shared modal or decision-confirmation flow for secondary, contextual, or confirmatory work when leaving the page would interrupt the primary workflow.
- Do not move a primary workflow into a modal merely to save space.
- Preserve accessible naming, keyboard operation, dismissal/focus behavior, responsive viewport fit, and server authorization.
- Reuse existing `data-confirm-*` hooks; do not introduce competing confirmation dialogs or browser `alert()`/`confirm()` calls.

### Navigation

- Preserve route names, breadcrumbs, back/redirect behavior, sidebar active states, nested `nav-item` structure, panel context, and permission-based visibility.
- Do not hide a forbidden action only in the UI and treat that as authorization. Server-side enforcement remains required.
- Use the existing icon component and consistent outline style. Icon-only controls require accessible names; decorative icons remain hidden from assistive technology.

## Feedback, Banners, Loading, and Empty States

### Notifications and Banners

- Authenticated `<x-app-layout>` pages use `layouts/partials/toast-notifications.blade.php` as the single feedback HUD for `status`, `success`, `error`, `warning`, and `info`.
- Do not duplicate a toast with an in-page success banner. Use `<x-ui.alert>` only for actionable error summaries or persistent operational/compliance warnings.
- Do not add welcome, marketing, reassurance, or decorative banners. Keep the page-header subtitle to one line of orientation.
- Do not create ad-hoc popup styles, SweetAlert dialogs, or browser alerts. Update the shared toast or confirmation primitive when a system-wide change is genuinely required.

### Loading and Async States

- Forms with submit buttons use the existing in-button loader through `data-loading-text`; do not also show the central loading overlay for the same action.
- The central overlay is for link navigation, downloads/exports, or programmatic submissions without a contextual submit button.
- For server-backed tabs or views, follow the tab-transition sequencing contract in `references/design-consistency.md`; never expose the target tab with stale content before its loading state begins.
- Async HIMS content must distinguish loading, success, empty, and error. Use a lightweight skeleton shaped like the final component, preserve layout size, support dark mode and reduced motion, and prevent stale responses from replacing newer results.
- Never display fake HIMS data during loading or expose testing shortcuts in production UI.

## Restraint Rules

- No emojis in headings, labels, buttons, options, badges, table cells, validation, or notification copy. Use `<x-ui.icon>` when an icon has a real purpose.
- Reserve `<x-ui.badge>` for essential entity lifecycle/status values. Do not use decorative pills, duplicate a state already stated in text, or use badges for counts.
- Every visible control or block must support a real user task, communicate necessary state, prevent an error, or meet an accessibility need. Omit speculative controls and decorative metadata.
- Preserve unrelated controls and behavior unless removal is explicitly requested or the changed area contains a clearly obsolete or developer-only element.

## Accessibility and Themes

Use `ui-ux-pro-max` for broad accessibility and interaction guidance, then apply these HIMS requirements:

- Target WCAG 2.2 AA: normal text contrast at least 4.5:1, large text at least 3:1, and meaningful non-text boundaries/focus indicators at least 3:1 where applicable.
- Preserve semantic headings, landmarks, labels, tables, buttons/links, document order, error associations, keyboard navigation, and screen-reader meaning.
- Never rely on color alone for status, validation, risk, or selection. Keep visible text or another non-color indicator.
- Use existing semantic color tokens and verify light and dark themes independently. Do not add page-local hardcoded colors that bypass HIMS theme behavior.
- Preserve visible focus and prevent fixed/sticky elements, modals, or loading overlays from obscuring keyboard focus.
- Respect `prefers-reduced-motion`; motion must remain restrained and must not be required to understand or operate the interface.

## Verification

Use `hims-testing` to choose proportional checks and report exactly what ran.

- Blade/component change: run the nearest feature tests and inspect guest/authenticated plus permitted/forbidden states when relevant.
- CSS, Tailwind, Alpine, JavaScript, or Vite input change: run `npm run build` and exercise the actual interaction.
- Layout change: render mobile, tablet, desktop, and wide desktop; check light/dark themes, content clipping, page and nested overflow, focus states, and primary actions.
- Form change: verify validation errors, retained values, duplicate-submit protection, keyboard flow, and server-error behavior.
- Modal or async change: verify open/close/cancel, focus, loading, success, empty, error, retry, and stale-request behavior as applicable.

A passing Blade assertion proves rendered output, not browser interaction or responsive behavior. Do not claim the UI is complete without the checks appropriate to the changed layer.
