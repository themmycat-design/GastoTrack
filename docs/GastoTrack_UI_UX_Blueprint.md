# GastoTrack UI/UX improvement blueprint

Prepared: 26 October 2026<br>
Scope: Android staff app, owner web portal, Super Admin web portal
Status: Proposed implementation guide; this document does not implement the redesign.

## 1. Outcome and evidence

Make daily tasks easier to find, read, and complete while preserving working business behavior. Keep GastoTrack's green identity and compact layouts. Improve consistency before introducing new visual effects.

This blueprint is based on source inspection of the current working tree, whose Git HEAD is `4322d14`, plus the external references below. The working tree contains substantial existing changes, so that commit alone is not a complete recovery point. Screen behavior and device performance have not been comprehensively exercised for this blueprint. Findings described as risks require reproduction before being treated as defects.

Verified implementation anchors:

| Area | Existing implementation | Implication for the redesign |
| --- | --- | --- |
| Staff appearance | `src/theme.js` | Extend existing colors, spacing, typography, and radius definitions instead of creating a second theme. |
| Staff navigation | `src/navigation/StaffNavigator.js` and its stacks | Preserve route names, tab behavior, keyboard handling, and safe area insets. The Assistant is not a visible bottom tab. |
| Staff feedback | `AppDialogHost.js`, `SkeletonPlaceholder.js` | Improve these shared components first. Check reduced motion and interrupted animation behavior. |
| Owner appearance | `web/resources/css/app.css` and owner Blade layouts | Many dark colors are utility overrides. Migrate gradually to semantic component styles to reduce missed states. |
| Owner animation | `web/resources/js/owner-motion.js` and CSS | Motion is applied to nested content, counters, and charts. Audit overlapping animation owners and repeated updates. |
| Owner refresh | `web/resources/js/owner-live.js` | Preserve filters, forms, focus, and chart lifecycle during background updates. Refreshing data must not replay the whole page entrance. |
| Super Admin appearance | `web/resources/views/layouts/super-admin.blade.php` | Styling is partly inline in a shared layout. Extract only after visual parity is established. |
| Web access | `web/routes/web.php` | Existing authentication, verification, owner security, and Super Admin middleware remain authoritative. |
| Regression checks | `__tests__`, `web/tests`, `.github/workflows/quality.yml` | Reuse tests for actual business flows; add targeted tests only where interaction changes introduce risk. |

The recent Analytics failure demonstrates a release gap: a successful Vite build does not prove that a Blade page renders. Every changed Blade page must also be exercised through Laravel.

## 2. Reference systems and what to borrow

These are interaction references, not instructions to copy branding or add features that GastoTrack does not have.

| Reference | Useful pattern | Application to GastoTrack |
| --- | --- | --- |
| [Square POS item grid](https://squareup.com/help/us/en/article/8334-set-up-item-grid) | Quickly accessible product and category tiles | Staff ordering: consistent image sizes, legible prices, clear availability, and quick category access. Keep the current product-details-before-cart flow. |
| [Shopify Analytics overview](https://help.shopify.com/en/manual/reports-and-analytics/shopify-reports/overview-dashboard/using-the-overview-dashboard) | Date ranges, comparisons, and metric cards linked to reports | Owner analytics: one clear period control, explicit comparison dates, and predictable access to supporting records. Do not import Shopify's financial definitions. |
| [IBM Carbon data tables](https://www.carbondesignsystem.com/building-blocks/core/components/data-table/guidelines) | Consistent table toolbars, sorting, row actions, and selection | Super Admin lists and owner reports: stable columns, clear filters, and grouped actions. Only expose bulk actions when an authorized backend implementation exists. |
| [Material motion](https://m3.material.io/styles/motion) | A shared motion language | Reuse a small set of transitions for dialogs, changes, and navigation instead of giving every screen unrelated effects. |
| [NN/g: Animation for attention and comprehension](https://www.nngroup.com/articles/animation-usability/) | Motion needs a purpose and must account for repetition | Animate meaningful changes, keep frequent POS tasks fast, and avoid repeated decorative sequences. |
| [WCAG 2.2](https://www.w3.org/TR/WCAG22/), [target size](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum), [interaction animation](https://www.w3.org/WAI/WCAG22/Understanding/animation-from-interactions) | Contrast, keyboard access, usable targets, and motion alternatives | Use accessibility acceptance checks. Reduced interaction animation is an additional project requirement; the cited animation criterion is AAA. |

All values and timings below are proposed GastoTrack design decisions, not claims that the reference products use those exact values.

## 3. Rules that protect working functions

1. Establish a recoverable baseline of the entire working tree, including relevant untracked source files, before implementation. Record existing failing tests and screenshots. Keep credentials, `.env` files, keystores, and private records out of repository commits and review artifacts.
2. Work in small, independently reversible batches. A batch should have a clear role, screen family, and acceptance checklist.
3. Preserve API URLs, request fields, event contracts, form names, validation, route names, database schema, calculation formulas, authorization, and offline storage formats during presentation changes.
4. Treat changes to payment confirmation, refunds, stock deductions, synchronization, MPIN, MFA, and account access as separate behavioral work. A visual redesign must not silently change those decisions.
5. Preserve existing element IDs, `data-*` hooks, accessibility relationships, and listeners until their callers are traced and updated together.
6. Do not run migrations, seeders, or destructive database commands for a UI change. Use isolated synthetic fixtures for tests.
7. Animation completion must never authorize a payment, trigger a second save, determine whether a transaction exists, or erase a draft. Avoid coupling business callbacks to whether an animation finishes.
8. Review the diff before each release. Reject unexplained changes outside the intended screen/component scope.

No blueprint can guarantee zero defects. These rules reduce the size of changes, expose regressions earlier, and make rollback practical.

## 4. Shared visual specification

Use semantic names such as `surface`, `textPrimary`, `textMuted`, `actionPrimary`, `dangerSurface`, and `borderStrong` in the web and mobile themes. Values can match across platforms; implementations remain native to each platform.

| Token | Light proposal | Owner dark proposal |
| --- | --- | --- |
| Page background | `#F4F8F7` | `#111A17` |
| Main surface | `#FFFFFF` | `#192521` |
| Subtle surface | `#ECF7F3` | `#20372E` |
| Main text | `#0A2E2A` | `#E2ECE8` |
| Secondary text | `#60756F` | `#A5B5AE` |
| Primary action background | `#08745D` | `#08745D` |
| Primary action text | `#FFFFFF` | `#FFFFFF` |
| Link/action text | `#08745D` | `#71DFBF` |
| Decorative brand accent | `#00C897` | `#53D6B1` |

Measure final foreground/background combinations, including hover, selected, disabled, focus, error, and loading states. Use 4.5:1 for normal text, 3:1 for qualifying large text, and the applicable 3:1 requirement for essential control boundaries and graphics. Do not rely on green/red alone to convey a status.

Design rules:

- Spacing scale: 4, 8, 12, 16, 24, and 32. Use 12–16 inside compact cards and 16–24 between sections. Keep related labels and fields close together.
- Web body text: normally 14–16 px. Mobile body text: normally 14–16 logical units. Reserve smaller text for secondary metadata, with font scaling checked on the device.
- Touch controls: target 48 logical units on Android and approximately 44 CSS px for primary web controls. These are project targets, separate from WCAG's minimum target-size rules and exceptions.
- Standardize card radii and elevation; use borders and spacing before adding more shadows.
- One clear primary action per task area. Use explicit verbs: Save changes, Add to cart, Apply filters, Submit report.
- Keep money right-aligned and consistently formatted. Keep quantities beside their units. Wrap long names without hiding the price, status, or action.
- Maintain persistent labels. Placeholders provide examples rather than replacing field names.
- Dark mode requires intentional surface/text pairs, including dialogs, charts, hover states, errors, date controls, uploads, and empty states. Adding dark mode to staff or Super Admin is a separate product decision; first finish their current supported themes.

## 5. Staff Android blueprint

Keep Home, Transactions, Orders, Inventory, and Profile as the existing primary navigation. Preserve the header AI access, its absence on the AI screen, the Home bug-report shortcut, and the user's established placement preferences.

| Screen or flow | Proposed improvement | Behavior to preserve |
| --- | --- | --- |
| Welcome, login, MPIN | Compact hierarchy; keyboard-safe fields; visible errors; clear PIN setup and unlock states | Staff-only sign-in, existing expiry and PIN persistence, biometric/security behavior if present |
| Home | Income total and completed-income count clearly distinguished; compact quick actions; visible sync state | Existing daily scope, count definition, and staff permissions |
| Product browsing | Consistent photo crop and fallback; product name, price, category, and availability visible; readable search/category controls | Current product selection and pricing |
| Product details | Group ingredient customization, quantity, and Add to cart; show an understandable customization summary | Existing allowed ingredient edits and recipe calculations |
| Cart and checkout | Clear item rows, edit/remove affordances, totals, and one primary payment action | Cart contents, amounts, and the current placement of cart access; do not restore the removed bottom View cart control |
| Wallet selection and waiting | GCash/Maya choices in a column; waiting actions side by side where they fit; distinguish waiting, detected, confirmed, and failed using actual server states | Current cashless workflow, reference extraction, matching, confirmation rules, and cancellation returning to checkout |
| Transactions and manual entry | Readable daily rows, amount hierarchy, labels and validation near fields; show pending sync accurately | Existing daily behavior; do not restore staff history/raw-transaction shortcuts |
| Receipt scanner | Clear capture guidance and progress; useful blur/unreadable/provider errors; accessible existing fallback editing | Current OCR save/fallback rules; do not invent new confirmation steps or display a removed raw-text/photo preview |
| Inventory | Name, quantity/unit, and status easy to scan; adjustment form shows current quantity and input clearly | Existing manual adjustment, deduction, permissions, and offline support |
| AI assistant | Clear sending, responding, unavailable, and retry states; readable responses and context labels | Business-scoped answers and existing supported actions; no new autonomous financial actions |
| Profile/settings/permissions | Group personal details, photo upload, account actions, and device permissions; distinguish uploading from saved | Existing validation, upload behavior, sign-out, and navigation destinations |
| Bug reports | Simple form, retained values on failed submission, clear upload/status feedback | Reporter scope and online-only submission behavior |

Offline language must be precise: Saved on this device, Waiting to sync, Syncing, Synced, or Needs attention. Show Synced only after acknowledgment. Keep manual transactions, cash orders, and stock adjustments within their implemented offline scope; cashless/AI/account operations remain governed by current connectivity requirements.

## 6. Owner web blueprint

| Screen family | Proposed improvement | Acceptance focus |
| --- | --- | --- |
| Shared shell | Aligned brand/topbar, compact navigation, clear active item, predictable responsive overflow | Keyboard focus visible; sidebar remains stationary; existing links and role boundaries preserved |
| Overview | Clear financial hierarchy; completed/target goal values paired; compact cards; recent transactions easy to scan | Income, expenses, confirmed sales, and net movement retain their distinct meanings |
| Transactions/raw captures | Consistent search/filter toolbar, aligned amounts, clear wallet/reference/date/sender fields | Raw notification evidence never presented as reconciled money solely because of its appearance |
| Orders/details | Stable row columns and clear payment/order status grouping; visible official wallet reference | No invented reference for cash orders; financial actions remain under current role rules |
| Inventory/products | Consistent product photos and units; clear stock alerts; accessible modal labels and errors | Forms preserve input and duplicate-submit protection |
| Staff | Clear identity and role columns, compact filters, distinguish edit from destructive actions | Existing account and tenant permissions |
| Analytics/day/cost logs | Consistent period controls and drill-downs; explain estimates; distinct chart/table hierarchy | No changes to source queries, comparison periods, totals, or margin definitions |
| Goals | Current achievement, target, and period clearly paired | Preserve weekly/monthly behavior and calculations |
| Bug reports/profile | Consistent form, upload, validation, status, and account controls | Reporter permissions, image handling, and account validation |
| Reports | Each of the six report destinations has its own screen and shared date/export toolbar | Sales and orders; Income and expenses; Product costs and margins; Inventory and waste; Payment review; Staff activity |

Keep Reports at the bottom of the primary navigation with its existing expandable submenu. Keep the Inventory alert visible until stock is resolved; opening notifications marks them read according to current behavior. Do not add another Mark as read button.

For every financial chart, document its denominator and meaning. A donut is appropriate only for a meaningful part-to-whole relationship. If a negative balance or unrelated amount cannot be represented honestly, retain the number and propose an appropriate alternative before changing the chart. Never invent a percentage or change financial totals to fit a visual.

Background updates should retain filters, scroll, focused fields, dialogs, and unsaved input. Highlight only changed values briefly; avoid restarting count-up animations from zero on every poll. A proposed freshness label must reflect successful refresh time, and a stale/error state must not replace valid totals with zero.

## 7. Super Admin web blueprint

Use a calm, compact operations interface. Prioritize business identity, current status, and the consequence of an action.

| Screen family | Proposed improvement | Acceptance focus |
| --- | --- | --- |
| Platform dashboard | Group operational counts and attention items; clear routes to relevant lists | Metric definitions unchanged; no expansion of access to tenant financial records |
| Businesses/archive | Stable toolbar, visible filters, consistent status labels, row actions in a predictable location | Clearly distinguish archive, restore, and permanent deletion |
| Business detail/create/edit | Group identity, owner access, and status controls; place destructive operations separately | Existing required fields, validation, email verification, and approval workflow |
| Bug reports/detail/edit | Reporter/business identity, area, priority, status, and public update easy to scan | Preserve separation of reporter-visible updates and private notes |
| Platform reports/export | Consistent date/filter placement, chart/list formatting, and empty states | Existing aggregate definitions and export permissions |
| Global users, audit logs, subscription controls, bulk administration | Inventory their actual routes before styling; apply the same table/detail patterns if present | Do not build a missing backend module as part of a visual refresh |

A batch-action UI must specify whether selection means the current page or all filtered records. If that behavior is not implemented and tested, retain individual actions. Destructive confirmations must identify the affected business and consequence without introducing a second accidental submission path.

## 8. Motion specification

The goal is a quick, subtle wave through relevant content with clear feedback. Keep static content static when motion adds no information. Preserve the user's request that owner navigation itself does not animate; the Reports disclosure may open/close smoothly.

| Interaction | Proposed duration | Rules |
| --- | --- | --- |
| Press/hover feedback | 90–140 ms | Small opacity/color change; avoid layout movement |
| Menu or disclosure | 160–220 ms | Follow its opening direction; preserve focus |
| Dialog/sheet | 180–240 ms open; 120–180 ms close | Opacity plus 8–12 px movement; no large bounce |
| Main content entrance | 180–260 ms | Animate sections rather than every label/button independently |
| Section wave | 25–40 ms between sections; total delay capped at 120 ms | Content and controls remain immediately usable |
| Counter/chart first reveal | 250–400 ms | Final value remains available to assistive technology; animate once per relevant data load |
| Live data update | 120–180 ms | Update the affected value/row; no whole-page replay or zero reset |
| Skeleton | Gentle pulse about 1.2–1.6 s | Use only while genuinely loading; keep layout dimensions stable |

Implementation rules:

- One animation owner per element. Remove competing CSS and JavaScript effects as each screen is migrated.
- Prefer opacity and transforms. Avoid blurring text, animating large shadows, and repeatedly animating layout dimensions on dense tables.
- Use native-driven React Native animations where the properties support it. Stop loops when hidden/unmounted and handle interrupted transitions safely.
- Honor web `prefers-reduced-motion` and Android reduced-motion settings. Use static loading shapes and immediate state changes when motion is reduced.
- Never hold real data behind a minimum fake loading delay. Do not show skeletons over usable cached data during a background refresh.
- Keep money updates legible. Do not announce every animation frame to screen readers.
- The post-login logo transition must not run on every navigation or delay authentication completion. Verify it separately from ordinary page entrances.

## 9. Delivery sequence and gates

| Batch | Deliverable | Required gate |
| --- | --- | --- |
| 0 — Baseline | Working-tree snapshot, route/screen inventory, synthetic test fixtures, baseline results and screenshots | Recovery point verified; existing failures documented |
| 1 — Shared foundation | Theme tokens, typography, labels, buttons, focus, statuses, common loading/error/empty states | Representative owner form/table, staff dialog, and admin table reviewed before wider adoption |
| 2 — Owner pilot | Overview, Analytics, and one report screen; shared motion consolidation | Blade renders, light/dark checks, accurate metrics, live updates preserve edits |
| 3 — Staff pilot | Home, product details, checkout, and wallet-wait presentation | Cash/cashless, cancellation, keyboard, offline, and duplicate-tap checks pass |
| 4 — Super Admin pilot | Business list/detail and bug-report list/detail | Approval/status/archive/restore actions and access checks pass |
| 5 — Remaining screens | Apply established patterns to every remaining screen in the matrices | Completed screen/state coverage matrix; no newly broken routes |
| 6 — Release candidate | Full role regression, actual Android device check, responsive and reduced-motion review | No unresolved critical regressions; reversible release artifact and documented rollback |

For each batch: record the problem, capture a before state, change presentation, exercise the same task, compare outputs and screenshots, and review the diff. Do not proceed past a failed critical gate by describing it as cosmetic.

Rollback means reverting the isolated UI batch or restoring the prior web assets/APK as appropriate. Preserve newer unrelated changes. Database rollback should not be needed because UI batches do not modify schema or stored records.

## 10. Verification and completion checklist

Test combinations include normal, empty, loading, failed, disabled, selected, hover/focus, long content, and validation states. Web checks cover supported light/dark modes at 360, 390, 768, 1366, and 1920 CSS px. Android checks cover the real phone, keyboard open, large font setting, Back behavior, offline/reconnect, and reduced motion.

| Critical journey | What must remain true |
| --- | --- |
| Owner registration/sign-in/MFA | Verification, pending approval, trusted-device expiry, and direct-route protection remain enforced |
| Staff login/MPIN/session expiry | Existing PIN persists as intended; navigation and app return do not introduce extra unlocks |
| Product to cart to cash sale | Ingredient choices, quantity, total, ledger, and stock effects match the baseline |
| Wallet payment and cancellation | Actual state drives feedback; cancellation returns correctly; retries do not duplicate orders/payments |
| Offline recording and reconnect | Drafts/queued records survive expected interruptions and sync once under existing contracts |
| OCR success/failure/manual fallback | Correct fields and category flow; useful errors; no hidden save caused by visual feedback |
| Photo upload and personal details | Validation and upload errors remain visible; successful saves persist |
| Owner live views and reports | Tenant scope, figures, filter/date context, exported data, and input preservation match baseline |
| Stock notifications | Read status and unresolved inventory alerts remain distinct |
| Super Admin actions | Role checks, public/private notes, approvals, and destructive confirmations remain correct |

Verification tools to use during implementation:

- Mobile: relevant Jest suites first; full Jest and ESLint before release. Run an Android debug build and exercise the physical device before accepting changes to keyboard, dialogs, camera, permissions, MPIN, or payment waiting.
- Web: relevant Laravel feature tests under PHP 8.4 and the existing isolated SQLite test configuration, plus `npm run build`. Render every changed Blade route with synthetic data. Include empty and populated Analytics states to catch template compilation errors.
- Real wallet notifications, MySQL-specific behavior, and measured animation performance require direct checks; mocked or SQLite tests do not prove them.
- Existing CI is useful only when its jobs actually run and pass. A workflow file or local build alone is not evidence of a deployed release.
- Do not label a screen visually verified when browser access is blocked. Record the blocker and finish the visual check before release.

A screen is complete when its layout is readable in all supported states, its controls retain their function, its motion respects accessibility settings, and its critical journey passes. Each implementation report should list changed screens, verification performed, remaining blockers, and the rollback point.

## 11. Recommended starting point

Begin with Batch 0 and shared foundation components, then use Owner Overview + Analytics as the first pilot. This directly addresses recent color and animation issues while establishing patterns for the remaining roles. Avoid a simultaneous rewrite of all three interfaces.

The first implementation batch should deliver a recoverable baseline, a screen/state checklist, semantic theme additions, and one reviewed example each of a form, table, dialog, and loading state. The full redesign follows those examples only after their functional checks pass.
