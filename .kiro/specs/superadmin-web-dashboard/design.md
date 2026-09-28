# Design Document

## Overview

The Super Admin Web Dashboard is a responsive Laravel-based platform management application built with Laravel Blade templates and Livewire components. This dashboard provides Super Admins with complete oversight of the entire GastoTrack platform, including all businesses, users, system settings, analytics, and platform health monitoring.

The Super Admin dashboard is designed with a distinct visual identity (purple theme) to differentiate it from the Business Owner dashboard (green theme). It supports full responsive design from mobile phones to desktop displays, enabling Super Admins to monitor and manage the platform from any device.

The application shares the same Laravel codebase with the Owner dashboard and API, using separate route groups, middleware, and layouts to maintain complete separation of concerns while maximizing code reusability.

### Key Design Principles

1. **Platform-Wide Oversight**: Super Admins see aggregated data across all businesses
2. **Multi-Tenant Architecture**: Manage multiple independent business accounts
3. **Security-First**: Elevated privileges with additional authentication layers
4. **Audit Everything**: Complete logging of all Super Admin actions
5. **Responsive Design**: Mobile-first approach for on-the-go management
6. **Performance**: Handle large datasets with pagination and caching
7. **Distinct Identity**: Purple theme distinguishes admin panel from owner interface

### Technology Stack

- **Backend Framework**: Laravel 10+ (PHP 8.1+)
- **Templating**: Laravel Blade
- **Reactive Components**: Livewire 3.x
- **Frontend JavaScript**: Alpine.js 3.x (minimal)
- **CSS Framework**: Tailwind CSS 3.x
- **Charts**: Livewire Charts (Chart.js wrapper)
- **Authentication**: Laravel Fortify with optional 2FA
- **Database**: MySQL 8.0+ (shared schema)
- **Caching**: Redis for performance
- **Queue**: Laravel Queue for async operations
- **Asset Build**: Laravel Vite

---

## Architecture

### High-Level System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Client Devices                           │
│  ┌──────────┐    ┌──────────┐    ┌──────────────────┐     │
│  │  Mobile  │    │  Tablet  │    │  Desktop/Laptop  │     │
│  │ 320-767px│    │768-1023px│    │    1024px+       │     │
│  └──────────┘    └──────────┘    └──────────────────┘     │
└───────────────────────┬─────────────────────────────────────┘
                        │ HTTPS
                        ↓
┌─────────────────────────────────────────────────────────────┐
│              Laravel Application Server                     │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │      Super Admin Routes (routes/web.php)            │  │
│  │  /admin/login, /admin/dashboard, /admin/businesses │  │
│  │  Prefix: /admin/*                                   │  │
│  └───────────────────┬─────────────────────────────────┘  │
│                      │                                     │
│  ┌───────────────────▼─────────────────────────────────┐  │
│  │     Super Admin Middleware Stack                     │  │
│  │  auth:admin, role:super_admin, 2fa (optional)       │  │
│  └───────────────────┬─────────────────────────────────┘  │
│                      │                                     │
│  ┌───────────────────▼─────────────────────────────────┐  │
│  │    Super Admin Controllers & Livewire Components     │  │
│  │  AdminDashboardController, BusinessTable,           │  │
│  │  UserTable, PlatformAnalytics, SystemSettings       │  │
│  └───────────────────┬─────────────────────────────────┘  │
│                      │                                     │
│  ┌───────────────────▼─────────────────────────────────┐  │
│  │         Shared Eloquent Models                       │  │
│  │  User, Business, Transaction, ActivityLog, etc.     │  │
│  └───────────────────┬─────────────────────────────────┘  │
│                      │                                     │
│  ┌───────────────────▼─────────────────────────────────┐  │
│  │              MySQL Database                          │  │
│  │  Shared schema with Owner & API                     │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │    Owner Routes & API Routes (separate)              │  │
│  └─────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

### Project Directory Structure

```
gastotrack-laravel/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/                    ← Super Admin controllers
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── BusinessController.php
│   │   │   │   ├── UserController.php
│   │   │   │   ├── AnalyticsController.php
│   │   │   │   ├── SettingsController.php
│   │   │   │   ├── AuditLogController.php
│   │   │   │   └── ImpersonationController.php
│   │   │   ├── Web/                      ← Owner controllers
│   │   │   └── Api/                      ← API controllers
│   │   ├── Livewire/
│   │   │   ├── Admin/                    ← Super Admin components
│   │   │   │   ├── Dashboard/
│   │   │   │   │   ├── PlatformMetrics.php
│   │   │   │   │   ├── RecentActivities.php
│   │   │   │   │   └── HealthStatus.php
│   │   │   │   ├── Businesses/
│   │   │   │   │   ├── BusinessTable.php
│   │   │   │   │   ├── BusinessDetail.php
│   │   │   │   │   └── BusinessFilters.php
│   │   │   │   ├── Users/
│   │   │   │   │   ├── UserTable.php
│   │   │   │   │   ├── UserDetail.php
│   │   │   │   │   └── ResetPassword.php
│   │   │   │   ├── Analytics/
│   │   │   │   │   ├── PlatformCharts.php
│   │   │   │   │   └── RevenueAnalytics.php
│   │   │   │   ├── Settings/
│   │   │   │   │   ├── GeneralSettings.php
│   │   │   │   │   ├── CategoryManager.php
│   │   │   │   │   └── EmailTemplateEditor.php
│   │   │   │   └── AuditLogs/
│   │   │   │       └── AuditLogTable.php
│   │   │   └── Owner/                    ← Owner components
│   │   └── Middleware/
│   │       ├── EnsureSuperAdmin.php
│   │       ├── LogAdminActivity.php
│   │       └── CheckImpersonation.php
│   ├── Models/                           ← Shared models
│   │   ├── User.php
│   │   ├── Business.php
│   │   ├── Transaction.php
│   │   ├── ActivityLog.php
│   │   ├── SystemSetting.php
│   │   └── ...
│   └── Services/
│       ├── ImpersonationService.php
│       ├── PlatformAnalyticsService.php
│       └── AuditLogService.php
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── admin.blade.php           ← Super Admin layout
│   │   │   ├── owner.blade.php           ← Owner layout
│   │   │   └── guest.blade.php
│   │   ├── components/
│   │   │   ├── admin/                    ← Admin-specific components
│   │   │   │   ├── sidebar.blade.php
│   │   │   │   ├── header.blade.php
│   │   │   │   └── impersonation-banner.blade.php
│   │   │   └── shared/                   ← Shared UI components
│   │   ├── admin/                        ← Super Admin pages
│   │   │   ├── dashboard.blade.php
│   │   │   ├── businesses.blade.php
│   │   │   ├── users.blade.php
│   │   │   ├── analytics.blade.php
│   │   │   ├── settings.blade.php
│   │   │   └── audit-logs.blade.php
│   │   ├── owner/                        ← Owner pages
│   │   └── livewire/
│   │       └── admin/                    ← Livewire views
│   ├── css/
│   │   ├── admin.css                     ← Admin-specific styles
│   │   └── app.css
│   └── js/
│       └── app.js
├── routes/
│   ├── web.php                           ← All web routes
│   └── api.php
├── database/
│   └── migrations/
│       └── 2024_xx_xx_create_system_settings_table.php
└── config/
    └── admin.php                         ← Admin configuration
```

---

## Components and Interfaces

### 1. Layouts

#### Admin Layout (layouts/admin.blade.php)

Base layout for all Super Admin pages with purple theme.

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Super Admin' }} - GastoTrack</title>
    
    @vite(['resources/css/admin.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50 antialiased">
    <!-- Impersonation Banner (if active) -->
    @if(session('impersonating'))
        <x-admin.impersonation-banner />
    @endif
    
    <div class="min-h-screen">
        <!-- Mobile: Bottom Navigation -->
        <div class="lg:hidden">
            <x-admin.header />
            <main class="pb-20">
                {{ $slot }}
            </main>
            <x-admin.bottom-nav />
        </div>
        
        <!-- Desktop: Sidebar Navigation -->
        <div class="hidden lg:flex">
            <x-admin.sidebar />
            <main class="flex-1 ml-64">
                <x-admin.header />
                <div class="p-6">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
    
    @livewireScripts
    <x-toast />
</body>
</html>
```

#### Impersonation Banner Component

```blade
<!-- resources/views/components/admin/impersonation-banner.blade.php -->
<div class="fixed top-0 left-0 right-0 bg-yellow-500 text-white px-4 py-3 z-50">
    <div class="container mx-auto flex items-center justify-between">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5"><!-- Warning Icon --></svg>
            <span class="font-medium">
                You are impersonating: <strong>{{ session('impersonated_user_name') }}</strong>
            </span>
        </div>
        <form action="{{ route('admin.impersonation.stop') }}" method="POST">
            @csrf
            <button type="submit" 
                    class="px-4 py-1 bg-white text-yellow-600 rounded hover:bg-gray-100 font-medium">
                Exit Impersonation
            </button>
        </form>
    </div>
</div>
```

### 2. Navigation Components

#### Admin Sidebar (components/admin/sidebar.blade.php)

Desktop/tablet sidebar with purple theme.

```blade
<aside class="fixed inset-y-0 left-0 w-64 bg-white border-r border-gray-200 z-40">
    <!-- Logo -->
    <div class="flex items-center h-16 px-6 border-b bg-indigo-600">
        <h1 class="text-xl font-bold text-white">GastoTrack Admin</h1>
    </div>
    
    <!-- Navigation Links -->
    <nav class="flex-1 px-4 py-6 space-y-1">
        <a href="{{ route('admin.dashboard') }}" 
           class="flex items-center px-4 py-3 text-sm font-medium rounded-lg
                  {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
            <svg class="w-5 h-5 mr-3"><!-- Dashboard Icon --></svg>
            Dashboard
        </a>
        
        <a href="{{ route('admin.businesses') }}" 
           class="flex items-center px-4 py-3 text-sm font-medium rounded-lg
                  {{ request()->routeIs('admin.businesses*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
            <svg class="w-5 h-5 mr-3"><!-- Business Icon --></svg>
            Businesses
        </a>
        
        <a href="{{ route('admin.users') }}" 
           class="flex items-center px-4 py-3 text-sm font-medium rounded-lg
                  {{ request()->routeIs('admin.users*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
            <svg class="w-5 h-5 mr-3"><!-- Users Icon --></svg>
            Users
        </a>
        
        <a href="{{ route('admin.analytics') }}" 
           class="flex items-center px-4 py-3 text-sm font-medium rounded-lg
                  {{ request()->routeIs('admin.analytics') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
            <svg class="w-5 h-5 mr-3"><!-- Analytics Icon --></svg>
            Analytics
        </a>
        
        <a href="{{ route('admin.audit-logs') }}" 
           class="flex items-center px-4 py-3 text-sm font-medium rounded-lg
                  {{ request()->routeIs('admin.audit-logs') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
            <svg class="w-5 h-5 mr-3"><!-- Logs Icon --></svg>
            Audit Logs
        </a>
        
        <a href="{{ route('admin.settings') }}" 
           class="flex items-center px-4 py-3 text-sm font-medium rounded-lg
                  {{ request()->routeIs('admin.settings*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
            <svg class="w-5 h-5 mr-3"><!-- Settings Icon --></svg>
            Settings
        </a>
    </nav>
    
    <!-- Admin Profile & Logout -->
    <div class="p-4 border-t">
        <div class="flex items-center mb-2">
            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold">
                {{ substr(auth()->guard('admin')->user()->name, 0, 1) }}
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium">{{ auth()->guard('admin')->user()->name }}</p>
                <p class="text-xs text-gray-500">Super Admin</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg">
                Logout
            </button>
        </form>
    </div>
</aside>
```

#### Admin Bottom Navigation (components/admin/bottom-nav.blade.php)

Mobile navigation with purple theme.

```blade
<nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 lg:hidden">
    <div class="flex justify-around">
        <a href="{{ route('admin.dashboard') }}" 
           class="flex flex-col items-center py-2 px-3 text-xs
                  {{ request()->routeIs('admin.dashboard') ? 'text-indigo-600' : 'text-gray-600' }}">
            <svg class="w-6 h-6 mb-1"><!-- Dashboard Icon --></svg>
            <span>Dashboard</span>
        </a>
        
        <a href="{{ route('admin.businesses') }}" 
           class="flex flex-col items-center py-2 px-3 text-xs
                  {{ request()->routeIs('admin.businesses*') ? 'text-indigo-600' : 'text-gray-600' }}">
            <svg class="w-6 h-6 mb-1"><!-- Business Icon --></svg>
            <span>Businesses</span>
        </a>
        
        <a href="{{ route('admin.users') }}" 
           class="flex flex-col items-center py-2 px-3 text-xs
                  {{ request()->routeIs('admin.users*') ? 'text-indigo-600' : 'text-gray-600' }}">
            <svg class="w-6 h-6 mb-1"><!-- Users Icon --></svg>
            <span>Users</span>
        </a>
        
        <a href="{{ route('admin.analytics') }}" 
           class="flex flex-col items-center py-2 px-3 text-xs
                  {{ request()->routeIs('admin.analytics') ? 'text-indigo-600' : 'text-gray-600' }}">
            <svg class="w-6 h-6 mb-1"><!-- Analytics Icon --></svg>
            <span>Analytics</span>
        </a>
        
        <a href="{{ route('admin.settings') }}" 
           class="flex flex-col items-center py-2 px-3 text-xs
                  {{ request()->routeIs('admin.settings*') ? 'text-indigo-600' : 'text-gray-600' }}">
            <svg class="w-6 h-6 mb-1"><!-- Settings Icon --></svg>
            <span>Settings</span>
        </a>
    </div>
</nav>
```

---

## Data Models

### System Setting Model (New)

```php
<?php
// app/Models/SystemSetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type', // string, boolean, json, etc.
        'group', // general, categories, email, security
    ];

    protected $casts = [
        'value' => 'json',
    ];

    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function set($key, $value, $group = 'general')
    {
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );
    }
}
```

### Migration

```php
<?php
// database/migrations/2024_xx_xx_create_system_settings_table.php

public function up()
{
    Schema::create('system_settings', function (Blueprint $table) {
        $table->id();
        $table->string('key')->unique();
        $table->json('value')->nullable();
        $table->string('type')->default('string');
        $table->string('group')->default('general');
        $table->timestamps();
    });
}
```

---

## Livewire Components

### Dashboard Components

#### Platform Metrics Component

```php
<?php
// app/Http/Livewire/Admin/Dashboard/PlatformMetrics.php

namespace App\Http\Livewire\Admin\Dashboard;

use Livewire\Component;
use App\Models\Business;
use App\Models\User;
use App\Models\Transaction;
use Carbon\Carbon;

class PlatformMetrics extends Component
{
    public $totalBusinesses;
    public $totalUsers;
    public $totalRevenue;
    public $activeBusinesses;
    public $newBusinessesThisMonth;

    public function mount()
    {
        $this->loadMetrics();
    }

    public function loadMetrics()
    {
        // Cache for 5 minutes
        $this->totalBusinesses = cache()->remember('admin.metrics.total_businesses', 300, function () {
            return Business::count();
        });

        $this->totalUsers = cache()->remember('admin.metrics.total_users', 300, function () {
            return User::count();
        });

        $this->totalRevenue = cache()->remember('admin.metrics.total_revenue', 300, function () {
            return Transaction::where('type', 'income')->sum('amount');
        });

        $this->activeBusinesses = cache()->remember('admin.metrics.active_businesses', 300, function () {
            return Business::whereHas('users', function ($query) {
                $query->where('last_login_at', '>=', Carbon::now()->subDays(30));
            })->count();
        });

        $this->newBusinessesThisMonth = Business::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();
    }

    public function refreshMetrics()
    {
        cache()->forget('admin.metrics.total_businesses');
        cache()->forget('admin.metrics.total_users');
        cache()->forget('admin.metrics.total_revenue');
        cache()->forget('admin.metrics.active_businesses');
        
        $this->loadMetrics();
        
        $this->dispatchBrowserEvent('toast', [
            'type' => 'success',
            'message' => 'Metrics refreshed'
        ]);
    }

    public function render()
    {
        return view('livewire.admin.dashboard.platform-metrics');
    }
}
```

```blade
<!-- resources/views/livewire/admin/dashboard/platform-metrics.blade.php -->
<div>
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Platform Metrics</h2>
        <button wire:click="refreshMetrics" 
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
            <svg wire:loading wire:target="refreshMetrics" class="inline w-4 h-4 animate-spin">
                <!-- Loading spinner -->
            </svg>
            <span wire:loading.remove wire:target="refreshMetrics">Refresh</span>
        </button>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Businesses -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Businesses</p>
                    <p class="mt-2 text-3xl font-bold text-indigo-600">
                        {{ number_format($totalBusinesses) }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        +{{ $newBusinessesThisMonth }} this month
                    </p>
                </div>
                <div class="p-3 bg-indigo-50 rounded-full">
                    <svg class="w-8 h-8 text-indigo-600"><!-- Icon --></svg>
                </div>
            </div>
        </div>

        <!-- Total Users -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Users</p>
                    <p class="mt-2 text-3xl font-bold text-blue-600">
                        {{ number_format($totalUsers) }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">Owners & Staff</p>
                </div>
                <div class="p-3 bg-blue-50 rounded-full">
                    <svg class="w-8 h-8 text-blue-600"><!-- Icon --></svg>
                </div>
            </div>
        </div>

        <!-- Total Revenue -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                    <p class="mt-2 text-3xl font-bold text-green-600">
                        ₱{{ number_format($totalRevenue, 2) }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">Platform-wide</p>
                </div>
                <div class="p-3 bg-green-50 rounded-full">
                    <svg class="w-8 h-8 text-green-600"><!-- Icon --></svg>
                </div>
            </div>
        </div>

        <!-- Active Businesses -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Active Businesses</p>
                    <p class="mt-2 text-3xl font-bold text-purple-600">
                        {{ number_format($activeBusinesses) }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">Last 30 days</p>
                </div>
                <div class="p-3 bg-purple-50 rounded-full">
                    <svg class="w-8 h-8 text-purple-600"><!-- Icon --></svg>
                </div>
            </div>
        </div>
    </div>
</div>
```

---

### Business Management Components

#### Business Table Component

```php
<?php
// app/Http/Livewire/Admin/Businesses/BusinessTable.php

namespace App\Http\Livewire\Admin\Businesses;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Business;

class BusinessTable extends Component
{
    use WithPagination;

    public $search = '';
    public $status = 'all'; // all, active, inactive
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    public $selectedBusinesses = [];
    public $selectAll = false;

    protected $queryString = ['search', 'status'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedBusinesses = Business::pluck('id')->toArray();
        } else {
            $this->selectedBusinesses = [];
        }
    }

    public function deactivateBusiness($id)
    {
        $business = Business::findOrFail($id);
        $business->update(['status' => 'inactive']);
        
        activity()
            ->causedBy(auth()->guard('admin')->user())
            ->performedOn($business)
            ->log('Deactivated business');
        
        $this->dispatchBrowserEvent('toast', [
            'type' => 'success',
            'message' => 'Business deactivated successfully'
        ]);
    }

    public function activateBusiness($id)
    {
        $business = Business::findOrFail($id);
        $business->update(['status' => 'active']);
        
        activity()
            ->causedBy(auth()->guard('admin')->user())
            ->performedOn($business)
            ->log('Activated business');
        
        $this->dispatchBrowserEvent('toast', [
            'type' => 'success',
            'message' => 'Business activated successfully'
        ]);
    }

    public function bulkDeactivate()
    {
        Business::whereIn('id', $this->selectedBusinesses)
            ->update(['status' => 'inactive']);
        
        $this->selectedBusinesses = [];
        
        $this->dispatchBrowserEvent('toast', [
            'type' => 'success',
            'message' => 'Selected businesses deactivated'
        ]);
    }

    public function render()
    {
        $query = Business::with('owner');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhereHas('owner', function ($q) {
                      $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        $businesses = $query
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(20);

        return view('livewire.admin.businesses.business-table', [
            'businesses' => $businesses,
        ]);
    }
}
```

---

## Routing

```php
<?php
// routes/web.php

use App\Http\Controllers\Admin\{
    DashboardController,
    BusinessController,
    UserController,
    AnalyticsController,
    SettingsController,
    AuditLogController,
    ImpersonationController
};

// Super Admin Routes
Route::prefix('admin')->name('admin.')->group(function () {
    // Guest routes
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login']);
    });

    // Authenticated admin routes
    Route::middleware(['auth:admin', 'role:super_admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/businesses', [BusinessController::class, 'index'])->name('businesses');
        Route::get('/businesses/{business}', [BusinessController::class, 'show'])->name('businesses.show');
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        
        // Impersonation
        Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonation.start');
        Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonation.stop');
        
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });
});
```

---

## Services

### Impersonation Service

```php
<?php
// app/Services/ImpersonationService.php

namespace App\Services;

use App\Models\User;
use App\Models\ActivityLog;

class ImpersonationService
{
    public function start($admin, $targetUser)
    {
        // Prevent impersonating other admins
        if ($targetUser->role === 'super_admin') {
            throw new \Exception('Cannot impersonate other super admins');
        }

        // Store impersonation data in session
        session([
            'impersonating' => true,
            'impersonated_user_id' => $targetUser->id,
            'impersonated_user_name' => $targetUser->name,
            'impersonator_id' => $admin->id,
        ]);

        // Log the impersonation
        ActivityLog::create([
            'user_id' => $admin->id,
            'business_id' => $targetUser->business_id,
            'action' => 'impersonation_started',
            'resource_type' => 'User',
            'resource_id' => $targetUser->id,
            'new_values' => [
                'impersonated_user' => $targetUser->email,
                'impersonator' => $admin->email,
            ],
        ]);

        // Log out admin and log in as target user
        auth()->guard('admin')->logout();
        auth()->login($targetUser);
    }

    public function stop()
    {
        $impersonatorId = session('impersonator_id');
        $impersonatedUserId = session('impersonated_user_id');

        // Log the end of impersonation
        ActivityLog::create([
            'user_id' => $impersonatorId,
            'action' => 'impersonation_stopped',
            'resource_type' => 'User',
            'resource_id' => $impersonatedUserId,
        ]);

        // Log out user and log back in as admin
        auth()->logout();
        $admin = User::findOrFail($impersonatorId);
        auth()->guard('admin')->login($admin);

        // Clear impersonation session data
        session()->forget(['impersonating', 'impersonated_user_id', 'impersonated_user_name', 'impersonator_id']);
    }
}
```

---

## Security

### Middleware

```php
<?php
// app/Http/Middleware/EnsureSuperAdmin.php

namespace App\Http\Middleware;

use Closure;

class EnsureSuperAdmin
{
    public function handle($request, Closure $next)
    {
        if (!auth()->guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        if (auth()->guard('admin')->user()->role !== 'super_admin') {
            abort(403, 'Unauthorized access to admin panel');
        }

        return $next($request);
    }
}
```

### Activity Logging Middleware

```php
<?php
// app/Http/Middleware/LogAdminActivity.php

namespace App\Http\Middleware;

use Closure;
use App\Models\ActivityLog;

class LogAdminActivity
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Log admin actions (except GET requests)
        if ($request->method() !== 'GET' && auth()->guard('admin')->check()) {
            ActivityLog::create([
                'user_id' => auth()->guard('admin')->id(),
                'action' => $request->method() . ' ' . $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $response;
    }
}
```

---

## Testing Strategy

**Unit Tests:**
- Test impersonation service logic
- Test platform metrics calculations
- Test system settings CRUD

**Feature Tests:**
- Test admin authentication
- Test business management actions
- Test user management
- Test audit log creation

**Browser Tests (Dusk):**
- Test complete admin workflows
- Test responsive behavior
- Test impersonation flow

---

## Performance Optimization

1. **Caching**: Cache platform metrics for 5 minutes
2. **Eager Loading**: Load relationships to prevent N+1 queries
3. **Pagination**: All lists paginated at 20 items
4. **Indexing**: Database indexes on frequently queried columns
5. **Lazy Loading**: Livewire lazy loading for heavy components

---

## Deployment

**Additional Configuration:**
- Set up separate admin guard in `config/auth.php`
- Configure Redis for caching
- Set up queue worker for async jobs
- Enable 2FA for Super Admin accounts (optional)
- Configure log retention (30 days minimum)

---

## Summary

The Super Admin dashboard provides comprehensive platform management with a distinct purple theme, complete audit trail, user impersonation, and responsive design. It shares the Laravel codebase with the Owner dashboard while maintaining complete separation through routes, middleware, and layouts.
