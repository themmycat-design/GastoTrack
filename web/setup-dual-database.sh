#!/bin/bash

echo "========================================="
echo "GastoTrack Dual Database Setup"
echo "========================================="
echo ""

# Create MySQL database
echo "1. Creating MySQL database 'gastotrack'..."
mysql -u root -e "CREATE DATABASE IF NOT EXISTS gastotrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
if [ $? -eq 0 ]; then
    echo "   ✓ MySQL database created"
else
    echo "   ⚠ MySQL may not be running or accessible"
fi

# Create SQLite database for staff offline
echo ""
echo "2. Creating SQLite databases..."
touch database/database.sqlite
touch database/staff_offline.sqlite
echo "   ✓ SQLite databases created"

# Run migrations on MySQL (for Owner/Admin)
echo ""
echo "3. Running migrations on MySQL..."
php artisan migrate --database=mysql --force
echo "   ✓ MySQL migrations completed"

# Run migrations on SQLite (for Staff)
echo ""
echo "4. Running migrations on SQLite (staff_offline)..."
php artisan migrate --database=staff_offline --force
echo "   ✓ SQLite migrations completed"

# Seed MySQL database
echo ""
echo "5. Seeding MySQL database..."
php artisan db:seed --database=mysql --force
echo "   ✓ MySQL seeding completed"

# Sync data from MySQL to SQLite for staff
echo ""
echo "6. Syncing data to staff offline database..."
php artisan staff:sync pull
echo "   ✓ Data synced to staff offline database"

echo ""
echo "========================================="
echo "Setup completed successfully!"
echo "========================================="
echo ""
echo "Database Configuration:"
echo "  - Owner/Admin: MySQL (gastotrack)"
echo "  - Staff Offline: SQLite (staff_offline.sqlite)"
echo "  - Staff Online Sync: MySQL (gastotrack)"
echo ""
echo "Test Credentials:"
echo "  Owner:  owner@gastotrack.com / password"
echo "  Staff1: staff1@gastotrack.com / password"
echo "  Staff2: staff2@gastotrack.com / password"
echo ""
