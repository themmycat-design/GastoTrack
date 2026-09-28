# Requirements Document

## Introduction

**Feature:** Business Owner Web Dashboard (Laravel Blade + Livewire)

The Business Owner Web Dashboard is a responsive web application built with Laravel Blade templates and Livewire components. This dashboard enables business owners to manage their business operations from any device - mobile phones, tablets, or desktop computers - providing full access to financial tracking, inventory management, analytics, and business settings.

The web dashboard is part of the GastoTrack ecosystem that includes a React Native mobile app for on-the-go access and a separate Super Admin dashboard for platform management. This Owner dashboard focuses on individual business management with a mobile-first responsive design.

## Glossary

- **Business_Owner**: User who owns and manages a single business account
- **Laravel_Blade**: Server-side templating engine for generating HTML
- **Livewire**: Full-stack framework for building reactive interfaces without writing JavaScript
- **Wire_Model**: Livewire's two-way data binding directive
- **Livewire_Component**: PHP class that renders dynamic UI without page reloads
- **Alpine_js**: Lightweight JavaScript framework for interactive behavior
- **Tailwind_CSS**: Utility-first CSS framework for responsive design
- **Responsive_Design**: UI that adapts to mobile (320px+), tablet (768px+), and desktop (1024px+)
- **Mobile_First**: Design approach starting with mobile layout, then scaling up
- **Sidebar_Navigation**: Left-side collapsible menu for navigation
- **Bottom_Navigation**: Mobile-friendly navigation bar at screen bottom
- **DataTable**: Sortable, filterable table component with pagination
- **Modal**: Overlay dialog for forms and confirmations
- **Toast**: Temporary notification message
- **Hamburger_Menu**: Three-line button that toggles mobile navigation
- **Breakpoint**: Screen width threshold for responsive behavior changes
- **CSRF_Token**: Laravel's security token for form submissions
- **Middleware**: Laravel's request filtering layer
- **Session**: Server-side storage for user authentication

## Requirements

### Requirement 1: Responsive Dashboard Layout

**User Story:** As a Business_Owner, I want to access my dashboard from mobile, tablet, or desktop, so that I can manage my business from any device.

#### Acceptance Criteria

1. WHEN Business_Owner accesses dashboard on mobile (320px-767px), THE layout SHALL display single-column cards with bottom navigation
2. WHEN Business_Owner accesses dashboard on tablet (768px-1023px), THE layout SHALL display two-column cards with collapsible sidebar
3. WHEN Business_Owner accesses dashboard on desktop (1024px+), THE layout SHALL display multi-column cards with persistent sidebar
4. THE sidebar SHALL collapse into hamburger menu on mobile and tablet
5. THE navigation SHALL switch between sidebar (desktop) and bottom bar (mobile) automatically
6. ALL interactive elements SHALL have minimum 44px touch target on mobile
7. THE page SHALL load and be interactive within 2 seconds on 3G connection

### Requirement 2: Dashboard Financial Overview

**User Story:** As a Business_Owner, I want to view my business financial summary, so that I can quickly understand my current status.

#### Acceptance Criteria

1. THE dashboard SHALL display three financial cards: Total Balance, Total Income, Total Expense
2. THE financial cards SHALL stack vertically on mobile, display 2-up on tablet, and 3-up on desktop
3. THE dashboard SHALL have period filter buttons: Daily, Weekly, Monthly, Yearly
4. WHEN period is changed, THE financial data SHALL update using Livewire without page reload
5. THE dashboard SHALL display savings goal progress bar with percentage
6. THE dashboard SHALL show "Recent Transactions" list with the last 5 transactions
7. THE dashboard SHALL have quick action buttons: Add Transaction, View Analytics
8. THE dashboard SHALL use green theme (#00C897) for primary elements

### Requirement 3: Responsive Navigation System

**User Story:** As a Business_Owner, I want easy navigation that works on my device, so that I can access all features quickly.

#### Acceptance Criteria

1. ON mobile, THE navigation SHALL display as a bottom bar with icons and labels
2. ON tablet, THE navigation SHALL display as a collapsible sidebar
3. ON desktop, THE navigation SHALL display as a persistent sidebar
4. THE navigation SHALL include: Dashboard, Transactions, Analytics, Calendar, Goals, Stock, Products, Profile
5. THE active page SHALL be visually highlighted in navigation
6. THE hamburger menu button SHALL toggle sidebar on mobile and tablet
7. THE sidebar SHALL overlay content on mobile (not push)
8. THE bottom navigation SHALL remain fixed at screen bottom on mobile

### Requirement 4: Transaction Management with Responsive Table

**User Story:** As a Business_Owner, I want to manage transactions on any device, so that I can record income and expenses anytime.

#### Acceptance Criteria

1. THE Transactions page SHALL display a Livewire DataTable
2. ON mobile, THE table SHALL display as transaction cards (one per row)
3. ON tablet and desktop, THE table SHALL display as a traditional table with columns
4. THE table SHALL have columns: Date, Type, Category, Description, Source, Amount, Actions
5. THE table SHALL support pagination with 20 items per page
6. THE table SHALL have search input that filters in real-time using Livewire
7. THE table SHALL have filter tabs: All, Income, Expense
8. THE table SHALL have date range filter inputs
9. WHEN "Add Transaction" button is clicked, THE app SHALL open a responsive modal
10. THE modal SHALL be full-screen on mobile, centered dialog on tablet/desktop
11. THE transaction form SHALL have real-time validation using Livewire
12. THE form SHALL have fields: Type, Category, Amount, Description, Source, Date
13. WHEN transaction is saved, THE table SHALL refresh automatically via Livewire
14. THE Actions column SHALL show edit/delete icons on desktop, swipe actions on mobile

### Requirement 5: Responsive Transaction Form Modal

**User Story:** As a Business_Owner, I want to add transactions easily on my phone, so that I can record sales immediately.

#### Acceptance Criteria

1. THE transaction form modal SHALL be full-screen on mobile with header and close button
2. THE transaction form modal SHALL be centered dialog (max 600px) on tablet/desktop
3. THE form fields SHALL stack vertically on all screen sizes
4. THE Type selection SHALL be radio buttons (Income/Expense) with large touch targets
5. THE Category field SHALL be a dropdown with scrollable options
6. THE Amount field SHALL trigger numeric keyboard on mobile
7. THE Date field SHALL open native date picker on mobile, calendar widget on desktop
8. THE form SHALL show validation errors below each field in real-time
9. THE Save button SHALL be full-width on mobile, auto-width on desktop
10. THE modal SHALL be dismissible by clicking outside on desktop only

### Requirement 6: Analytics with Responsive Charts

**User Story:** As a Business_Owner, I want to view business analytics on any device, so that I can analyze performance anywhere.

#### Acceptance Criteria

1. THE Analytics page SHALL display summary cards for balance and expenses
2. THE summary cards SHALL stack on mobile, display side-by-side on tablet/desktop
3. THE Analytics page SHALL have period tabs: Daily, Weekly, Monthly, Yearly
4. THE tabs SHALL scroll horizontally on mobile if needed
5. THE Analytics page SHALL display bar chart comparing income vs expenses
6. THE chart SHALL be full-width with responsive height
7. THE chart SHALL use Livewire Charts package
8. THE chart SHALL be touch-interactive on mobile (swipe, pinch-zoom)
9. WHEN period is changed, THE chart SHALL update via Livewire
10. THE chart legend SHALL position below chart on mobile, right-side on desktop

### Requirement 7: Mobile-Friendly Calendar View

**User Story:** As a Business_Owner, I want to view transactions by date on my phone, so that I can quickly find specific days.

#### Acceptance Criteria

1. THE Calendar page SHALL display a responsive calendar grid
2. THE calendar SHALL show 7 columns (Mon-Sun) on all screen sizes
3. THE calendar cells SHALL have minimum 44px touch target on mobile
4. THE calendar SHALL have large, touch-friendly prev/next month buttons
5. THE month/year selector SHALL be a dropdown on desktop, full-screen picker on mobile
6. THE dates with transactions SHALL show a green dot indicator
7. WHEN a date is clicked, THE app SHALL display transactions for that date below calendar
8. THE selected date SHALL be highlighted with green background
9. THE transaction list SHALL display as cards on mobile, table rows on desktop
10. THE calendar SHALL be horizontally scrollable on very small screens (<360px)

### Requirement 8: Goals Management with Progress Visualization

**User Story:** As a Business_Owner, I want to track financial goals on my device, so that I can monitor progress.

#### Acceptance Criteria

1. THE Goals page SHALL display all goals as cards
2. THE goal cards SHALL stack vertically on mobile, grid layout (2 columns) on tablet/desktop
3. EACH goal card SHALL show: Name, Target Amount, Current Amount, Deadline, Progress Bar
4. THE progress bar SHALL be full-width within card
5. WHEN "Add Goal" button is clicked, THE app SHALL open responsive modal
6. THE goal form SHALL have fields: Name, Description, Target Amount, Deadline
7. THE goal form SHALL validate target amount is positive and deadline is future date
8. THE Goals page SHALL have "Quick Access" section with Stock and Products cards
9. THE quick access cards SHALL be 2-up on mobile, 4-up on desktop

### Requirement 9: Responsive Stock Management

**User Story:** As a Business_Owner, I want to manage inventory on any device, so that I can update stock levels in real-time.

#### Acceptance Criteria

1. THE Stock page SHALL display stock items in responsive DataTable
2. ON mobile, THE stock items SHALL display as cards showing: Name, Quantity, Status
3. ON desktop, THE table SHALL show columns: Name, Unit, Current Qty, Min Qty, Unit Cost, Actions
4. THE low stock items SHALL show red badge on all screen sizes
5. THE Stock page SHALL have "Low Stock Only" toggle filter
6. WHEN "Add Stock Item" is clicked, THE app SHALL open responsive modal
7. WHEN "Adjust Stock" is clicked, THE app SHALL open adjustment modal
8. THE adjustment form SHALL have: Quantity Change, Type (In/Out), Reason
9. THE adjustment form SHALL use numeric keyboard on mobile for quantity input
10. THE "Movement History" button SHALL open full-screen modal on mobile, dialog on desktop

### Requirement 10: Products Catalog with Responsive Grid

**User Story:** As a Business_Owner, I want to manage products on any device, so that I can update my menu anywhere.

#### Acceptance Criteria

1. THE Products page SHALL display products in responsive grid
2. THE grid SHALL be 1 column on mobile, 2 columns on tablet, 3-4 columns on desktop
3. EACH product card SHALL show: Image placeholder, Name, Category, Price, Status badge
4. THE product cards SHALL have consistent aspect ratio across all screen sizes
5. THE Products page SHALL have horizontal scrolling category filter chips
6. THE filter chips SHALL have large touch targets (min 44px height) on mobile
7. WHEN "Add Product" is clicked, THE app SHALL open responsive modal
8. THE product form SHALL support image upload with preview
9. THE product form SHALL allow linking stock item ingredients
10. THE ingredient selection SHALL be searchable dropdown on desktop, full-screen picker on mobile

### Requirement 11: Profile and Settings Responsive Layout

**User Story:** As a Business_Owner, I want to update my profile on any device, so that I can keep information current.

#### Acceptance Criteria

1. THE Profile page SHALL have tabs: Personal Info, Business Info, Settings
2. THE tabs SHALL be scrollable horizontally on mobile, full-width on desktop
3. THE Personal Info section SHALL show: Name, Email, Password Change button
4. THE Business Info section SHALL show: Business Name, Address, Contact, Logo
5. THE forms SHALL stack fields vertically on all screen sizes
6. THE Save button SHALL be sticky at bottom on mobile, inline on desktop
7. THE Profile page SHALL have large, touch-friendly logout button
8. THE password change modal SHALL validate password strength in real-time

### Requirement 12: Authentication with Responsive Login

**User Story:** As a Business_Owner, I want to log in securely from any device, so that I can access my dashboard.

#### Acceptance Criteria

1. THE login page SHALL be centered and responsive on all screen sizes
2. THE login form SHALL have: Email, Password, Remember Me checkbox, Login button
3. THE login form SHALL be max 400px width on desktop, full-width on mobile
4. THE login button SHALL be full-width with large touch target
5. WHEN credentials are valid, THE app SHALL create session and redirect to dashboard
6. WHEN credentials are invalid, THE app SHALL show error message above form
7. THE login page SHALL have "Forgot Password" link
8. THE Remember Me checkbox SHALL have large touch target (44px)

### Requirement 13: Role-Based Access Control

**User Story:** As the application, I want to enforce owner-only access, so that only authorized users see business data.

#### Acceptance Criteria

1. THE application SHALL use Laravel middleware to check authentication
2. THE application SHALL verify user has role "owner"
3. WHEN unauthenticated user accesses protected route, THE app SHALL redirect to login
4. WHEN authenticated non-owner accesses owner routes, THE app SHALL return 403 Forbidden
5. THE session SHALL expire after 30 days of inactivity
6. THE application SHALL use CSRF tokens on all forms

### Requirement 14: Responsive Design System with Tailwind CSS

**User Story:** As the application, I want consistent responsive behavior, so that layouts adapt properly to all devices.

#### Acceptance Criteria

1. THE application SHALL use Tailwind CSS utility classes for all styling
2. THE application SHALL define custom breakpoints: sm (640px), md (768px), lg (1024px), xl (1280px)
3. THE application SHALL use mobile-first approach (base styles for mobile, then md:, lg: for larger)
4. THE color scheme SHALL use green theme: primary (#00C897), primary-dark (#00A87E)
5. THE typography SHALL use responsive font sizes: text-sm on mobile, text-base on desktop
6. THE spacing SHALL use consistent scale: p-4 on mobile, p-6 on desktop
7. ALL touch targets SHALL be minimum 44x44px on mobile devices

### Requirement 15: Real-Time Updates with Livewire

**User Story:** As a Business_Owner, I want instant updates without page reloads, so that I have a smooth experience.

#### Acceptance Criteria

1. ALL forms SHALL use wire:model for two-way data binding
2. ALL tables SHALL refresh automatically when data changes via Livewire events
3. THE application SHALL show loading indicator during Livewire requests
4. THE loading indicator SHALL be positioned contextually (button spinner, overlay, skeleton)
5. THE application SHALL handle Livewire validation errors inline
6. THE financial cards SHALL update in real-time when period filter changes
7. THE search inputs SHALL debounce at 300ms using wire:model.debounce

### Requirement 16: Touch-Friendly Interactions

**User Story:** As a mobile user, I want touch-friendly controls, so that I can easily interact with the app.

#### Acceptance Criteria

1. ALL buttons SHALL have minimum 44x44px touch target
2. ALL clickable elements SHALL have visible tap feedback (active state)
3. THE swipe gestures SHALL be supported for table row actions on mobile
4. THE pull-to-refresh SHALL work on mobile for refreshing lists
5. THE modal close button SHALL be large and positioned for thumb reach
6. THE FAB (Floating Action Button) SHALL be positioned in thumb-reach zone on mobile
7. THE horizontal scrolling elements SHALL have momentum scrolling

### Requirement 17: Offline Indicator and Error Handling

**User Story:** As a Business_Owner, I want clear feedback when offline or errors occur, so that I understand what's happening.

#### Acceptance Criteria

1. WHEN internet connection is lost, THE app SHALL show "Offline" banner at top
2. WHEN Livewire request fails, THE app SHALL show error toast notification
3. THE error toast SHALL be dismissible and auto-hide after 5 seconds
4. WHEN form submission fails validation, THE app SHALL show errors below each field
5. THE application SHALL prevent form resubmission on page refresh
6. THE application SHALL handle session expiration gracefully with redirect to login

### Requirement 18: Performance Optimization for Mobile

**User Story:** As the application, I want fast page loads on mobile, so that users have good experience on slow connections.

#### Acceptance Criteria

1. THE application SHALL lazy-load images below the fold
2. THE application SHALL use Livewire lazy loading for non-critical components
3. THE application SHALL defer loading of charts until they're in viewport
4. THE application SHALL minify CSS and JavaScript in production
5. THE application SHALL use browser caching for static assets (30 days)
6. THE page load time SHALL be under 2 seconds on 3G connection
7. THE Lighthouse performance score SHALL be 90+ on mobile

### Requirement 19: Data Export with Device-Appropriate Format

**User Story:** As a Business_Owner, I want to export data from any device, so that I can generate reports.

#### Acceptance Criteria

1. THE Transactions page SHALL have "Export" button
2. ON mobile, THE export SHALL download CSV (simpler, smaller file)
3. ON desktop, THE export SHALL offer CSV and PDF options
4. THE exported file SHALL include all filtered transactions
5. THE file download SHALL trigger automatically after generation
6. THE export button SHALL show loading spinner during generation

### Requirement 20: Accessibility for Mobile and Desktop

**User Story:** As a user with accessibility needs, I want the dashboard to be usable with assistive technologies, so that I can access all features.

#### Acceptance Criteria

1. ALL forms SHALL have properly associated labels
2. ALL images SHALL have descriptive alt text
3. ALL interactive elements SHALL be keyboard accessible (desktop)
4. ALL interactive elements SHALL be screen reader accessible
5. THE application SHALL use semantic HTML (header, nav, main, section)
6. THE color contrast SHALL meet WCAG 2.1 AA standards (4.5:1 minimum)
7. THE font size SHALL be minimum 16px base to prevent iOS zoom
8. THE form inputs SHALL have appropriate inputmode and autocomplete attributes on mobile

