# GastoTrack Database Schema

**Created**: September 27, 2026  
**Database**: MySQL/MariaDB  
**Framework**: Laravel 13.17

---

## Overview

This document describes the complete database schema for the GastoTrack owner web dashboard.

---

## Tables

### 1. **users**
User accounts for business owners and staff members.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint unsigned | Primary key |
| name | varchar(255) | User's full name |
| email | varchar(255) | Unique email address |
| phone | varchar(255) | Phone number (nullable) |
| role | enum('owner', 'staff') | User role |
| business_id | bigint unsigned | Foreign key to businesses |
| password | varchar(255) | Hashed password |
| email_verified_at | timestamp | Email verification timestamp |
| remember_token | varchar(100) | Remember me token |
| created_at | timestamp | Record creation timestamp |
| updated_at | timestamp | Record update timestamp |

**Indexes:**
- Primary key on `id`
- Unique index on `email`
- Foreign key on `business_id` → `businesses(id)`

---

### 2. **businesses**
Business information for milk tea shops, cafes, restaurants, etc.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint unsigned | Primary key |
| name | varchar(255) | Business name |
| address | text | Business address (nullable) |
| phone | varchar(255) | Contact phone (nullable) |
| email | varchar(255) | Contact email (nullable) |
| logo | varchar(255) | Logo file path (nullable) |
| business_type | enum | restaurant, cafe, retail, service, other |
| active | boolean | Is business active (default: true) |
| created_at | timestamp | Record creation timestamp |
| updated_at | timestamp | Record update timestamp |

---

### 3. **transactions**
Financial transactions (income and expenses).

| Column | Type | Description |
|--------|------|-------------|
| id | bigint unsigned | Primary key |
| business_id | bigint unsigned | Foreign key to businesses |
| user_id | bigint unsigned | Foreign key to users (who created it) |
| type | enum('income', 'expense') | Transaction type |
| category | varchar(255) | Sales, Delivery, Rent, Salaries, etc. |
| amount | decimal(10,2) | Transaction amount |
| description | text | Transaction description (nullable) |
| source | varchar(255) | Cash, GCash, Maya, Bank (nullable) |
| receipt_image | varchar(255) | Receipt photo path (nullable) |
| transaction_date | date | Date of transaction |
| created_at | timestamp | Record creation timestamp |
| updated_at | timestamp | Record update timestamp |

**Indexes:**
- Primary key on `id`
- Foreign keys on `business_id`, `user_id`
- Composite index on `(business_id, transaction_date)`
- Composite index on `(business_id, type)`

---

### 4. **goals**
Savings and financial goals for businesses.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint unsigned | Primary key |
| business_id | bigint unsigned | Foreign key to businesses |
| name | varchar(255) | Goal name |
| description | text | Goal description (nullable) |
| target_amount | decimal(10,2) | Target amount to reach |
| current_amount | decimal(10,2) | Current progress (default: 0) |
| deadline | date | Goal deadline (nullable) |
| status | enum | active, completed, cancelled |
| created_at | timestamp | Record creation timestamp |
| updated_at | timestamp | Record update timestamp |

**Indexes:**
- Primary key on `id`
- Foreign key on `business_id`
- Composite index on `(business_id, status)`

---

### 5. **stock_items**
Inventory items (ingredients, packaging, supplies).

| Column | Type | Description |
|--------|------|-------------|
| id | bigint unsigned | Primary key |
| business_id | bigint unsigned | Foreign key to businesses |
| name | varchar(255) | Item name (e.g., "Black Tea Leaves") |
| unit | varchar(255) | Unit of measurement (kg, liters, pieces) |
| current_quantity | decimal(10,2) | Current stock quantity (default: 0) |
| minimum_quantity | decimal(10,2) | Minimum threshold for alerts (default: 0) |
| unit_cost | decimal(10,2) | Cost per unit (default: 0) |
| active | boolean | Is item active (default: true) |
| created_at | timestamp | Record creation timestamp |
| updated_at | timestamp | Record update timestamp |

**Indexes:**
- Primary key on `id`
- Foreign key on `business_id`
- Composite index on `(business_id, active)`

---

### 6. **products**
Products/menu items sold by the business.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint unsigned | Primary key |
| business_id | bigint unsigned | Foreign key to businesses |
| name | varchar(255) | Product name |
| description | text | Product description (nullable) |
| category | varchar(255) | Product category (Milk Tea, Coffee, etc.) |
| price | decimal(10,2) | Selling price |
| image | varchar(255) | Product image path (nullable) |
| active | boolean | Is product available (default: true) |
| prep_time | integer | Preparation time in minutes (nullable) |
| created_at | timestamp | Record creation timestamp |
| updated_at | timestamp | Record update timestamp |

**Indexes:**
- Primary key on `id`
- Foreign key on `business_id`
- Composite index on `(business_id, category)`
- Composite index on `(business_id, active)`

---

### 7. **product_ingredients**
Pivot table linking products to their required ingredients.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint unsigned | Primary key |
| product_id | bigint unsigned | Foreign key to products |
| stock_item_id | bigint unsigned | Foreign key to stock_items |
| quantity | decimal(10,2) | Amount of ingredient needed per product |
| created_at | timestamp | Record creation timestamp |
| updated_at | timestamp | Record update timestamp |

**Indexes:**
- Primary key on `id`
- Foreign keys on `product_id`, `stock_item_id`
- Unique composite index on `(product_id, stock_item_id)`

---

### 8. **orders**
Customer orders placed through the staff app.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint unsigned | Primary key |
| business_id | bigint unsigned | Foreign key to businesses |
| user_id | bigint unsigned | Foreign key to users (staff who created) |
| customer_name | varchar(255) | Customer name (default: "Walk-in Customer") |
| customer_phone | varchar(255) | Customer phone (nullable) |
| payment_type | enum('cash', 'cashless') | Payment type |
| payment_method | enum | cash, gcash, maya, bank (nullable) |
| status | enum | pending, preparing, completed, cancelled |
| subtotal | decimal(10,2) | Order subtotal |
| total | decimal(10,2) | Order total |
| notes | text | Order notes (nullable) |
| completed_at | timestamp | When order was completed (nullable) |
| created_at | timestamp | Record creation timestamp |
| updated_at | timestamp | Record update timestamp |

**Indexes:**
- Primary key on `id`
- Foreign keys on `business_id`, `user_id`
- Composite index on `(business_id, status)`
- Composite index on `(business_id, created_at)`

---

### 9. **order_items**
Line items within an order.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint unsigned | Primary key |
| order_id | bigint unsigned | Foreign key to orders |
| product_id | bigint unsigned | Foreign key to products |
| product_name | varchar(255) | Product name at time of order |
| quantity | integer | Quantity ordered |
| price | decimal(10,2) | Price per item at time of order |
| subtotal | decimal(10,2) | Line item subtotal |
| created_at | timestamp | Record creation timestamp |
| updated_at | timestamp | Record update timestamp |

**Indexes:**
- Primary key on `id`
- Foreign keys on `order_id`, `product_id`
- Index on `order_id`

---

### 10. **stock_movements**
Audit trail of all stock quantity changes.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint unsigned | Primary key |
| stock_item_id | bigint unsigned | Foreign key to stock_items |
| user_id | bigint unsigned | Foreign key to users (who made change) |
| type | enum('in', 'out', 'adjustment') | Movement type |
| quantity | decimal(10,2) | Quantity changed (+ or -) |
| previous_quantity | decimal(10,2) | Quantity before change |
| new_quantity | decimal(10,2) | Quantity after change |
| reason | varchar(255) | Reason for movement (nullable) |
| order_id | bigint unsigned | Related order if applicable (nullable) |
| created_at | timestamp | Record creation timestamp |
| updated_at | timestamp | Record update timestamp |

**Indexes:**
- Primary key on `id`
- Foreign keys on `stock_item_id`, `user_id`, `order_id`
- Index on `stock_item_id`
- Composite index on `(stock_item_id, created_at)`

---

## Relationships

### User Relationships
- **belongsTo** Business
- **hasMany** Transactions
- **hasMany** Orders
- **hasMany** StockMovements

### Business Relationships
- **hasMany** Users (owners and staff)
- **hasMany** Transactions
- **hasMany** Goals
- **hasMany** StockItems
- **hasMany** Products
- **hasMany** Orders

### Transaction Relationships
- **belongsTo** Business
- **belongsTo** User (creator)

### Goal Relationships
- **belongsTo** Business

### StockItem Relationships
- **belongsTo** Business
- **belongsToMany** Products (through product_ingredients)
- **hasMany** StockMovements

### Product Relationships
- **belongsTo** Business
- **belongsToMany** StockItems (through product_ingredients)
- **hasMany** OrderItems

### Order Relationships
- **belongsTo** Business
- **belongsTo** User (staff creator)
- **hasMany** OrderItems
- **hasMany** StockMovements

### OrderItem Relationships
- **belongsTo** Order
- **belongsTo** Product

### StockMovement Relationships
- **belongsTo** StockItem
- **belongsTo** User (who made the change)
- **belongsTo** Order (if movement was due to an order)

---

## Sample Data

The database has been seeded with:

### Business
- **GastoTrack Milk Tea Shop**
  - Address: 123 Main St, Manila, Philippines
  - Phone: +63 917 123 4567
  - Type: Cafe

### Users
- **Owner**: owner@gastotrack.com (password: password)
- **Staff 1**: staff1@gastotrack.com (password: password)
- **Staff 2**: staff2@gastotrack.com (password: password)

### Stock Items (6 items)
1. Black Tea Leaves (50 kg)
2. Fresh Milk (30 liters)
3. Brown Sugar (25 kg)
4. Matcha Powder (15 kg)
5. Tapioca Pearls (20 kg)
6. Plastic Cups Large (500 pieces)

### Products (5 items)
1. Brown Sugar Milk Tea (₱85)
2. Matcha Latte (₱95)
3. Classic Milk Tea (₱75)
4. Wintermelon Milk Tea (₱80)
5. Thai Milk Tea (₱85)

All products have ingredients linked with appropriate quantities.

---

## Migrations

All migrations are located in `database/migrations/` and were run successfully on September 27, 2026.

**Migration Files:**
1. `2026_09_27_133742_add_business_fields_to_users_table.php`
2. `2026_09_27_133812_create_businesses_table.php`
3. `2026_09_27_133914_create_transactions_table.php`
4. `2026_09_27_133922_create_goals_table.php`
5. `2026_09_27_133931_create_stock_items_table.php`
6. `2026_09_27_133939_create_products_table.php`
7. `2026_09_27_134005_create_product_ingredients_table.php`
8. `2026_09_27_134010_create_orders_table.php`
9. `2026_09_27_134017_create_order_items_table.php`
10. `2026_09_27_134023_create_stock_movements_table.php`

---

## Notes

- All monetary values use `decimal(10,2)` for precision
- All tables use soft timestamps (`created_at`, `updated_at`)
- Foreign key constraints are enforced with appropriate `onDelete` actions
- Indexes are strategically placed for common queries
- Enum fields provide data integrity for predefined values
- The schema supports multi-business scenarios (though current focus is single business)

---

## Next Steps

1. Build Livewire components for CRUD operations
2. Create owner layouts (sidebar, mobile nav)
3. Implement dashboard with financial calculations
4. Add transaction management UI
5. Build analytics and reporting features
