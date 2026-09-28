# GastoTrack Dual Database Architecture

## Overview

GastoTrack uses a **dual database system** to support both online and offline operations for different user roles.

## Database Configuration

### 1. **MySQL** (Online Database)
- **Used by**: Owner, Admin, Super Admin
- **Purpose**: Primary online database
- **Location**: `mysql://127.0.0.1:3306/gastotrack`
- **Features**:
  - Real-time data access
  - Multi-user support
  - Data synchronization hub
  - Complete business analytics

### 2. **SQLite** (Offline Database)
- **Used by**: Staff users
- **Purpose**: Offline-first operations
- **Location**: `database/staff_offline.sqlite`
- **Features**:
  - Works without internet connection
  - Fast local operations
  - Syncs back to MySQL when online
  - POS and order management

## How It Works

### Staff User Workflow

```
┌─────────────────────────────────────────────────┐
│                 Staff Login                     │
└───────────────┬─────────────────────────────────┘
                │
                ▼
        Is Internet Available?
                │
        ┌───────┴───────┐
        │               │
       YES             NO
        │               │
        ▼               ▼
   Use MySQL      Use SQLite
   (Online)       (Offline)
        │               │
        │               │
        └───────┬───────┘
                │
                ▼
         Background Sync
         (when online)
                │
                ▼
         MySQL Database
    (Central data repository)
```

### Data Flow

1. **Initial Setup (Pull)**
   ```bash
   php artisan staff:sync pull
   ```
   - Downloads products, stock, and business data from MySQL
   - Populates staff offline SQLite database
   - Staff can now work offline

2. **Offline Operations (Staff)**
   - Create orders → Saved to SQLite
   - Process transactions → Saved to SQLite
   - All marked as `synced = false`

3. **Online Sync (Push)**
   ```bash
   php artisan staff:sync push
   ```
   - Uploads unsynced orders to MySQL
   - Uploads unsynced transactions to MySQL
   - Marks records as `synced = true`

4. **Owner/Admin Operations**
   - Always uses MySQL directly
   - Real-time access to all data
   - Can see synced staff transactions immediately

## API Usage

### For Staff Users

**Offline Mode (Default)**
```http
GET /api/products
Authorization: Bearer {token}
```
→ Reads from SQLite

**Online Sync Mode**
```http
GET /api/products
Authorization: Bearer {token}
X-Sync-Mode: online
```
→ Reads from MySQL

**Alternative Query Parameter**
```http
GET /api/products?sync=online
Authorization: Bearer {token}
```
→ Reads from MySQL

### For Owner/Admin Users

```http
GET /api/products
Authorization: Bearer {token}
```
→ Always reads from MySQL (no header needed)

## Setup Instructions

### Windows (PowerShell)

```powershell
cd web
.\setup-dual-database.ps1
```

### Linux/Mac (Bash)

```bash
cd web
chmod +x setup-dual-database.sh
./setup-dual-database.sh
```

### Manual Setup

1. **Create MySQL database**
   ```sql
   CREATE DATABASE gastotrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Create SQLite files**
   ```bash
   touch database/database.sqlite
   touch database/staff_offline.sqlite
   ```

3. **Run migrations on both databases**
   ```bash
   php artisan migrate --database=mysql
   php artisan migrate --database=staff_offline
   ```

4. **Seed MySQL database**
   ```bash
   php artisan db:seed --database=mysql
   ```

5. **Sync to staff offline**
   ```bash
   php artisan staff:sync pull
   ```

## Sync Commands

### Pull Data (MySQL → SQLite)
```bash
php artisan staff:sync pull
```
Synchronizes:
- Businesses
- Products
- Stock Items
- Product Ingredients

### Push Data (SQLite → MySQL)
```bash
php artisan staff:sync push
```
Synchronizes:
- Orders (unsynced only)
- Transactions (unsynced only)

## Middleware

The `SetDatabaseConnection` middleware automatically:
- Detects user role from auth token
- Sets appropriate database connection
- Handles sync mode headers for staff
- Ensures owner/admin always use MySQL

## Mobile App Integration

### React Native (Staff App)

```javascript
// Check network status
import NetInfo from '@react-native-community/netinfo';

const isOnline = await NetInfo.fetch().then(state => state.isConnected);

// API call with sync header
const headers = {
  'Authorization': `Bearer ${token}`,
  'X-Sync-Mode': isOnline ? 'online' : 'offline'
};
```

### Automatic Background Sync

```javascript
// In a background task or when app comes online
if (isOnline) {
  // Push local data to server
  await api.post('/api/sync/push');
  
  // Pull latest data
  await api.post('/api/sync/pull');
}
```

## Database Schema

Both databases share the same schema:

- `businesses` - Business information
- `users` - User accounts (owner, staff)
- `products` - Menu items
- `stock_items` - Inventory items
- `product_ingredients` - Recipe mapping
- `orders` - Customer orders (`synced` flag)
- `order_items` - Order line items
- `transactions` - Financial records (`synced` flag)
- `goals` - Business goals
- `stock_movements` - Inventory tracking

## Conflict Resolution

### Strategy: Last Write Wins

- MySQL is the source of truth
- Staff offline changes are pushed with timestamp
- Owner changes take precedence
- Future enhancement: conflict detection UI

## Performance Considerations

### SQLite (Staff)
- ✅ Fast local reads/writes
- ✅ No network latency
- ✅ Works offline
- ⚠️ Single user per device

### MySQL (Owner/Admin)
- ✅ Multi-user support
- ✅ ACID compliance
- ✅ Advanced queries
- ⚠️ Requires internet connection

## Security

- ✅ SQLite files stored locally on device (encrypted storage recommended)
- ✅ MySQL uses authentication
- ✅ API tokens (Sanctum) for all requests
- ✅ Role-based access control (RBAC)

## Troubleshooting

### Staff can't sync data

```bash
# Check if MySQL is running
mysql -u root -e "SELECT 1;"

# Check connection in .env
cat .env | grep DB_

# Manually run sync
php artisan staff:sync push --verbose
```

### Owner sees old data

```bash
# Ensure default connection is MySQL
php artisan tinker
>>> config('database.default');
=> "mysql"
```

### SQLite file missing

```bash
# Recreate SQLite databases
touch database/database.sqlite
touch database/staff_offline.sqlite

# Run migrations
php artisan migrate --database=staff_offline
```

## Future Enhancements

1. ✨ Automatic conflict detection
2. ✨ Real-time sync via WebSockets
3. ✨ Incremental sync (delta only)
4. ✨ Sync history and audit log
5. ✨ Multi-branch support (chain businesses)

---

## Quick Reference

| User Role    | Default DB | Can Switch? | Sync Required? |
|-------------|-----------|-------------|----------------|
| Staff       | SQLite    | Yes (header)| Yes           |
| Owner       | MySQL     | No          | No            |
| Admin       | MySQL     | No          | No            |
| Super Admin | MySQL     | No          | No            |

