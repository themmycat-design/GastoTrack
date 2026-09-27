# GastoTrack App Structure - Before Owner Cleanup

**Date:** 2024 (Before Staff-Only Migration)
**Tag:** backup-before-owner-cleanup

This document captures the app structure before converting to staff-only mobile app.

---

## Screen Structure

### Owner Screens (To Be Removed)
Located in: `src/screens/owner/`

1. **AIChatbotScreen.js** - Gemini AI assistant for business advice
2. **AIInsightsScreen.js** - AI-generated business insights and trends
3. **AlertsScreen.js** - Business alerts (low stock, goal deadlines)
4. **AnalyticsScreen.js** - Business analytics, charts, financial reports
5. **CalendarScreen.js** - Transactions in calendar format
6. **DashboardScreen.js** - Owner-specific overview with analytics widgets
7. **GoalsScreen.js** - Financial goals creation and tracking
8. **NotificationCaptureScreen.js** - E-wallet notification capture for owners
9. **ProductsScreen.js** - Product management (owner view)
10. **ProfileScreen.js** - Owner profile management
11. **StockScreen.js** - Advanced stock management with min/max quantities
12. **TransactionsScreen.js** - Owner transaction management

**Total Owner Screens: 12 files**

### Staff Screens (To Be Preserved)
Located in: `src/screens/staff/`

1. **DashboardScreen.js** - Staff dashboard
2. **OrderQueueScreen.js** - Order management with POS
3. **ProfileScreen.js** - Staff profile
4. **ReceiptScannerScreen.js** - OCR receipt scanning
5. **StockScreen.js** - Basic stock tracking
6. **TransactionsScreen.js** - Staff transaction management

**Total Staff Screens: 6 files**

### Customer Screens (Separate System)
Located in: `src/screens/customer/`
- Not part of this cleanup (customer-facing features)

---

## Navigation Structure

Located in: `src/navigation/`

### To Be Removed:
- **OwnerNavigator.js** - Owner navigation stack
- **AIStack.js** - AI features stack (likely owner-only)
- **AnalyticsStack.js** - Analytics navigation stack (likely owner-only)
- **GoalsStack.js** - Goals navigation stack (owner-only)
- **ProfileStack.js** - May contain owner profile navigation
- **TransactionsStack.js** - May be shared or owner-specific

### To Be Preserved:
- **StaffNavigator.js** - Staff navigation stack
- **CustomerNavigator.js** - Customer navigation (not part of cleanup)

### To Be Modified:
- **App.tsx** or root navigator - Remove owner role routing

---

## State Management Structure

### Likely Redux Slices/Contexts to Remove:
- Goals slice/context
- Analytics slice/context
- AI insights slice/context
- Owner-specific state management

### To Preserve:
- Auth state
- Transactions state
- Stock state
- Orders state
- Staff-specific state

---

## API Services Structure

Located in: `src/services/`

### Owner-Specific APIs (To Audit):
- Goals API calls
- Analytics API calls
- AI API calls (Gemini integration)
- Owner-specific transaction/product/stock APIs

### Shared/Staff APIs (To Preserve):
- Staff transactions API
- Orders API
- Stock API (staff view)
- Products API (staff view)
- E-wallet notification API

---

## Dependencies to Audit

### Potentially Owner-Only:
- Chart libraries (react-native-chart-kit, victory-native)
- AI libraries (@google/generative-ai)
- Calendar libraries (react-native-calendars)
- Analytics libraries

### To Preserve:
- React Native core
- Navigation libraries
- Storage libraries (AsyncStorage, SQLite)
- UI libraries
- Networking libraries

---

## Current Git Status (Before Branch Creation)

**Branch:** main

**Modified Files:**
- App.tsx
- android/app/build.gradle
- android/app/src/main/AndroidManifest.xml
- android/build.gradle
- package-lock.json
- package.json

**Deleted Files:**
- android/app/src/main/java/com/gastotrack/MainActivity.kt
- android/app/src/main/java/com/gastotrack/MainApplication.kt

**New Untracked Files:**
- .kiro/ (spec directory)
- .vscode/ (editor config)
- Multiple markdown documentation files
- New Java files (converted from Kotlin)

**Note:** There are uncommitted changes from previous work (notification service, Java conversion).

---

## Architecture Overview

### Current Architecture (Dual-Role):
```
App Root
├── Auth Navigator
├── Owner Navigator (12 screens) ❌ TO BE REMOVED
│   ├── Dashboard
│   ├── Analytics
│   ├── Goals
│   ├── AI Chatbot
│   ├── AI Insights
│   ├── Calendar
│   ├── Alerts
│   ├── Products
│   ├── Stock
│   ├── Transactions
│   ├── Profile
│   └── Notification Capture
├── Staff Navigator (6 screens) ✅ TO BE PRESERVED
│   ├── Dashboard
│   ├── Transactions
│   ├── Orders
│   ├── Stock
│   ├── Receipt Scanner
│   └── Profile
└── Customer Navigator (Separate)
```

### Target Architecture (Staff-Only):
```
App Root
├── Auth Navigator
│   └── Owner Login Block Screen (New)
└── Staff Navigator (6 screens)
    ├── Dashboard
    ├── Transactions
    ├── Orders
    ├── Stock
    ├── Receipt Scanner
    └── Profile
```

---

## File Count Summary

**Before Cleanup:**
- Owner screens: 12 files
- Staff screens: 6 files
- Navigation files: 8 files
- Total screens: 18 files

**After Cleanup (Estimated):**
- Owner screens: 0 files
- Staff screens: 6 files
- Navigation files: ~3-4 files
- New: 1 OwnerBlockedScreen
- Total screens: 7 files

**Expected Deletion:** ~12-15 files

---

## Key Features to Preserve

### Staff Functionality:
✅ Transaction creation and management
✅ E-wallet notification capture
✅ Order management with POS
✅ Stock tracking and inventory
✅ OCR receipt scanning
✅ Offline-first sync
✅ Staff profile management

### Features Moving to Web Only:
🌐 Business analytics and reports
🌐 Financial goals tracking
🌐 AI chatbot and insights
🌐 Calendar view
🌐 Business alerts
🌐 Advanced product management
🌐 Advanced stock management (min/max, alerts)
🌐 Owner dashboard

---

## Rollback Information

**Backup Tag:** backup-before-owner-cleanup
**Feature Branch:** feature/mobile-staff-only
**Base Branch:** main

**To Rollback:**
```bash
git checkout main
git reset --hard backup-before-owner-cleanup
```

---

## Next Steps (As Per Tasks.md)

1. ✅ Create feature branch: `feature/mobile-staff-only`
2. ✅ Tag current state: `backup-before-owner-cleanup`
3. ✅ Document app structure (this file)
4. ⏭️ Proceed with Phase 1: Audit owner screen imports
5. ⏭️ Continue with remaining 49 tasks

---

## Notes

- This is a **major architectural change** removing ~30-40% of mobile app features
- Business owners will gain superior experience via responsive web dashboard
- Mobile app will be simpler, faster, and staff-focused
- Expected bundle size reduction: 10-15%
- Expected LOC reduction: 2,000-3,000 lines

---

**Created by:** Kiro AI Assistant
**Purpose:** Documentation for safe rollback and reference during migration
**Status:** Ready for feature branch creation
