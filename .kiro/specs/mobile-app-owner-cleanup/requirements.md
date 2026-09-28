# Requirements Specification

**Feature:** React Native App - Remove Owner Screens (Staff-Only Mobile App)

**Status:** Planned  
**Priority:** High  
**Target Completion:** 1 week  

---

## Introduction

The GastoTrack React Native mobile application originally included screens for both Business Owners and Staff Members. Following an architectural clarification, Business Owners will now access the system **exclusively via the responsive web dashboard** (accessible from mobile browsers, tablets, and desktops). The mobile app will be **Staff-only**.

This specification defines the requirements for removing all Owner-specific screens, navigation, and related code from the React Native app while preserving all Staff functionality.

**Updated Architecture:**
- **Business Owners**: Use responsive web dashboard only (Laravel Blade + Livewire)
- **Staff Members**: Use React Native mobile app only (Android)
- **Super Admins**: Use web dashboard only (Laravel Blade + Livewire)

---

## Glossary

- **Owner Screens**: React Native screens designed for business owner features (analytics, goals, AI insights, etc.)
- **Staff Screens**: React Native screens for daily operations (transactions, stock, orders, e-wallet)
- **OwnerNavigator**: React Navigation stack for Owner screens
- **StaffNavigator**: React Navigation stack for Staff screens
- **AuthNavigator**: Login/registration navigation flow
- **Role-Based Navigation**: Navigation system that shows different screens based on user role

---

## Requirements

### 1. Remove Owner-Specific Screens

**REQ-1.1**: Delete Owner Dashboard Screen  
Remove `src/screens/owner/DashboardScreen.js` that shows owner-specific overview with analytics widgets.

**REQ-1.2**: Delete Analytics & Reports Screen  
Remove `src/screens/owner/AnalyticsScreen.js` that displays business analytics, charts, and financial reports.

**REQ-1.3**: Delete Goals Management Screen  
Remove `src/screens/owner/GoalsScreen.js` that allows owners to create and track financial goals.

**REQ-1.4**: Delete AI Chatbot Screen  
Remove `src/screens/owner/AIChatbotScreen.js` that provides Gemini AI assistant for business advice.

**REQ-1.5**: Delete AI Insights Screen  
Remove `src/screens/owner/AIInsightsScreen.js` that displays AI-generated business insights and trends.

**REQ-1.6**: Delete Calendar View Screen  
Remove `src/screens/owner/CalendarScreen.js` that shows transactions in calendar format (owner-specific view).

**REQ-1.7**: Delete Alerts/Notifications Screen  
Remove `src/screens/owner/AlertsScreen.js` that displays business alerts (low stock, goal deadlines) for owners.

**REQ-1.8**: Delete Notification Capture Screen  
Remove `src/screens/owner/NotificationCaptureScreen.js` that handles e-wallet notification capture for owners.

**REQ-1.9**: Delete Owner Products Screen  
Remove `src/screens/owner/ProductsScreen.js` - Product management will be web-only for owners. Staff can view products but not manage them.

**REQ-1.10**: Delete Owner Stock Screen  
Remove `src/screens/owner/StockScreen.js` - Advanced stock management (min/max quantities, alerts) will be web-only for owners. Staff have basic stock screens.

**REQ-1.11**: Delete Owner Transactions Screen  
Remove `src/screens/owner/TransactionsScreen.js` - Owners will manage transactions via web. Staff have their own transaction screens.

**REQ-1.12**: Delete Owner Profile Screen  
Remove `src/screens/owner/ProfileScreen.js` - Owner profile management will be web-only. Staff have their own profile screen.

---

### 2. Remove Owner Navigation

**REQ-2.1**: Delete OwnerNavigator Component  
Remove `src/navigation/OwnerNavigator.js` that defines the navigation stack for owner screens.

**REQ-2.2**: Update Root Navigator  
Modify `src/navigation/RootNavigator.js` to remove conditional rendering for owner role. The app should only support staff role navigation.

**REQ-2.3**: Update Auth Flow  
Modify authentication flow to prevent owner role users from logging into the mobile app. Show a message directing them to the web dashboard.

---

### 3. Update Authentication & Onboarding

**REQ-3.1**: Block Owner Login  
When a user with `role: 'owner'` attempts to log in to the mobile app, display an error message:
```
"Business Owners use the web dashboard. Please visit https://gastotrack.com from your browser."
```

**REQ-3.2**: Update Registration Flow  
Remove the option to register as a Business Owner from the mobile app registration screen. Only allow Staff registration (with business invite code).

**REQ-3.3**: Update Welcome/Onboarding Screens  
Modify onboarding screens to remove references to owner features. Focus messaging on staff member capabilities.

---

### 4. Clean Up Shared Components

**REQ-4.1**: Audit Shared Components  
Review components in `src/components/` to identify any that are exclusively used by owner screens. Remove unused components.

**REQ-4.2**: Remove Owner-Specific Context/State  
Remove Redux slices, contexts, or state management logic that is exclusively for owner features (e.g., `goalsSlice`, `analyticsSlice`).

**REQ-4.3**: Remove Owner-Specific API Calls  
Remove API service functions that are exclusively used by owner screens (e.g., `fetchGoals`, `fetchAnalytics`, `fetchInsights`).

**REQ-4.4**: Clean Up Owner-Specific Utilities  
Remove utility functions in `src/utils/` that are only used by owner screens (e.g., chart helpers, analytics formatters).

---

### 5. Update Dependencies & Assets

**REQ-5.1**: Remove Unused Chart Libraries  
If charting libraries (e.g., `react-native-chart-kit`, `victory-native`) are only used in owner screens, remove them from `package.json`.

**REQ-5.2**: Remove Owner-Specific Assets  
Delete images, icons, or illustrations in `src/assets/` that are exclusively used in owner screens.

**REQ-5.3**: Update App Configuration  
Update app metadata (name, description) if necessary to reflect "Staff Edition" or similar branding.

---

### 6. Testing & Verification

**REQ-6.1**: Verify Staff Screens Function  
Ensure all staff screens (Transactions, Stock, Orders, E-Wallet, Profile) continue to work correctly after owner screen removal.

**REQ-6.2**: Verify Owner Login Blocked  
Test that users with `role: 'owner'` cannot log in and receive the appropriate redirect message.

**REQ-6.3**: Verify Navigation Works  
Ensure the staff navigation stack works without errors after removing owner navigator.

**REQ-6.4**: Verify Build Succeeds  
Ensure the React Native app builds successfully for Android after all changes.

**REQ-6.5**: Test on Physical Device  
Run the modified app on a physical Android device to verify performance and UX.

---

### 7. Documentation

**REQ-7.1**: Update README  
Update project README to clarify the app is Staff-only. Add instructions for owners to access the web dashboard.

**REQ-7.2**: Update Code Comments  
Remove or update code comments that reference owner features or dual-role support.

**REQ-7.3**: Document Architecture Change  
Add a note in documentation explaining the architectural decision (Staff mobile, Owner web).

---

## Success Criteria

- ✅ All 12 owner screen files deleted
- ✅ OwnerNavigator deleted
- ✅ Owner login blocked with clear message
- ✅ All staff screens function correctly
- ✅ App builds without errors
- ✅ No unused dependencies remain
- ✅ Navigation is simplified and staff-focused
- ✅ Documentation updated

---

## Out of Scope

- Modifying staff screen functionality (staff features remain unchanged)
- Creating new staff features
- Backend API changes (API already supports both mobile and web clients)
- Web dashboard development (covered by separate specs)

---

## Dependencies

- Requires understanding of current React Navigation setup
- Requires access to Redux/Context state management structure
- Requires knowledge of which API calls are shared vs. owner-specific

---

## Risks & Mitigation

**Risk**: Accidentally removing shared components used by staff screens  
**Mitigation**: Carefully audit component usage with global search before deletion

**Risk**: Breaking staff navigation by incorrectly modifying RootNavigator  
**Mitigation**: Test navigation thoroughly after changes, use Git branching for safety

**Risk**: Removing dependencies that are still needed by staff screens  
**Mitigation**: Search codebase for imports before removing any packages

---

## Acceptance Criteria

1. All 12 owner screens are deleted from `src/screens/owner/`
2. `src/navigation/OwnerNavigator.js` is deleted
3. `src/navigation/RootNavigator.js` only shows staff navigation
4. Owner users receive a clear error message directing them to web dashboard
5. Staff users can log in and access all staff features without errors
6. App builds successfully with `npx react-native run-android`
7. No console warnings related to missing owner components
8. All unused dependencies are removed from `package.json`
9. Documentation is updated to reflect staff-only architecture
10. Code review confirms no remnants of owner-specific code remain

---

## Future Considerations

- **Progressive Web App (PWA) for Staff**: Consider creating a web version of staff screens for tablet-based POS systems
- **QR Code Business Invite**: Implement QR code scanning for staff to join businesses
- **Staff Role Differentiation**: Add "Manager" role between Owner and Staff with limited web access
