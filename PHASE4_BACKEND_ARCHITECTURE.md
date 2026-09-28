# Phase 4 - Task 3: Backend Development Architecture
**Status**: 📋 **PLANNED** (Architecture designed, ready to implement)
**Date**: September 27, 2026

---

## 🎯 Backend Overview

**Technology Stack:**
- **Framework**: Laravel 11.x (PHP 8.2+)
- **Database**: MySQL 8.0
- **Authentication**: Laravel Sanctum (API tokens)
- **API**: RESTful JSON API
- **File Storage**: Laravel Storage (local/S3)
- **Queue**: Redis (for background jobs)
- **Cache**: Redis

---

## 🗄️ Database Schema Design

### **1. Users Table**
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('owner', 'staff', 'admin') DEFAULT 'staff',
    business_id BIGINT UNSIGNED,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_role (role),
    INDEX idx_business (business_id),
    INDEX idx_status (status)
);
```

### **2. Businesses Table**
```sql
CREATE TABLE businesses (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    owner_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    location VARCHAR(255),
    city VARCHAR(100),
    province VARCHAR(100) DEFAULT 'Pangasinan',
    phone VARCHAR(20),
    email VARCHAR(255),
    logo_url VARCHAR(500),
    settings JSON,
    status ENUM('active', 'suspended', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_owner (owner_id),
    INDEX idx_status (status)
);
```

### **3. Transactions Table**
```sql
CREATE TABLE transactions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    source VARCHAR(100) NOT NULL,
    category VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    notes TEXT,
    entry_method ENUM('manual', 'notification_capture', 'receipt_scan', 'order_system') DEFAULT 'manual',
    recorded_by BIGINT UNSIGNED,
    receipt_url VARCHAR(500),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_business_date (business_id, date),
    INDEX idx_type (type),
    INDEX idx_category (category),
    INDEX idx_source (source)
);
```

### **4. Products Table**
```sql
CREATE TABLE products (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    cost DECIMAL(10, 2),
    emoji VARCHAR(10),
    image_url VARCHAR(500),
    is_available BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_category (business_id, category),
    INDEX idx_available (is_available)
);
```

### **5. Product Ingredients Table** (Junction)
```sql
CREATE TABLE product_ingredients (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    stock_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (stock_id) REFERENCES stock_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_stock (product_id, stock_id)
);
```

### **6. Stock Items Table**
```sql
CREATE TABLE stock_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 0,
    unit VARCHAR(20) NOT NULL,
    threshold DECIMAL(10, 2) NOT NULL,
    cost_per_unit DECIMAL(10, 2),
    supplier VARCHAR(255),
    last_restocked_at TIMESTAMP NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business (business_id),
    INDEX idx_quantity (quantity)
);
```

### **7. Stock Movements Table** (History)
```sql
CREATE TABLE stock_movements (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    stock_id BIGINT UNSIGNED NOT NULL,
    business_id BIGINT UNSIGNED NOT NULL,
    type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    quantity_before DECIMAL(10, 2) NOT NULL,
    quantity_after DECIMAL(10, 2) NOT NULL,
    reason VARCHAR(255),
    order_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (stock_id) REFERENCES stock_items(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_stock_date (stock_id, created_at),
    INDEX idx_business (business_id)
);
```

### **8. Orders Table**
```sql
CREATE TABLE orders (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20),
    notes TEXT,
    subtotal DECIMAL(10, 2) NOT NULL,
    discount DECIMAL(10, 2) DEFAULT 0,
    total DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    status ENUM('pending', 'preparing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
    created_by BIGINT UNSIGNED NOT NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancel_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    prepared_at TIMESTAMP NULL,
    ready_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_business_status (business_id, status),
    INDEX idx_order_number (order_number),
    INDEX idx_created_at (created_at)
);
```

### **9. Order Items Table**
```sql
CREATE TABLE order_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_order (order_id)
);
```

### **10. Goals Table**
```sql
CREATE TABLE goals (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('savings', 'spending_limit') NOT NULL,
    period ENUM('weekly', 'monthly', 'yearly') NOT NULL,
    target DECIMAL(12, 2) NOT NULL,
    current DECIMAL(12, 2) DEFAULT 0,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_business_active (business_id, is_active),
    INDEX idx_period (period)
);
```

### **11. Notifications Table**
```sql
CREATE TABLE notifications (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    business_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_created_at (created_at)
);
```

### **12. Activity Logs Table**
```sql
CREATE TABLE activity_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    business_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    model_type VARCHAR(100),
    model_id BIGINT UNSIGNED,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_user_action (user_id, action),
    INDEX idx_model (model_type, model_id),
    INDEX idx_created_at (created_at)
);
```

---

## 🔐 API Endpoints

### **Authentication**
```
POST   /api/register               - Register new owner/business
POST   /api/login                  - Login (email/phone + password)
POST   /api/logout                 - Logout (revoke token)
GET    /api/user                   - Get authenticated user
POST   /api/refresh-token          - Refresh auth token
POST   /api/forgot-password        - Request password reset
POST   /api/reset-password         - Reset password with token
```

### **Business Management**
```
GET    /api/businesses             - List user's businesses
GET    /api/businesses/{id}        - Get business details
POST   /api/businesses             - Create new business
PUT    /api/businesses/{id}        - Update business
DELETE /api/businesses/{id}        - Delete business
GET    /api/businesses/{id}/stats  - Get business statistics
```

### **Transactions**
```
GET    /api/transactions           - List transactions (filterable)
GET    /api/transactions/{id}      - Get transaction details
POST   /api/transactions           - Create transaction
PUT    /api/transactions/{id}      - Update transaction
DELETE /api/transactions/{id}      - Delete transaction
GET    /api/transactions/summary   - Get summary (income/expense/balance)
GET    /api/transactions/export    - Export transactions (CSV/Excel)
```

### **Products**
```
GET    /api/products               - List products (filterable by category)
GET    /api/products/{id}          - Get product details
POST   /api/products               - Create product
PUT    /api/products/{id}          - Update product
DELETE /api/products/{id}          - Delete product
PUT    /api/products/{id}/toggle   - Toggle availability
POST   /api/products/bulk-update   - Bulk update prices/availability
```

### **Stock Management**
```
GET    /api/stock                  - List stock items
GET    /api/stock/{id}             - Get stock details
POST   /api/stock                  - Create stock item
PUT    /api/stock/{id}             - Update stock item
DELETE /api/stock/{id}             - Delete stock item
POST   /api/stock/{id}/adjust      - Adjust stock quantity
GET    /api/stock/alerts           - Get low stock alerts
GET    /api/stock/{id}/movements   - Get stock movement history
```

### **Orders**
```
GET    /api/orders                 - List orders (filterable by status/date)
GET    /api/orders/{id}            - Get order details
POST   /api/orders                 - Create order
PUT    /api/orders/{id}/status     - Update order status
PUT    /api/orders/{id}            - Update order details
DELETE /api/orders/{id}            - Cancel order
GET    /api/orders/stats           - Get order statistics
```

### **Goals**
```
GET    /api/goals                  - List goals
GET    /api/goals/{id}             - Get goal details
POST   /api/goals                  - Create goal
PUT    /api/goals/{id}             - Update goal
DELETE /api/goals/{id}             - Delete goal
PUT    /api/goals/{id}/progress    - Update goal progress
```

### **Staff Management** (Owner only)
```
GET    /api/staff                  - List staff members
POST   /api/staff                  - Invite staff member
PUT    /api/staff/{id}             - Update staff info
DELETE /api/staff/{id}             - Remove staff
POST   /api/staff/{id}/permissions - Update staff permissions
```

### **Notifications**
```
GET    /api/notifications          - List notifications
PUT    /api/notifications/{id}/read - Mark as read
PUT    /api/notifications/read-all  - Mark all as read
DELETE /api/notifications/{id}      - Delete notification
```

### **Analytics & Reports**
```
GET    /api/analytics/dashboard    - Dashboard summary
GET    /api/analytics/sales        - Sales analytics (daily/weekly/monthly)
GET    /api/analytics/expenses     - Expense breakdown
GET    /api/analytics/profit       - Profit & loss report
GET    /api/analytics/products     - Top selling products
GET    /api/analytics/trends       - Sales trends & predictions
```

---

## 🔒 Authentication Flow

### **1. Registration**
```
Client → POST /api/register {name, email, password, phone, business_name}
       ← {user, business, token}
```

### **2. Login**
```
Client → POST /api/login {email, password}
       ← {user, businesses[], token}
```

### **3. Authenticated Requests**
```
Client → GET /api/transactions
Headers: Authorization: Bearer {token}
       ← {transactions[]}
```

### **4. Token Refresh**
```
Client → POST /api/refresh-token
Headers: Authorization: Bearer {old_token}
       ← {token: new_token}
```

---

## 📱 React Native Integration

### **API Service Layer**
```javascript
// src/services/api/ApiClient.js
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

const API_BASE_URL = 'https://your-domain.com/api';

class ApiClient {
  constructor() {
    this.client = axios.create({
      baseURL: API_BASE_URL,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });

    // Add auth token to requests
    this.client.interceptors.request.use(async (config) => {
      const token = await AsyncStorage.getItem('auth_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
      return config;
    });

    // Handle token expiration
    this.client.interceptors.response.use(
      response => response,
      async error => {
        if (error.response?.status === 401) {
          await AsyncStorage.removeItem('auth_token');
          // Navigate to login
        }
        return Promise.reject(error);
      }
    );
  }

  // Auth
  async register(data) {
    return this.client.post('/register', data);
  }

  async login(email, password) {
    return this.client.post('/login', { email, password });
  }

  async logout() {
    return this.client.post('/logout');
  }

  // Transactions
  async getTransactions(params) {
    return this.client.get('/transactions', { params });
  }

  async createTransaction(data) {
    return this.client.post('/transactions', data);
  }

  async updateTransaction(id, data) {
    return this.client.put(`/transactions/${id}`, data);
  }

  async deleteTransaction(id) {
    return this.client.delete(`/transactions/${id}`);
  }

  // Products, Stock, Orders... (similar pattern)
}

export default new ApiClient();
```

---

## 🔄 Offline-First Architecture

### **Strategy: Local-First with Sync**

```javascript
// src/services/sync/SyncService.js

class SyncService {
  constructor() {
    this.syncQueue = [];
    this.isSyncing = false;
  }

  // Add action to sync queue
  async queueAction(action) {
    await AsyncStorage.setItem(
      `sync_${Date.now()}`,
      JSON.stringify(action)
    );
    this.sync();
  }

  // Sync with backend
  async sync() {
    if (this.isSyncing || !isOnline()) return;
    
    this.isSyncing = true;
    const pendingActions = await this.getPendingActions();
    
    for (const action of pendingActions) {
      try {
        await this.executeAction(action);
        await this.removeAction(action.id);
      } catch (error) {
        console.log('Sync failed:', error);
        break; // Stop on first failure
      }
    }
    
    this.isSyncing = false;
  }

  // Pull latest data from server
  async pullData() {
    const lastSync = await AsyncStorage.getItem('last_sync');
    const data = await ApiClient.get('/sync/pull', {
      params: { since: lastSync }
    });
    
    // Update local data
    await this.updateLocalData(data);
    await AsyncStorage.setItem('last_sync', new Date().toISOString());
  }
}
```

---

## 📂 Laravel Project Structure

```
gastotrack-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── API/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── TransactionController.php
│   │   │   │   ├── ProductController.php
│   │   │   │   ├── StockController.php
│   │   │   │   ├── OrderController.php
│   │   │   │   └── GoalController.php
│   │   ├── Middleware/
│   │   │   ├── CheckBusinessAccess.php
│   │   │   └── CheckOwnerRole.php
│   │   └── Requests/
│   │       ├── StoreTransactionRequest.php
│   │       └── UpdateProductRequest.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Business.php
│   │   ├── Transaction.php
│   │   ├── Product.php
│   │   ├── StockItem.php
│   │   ├── Order.php
│   │   └── Goal.php
│   ├── Services/
│   │   ├── TransactionService.php
│   │   ├── StockService.php
│   │   └── OrderService.php
│   └── Events/
│       ├── OrderCompleted.php
│       └── StockLowAlert.php
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── routes/
│   └── api.php
├── config/
├── tests/
└── .env
```

---

## 🚀 Implementation Steps

### **Phase 1: Setup** (Day 1)
1. Install Laravel: `composer create-project laravel/laravel gastotrack-backend`
2. Configure database in `.env`
3. Install Sanctum: `composer require laravel/sanctum`
4. Set up CORS for React Native

### **Phase 2: Database** (Day 1-2)
1. Create all migrations
2. Create model classes
3. Define relationships
4. Create seeders for testing

### **Phase 3: Authentication** (Day 2)
1. Implement registration
2. Implement login
3. Implement token management
4. Test with Postman

### **Phase 4: Core APIs** (Day 3-4)
1. Transactions CRUD
2. Products CRUD
3. Stock CRUD
4. Orders CRUD
5. Goals CRUD

### **Phase 5: Business Logic** (Day 5)
1. Stock deduction on order
2. Transaction creation on order
3. Low stock alerts
4. Goal progress calculation

### **Phase 6: React Native Integration** (Day 6-7)
1. Create API client service
2. Update contexts to use API
3. Implement offline sync
4. Test end-to-end

---

## 📝 Next Steps

1. **Get API key for Gemini** (if not done)
2. **Test E-wallet capture** (Task 1)
3. **Test AI features** (Task 2)
4. **Set up Laravel backend** (Task 3 - this document)
5. **Deploy to production**

---

**End of Backend Architecture Document**
