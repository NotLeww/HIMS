# Accessibility Test: Keyboard Accessibility

Date: September 28, 2026  
Primary browser: Microsoft Edge 154.0.4258.37  
Requirement: All functions are accessible using the keyboard.

## Result

Pass after focused fixes. The audit retained native HTML behavior wherever available and corrected only confirmed custom-control and dialog gaps.

## Test Matrix

| Page / component | Keyboard action | Expected behavior | Actual behavior | Result |
| --- | --- | --- | --- | --- |
| Staff, Admin, and Super Admin login panels | `Tab`, `Shift+Tab`, `Space`, `Enter` | Move through email, password, password visibility, recovery link, and submit in document order | Native inputs, link, and buttons expose the expected order and activation behavior; email receives initial focus | Pass |
| Application skip link and sidebar | `Tab`, `Enter`, `Escape` | Reveal skip link, move to main content, activate links/sections, and close mobile navigation | Skip link targets `#main-content`; navigation uses links/buttons; hidden sidebar is inert; Escape closes the mobile sidebar | Pass |
| Header search and menus | `Tab`, arrow keys, `Enter`, `Escape` | Search suggestions are selectable without a mouse and overlays can be exited | Search supports Up/Down, Enter, and Escape; notification/user controls are native buttons; dropdown Escape handling preserves focus | Pass |
| Dashboard controls and chart | `Tab`, `Enter`, `Space`, Left/Right arrows | Operate filters, toggles, risk items, and inspect chart points | Native controls retain browser behavior; custom risk items now expose button semantics and Enter/Space activation; chart supports Left/Right inspection | Pass |
| Forms and validation | `Tab`, `Shift+Tab`, `Space`, `Enter` | Complete controls, toggle checkboxes, submit, and focus the first invalid field | Inputs, selects, textareas, checkboxes, and submit buttons are native; runtime focuses the first invalid field | Pass |
| Inventory item location combobox | `Tab`, `Enter`, `Space`, `Escape` | Open/search/select/clear a location and leave the popup | Trigger is a native button; popup search is focusable; clear control now supports Enter/Space and has a visible focus indicator; Escape closes the popup | Pass |
| Tables, CRUD actions, and pagination | `Tab`, `Enter`, `Space` | Reach row actions and pagination without a mouse | Actions use links/buttons/forms and pagination uses semantic links; disabled pagination entries are not actionable | Pass |
| Shared and local dialogs | `Tab`, `Shift+Tab`, `Escape` | Initial focus enters the dialog, focus stays contained, Escape closes applicable dialogs, and focus returns to the opener | Shared runtime provides initial focus, focus containment, and return; logistics dialogs now close with Escape; mandatory device-approval decisions intentionally require an explicit action | Pass |
| Warehouse task activity tabs | Left/Right arrows, `Enter`, `Space` | Move between Scan History and Exceptions and update the active panel | Tabs now move focus and selection with Left/Right; native button activation remains available | Pass |
| Historical reports and export actions | `Tab`, `Enter`, `Space` | Change filters/tabs and activate print/export actions | Filters are native controls; report tabs/actions use buttons or links; no mouse-only export control was found | Pass |
| Process-review menus and dialogs | `Tab`, `Enter`, `Space`, `Escape` | Open sections/actions and exit menus/dialogs | Controls are native buttons/selects; Escape now closes open review menus and applicable dialogs | Pass |

## Browser Evidence

The live Edge accessibility tree exposed the rendered HIMS dashboard as semantic links, buttons, comboboxes, toggle buttons, landmarks, and a named chart graphic. After the fixes, both custom forecast-risk entries were exposed as buttons rather than generic groups. The browser connector accepted keyboard input but did not reliably report the currently focused DOM element or capture a focus screenshot, so focus-order conclusions were cross-checked against native document order, rendered accessibility roles, and the automated regression assertions below.

## Automated Verification

```text
php artisan test tests/Feature/ScreenReaderAccessibilityTest.php tests/Feature/DocumentTrackingAndLogisticsTest.php
33 passed, 278 assertions

php artisan test tests/Feature/ScreenReaderAccessibilityTest.php
5 passed, 72 assertions

php artisan view:cache
Blade templates cached successfully

git diff --check
No errors
```

## Fixes Made

- Added Enter/Space support, accessible naming, and visible focus styling to the item-location clear control.
- Added button semantics and Space activation to dashboard forecast-risk items.
- Added Escape dismissal to logistics document, shipment, and inspection/acceptance dialogs.
- Added Left/Right focus movement to the warehouse-task ARIA tabset.
- Added Escape dismissal with focus return to shared dropdown and sidebar-section components.
- Added Escape dismissal to process-review menus and applicable dialogs.

## Scope Notes

- Authorization, authentication rules, business logic, and permissions were unchanged.
- Native links, buttons, inputs, selects, checkboxes, and form submission behavior were retained.
- The device-approval security prompt intentionally cannot be dismissed with Escape because the user must explicitly approve or reject the pending sign-in request.
