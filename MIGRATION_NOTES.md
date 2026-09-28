# Staff-only mobile migration

## Summary

The mobile app now starts directly in `StaffNavigator`. The temporary Owner/Staff/Customer role selector and all owner navigation routes have been removed, so no owner screen is reachable from the mobile app.

## Removed

- The owner screen directory: dashboards, analytics, goals, AI, calendar, alerts, owner profile, products, stock, transactions, and notification capture.
- `OwnerNavigator` plus the owner-only AI, analytics, goals, and profile stacks.
- Owner-only `AlertContext` and `AIService`.

`GeminiService` remains because receipt scanning uses it through `OCRService`.

## Owner-account behaviour

`src/screens/auth/OwnerBlockedScreen.js` is the owner redirect UI. It explains that mobile is for staff, opens `https://gastotrack.com`, and can show a logout action when passed an `onLogout` callback.

The current codebase has no authentication, user model, or `RootNavigator`; therefore this screen is intentionally not mounted yet. An authentication implementation must route `user.role === 'owner'` to it before rendering `StaffNavigator`.

## Validation

- `npm run lint`
- `npm test`

## Rollback

Restore the change from version control. Do not recreate owner routes in the mobile app; owners should use the web dashboard.
