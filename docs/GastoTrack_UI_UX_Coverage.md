# UI/UX blueprint coverage

Updated: 27 October 2026. This tracks the current working tree against `GastoTrack_UI_UX_Blueprint.md`. “Source pass” means the screen and its shared styles or components were reviewed and adjusted; it does not mean that every rendered state has been verified.

| Area | Source pass | Checks still required before release |
| --- | --- | --- |
| Shared Staff components | Theme aliases, dialog motion and reduced motion, skeletons, button contrast, touch targets | Physical phone: large fonts, reduced motion, keyboard, interrupted dialogs |
| Staff welcome, login, MPIN | Existing flow retained; PIN actions and common visual controls reviewed | Device: keyboard visibility, return from background, expiry, errors |
| Staff Home and products | Income/offline status copy, compact actions, cached-data loading, common product controls reviewed | Device: offline/reconnect, long product names/photos, real sync states |
| Staff cart, checkout, wallet waiting | Existing order flow retained; wallet card constrained and scrollable, guidance shortened, actions accessible | Device: cash/cashless, cancel, failed payment, Back, large fonts, real wallet capture |
| Staff transactions, receipt scanner, stock | Shared controls, empty/loading/error states, adjustment and category targets reviewed | Device: offline edits, camera/OCR errors, permissions, stock adjustment |
| Staff AI, profile, permissions, bug reports | Common palette and feedback applied; bug-report loading keeps cached reports visible | Device: upload, online failure/retry, AI unavailable, profile photo |
| Owner shell, Overview, notifications | Static sidebar, action colors, short section motion, live-value highlights, existing alert behavior retained | Signed-in browser: 360–1920 px, light/dark, focus and live refresh |
| Owner Transactions, Orders, Inventory, Staff | Filters, fields, actions, tables and contrast reviewed | Signed-in browser: empty/populated/validation states, data accuracy |
| Owner Analytics, goals, bug reports | Period/date controls, cost logs, form feedback and section motion reviewed | Laravel Blade render and signed-in browser; populated/empty charts |
| Owner six Reports destinations | Shared report presentation and export/date controls reviewed; separate destinations retained | Render each destination; check dates, PDF/CSV, light/dark, access |
| Super Admin dashboard, businesses, business detail | Shared layout, table/action targets, identity-specific confirmations reviewed | Signed-in browser and Laravel tests for approve/archive/restore/delete |
| Super Admin bug reports and platform reports | Lists/forms/detail presentation and public/private note hierarchy reviewed | Signed-in browser and Laravel tests for filters, notes, export, permissions |

## Verification status

- **Passed:** 24 Jest suites / 104 tests, ESLint with zero errors (six existing customer-file warnings), Vite production build, and `git diff --check` after the remaining-screen pass.
- **Blocked here:** PHP 8.4 is unavailable (XAMPP provides PHP 8.2.12), so Laravel feature tests and Blade rendering were not executed. An offline Android debug build with a workspace-local Gradle cache stopped during configuration because `org.gradle.toolchains.foojay-resolver-convention:1.0.0` was not cached; its disposable cache was removed. Automatic approval review rejected opening the only visible Edge window because it was identified as Facebook; no alternate browser access was attempted.
- **Pending:** Android debug build on a configured build environment; physical-phone and signed-in browser checks for the states and journeys listed above. A successful static check is not a release sign-off.

The current working tree contains unrelated uncommitted implementation changes. Review or revert the UI changes by file and diff; do not reset the whole tree to roll back this pass.
