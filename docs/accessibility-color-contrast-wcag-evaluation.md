# WCAG 2.2 AA Color Contrast Evaluation

Date: 2026-09-27

Scope: shared HIMS Tailwind tokens, CSS utility guards, Blade UI components, app/guest layouts, navigation, forms, buttons, badges, alerts, tables, pagination, toast notifications, dashboard and demand forecast chart labels.

Method: repository inspection plus measured WCAG contrast ratios using the WCAG relative luminance formula against representative foreground/background pairs. No new accessibility dependency was added.

## Corrected Failures

| Combination | Before | After | Result |
| --- | ---: | ---: | --- |
| White text on `primary-600` buttons | 4.27:1 | 5.65:1 | Pass AA normal text |
| White text on `success-600` buttons | 3.30:1 | 5.02:1 | Pass AA normal text |
| White text on `warning-600` buttons | 2.96:1 | 7.09:1 | Pass AA normal text |
| `neutral-400` text on white | 2.52:1 | 4.74:1 | Pass AA normal text |
| Dark-theme explicit `neutral-500` metadata on `neutral-900` | 3.78:1 | 7.11:1 | Pass AA normal text |

## Representative Passing Results

| UI Area | Combination | Ratio |
| --- | --- | ---: |
| Body text | `neutral-800` on white | 15.09:1 |
| Secondary text | `neutral-500` on white | 4.74:1 |
| Links / primary text | `primary-600` on white | 5.65:1 |
| Primary buttons | white on `primary-600` | 5.65:1 |
| Destructive buttons | white on `danger-600` | 4.83:1 |
| Success badges | `success-700` on `success-50` | 4.79:1 |
| Warning badges | `warning-700` on `warning-50` | 6.84:1 |
| Danger badges | `danger-700` on `danger-50` | 5.91:1 |
| Primary badges | `primary-700` on `primary-50` | 7.06:1 |
| Dark muted text | `neutral-400` on `neutral-900` | 7.11:1 |
| Dark primary text | `primary-400` on `neutral-900` | 8.02:1 |
| Focus indicators | `primary-500` ring on white | 5.65:1 |

Evidence entry: WCAG 2.2 AA contrast evaluation with measured text/UI contrast ratios and build verification confirming compliant shared colors and focus states across representative HIMS screens.