# GastoTrack Dual Database Setup Script (PowerShell)

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "GastoTrack Dual Database Setup" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

# Create MySQL database
Write-Host "1. Creating MySQL database 'gastotrack'..." -ForegroundColor Yellow
$result = mysql -u root -e "CREATE DATABASE IF NOT EXISTS gastotrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "   ✓ MySQL database created" -ForegroundColor Green
} else {
    Write-Host "   ⚠ MySQL not found or error occurred" -ForegroundColor Red
    Write-Host "   Continuing with SQLite only..." -ForegroundColor Yellow
}

# Create SQLite databases
Write-Host ""
Write-Host "2. Creating SQLite databases..." -ForegroundColor Yellow
New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
New-Item -ItemType File -Path "database\staff_offline.sqlite" -Force | Out-Null
Write-Host "   ✓ SQLite databases created" -ForegroundColor Green

# Run migrations on MySQL (for Owner/Admin)
Write-Host ""
Write-Host "3. Running migrations on MySQL..." -ForegroundColor Yellow
php artisan migrate --database=mysql --force
if ($LASTEXITCODE -eq 0) {
    Write-Host "   ✓ MySQL migrations completed" -ForegroundColor Green
} else {
    Write-Host "   ⚠ MySQL migrations failed" -ForegroundColor Red
}

# Run migrations on SQLite (for Staff)
Write-Host ""
Write-Host "4. Running migrations on SQLite (staff_offline)..." -ForegroundColor Yellow
php artisan migrate --database=staff_offline --force
Write-Host "   ✓ SQLite migrations completed" -ForegroundColor Green

# Seed MySQL database
Write-Host ""
Write-Host "5. Seeding MySQL database..." -ForegroundColor Yellow
php artisan db:seed --database=mysql --force
if ($LASTEXITCODE -eq 0) {
    Write-Host "   ✓ MySQL seeding completed" -ForegroundColor Green
} else {
    Write-Host "   ⚠ MySQL seeding failed" -ForegroundColor Red
}

# Sync data from MySQL to SQLite for staff
Write-Host ""
Write-Host "6. Syncing data to staff offline database..." -ForegroundColor Yellow
php artisan staff:sync pull
if ($LASTEXITCODE -eq 0) {
    Write-Host "   ✓ Data synced to staff offline database" -ForegroundColor Green
} else {
    Write-Host "   ⚠ Sync failed" -ForegroundColor Red
}

Write-Host ""
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "Setup completed!" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Database Configuration:" -ForegroundColor White
Write-Host "  - Owner/Admin: MySQL (gastotrack)" -ForegroundColor Gray
Write-Host "  - Staff Offline: SQLite (staff_offline.sqlite)" -ForegroundColor Gray
Write-Host "  - Staff Online Sync: MySQL (gastotrack)" -ForegroundColor Gray
Write-Host ""
Write-Host "Test Credentials:" -ForegroundColor White
Write-Host "  Owner:  owner@gastotrack.com / password" -ForegroundColor Gray
Write-Host "  Staff1: staff1@gastotrack.com / password" -ForegroundColor Gray
Write-Host "  Staff2: staff2@gastotrack.com / password" -ForegroundColor Gray
Write-Host ""
