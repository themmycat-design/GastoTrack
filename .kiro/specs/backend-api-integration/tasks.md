# Implementation Plan: GastoTrack Laravel Backend API Integration

## Overview

This implementation plan breaks down the development of the GastoTrack Laravel Backend API into discrete, actionable tasks. The API provides secure authentication, role-based authorization, comprehensive business data management, and offline-first synchronization capabilities for the GastoTrack Staff mobile app (React Native) plus web-based interfaces for Business Owners and Super Admins (Laravel Blade).

**Technology Stack**: Laravel 10+ with PHP 8.1+, MySQL 8.0+, Laravel Sanctum for authentication

**Estimated Timeline**: 8 weeks (40 development days)

**Implementation Strategy**: Build incrementally with frequent testing, starting with foundation (auth, database) and progressing to complex features (sync, analytics).

## Tasks

- [ ] 1. Project Foundation and Setup
  - Initialize Laravel 10+ project with PHP 8.1+ and Composer
  - Configure `.env` file with database credentials, app key, and CORS settings
  - Install Laravel Sanctum for API authentication
  - Configure CORS middleware for React Native Staff mobile app
  - Set up development environment (local MySQL database)
  - Create `.gitignore` rules for Laravel
  - _Requirements: 1.1, 1.2, 14.1, 14.2, 19.1_

- [ ] 2. Database Schema Implementation
  - [ ] 2.1 Create foundational migrations (users, businesses)
    - Create migration: `create_users_table` (id, name, email, password, role, timestamps)
    - Create migration: `create_businesses_table` (id, owner_id, name, address, contact, currency, timestamps)
    - Create migration: `add_business_id_to_users_table` (resolve circular dependency)
    - Add indexes on foreign keys
    - _Requirements: 16.1, 16.2, 16.14_
  
  - [ ] 2.2 Create transaction and product migrations
    - Create migration: `create_transactions_table` with soft deletes
    - Create migration: `create_products_table` with soft deletes
    - Create migration: `create_product_ingredients_table` (many-to-many pivot)
    - Add indexes on business_id, transaction_date, category
    - _Requirements: 16.3, 16.4, 16.5, 16.14_
  
  - [ ] 2.3 Create stock management migrations
    - Create migration: `create_stock_items_table` with soft deletes
    - Create migration: `create_stock_movements_table` (audit trail for stock changes)
    - Add indexes on business_id, stock_item_id
    - _Requirements: 16.6, 16.7, 16.14_
  
  - [ ] 2.4 Create order management migrations
    - Create migration: `create_orders_table` (order_number, status, total_amount)
    - Create migration: `create_order_items_table` (quantity, unit_price, subtotal)
    - Add indexes on business_id, order_date
    - _Requirements: 16.8, 16.9, 16.14_
  
  - [ ] 2.5 Create supporting tables migrations
    - Create migration: `create_goals_table` (financial goals tracking)
    - Create migration: `create_notifications_table` (user notifications)
    - Create migration: `create_activity_logs_table` (audit trail)
    - Create migration: `create_personal_access_tokens_table` (Sanctum tokens)
    - Add indexes on user_id, business_id, created_at
    - _Requirements: 16.10, 16.11, 16.12, 16.14_

- [ ] 3. Checkpoint - Database Migration Verification
  - Run `php artisan migrate:fresh` to verify all migrations execute successfully
  - Verify foreign key constraints are properly configured
  - Verify indexes are created on all specified columns
  - Test migration rollback with `php artisan migrate:rollback`
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 4. Eloquent Models Implementation
  - [ ] 4.1 Implement core models (User, Business)
    - Create `User` model with `HasApiTokens`, `Notifiable`, `SoftDeletes` traits
    - Define relationships: `business()`, `ownedBusiness()`, `transactions()`, `orders()`
    - Implement helper methods: `isOwner()`, `isStaff()`, `canAccessBusiness()`
    - Create `Business` model with relationships to owner, staff, transactions, products, etc.
    - Add fillable properties and casts
    - _Requirements: 1.1, 2.1, 3.1, 3.4_
  
  - [ ] 4.2 Implement transaction and product models
    - Create `Transaction` model with soft deletes, casts for JSON and decimals
    - Define scopes: `income()`, `expense()`, `forBusiness()`, `dateRange()`
    - Create `Product` model with relationships to business, ingredients, orderItems
    - Create `ProductIngredient` model (pivot table handler)
    - _Requirements: 4.1, 4.7, 5.1, 5.7_
  
  - [ ] 4.3 Implement stock management models
    - Create `StockItem` model with soft deletes
    - Implement `isLowStock()` helper method
    - Implement `adjustQuantity()` method that creates stock movements
    - Create `StockMovement` model for audit trail
    - _Requirements: 6.1, 6.3, 6.7, 6.8_
  
  - [ ] 4.4 Implement order and support models
    - Create `Order` model with `calculateTotal()` method
    - Implement `generateOrderNumber()` static method
    - Create `OrderItem` model with relationship to products
    - Create `Goal` model with status and progress tracking
    - Create `Notification` model with read/unread status
    - Create `ActivityLog` model for audit trail
    - _Requirements: 7.1, 7.3, 8.1, 8.6, 12.1, 13.1_

- [ ] 5. Model Factories and Seeders
  - [ ] 5.1 Create model factories
    - Create `UserFactory` with owner and staff states
    - Create `BusinessFactory` with realistic Filipino business data
    - Create `TransactionFactory`, `ProductFactory`, `StockItemFactory`
    - Create factories for all remaining models
    - _Requirements: Testing support_
  
  - [ ] 5.2 Create database seeders
    - Create `DatabaseSeeder` with sample owner and staff accounts
    - Seed 2 businesses with 50 transactions each
    - Seed 10 products and 15 stock items per business
    - Seed 20 orders with order items
    - Seed 3 goals and 5 notifications per business
    - _Requirements: Testing support_

- [ ] 6. Checkpoint - Model Testing
  - Test all model relationships with `php artisan tinker`
  - Run seeders with `php artisan db:seed` and verify data integrity
  - Test model helper methods and scopes
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 7. Authentication System Implementation
  - [ ] 7.1 Implement AuthController endpoints
    - Implement `POST /api/v1/auth/register` - Create business owner account with business
    - Implement `POST /api/v1/auth/login` - Authenticate and return Sanctum token
    - Implement `POST /api/v1/auth/logout` - Revoke current token
    - Implement `GET /api/v1/auth/me` - Get authenticated user details
    - Implement `POST /api/v1/auth/refresh` - Refresh authentication token
    - Implement `POST /api/v1/auth/change-password` - Change user password
    - Return standardized JSON responses with user, business, and token
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 15.1, 15.2_
  
  - [ ]* 7.2 Write authentication tests
    - Test successful registration creates user, business, and returns token
    - Test login with valid credentials returns token
    - Test login with invalid credentials returns 401 error
    - Test logout revokes token
    - Test protected endpoints reject invalid tokens
    - Test password change with correct current password
    - _Requirements: 1.1, 1.2, 1.6_

- [ ] 8. Form Request Validation Classes
  - [ ] 8.1 Create auth validation requests
    - Create `RegisterRequest` with email uniqueness, password confirmation
    - Create `LoginRequest` with required email and password
    - Create `ChangePasswordRequest` with current and new password validation
    - _Requirements: 14.1, 14.2, 14.4, 14.7_
  
  - [ ] 8.2 Create transaction validation requests
    - Create `StoreTransactionRequest` with type, category, amount, date validation
    - Create `UpdateTransactionRequest` with optional field updates
    - Validate amount is positive, date is not future, category is valid
    - _Requirements: 4.2, 4.3, 4.4, 14.5, 14.7_
  
  - [ ] 8.3 Create product and stock validation requests
    - Create `StoreProductRequest`, `UpdateProductRequest`
    - Create `StoreStockItemRequest`, `UpdateStockItemRequest`
    - Create `AdjustStockRequest` for stock quantity adjustments
    - Validate numeric ranges, required fields, business ownership
    - _Requirements: 5.2, 5.3, 6.2, 6.4, 14.4, 14.5_
  
  - [ ] 8.4 Create order and goal validation requests
    - Create `StoreOrderRequest` with order items validation
    - Create `UpdateOrderStatusRequest` with status enum validation
    - Create `StoreGoalRequest`, `UpdateGoalRequest` with date and amount validation
    - _Requirements: 7.2, 7.7, 8.2, 14.5_

- [ ] 9. Authorization Policies Implementation
  - [ ] 9.1 Create resource policies
    - Create `BusinessPolicy` - only owners can update business settings
    - Create `TransactionPolicy` - owners and staff can create, only owners delete
    - Create `ProductPolicy` - only owners can manage products
    - Create `StockItemPolicy` - owners and staff can manage stock
    - Create `OrderPolicy` - owners and staff can create orders
    - Create `GoalPolicy` - only owners can manage goals
    - Create `StaffPolicy` - only owners can manage staff
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_
  
  - [ ] 9.2 Create authorization middleware
    - Create `CheckBusinessOwner` middleware - verify user role is 'owner'
    - Create `CheckBusinessAccess` middleware - verify user belongs to business
    - Register middleware in `app/Http/Kernel.php`
    - _Requirements: 2.1, 2.7_

- [ ] 10. API Resource Transformers
  - [ ] 10.1 Create resource classes for models
    - Create `UserResource` - format user data with role and business
    - Create `BusinessResource` - format business information
    - Create `TransactionResource` - format with decimal amounts, ISO dates
    - Create `ProductResource` - include ingredients when loaded
    - Create `StockItemResource` - include low stock indicator
    - Create `OrderResource` - include items and calculated totals
    - Create `GoalResource` - include progress percentage
    - Create `NotificationResource` - format notification data
    - _Requirements: 15.1, 15.8_

- [ ] 11. Business Management Endpoints
  - [ ] 11.1 Implement business endpoints
    - Implement `GET /api/v1/business` - Get current user's business info
    - Implement `PUT /api/v1/business` - Update business details (owner only)
    - Apply `CheckBusinessOwner` middleware to update endpoint
    - Return `BusinessResource` for consistent formatting
    - Create activity log entries on updates
    - _Requirements: 3.2, 3.3, 3.5, 15.1_
  
  - [ ]* 11.2 Write business management tests
    - Test business retrieval returns correct data
    - Test owner can update business details
    - Test staff cannot update business details (403 error)
    - Test validation errors for invalid data
    - _Requirements: 2.3, 3.2, 3.3_

- [ ] 12. Transaction Management Endpoints
  - [ ] 12.1 Implement transaction CRUD endpoints
    - Implement `GET /api/v1/transactions` - Paginated list with filters (type, category, date range)
    - Implement `GET /api/v1/transactions/{id}` - Single transaction details
    - Implement `POST /api/v1/transactions` - Create new transaction
    - Implement `PUT /api/v1/transactions/{id}` - Update transaction
    - Implement `DELETE /api/v1/transactions/{id}` - Soft delete (owner only)
    - Apply business access checks and authorization policies
    - _Requirements: 4.1, 4.5, 4.6, 4.7, 15.8, 15.9_
  
  - [ ] 12.2 Implement transaction analytics endpoints
    - Implement `GET /api/v1/transactions/summary` - Calculate totals (income, expenses, net)
    - Implement `POST /api/v1/transactions/batch` - Create multiple transactions (for sync)
    - Apply date range filters with query parameters
    - _Requirements: 4.10, 10.1, 10.2, 15.8_
  
  - [ ]* 12.3 Write transaction endpoint tests
    - Test transaction creation with valid data
    - Test transaction list pagination and filtering
    - Test staff cannot delete transactions
    - Test validation errors for negative amounts
    - Test summary calculations are accurate
    - _Requirements: 4.1, 4.2, 4.3, 4.5, 4.10_

- [ ] 13. Checkpoint - Core Endpoints Working
  - Verify authentication, business, and transaction endpoints work end-to-end
  - Test with Postman or curl commands
  - Verify pagination and filtering work correctly
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 14. Product Management Endpoints
  - [ ] 14.1 Implement product CRUD endpoints
    - Implement `GET /api/v1/products` - Paginated list with category filter
    - Implement `GET /api/v1/products/{id}` - Single product with ingredients
    - Implement `POST /api/v1/products` - Create product with ingredients (owner only)
    - Implement `PUT /api/v1/products/{id}` - Update product (owner only)
    - Implement `DELETE /api/v1/products/{id}` - Soft delete (owner only)
    - Eager load ingredients relationship to avoid N+1 queries
    - _Requirements: 5.1, 5.3, 5.4, 5.5, 5.6, 15.8_
  
  - [ ]* 14.2 Write product endpoint tests
    - Test product creation with ingredients
    - Test product list returns active products
    - Test staff cannot create or delete products
    - Test soft delete preserves data
    - _Requirements: 5.1, 5.3, 5.5, 5.6_

- [ ] 15. Stock Management Endpoints
  - [ ] 15.1 Implement stock CRUD endpoints
    - Implement `GET /api/v1/stock` - Paginated list with low_stock filter
    - Implement `GET /api/v1/stock/{id}` - Single stock item details
    - Implement `POST /api/v1/stock` - Create new stock item
    - Implement `PUT /api/v1/stock/{id}` - Update stock item (not quantity)
    - Implement `POST /api/v1/stock/{id}/adjust` - Adjust quantity and create movement
    - Implement `GET /api/v1/stock/{id}/movements` - Get movement history
    - Implement `DELETE /api/v1/stock/{id}` - Soft delete (owner only)
    - _Requirements: 6.1, 6.2, 6.3, 6.6, 6.7, 15.8_
  
  - [ ]* 15.2 Write stock endpoint tests
    - Test stock adjustment creates movement record
    - Test low stock filter returns correct items
    - Test quantity validation prevents negative values
    - Test movement history pagination
    - _Requirements: 6.1, 6.3, 6.4, 6.7_

- [ ] 16. Order Management Endpoints
  - [ ] 16.1 Implement order CRUD endpoints
    - Implement `GET /api/v1/orders` - Paginated list with status and date filters
    - Implement `GET /api/v1/orders/{id}` - Single order with items
    - Implement `POST /api/v1/orders` - Create order with automatic stock deduction
    - Implement `PUT /api/v1/orders/{id}` - Update order status
    - Implement `DELETE /api/v1/orders/{id}` - Delete order (owner only, rare)
    - Apply business access checks
    - _Requirements: 7.1, 7.2, 7.4, 7.6, 7.7, 15.8_
  
  - [ ]* 16.2 Write order endpoint tests
    - Test order creation with items
    - Test automatic stock deduction on order creation
    - Test total amount calculation
    - Test order status validation
    - _Requirements: 7.1, 7.2, 7.3, 7.7_

- [ ] 17. Goals Management Endpoints
  - [ ] 17.1 Implement goals CRUD endpoints
    - Implement `GET /api/v1/goals` - List all goals (owner only)
    - Implement `GET /api/v1/goals/{id}` - Single goal details
    - Implement `POST /api/v1/goals` - Create new goal (owner only)
    - Implement `PUT /api/v1/goals/{id}` - Update goal progress (owner only)
    - Implement `POST /api/v1/goals/{id}/complete` - Mark goal completed
    - Implement `DELETE /api/v1/goals/{id}` - Delete goal (owner only)
    - Apply `CheckBusinessOwner` middleware to all endpoints
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 2.6_
  
  - [ ]* 17.2 Write goals endpoint tests
    - Test goal creation with future deadline
    - Test progress calculation
    - Test staff cannot access goals endpoints
    - Test goal completion sets timestamp
    - _Requirements: 8.1, 8.2, 8.5, 2.6_

- [ ] 18. Staff Management Endpoints
  - [ ] 18.1 Implement staff CRUD endpoints
    - Implement `GET /api/v1/staff` - List all staff members (owner only)
    - Implement `GET /api/v1/staff/{id}` - Single staff member details
    - Implement `POST /api/v1/staff` - Invite new staff member (owner only)
    - Implement `DELETE /api/v1/staff/{id}` - Remove staff member and revoke tokens (owner only)
    - Implement `GET /api/v1/staff/{id}/activity` - Get staff activity logs
    - Apply `CheckBusinessOwner` middleware
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 2.6_
  
  - [ ]* 18.2 Write staff management tests
    - Test staff creation associates with business
    - Test staff removal revokes all tokens
    - Test historical data preserved after removal
    - Test staff cannot manage other staff
    - _Requirements: 9.1, 9.2, 9.4, 9.5, 2.6_

- [ ] 19. Checkpoint - All CRUD Endpoints Complete
  - Test all resource endpoints with Postman collection
  - Verify authorization rules work correctly
  - Verify pagination works on all list endpoints
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 20. Notification System Implementation
  - [ ] 20.1 Create NotificationService
    - Implement `notifyLowStock()` - Create notification when stock below minimum
    - Implement `notifyGoalDeadline()` - Create notification for approaching deadlines
    - Schedule background job to check notifications daily
    - _Requirements: 12.1, 12.2_
  
  - [ ] 20.2 Implement notification endpoints
    - Implement `GET /api/v1/notifications` - Get user notifications with unread filter
    - Implement `POST /api/v1/notifications/{id}/read` - Mark as read
    - Implement `POST /api/v1/notifications/read-all` - Mark all as read
    - Implement `DELETE /api/v1/notifications/{id}` - Delete notification
    - Include unread count in response metadata
    - _Requirements: 12.3, 12.4, 12.5_
  
  - [ ]* 20.3 Write notification tests
    - Test low stock creates notification
    - Test marking notification as read
    - Test unread count calculation
    - Test notification filtering
    - _Requirements: 12.1, 12.3, 12.4_

- [ ] 21. Observer Implementation
  - [ ] 21.1 Create model observers
    - Create `OrderObserver` - Create transaction and deduct stock on order creation
    - Create `OrderObserver::updated()` - Reverse stock on order cancellation
    - Create `TransactionObserver` - Log transaction changes to activity_logs
    - Create `ProductObserver` - Log product changes to activity_logs
    - Register observers in `AppServiceProvider`
    - _Requirements: 7.4, 7.5, 7.8, 13.1, 13.2, 13.3_
  
  - [ ]* 21.2 Test observer behavior
    - Test order creation triggers stock deduction
    - Test order cancellation reverses stock
    - Test all model changes create audit logs
    - Test audit logs include old and new values
    - _Requirements: 7.4, 7.8, 13.1, 13.2, 13.3, 13.4_

- [ ] 22. Service Layer Implementation
  - [ ] 22.1 Implement AnalyticsService
    - Implement `getDailySummary()` - Calculate income, expenses, net for a date
    - Implement `getCategoryBreakdown()` - Group transactions by category with percentages
    - Implement `getTopProducts()` - Rank products by sales volume
    - Implement `getStockValue()` - Calculate total inventory value
    - Implement `getTrends()` - Generate time-series data for charts
    - Use query optimization and caching
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7_
  
  - [ ] 22.2 Implement StockService
    - Implement `deductStockForOrder()` - Deduct ingredients from stock for order
    - Implement `checkLowStock()` - Find items below minimum quantity
    - Implement `calculateStockValue()` - Calculate total inventory worth
    - _Requirements: 6.5, 6.8, 7.5_
  
  - [ ] 22.3 Implement SyncService (preparation)
    - Create `SyncService` class structure
    - Implement helper methods for conflict detection
    - Prepare batch processing structure
    - _Requirements: 10.1, 10.2, 10.3, 10.4_

- [ ] 23. Analytics & Reports Endpoints
  - [ ] 23.1 Implement analytics endpoints (owner only)
    - Implement `GET /api/v1/analytics/summary` - Business analytics with period parameter
    - Implement `GET /api/v1/analytics/category-breakdown` - Income/expense by category
    - Implement `GET /api/v1/analytics/top-products` - Top selling products report
    - Implement `GET /api/v1/analytics/stock-value` - Total inventory value
    - Implement `GET /api/v1/analytics/trends` - Time-series trends data
    - Apply `CheckBusinessOwner` middleware
    - Use `AnalyticsService` methods
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7_
  
  - [ ]* 23.2 Write analytics tests
    - Test daily summary calculations are accurate
    - Test category breakdown percentages sum to 100%
    - Test top products ranking is correct
    - Test stock value calculation includes all items
    - Test trends data covers full date range
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

- [ ] 24. Checkpoint - Advanced Features Complete
  - Test analytics endpoints return accurate calculations
  - Verify observers create audit logs correctly
  - Test notification generation
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 25. Offline Synchronization Implementation
  - [ ] 25.1 Implement SyncService methods
    - Implement `syncBatch()` - Process batch of offline records in transaction
    - Implement `syncTransactions()` - Sync offline transactions with conflict detection
    - Implement `syncOrders()` - Sync offline orders with items
    - Implement `syncStockMovements()` - Sync offline stock adjustments
    - Implement `getServerChanges()` - Fetch server changes since last sync
    - Implement `detectConflicts()` - Compare timestamps and identify conflicts
    - Use last-write-wins strategy for conflict resolution
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6_
  
  - [ ] 25.2 Implement sync endpoint
    - Implement `POST /api/v1/sync` - Main synchronization endpoint
    - Accept batch of transactions, orders, stock movements with client IDs
    - Process all changes in database transaction for atomicity
    - Return results with client_id to server_id mappings
    - Return server changes since last_sync_timestamp
    - Return conflicts array with both versions
    - Include new sync_timestamp in response
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.7_
  
  - [ ]* 25.3 Write synchronization tests
    - Test sync creates new records with server IDs
    - Test conflict detection when timestamps differ
    - Test last-write-wins resolution strategy
    - Test server changes returned correctly
    - Test transaction rollback on sync failure
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6_

- [ ] 26. Error Handling and Logging
  - [ ] 26.1 Configure exception handler
    - Update `app/Exceptions/Handler.php` to format API errors
    - Handle `ValidationException` - Return 422 with field errors
    - Handle `AuthenticationException` - Return 401 with message
    - Handle `AuthorizationException` - Return 403 with message
    - Handle `ModelNotFoundException` - Return 404 with message
    - Create custom `ConflictException` for sync conflicts - Return 409
    - Handle generic exceptions - Log and return 500 with error ID
    - _Requirements: 15.3, 15.4, 15.5, 15.6, 15.7, 18.1, 18.2, 18.3_
  
  - [ ] 26.2 Configure logging
    - Set up daily log rotation in `config/logging.php`
    - Create separate channels: application, authentication, database, api
    - Log all authentication attempts with user identifier
    - Log all validation failures
    - Log all exceptions with stack trace in development
    - Configure log retention for 30 days
    - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.5_

- [ ] 27. Performance Optimization
  - [ ] 27.1 Implement query optimization
    - Add eager loading to prevent N+1 queries in all list endpoints
    - Implement query scopes for common filters
    - Add database indexes verification
    - Optimize complex analytics queries
    - _Requirements: 17.1, 17.3_
  
  - [ ] 27.2 Implement caching (optional with Redis)
    - Cache business settings for 1 hour
    - Cache product catalog for 30 minutes
    - Implement cache invalidation on updates
    - Configure Redis connection in `.env`
    - _Requirements: 17.4, 17.5_
  
  - [ ] 27.3 Configure connection pooling
    - Enable persistent connections in `config/database.php`
    - Configure connection pool size
    - Test concurrent request handling
    - _Requirements: 17.5, 17.6_

- [ ] 28. API Documentation
  - [ ] 28.1 Generate OpenAPI documentation
    - Install `darkaonline/l5-swagger` package
    - Add PHPDoc annotations to all controller methods
    - Document request body schemas
    - Document response schemas
    - Document authentication requirements
    - Generate Swagger JSON with `php artisan l5-swagger:generate`
    - _Requirements: 19.2, 19.3, 19.4, 19.5_
  
  - [ ] 28.2 Create Postman collection
    - Export all endpoints to Postman collection
    - Include example requests with sample data
    - Include example responses
    - Create environment variables for base URL and token
    - Document authentication flow
    - _Requirements: 19.3, 19.5_

- [ ] 29. Testing Suite Completion
  - [ ]* 29.1 Write unit tests for services
    - Test `AnalyticsService` methods with known data
    - Test `StockService` stock deduction logic
    - Test `SyncService` conflict detection algorithm
    - Test `NotificationService` notification creation
    - Achieve 80%+ coverage of service layer
    - _Requirements: Testing strategy_
  
  - [ ]* 29.2 Write integration tests
    - Test transaction creation updates business totals
    - Test order creation deducts stock correctly
    - Test soft delete preserves relationships
    - Test eager loading eliminates N+1 queries
    - _Requirements: Testing strategy_
  
  - [ ]* 29.3 Write end-to-end API tests
    - Test complete user registration → business creation flow
    - Test complete order → transaction → stock deduction flow
    - Test authentication → protected endpoint flow
    - Test sync workflow with conflicts
    - Test authorization rules across all endpoints
    - _Requirements: Testing strategy_

- [ ] 30. Checkpoint - All Tests Passing
  - Run full test suite with `php artisan test`
  - Verify 80%+ code coverage achieved
  - Fix any failing tests
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 31. Security Hardening
  - [ ] 31.1 Configure security settings
    - Set `APP_DEBUG=false` in production `.env`
    - Configure CORS allowed origins for React Native Staff app
    - Set up rate limiting: 60/min unauthenticated, 300/min authenticated
    - Configure Sanctum token expiration (30 days)
    - Enable HTTPS enforcement
    - _Requirements: 14.1, 14.2, 14.7_
  
  - [ ] 31.2 Implement rate limiting
    - Apply `throttle:60,1` middleware to public endpoints
    - Apply `throttle:300,1` middleware to authenticated endpoints
    - Apply `throttle:10,1` middleware to sync endpoint
    - Configure rate limit headers in responses
    - _Requirements: Security considerations_
  
  - [ ] 31.3 Security audit
    - Review all SQL queries for injection vulnerabilities
    - Verify all inputs are validated
    - Check password hashing configuration
    - Verify no sensitive data in logs
    - Test CORS configuration
    - _Requirements: 14.3, 14.6, 14.7_

- [ ] 32. Deployment Preparation
  - [ ] 32.1 Create production configuration
    - Create `.env.production` template
    - Document all required environment variables
    - Configure production database connection
    - Set up Redis connection for cache and queues
    - Configure mail settings for notifications
    - _Requirements: 20.1, 20.3_
  
  - [ ] 32.2 Create deployment scripts
    - Create deployment script: pull code, install dependencies, run migrations
    - Create rollback script for emergency rollback
    - Configure queue worker service
    - Set up Laravel Horizon for queue monitoring (optional)
    - _Requirements: 20.1_
  
  - [ ] 32.3 Set up monitoring
    - Configure application monitoring (Laravel Telescope for dev)
    - Set up error tracking (Sentry or similar)
    - Configure uptime monitoring
    - Set up database backup automation
    - _Requirements: 18.6, 20.3, 20.4, 20.5_

- [ ] 33. Database Backup and Recovery
  - [ ] 33.1 Configure automated backups
    - Set up daily automated database backups
    - Configure 30-day backup retention
    - Implement backup verification script
    - Test backup restoration process
    - Set up backup failure alerts
    - _Requirements: 20.1, 20.2, 20.3, 20.4, 20.5_

- [ ] 34. Production Deployment
  - [ ] 34.1 Deploy to production server
    - Provision production server (VPS, Laravel Forge, or cloud)
    - Configure Nginx + PHP-FPM
    - Set up MySQL database (managed service recommended)
    - Configure SSL certificate (Let's Encrypt)
    - Deploy application code
    - Run migrations on production database
    - _Requirements: Deployment architecture_
  
  - [ ] 34.2 Production verification
    - Test all API endpoints in production
    - Verify authentication works correctly
    - Test sync endpoint with real Staff mobile app
    - Monitor performance and error rates
    - Verify backups are running
    - _Requirements: 17.1, 20.1_

- [ ] 35. Staff Mobile App Integration Documentation
  - [ ] 35.1 Create integration guide
    - Document API base URL and version
    - Document authentication flow for React Native Staff app
    - Provide code examples for API calls
    - Document sync protocol and conflict handling
    - Create troubleshooting guide
    - _Requirements: 19.1, 19.2, 19.3_
  
  - [ ] 35.2 Create React Native Staff app API client template
    - Create example `api.js` service with Axios
    - Implement token storage with AsyncStorage
    - Implement automatic token refresh
    - Implement offline queue for sync
    - Provide example usage for all endpoints
    - _Requirements: Mobile integration_

- [ ] 36. Final Checkpoint and Handoff
  - Verify all 60+ API endpoints are functional
  - Confirm all tests passing in production
  - Verify Staff mobile app can authenticate and sync
  - Review API documentation completeness
  - Conduct final security review
  - Ensure all tests pass, ask the user if questions arise.

## Notes

### Task Organization
- Tasks are grouped by logical feature areas (auth, models, endpoints, services)
- Tasks marked with `*` are optional testing sub-tasks (can be skipped for faster MVP but highly recommended)
- Checkpoint tasks pause to verify progress before continuing
- Each task references specific requirements from requirements.md

### Dependencies
- Tasks 1-6 must be completed sequentially (foundation)
- Tasks 7-19 can be partially parallelized by feature area
- Tasks 20-26 depend on core endpoints being complete
- Tasks 27-36 are optimization, deployment, and documentation

### Testing Strategy
- Unit tests for service layer business logic (80%+ coverage target)
- Integration tests for database relationships and queries
- API tests for request/response validation and authorization
- Property-based testing is NOT applicable (this is infrastructure/CRUD)

### Parallel Development Opportunities
- After database setup (Task 6), different resource endpoints can be built in parallel:
  - Developer A: Transactions + Analytics
  - Developer B: Products + Stock
  - Developer C: Orders + Goals
  - Developer D: Staff + Notifications

### Success Criteria
- All migrations run successfully
- All 60+ API endpoints return correct responses
- Authentication and authorization work correctly
- Offline sync handles conflicts properly
- Test suite passes with 80%+ coverage
- API documentation is complete
- Staff mobile app can successfully integrate
- Production deployment is stable

### Estimated Timeline Breakdown
- **Week 1-2**: Foundation (Tasks 1-6) - 10 days
- **Week 3-4**: Authentication & Core CRUD (Tasks 7-19) - 10 days
- **Week 5**: Advanced Features (Tasks 20-24) - 5 days
- **Week 6**: Sync & Services (Tasks 25-27) - 5 days
- **Week 7**: Testing & Documentation (Tasks 28-30) - 5 days
- **Week 8**: Security, Deployment & Handoff (Tasks 31-36) - 5 days
- **Total**: 40 development days (8 weeks)

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1"] },
    { "id": 1, "tasks": ["2.1"] },
    { "id": 2, "tasks": ["2.2", "2.3"] },
    { "id": 3, "tasks": ["2.4", "2.5"] },
    { "id": 4, "tasks": ["3"] },
    { "id": 5, "tasks": ["4.1"] },
    { "id": 6, "tasks": ["4.2", "4.3", "4.4"] },
    { "id": 7, "tasks": ["5.1", "5.2"] },
    { "id": 8, "tasks": ["6"] },
    { "id": 9, "tasks": ["7.1", "8.1"] },
    { "id": 10, "tasks": ["7.2", "8.2", "8.3", "8.4", "9.1", "9.2"] },
    { "id": 11, "tasks": ["10.1"] },
    { "id": 12, "tasks": ["11.1"] },
    { "id": 13, "tasks": ["11.2", "12.1"] },
    { "id": 14, "tasks": ["12.2", "12.3"] },
    { "id": 15, "tasks": ["13"] },
    { "id": 16, "tasks": ["14.1", "15.1", "16.1", "17.1", "18.1"] },
    { "id": 17, "tasks": ["14.2", "15.2", "16.2", "17.2", "18.2"] },
    { "id": 18, "tasks": ["19"] },
    { "id": 19, "tasks": ["20.1", "21.1", "22.1", "22.2", "22.3"] },
    { "id": 20, "tasks": ["20.2", "21.2", "23.1"] },
    { "id": 21, "tasks": ["20.3", "23.2"] },
    { "id": 22, "tasks": ["24"] },
    { "id": 23, "tasks": ["25.1", "26.1", "27.1"] },
    { "id": 24, "tasks": ["25.2", "26.2", "27.2", "27.3"] },
    { "id": 25, "tasks": ["25.3", "28.1", "28.2", "29.1", "29.2", "29.3"] },
    { "id": 26, "tasks": ["30"] },
    { "id": 27, "tasks": ["31.1", "31.2", "31.3", "32.1"] },
    { "id": 28, "tasks": ["32.2", "32.3", "33.1"] },
    { "id": 29, "tasks": ["34.1"] },
    { "id": 30, "tasks": ["34.2", "35.1"] },
    { "id": 31, "tasks": ["35.2"] },
    { "id": 32, "tasks": ["36"] }
  ]
}
```
