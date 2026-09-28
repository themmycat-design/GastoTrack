# Requirements Document

## Introduction

**Feature:** Backend API Integration

The GastoTrack Backend API is a Laravel-based RESTful API system that provides persistent data storage, authentication, authorization, and business logic for the GastoTrack ecosystem. The system serves three client types:

1. **Staff Mobile App (React Native)**: Android app for daily operations (transactions, stock management)
2. **Owner Web Dashboard (Laravel Blade)**: Responsive web interface for business management (accessed via mobile/tablet/desktop browsers)
3. **Super Admin Web Dashboard (Laravel Blade)**: Platform management interface

The API supports an offline-first architecture for the Staff mobile app, allowing it to operate independently and synchronize data when connectivity is available. The web dashboards (Owner and Super Admin) operate online and share the same Laravel codebase as the API, using Blade templates for server-side rendering.

The system handles multi-business scenarios with role-based access control, distinguishing between Super Admins (platform management), Business Owners (web-only access), and Staff Members (mobile-only access).

## Glossary

- **API_Server**: The Laravel application that handles HTTP requests and business logic
- **Database**: The MySQL database that stores all application data
- **Staff_Mobile_App**: The React Native Android application used by staff members
- **Owner_Web_Dashboard**: The responsive Blade-based web interface for business owners
- **Super_Admin_Dashboard**: The platform management web interface
- **Business_Owner**: A user who manages their business via web dashboard only (no mobile app)
- **Staff_Member**: A user with limited permissions who uses the mobile app for daily operations
- **Super_Admin**: A platform administrator with access to all businesses and system settings
- **Auth_Token**: A Sanctum-issued token for authenticating API requests (used by Staff mobile app)
- **Sync_Engine**: The system component that handles offline-to-online data synchronization for Staff mobile app
- **Business_Record**: A record representing a single business entity in the system
- **Transaction_Record**: A record representing an income or expense entry
- **Product_Record**: A record representing a menu item or sellable product
- **Stock_Item**: A record representing an inventory item
- **Order_Record**: A record representing a customer order
- **Goal_Record**: A record representing a financial savings goal
- **Audit_Log**: A record of all data modifications for accountability
- **E_Wallet_Transaction**: A transaction captured from Staff mobile app e-wallet notifications
- **OCR_Data**: Data extracted from receipt images using AI vision (Staff mobile app feature)
- **Sync_Conflict**: A situation where offline and online data versions differ (Staff mobile app)
- **Resource**: Any entity managed by the API (transactions, products, stock, etc.)
- **Endpoint**: A specific API URL that accepts HTTP requests

## Requirements

### Requirement 1: User Authentication System

**User Story:** As a Mobile_Client, I want to authenticate users securely, so that only authorized individuals can access the API.

#### Acceptance Criteria

1. WHEN a valid email and password are provided, THE API_Server SHALL create an Auth_Token and return user details
2. WHEN an invalid email or password is provided, THE API_Server SHALL return an HTTP 401 error with an error message
3. WHEN a registration request with valid data is received, THE API_Server SHALL create a new Business_Owner account and return an Auth_Token
4. WHEN a registration request with an existing email is received, THE API_Server SHALL return an HTTP 422 error
5. WHEN a valid Auth_Token is provided in the request header, THE API_Server SHALL authenticate the user
6. WHEN an invalid or expired Auth_Token is provided, THE API_Server SHALL return an HTTP 401 error
7. WHEN a logout request is received with a valid Auth_Token, THE API_Server SHALL revoke the token

### Requirement 2: Role-Based Authorization

**User Story:** As the API_Server, I want to enforce role-based permissions, so that Business_Owners and Staff_Members have appropriate access levels.

#### Acceptance Criteria

1. WHEN a Business_Owner requests access to any business resource they own, THE API_Server SHALL grant access
2. WHEN a Staff_Member requests access to a resource, THE API_Server SHALL verify they are assigned to that business
3. WHEN a Staff_Member attempts to modify business settings, THE API_Server SHALL return an HTTP 403 error
4. WHEN a Staff_Member attempts to create or view transactions, THE API_Server SHALL grant access
5. WHEN a Staff_Member attempts to manage stock items, THE API_Server SHALL grant access
6. WHEN a Staff_Member attempts to add other staff members, THE API_Server SHALL return an HTTP 403 error
7. WHEN a user requests access to a resource not belonging to their business, THE API_Server SHALL return an HTTP 403 error

### Requirement 3: Business Management

**User Story:** As a Business_Owner, I want to manage my business information, so that I can maintain accurate business details.

#### Acceptance Criteria

1. WHEN a Business_Owner creates an account, THE API_Server SHALL automatically create an associated Business_Record
2. WHEN a Business_Owner updates business details with valid data, THE API_Server SHALL update the Business_Record
3. WHEN a Business_Owner requests their business information, THE API_Server SHALL return the complete Business_Record
4. THE Business_Record SHALL include business name, address, contact information, and currency settings
5. WHEN a Business_Owner updates business details, THE API_Server SHALL create an Audit_Log entry

### Requirement 4: Transaction Management

**User Story:** As a Business_Owner or Staff_Member, I want to manage financial transactions, so that I can track income and expenses.

#### Acceptance Criteria

1. WHEN a valid transaction creation request is received, THE API_Server SHALL create a Transaction_Record with a unique identifier
2. WHEN a transaction is created, THE API_Server SHALL validate that the amount is a positive number
3. WHEN a transaction is created, THE API_Server SHALL validate that the type is either "income" or "expense"
4. WHEN a transaction is created, THE API_Server SHALL validate that the category matches predefined category lists
5. WHEN a transaction list is requested with date filters, THE API_Server SHALL return transactions within the specified date range
6. WHEN a transaction is updated by an authorized user, THE API_Server SHALL modify the Transaction_Record and create an Audit_Log entry
7. WHEN a transaction is deleted by a Business_Owner, THE API_Server SHALL soft-delete the Transaction_Record
8. THE API_Server SHALL store E_Wallet_Transaction data including source application name and notification timestamp
9. THE API_Server SHALL store OCR_Data including extracted text and confidence scores for receipt-based transactions
10. WHEN transaction totals are requested, THE API_Server SHALL calculate total income, total expenses, and net balance

### Requirement 5: Product Management

**User Story:** As a Business_Owner, I want to manage products and menu items, so that I can maintain an accurate product catalog.

#### Acceptance Criteria

1. WHEN a valid product creation request is received, THE API_Server SHALL create a Product_Record with a unique identifier
2. WHEN a product is created, THE API_Server SHALL validate that the name is not empty and price is non-negative
3. WHEN a product is created with ingredient links, THE API_Server SHALL create associations in the product_ingredients table
4. WHEN a product list is requested, THE API_Server SHALL return all products with optional category filtering
5. WHEN a product is updated by a Business_Owner, THE API_Server SHALL modify the Product_Record and create an Audit_Log entry
6. WHEN a product is deleted by a Business_Owner, THE API_Server SHALL soft-delete the Product_Record
7. THE Product_Record SHALL include name, description, price, category, image URL, and active status

### Requirement 6: Stock Management

**User Story:** As a Business_Owner or Staff_Member, I want to manage inventory stock, so that I can track available ingredients and supplies.

#### Acceptance Criteria

1. WHEN a valid stock item creation request is received, THE API_Server SHALL create a Stock_Item with a unique identifier
2. WHEN a stock item is created, THE API_Server SHALL validate that quantity is non-negative
3. WHEN stock quantity is updated, THE API_Server SHALL create a stock_movements record with the change details
4. WHEN stock quantity is updated, THE API_Server SHALL validate that the new quantity is non-negative
5. WHEN a stock movement is recorded, THE API_Server SHALL include type (in, out, adjustment), quantity, and reason
6. WHEN a stock list is requested, THE API_Server SHALL return all stock items with current quantities
7. WHEN stock movements history is requested for an item, THE API_Server SHALL return all movements ordered by date
8. WHEN a low stock check is requested, THE API_Server SHALL return items where current quantity is below minimum quantity threshold

### Requirement 7: Order Management

**User Story:** As a Business_Owner or Staff_Member, I want to manage customer orders, so that I can track sales and product demand.

#### Acceptance Criteria

1. WHEN a valid order creation request is received, THE API_Server SHALL create an Order_Record with a unique order number
2. WHEN an order is created with order items, THE API_Server SHALL create order_items records for each product
3. WHEN an order is created, THE API_Server SHALL calculate and store the total amount
4. WHEN an order with stock-linked products is created, THE API_Server SHALL automatically create a Transaction_Record for income
5. WHEN an order with stock-linked products is created, THE API_Server SHALL deduct ingredient quantities from stock via stock_movements
6. WHEN an order list is requested with date filters, THE API_Server SHALL return orders within the specified date range
7. WHEN an order status is updated, THE API_Server SHALL validate the status is one of: pending, completed, cancelled
8. WHEN an order is cancelled, THE API_Server SHALL reverse stock deductions if the order was previously completed

### Requirement 8: Goals Management

**User Story:** As a Business_Owner, I want to manage financial goals, so that I can track savings targets and business objectives.

#### Acceptance Criteria

1. WHEN a valid goal creation request is received, THE API_Server SHALL create a Goal_Record with a unique identifier
2. WHEN a goal is created, THE API_Server SHALL validate that target amount is positive and deadline is in the future
3. WHEN a goal progress update is requested, THE API_Server SHALL calculate current progress based on actual transactions
4. WHEN a goal list is requested, THE API_Server SHALL return all active goals with progress percentages
5. WHEN a goal is marked as completed, THE API_Server SHALL set the completion date and status
6. THE Goal_Record SHALL include name, description, target amount, current amount, deadline, and status

### Requirement 9: Staff Management

**User Story:** As a Business_Owner, I want to manage staff members, so that I can control who has access to my business data.

#### Acceptance Criteria

1. WHEN a Business_Owner invites a staff member with a valid email, THE API_Server SHALL create a Staff_Member account
2. WHEN a Business_Owner invites a staff member, THE API_Server SHALL associate the staff member with their business
3. WHEN a Business_Owner requests their staff list, THE API_Server SHALL return all staff members associated with their business
4. WHEN a Business_Owner removes a staff member, THE API_Server SHALL revoke all Auth_Tokens for that staff member
5. WHEN a Business_Owner removes a staff member, THE API_Server SHALL maintain historical data created by that staff member
6. WHEN a Staff_Member logs in, THE API_Server SHALL return only the business they are assigned to

### Requirement 10: Data Synchronization

**User Story:** As a Mobile_Client, I want to synchronize offline data with the server, so that changes made without connectivity are persisted.

#### Acceptance Criteria

1. WHEN a sync request is received with offline-created records, THE API_Server SHALL create the records with the provided client-generated timestamps
2. WHEN a sync request is received with updated records, THE API_Server SHALL compare server timestamps with client timestamps
3. WHEN a Sync_Conflict is detected, THE API_Server SHALL return both versions and mark the conflict
4. WHEN a sync request includes a last_sync_timestamp, THE API_Server SHALL return all records modified after that timestamp
5. WHEN multiple records are synced in one request, THE API_Server SHALL process them within a database transaction
6. IF any record in a sync batch fails validation, THEN THE API_Server SHALL rollback all changes in that batch
7. WHEN a successful sync completes, THE API_Server SHALL return the latest server timestamp for future sync requests

### Requirement 11: Analytics and Reporting

**User Story:** As a Business_Owner, I want to retrieve analytical data, so that I can gain insights into business performance.

#### Acceptance Criteria

1. WHEN daily summary is requested, THE API_Server SHALL return total income, total expenses, and transaction count for today
2. WHEN weekly summary is requested, THE API_Server SHALL return aggregated data for the past 7 days
3. WHEN monthly summary is requested, THE API_Server SHALL return aggregated data for the current calendar month
4. WHEN yearly summary is requested, THE API_Server SHALL return aggregated data for the current calendar year
5. WHEN category breakdown is requested, THE API_Server SHALL return income and expense totals grouped by category
6. WHEN top products report is requested, THE API_Server SHALL return products ordered by total sales volume
7. WHEN stock value report is requested, THE API_Server SHALL calculate total inventory value based on current quantities and unit costs

### Requirement 12: Notification Management

**User Story:** As a Business_Owner or Staff_Member, I want to receive system notifications, so that I am informed of important events.

#### Acceptance Criteria

1. WHEN a low stock condition is detected, THE API_Server SHALL create a notification record
2. WHEN a goal deadline is approaching (within 7 days), THE API_Server SHALL create a notification record
3. WHEN a notification list is requested, THE API_Server SHALL return unread notifications first, then read notifications
4. WHEN a notification is marked as read, THE API_Server SHALL update the read status and timestamp
5. WHEN new notifications exist for a user, THE API_Server SHALL include an unread count in authenticated responses

### Requirement 13: Audit Trail

**User Story:** As a Business_Owner, I want to track all data modifications, so that I have accountability and can review changes.

#### Acceptance Criteria

1. WHEN any Resource is created, THE API_Server SHALL create an Audit_Log entry with action type "created"
2. WHEN any Resource is updated, THE API_Server SHALL create an Audit_Log entry with action type "updated"
3. WHEN any Resource is deleted, THE API_Server SHALL create an Audit_Log entry with action type "deleted"
4. THE Audit_Log SHALL include user ID, resource type, resource ID, action type, old values, new values, and timestamp
5. WHEN audit logs are requested for a resource, THE API_Server SHALL return all logs ordered by timestamp descending
6. THE API_Server SHALL never delete Audit_Log entries

### Requirement 14: Data Validation and Security

**User Story:** As the API_Server, I want to validate and sanitize all input data, so that I prevent security vulnerabilities and data corruption.

#### Acceptance Criteria

1. WHEN any request is received, THE API_Server SHALL validate the Content-Type header is application/json
2. WHEN any request body is received, THE API_Server SHALL validate it is valid JSON
3. WHEN string fields are received, THE API_Server SHALL sanitize them to prevent SQL injection
4. WHEN numeric fields are received, THE API_Server SHALL validate they are within acceptable ranges
5. WHEN date fields are received, THE API_Server SHALL validate they are in ISO 8601 format
6. WHEN file uploads are received for images, THE API_Server SHALL validate file type is jpeg or png and size is under 5MB
7. WHEN validation fails, THE API_Server SHALL return an HTTP 422 error with specific field error messages

### Requirement 15: API Response Standards

**User Story:** As a Mobile_Client, I want consistent API responses, so that I can reliably parse and handle data.

#### Acceptance Criteria

1. WHEN a successful operation completes, THE API_Server SHALL return HTTP 200 with a JSON response containing data
2. WHEN a resource is successfully created, THE API_Server SHALL return HTTP 201 with the created resource
3. WHEN a validation error occurs, THE API_Server SHALL return HTTP 422 with a JSON object containing field-specific errors
4. WHEN an authentication error occurs, THE API_Server SHALL return HTTP 401 with an error message
5. WHEN an authorization error occurs, THE API_Server SHALL return HTTP 403 with an error message
6. WHEN a requested resource is not found, THE API_Server SHALL return HTTP 404 with an error message
7. WHEN a server error occurs, THE API_Server SHALL return HTTP 500 and log the error details
8. THE API_Server SHALL include pagination metadata (total, per_page, current_page) for list endpoints
9. THE API_Server SHALL support pagination parameters (page, per_page) with defaults of page 1 and 15 items per page

### Requirement 16: Database Schema Implementation

**User Story:** As the Database, I want to store all application data with proper relationships and constraints, so that data integrity is maintained.

#### Acceptance Criteria

1. THE Database SHALL implement users table with columns: id, name, email, password, role, business_id, timestamps
2. THE Database SHALL implement businesses table with columns: id, owner_id, name, address, contact, currency, timestamps
3. THE Database SHALL implement transactions table with columns: id, business_id, user_id, type, category, amount, description, source, entry_method, transaction_date, ocr_data, timestamps, deleted_at
4. THE Database SHALL implement products table with columns: id, business_id, name, description, price, category, image_url, is_active, timestamps, deleted_at
5. THE Database SHALL implement product_ingredients table with columns: id, product_id, stock_item_id, quantity_required, timestamps
6. THE Database SHALL implement stock_items table with columns: id, business_id, name, unit, current_quantity, minimum_quantity, unit_cost, timestamps, deleted_at
7. THE Database SHALL implement stock_movements table with columns: id, stock_item_id, user_id, type, quantity, reason, timestamps
8. THE Database SHALL implement orders table with columns: id, business_id, user_id, order_number, customer_name, total_amount, status, order_date, timestamps
9. THE Database SHALL implement order_items table with columns: id, order_id, product_id, quantity, unit_price, subtotal, timestamps
10. THE Database SHALL implement goals table with columns: id, business_id, name, description, target_amount, current_amount, deadline, status, completed_at, timestamps
11. THE Database SHALL implement notifications table with columns: id, user_id, type, title, message, is_read, read_at, timestamps
12. THE Database SHALL implement activity_logs table with columns: id, user_id, business_id, resource_type, resource_id, action, old_values, new_values, timestamps
13. THE Database SHALL enforce foreign key constraints between related tables
14. THE Database SHALL create indexes on frequently queried columns: business_id, user_id, transaction_date, order_date

### Requirement 17: Performance and Scalability

**User Story:** As the API_Server, I want to handle requests efficiently, so that the Mobile_Client has a responsive experience.

#### Acceptance Criteria

1. WHEN any API endpoint is called, THE API_Server SHALL respond within 500 milliseconds for simple queries
2. WHEN list endpoints are called, THE API_Server SHALL use pagination to limit response size
3. WHEN complex analytics queries are requested, THE API_Server SHALL use database indexing to optimize performance
4. THE API_Server SHALL implement query result caching for frequently accessed, rarely changing data
5. WHEN multiple users access the system concurrently, THE API_Server SHALL handle at least 100 concurrent requests
6. THE API_Server SHALL use database connection pooling to optimize database access

### Requirement 18: Error Handling and Logging

**User Story:** As a system administrator, I want comprehensive error logging, so that I can diagnose and fix issues quickly.

#### Acceptance Criteria

1. WHEN any exception occurs, THE API_Server SHALL log the error with stack trace, request details, and timestamp
2. WHEN a validation error occurs, THE API_Server SHALL log the validation failures
3. WHEN an authentication failure occurs, THE API_Server SHALL log the attempt with user identifier
4. THE API_Server SHALL separate logs into channels: application errors, authentication events, database queries, API requests
5. THE API_Server SHALL rotate log files daily and retain logs for 30 days
6. WHEN critical errors occur, THE API_Server SHALL send notifications to system administrators

### Requirement 19: API Documentation and Versioning

**User Story:** As a Mobile_Client developer, I want clear API documentation, so that I can integrate with the backend correctly.

#### Acceptance Criteria

1. THE API_Server SHALL prefix all endpoints with /api/v1/ for version management
2. THE API_Server SHALL provide OpenAPI (Swagger) documentation for all endpoints
3. THE API_Server SHALL document required request headers, body structure, and response formats for each endpoint
4. THE API_Server SHALL document authentication requirements for each endpoint
5. THE API_Server SHALL provide example requests and responses in documentation

### Requirement 20: Data Backup and Recovery

**User Story:** As a Business_Owner, I want my data to be backed up regularly, so that I do not lose business records in case of system failure.

#### Acceptance Criteria

1. THE Database SHALL be backed up automatically every 24 hours
2. THE API_Server SHALL retain database backups for 30 days
3. WHEN a backup completes successfully, THE API_Server SHALL verify backup integrity
4. IF a backup fails, THEN THE API_Server SHALL send an alert to system administrators
5. THE API_Server SHALL provide a mechanism to restore data from backups upon authorized request

