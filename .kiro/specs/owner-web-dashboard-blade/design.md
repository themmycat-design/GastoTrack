# Design Document

## Overview

The Business Owner Web Dashboard is a responsive Laravel-based web application that provides comprehensive business management capabilities accessible from mobile phones, tablets, and desktop computers. Built with Laravel Blade templates and Livewire for reactive components, the dashboard enables business owners to manage transactions, inventory, products, analytics, and goals without needing page reloads.

The application follows a mobile-first responsive design approach, ensuring optimal user experience across all device sizes (320px to 4K displays). It integrates seamlessly with the existing Laravel API backend (from backend-api-integration spec) and shares the same database schema, providing a unified data layer for both the web dashboard and React Native mobile app.

### Key Design Principles

1. **Mobile-First Responsive**: Design for smallest screens first, progressively enhance for larger displays
2. **Server-Side Rendering**: Fast initial page loads with Blade templates
3. **Reactive Updates**: Livewire provides SPA-like experience without writing JavaScript
4. **Component Reusability**: Modular Livewire components shared across pages
5. **Touch-Optimized**: 44px minimum touch targets, swipe gestures, large buttons
6. **Performance**: Lazy loading, pagination, caching for fast interactions
7. **Accessibility**: WCAG 2.1 AA compliant, semantic HTML, keyboard navigation

### Technology Stack

- **Backend Framework**: Laravel 10+ (PHP 8.1+)
- **Templating**: Laravel Blade
- **Reactive Components**: Livewire 3.x
- **Frontend JavaScript**: Alpine.js 3.x (minimal, for transitions/dropdowns)
- **CSS Framework**: Tailwind CSS 3.x (utility-first, responsive)
- **Charts**: Livewire Charts (based on Chart.js)
- **Authentication**: Laravel Breeze/Fortify
- **Database**: MySQL 8.0+ (shared with API)
- **Caching**: Redis (optional for session/query caching)
- **Asset Build**: Laravel Vite (fast HMR, optimized production builds)

---

## Architecture

### High-Level System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Client Devices                          │
│  ┌──────────┐    ┌──────────┐    ┌──────────────────┐    │
│  │  Mobile  │    │  Tablet  │    │  Desktop/Laptop  │    │
│  │ 320-767px│    │768-1023px│    │    1024px+       │    │
│  └──────────┘    └──────────┘    └──────────────────┘    │
└───────────────────────┬─────────────────────────────────────┘
                        │ HTTPS
                        ↓
┌─────────────────────────────────────────────────────────────┐
│              Laravel Application Server                     │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │           Web Routes (routes/web.php)               │  │
│  │  /login, /dashboard, /transactions, /analytics, ... │  │
│  └───────────────────┬─────────────────────────────────┘  │
│                      │                                     │
│  ┌───────────────────▼─────────────────────────────────┐  │
│  │         Middleware Stack                             │  │
│  │  auth, verified, role:owner                          │  │
│  └───────────────────┬─────────────────────────────────┘  │
│                      │                                     │
│  ┌───────────────────▼─────────────────────────────────┐  │
│  │         Controllers & Livewire Components            │  │
│  │  DashboardController, TransactionTable,             │  │
│  │  AnalyticsChart, StockTable, ProductGrid            │  │
│  └───────────────────┬─────────────────────────────────┘  │
│                      │                                     │
│  ┌───────────────────▼─────────────────────────────────┐  │
│  │         Eloquent Models (app/Models/)                │  │
│  │  User, Business, Transaction, Product, Stock, Goal  │  │
│  └───────────────────┬─────────────────────────────────┘  │
│                      │                                     │
│  ┌───────────────────▼─────────────────────────────────┐  │
│  │              MySQL Database                          │  │
│  │  Shared schema with API (backend-api-integration)   │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │           API Routes (routes/api.php)                │  │
│  │  /api/v1/* for React Native mobile app              │  │
│  └─────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

### Project Directory Structure

```
gastotrack-laravel/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Web/
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── TransactionController.php
│   │   │   │   ├── AnalyticsController.php
│   │   │   │   ├── GoalController.php
│   │   │   │   ├── StockController.php
│   │   │   │   ├── ProductController.php
│   │   │   │   └── ProfileController.php
│   │   │   └── Api/           ← API controllers (backend-api-integration)
│   │   │       └── V1/
│   │   ├── Livewire/
│   │   │   ├── Owner/
│   │   │   │   ├── Dashboard/
│   │   │   │   │   ├── FinancialCards.php
│   │   │   │   │   ├── SavingsGoal.php
│   │   │   │   │   └── RecentTransactions.php
│   │   │   │   ├── Transactions/
│   │   │   │   │   ├── TransactionTable.php
│   │   │   │   │   ├── TransactionForm.php
│   │   │   │   │   └── TransactionFilters.php
│   │   │   │   ├── Analytics/
│   │   │   │   │   ├── AnalyticsChart.php
│   │   │   │   │   └── PeriodSelector.php
│   │   │   │   ├── Calendar/
│   │   │   │   │   └── CalendarView.php
│   │   │   │   ├── Goals/
│   │   │   │   │   ├── GoalsList.php
│   │   │   │   │   └── GoalForm.php
│   │   │   │   ├── Stock/
│   │   │   │   │   ├── StockTable.php
│   │   │   │   │   └── StockForm.php
│   │   │   │   └── Products/
│   │   │   │       ├── ProductGrid.php
│   │   │   │       └── ProductForm.php
│   │   │   └── Components/      ← Shared components
│   │   │       ├── Modal.php
│   │   │       ├── Toast.php
│   │   │       └── ConfirmDialog.php
│   │   └── Middleware/
│   │       ├── EnsureUserIsOwner.php
│   │       └── EnsureBusinessActive.php
│   ├── Models/                  ← Shared with API
│   │   ├── User.php
│   │   ├── Business.php
│   │   ├── Transaction.php
│   │   ├── Product.php
│   │   ├── ProductIngredient.php
│   │   ├── StockItem.php
│   │   ├── StockMovement.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   ├── Goal.php
│   │   └── ActivityLog.php
│   └── View/
│       └── Components/          ← Blade components
│           ├── Layout/
│           │   ├── AppLayout.php
│           │   ├── Sidebar.php
│           │   └── BottomNav.php
│           └── UI/
│               ├── Card.php
│               ├── Button.php
│               └── Badge.php
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── app.blade.php              ← Base layout
│   │   │   ├── owner.blade.php            ← Owner layout with nav
│   │   │   └── guest.blade.php            ← Login layout
│   │   ├── components/
│   │   │   ├── layout/
│   │   │   │   ├── sidebar.blade.php
│   │   │   │   ├── bottom-nav.blade.php
│   │   │   │   └── header.blade.php
│   │   │   └── ui/
│   │   │       ├── card.blade.php
│   │   │       ├── button.blade.php
│   │   │       └── badge.blade.php
│   │   ├── owner/
│   │   │   ├── dashboard.blade.php
│   │   │   ├── transactions.blade.php
│   │   │   ├── analytics.blade.php
│   │   │   ├── calendar.blade.php
│   │   │   ├── goals.blade.php
│   │   │   ├── stock.blade.php
│   │   │   ├── products.blade.php
│   │   │   └── profile.blade.php
│   │   ├── livewire/
│   │   │   └── owner/          ← Livewire component views
│   │   └── auth/
│   │       ├── login.blade.php
│   │       └── register.blade.php
│   ├── css/
│   │   └── app.css             ← Tailwind directives
│   └── js/
│       └── app.js              ← Alpine.js setup
├── routes/
│   ├── web.php                 ← Web routes (Blade pages)
│   └── api.php                 ← API routes (mobile app)
├── database/
│   └── migrations/             ← Shared migrations (backend-api-integration)
├── config/
│   ├── livewire.php
│   └── fortify.php
├── tailwind.config.js
├── vite.config.js
└── composer.json
```

---

## Components and Interfaces

### 1. Layouts

#### App Layout (layouts/app.blade.php)

Base HTML structure with responsive meta tags and asset includes.

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'GastoTrack' }}</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50 antialiased">
    {{ $slot }}
    
    @livewireScripts
    <x-toast />
</body>
</html>
```

#### Owner Layout (layouts/owner.blade.php)

Main layout with responsive navigation (sidebar + bottom bar).

```blade
<x-app-layout>
    <div class="min-h-screen">
        <!-- Mobile: Bottom Navigation -->
        <div class="lg:hidden">
            <x-layout.header />
            <main class="pb-20">
                {{ $slot }}
            </main>
            <x-layout.bottom-nav />
        </div>
        
        <!-- Desktop: Sidebar Navigation -->
        <div class="hidden lg:flex">
            <x-layout.sidebar />
            <main class="flex-1 ml-64">
                <x-layout.header />
                <div class="p-6">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</x-app-layout>
```

### 2. Navigation Components

#### Sidebar Component (components/layout/sidebar.blade.php)

Desktop/tablet sidebar with navigation links.

```blade
<aside class="fixed inset-y-0 left-0 w-64 bg-white border-r border-gray-200 z-40">
    <!-- Logo -->
    <div class="flex items-center h-16 px-6 border-b">
        <h1 class="text-xl font-bold text-primary-600">GastoTrack</h1>
    </div>
    
    <!-- Navigation Links -->
    <nav class="flex-1 px-4 py-6 space-y-1">
        <a href="{{ route('dashboard') }}" 
           class="flex items-center px-4 py-3 text-sm font-medium rounded-lg
                  {{ request()->routeIs('dashboard') ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:bg-gray-50' }}">
            <svg class="w-5 h-5 mr-3"><!-- Dashboard Icon --></svg>
            Dashboard
        </a>
        
        <a href="{{ route('transactions') }}" 
           class="flex items-center px-4 py-3 text-sm font-medium rounded-lg
                  {{ request()->routeIs('transactions*') ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:bg-gray-50' }}">
            <svg class="w-5 h-5 mr-3"><!-- Transaction Icon --></svg>
            Transactions
        </a>
        
        <!-- More nav items... -->
    </nav>
    
    <!-- User Profile & Logout -->
    <div class="p-4 border-t">
        <div class="flex items-center mb-2">
            <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
                {{ substr(auth()->user()->name, 0, 1) }}
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium">{{ auth()->user()->name }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg">
                Logout
            </button>
        </form>
    </div>
</aside>
```

#### Bottom Navigation (components/layout/bottom-nav.blade.php)

Mobile bottom navigation bar.

```blade
<nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 lg:hidden">
    <div class="flex justify-around">
        <a href="{{ route('dashboard') }}" 
           class="flex flex-col items-center py-2 px-3 text-xs
                  {{ request()->routeIs('dashboard') ? 'text-primary-600' : 'text-gray-600' }}">
            <svg class="w-6 h-6 mb-1"><!-- Dashboard Icon --></svg>
            <span>Dashboard</span>
        </a>
        
        <a href="{{ route('transactions') }}" 
           class="flex flex-col items-center py-2 px-3 text-xs
                  {{ request()->routeIs('transactions*') ? 'text-primary-600' : 'text-gray-600' }}">
            <svg class="w-6 h-6 mb-1"><!-- Transaction Icon --></svg>
            <span>Transactions</span>
        </a>
        
        <a href="{{ route('analytics') }}" 
           class="flex flex-col items-center py-2 px-3 text-xs
                  {{ request()->routeIs('analytics') ? 'text-primary-600' : 'text-gray-600' }}">
            <svg class="w-6 h-6 mb-1"><!-- Analytics Icon --></svg>
            <span>Analytics</span>
        </a>
        
        <a href="{{ route('stock') }}" 
           class="flex flex-col items-center py-2 px-3 text-xs
                  {{ request()->routeIs('stock') ? 'text-primary-600' : 'text-gray-600' }}">
            <svg class="w-6 h-6 mb-1"><!-- Stock Icon --></svg>
            <span>Stock</span>
        </a>
        
        <a href="{{ route('profile') }}" 
           class="flex flex-col items-center py-2 px-3 text-xs
                  {{ request()->routeIs('profile') ? 'text-primary-600' : 'text-gray-600' }}">
            <svg class="w-6 h-6 mb-1"><!-- Profile Icon --></svg>
            <span>Profile</span>
        </a>
    </div>
</nav>
```

---

## Data Models

All models are shared with the API (from backend-api-integration spec). No changes needed to existing schema.

### Key Models Used by Web Dashboard

**User, Business, Transaction, Product, ProductIngredient, StockItem, StockMovement, Order, OrderItem, Goal, ActivityLog**

All model relationships and methods are defined in the backend-api-integration design document.

---

## Livewire Components

### Dashboard Components

#### Financial Cards Component

```php
<?php
// app/Http/Livewire/Owner/Dashboard/FinancialCards.php

namespace App\Http\Livewire\Owner\Dashboard;

use Livewire\Component;
use App\Models\Transaction;
use Carbon\Carbon;

class FinancialCards extends Component
{
    public $period = 'daily'; // daily, weekly, monthly, yearly
    public $balance;
    public $income;
    public $expenses;

    public function mount()
    {
        $this->calculateFinancials();
    }

    public function updatedPeriod()
    {
        $this->calculateFinancials();
    }

    private function calculateFinancials()
    {
        $business = auth()->user()->business;
        $dateRange = $this->getDateRange();

        $this->income = Transaction::where('business_id', $business->id)
            ->where('type', 'income')
            ->whereBetween('transaction_date', $dateRange)
            ->sum('amount');

        $this->expenses = Transaction::where('business_id', $business->id)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', $dateRange)
            ->sum('amount');

        $this->balance = $this->income - $this->expenses;
    }

    private function getDateRange()
    {
        return match($this->period) {
            'daily' => [Carbon::today(), Carbon::today()->endOfDay()],
            'weekly' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'monthly' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'yearly' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
        };
    }

    public function render()
    {
        return view('livewire.owner.dashboard.financial-cards');
    }
}
```

```blade
<!-- resources/views/livewire/owner/dashboard/financial-cards.blade.php -->
<div>
    <!-- Period Selector -->
    <div class="flex gap-2 mb-4 overflow-x-auto">
        <button wire:click="$set('period', 'daily')" 
                class="px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap
                       @if($period === 'daily') bg-primary-600 text-white @else bg-gray-100 text-gray-700 @endif">
            Daily
        </button>
        <button wire:click="$set('period', 'weekly')" 
                class="px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap
                       @if($period === 'weekly') bg-primary-600 text-white @else bg-gray-100 text-gray-700 @endif">
            Weekly
        </button>
        <button wire:click="$set('period', 'monthly')" 
                class="px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap
                       @if($period === 'monthly') bg-primary-600 text-white @else bg-gray-100 text-gray-700 @endif">
            Monthly
        </button>
        <button wire:click="$set('period', 'yearly')" 
                class="px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap
                       @if($period === 'yearly') bg-primary-600 text-white @else bg-gray-100 text-gray-700 @endif">
            Yearly
        </button>
    </div>

    <!-- Financial Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4" wire:loading.class="opacity-50">
        <!-- Total Balance Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-600">Total Balance</h3>
                <div class="p-2 bg-primary-50 rounded-full">
                    <svg class="w-6 h-6 text-primary-600"><!-- Icon --></svg>
                </div>
            </div>
            <p class="mt-4 text-2xl font-bold text-gray-900">
                ₱{{ number_format($balance, 2) }}
            </p>
        </div>

        <!-- Total Income Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-600">Total Income</h3>
                <div class="p-2 bg-green-50 rounded-full">
                    <svg class="w-6 h-6 text-green-600"><!-- Icon --></svg>
                </div>
            </div>
            <p class="mt-4 text-2xl font-bold text-green-600">
                ₱{{ number_format($income, 2) }}
            </p>
        </div>

        <!-- Total Expenses Card -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-600">Total Expenses</h3>
                <div class="p-2 bg-red-50 rounded-full">
                    <svg class="w-6 h-6 text-red-600"><!-- Icon --></svg>
                </div>
            </div>
            <p class="mt-4 text-2xl font-bold text-red-600">
                ₱{{ number_format($expenses, 2) }}
            </p>
        </div>
    </div>
</div>
```

---

### Transaction Components

#### Transaction Table Component

```php
<?php
// app/Http/Livewire/Owner/Transactions/TransactionTable.php

namespace App\Http\Livewire\Owner\Transactions;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Transaction;
use Carbon\Carbon;

class TransactionTable extends Component
{
    use WithPagination;

    public $search = '';
    public $type = 'all'; // all, income, expense
    public $startDate;
    public $endDate;
    public $sortField = 'transaction_date';
    public $sortDirection = 'desc';

    protected $queryString = ['search', 'type'];

    public function mount()
    {
        $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
    }

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

    public function deleteTransaction($id)
    {
        $transaction = Transaction::findOrFail($id);
        
        // Check authorization
        if ($transaction->business_id !== auth()->user()->business_id) {
            abort(403);
        }

        $transaction->delete();
        
        $this->dispatchBrowserEvent('toast', [
            'type' => 'success',
            'message' => 'Transaction deleted successfully'
        ]);
    }

    public function render()
    {
        $query = Transaction::where('business_id', auth()->user()->business_id);

        if ($this->search) {
            $query->where('description', 'like', '%' . $this->search . '%');
        }

        if ($this->type !== 'all') {
            $query->where('type', $this->type);
        }

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('transaction_date', [$this->startDate, $this->endDate]);
        }

        $transactions = $query
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(20);

        return view('livewire.owner.transactions.transaction-table', [
            'transactions' => $transactions,
        ]);
    }
}
```

```blade
<!-- resources/views/livewire/owner/transactions/transaction-table.blade.php -->
<div>
    <!-- Filters -->
    <div class="mb-6 space-y-4">
        <!-- Search -->
        <input type="text" 
               wire:model.debounce.300ms="search" 
               placeholder="Search transactions..."
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">

        <!-- Type Tabs -->
        <div class="flex gap-2">
            <button wire:click="$set('type', 'all')" 
                    class="px-4 py-2 text-sm font-medium rounded-lg
                           @if($type === 'all') bg-primary-600 text-white @else bg-gray-100 @endif">
                All
            </button>
            <button wire:click="$set('type', 'income')" 
                    class="px-4 py-2 text-sm font-medium rounded-lg
                           @if($type === 'income') bg-green-600 text-white @else bg-gray-100 @endif">
                Income
            </button>
            <button wire:click="$set('type', 'expense')" 
                    class="px-4 py-2 text-sm font-medium rounded-lg
                           @if($type === 'expense') bg-red-600 text-white @else bg-gray-100 @endif">
                Expenses
            </button>
        </div>

        <!-- Date Range -->
        <div class="grid grid-cols-2 gap-4">
            <input type="date" wire:model="startDate" class="px-4 py-2 border rounded-lg">
            <input type="date" wire:model="endDate" class="px-4 py-2 border rounded-lg">
        </div>
    </div>

    <!-- Desktop Table View -->
    <div class="hidden md:block bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th wire:click="sortBy('transaction_date')" 
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer">
                        Date
                        @if($sortField === 'transaction_date')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th wire:click="sortBy('amount')" 
                        class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase cursor-pointer">
                        Amount
                        @if($sortField === 'amount')
                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($transactions as $transaction)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        {{ $transaction->transaction_date->format('M d, Y') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-medium rounded-full
                                     @if($transaction->type === 'income') bg-green-100 text-green-800
                                     @else bg-red-100 text-red-800 @endif">
                            {{ ucfirst($transaction->type) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">{{ $transaction->category }}</td>
                    <td class="px-6 py-4 text-sm text-gray-900">{{ $transaction->description }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium
                               @if($transaction->type === 'income') text-green-600 @else text-red-600 @endif">
                        ₱{{ number_format($transaction->amount, 2) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                        <button wire:click="$emit('editTransaction', {{ $transaction->id }})"
                                class="text-primary-600 hover:text-primary-900 mr-3">
                            Edit
                        </button>
                        <button wire:click="deleteTransaction({{ $transaction->id }})"
                                onclick="return confirm('Delete this transaction?')"
                                class="text-red-600 hover:text-red-900">
                            Delete
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        No transactions found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Mobile Card View -->
    <div class="md:hidden space-y-4">
        @forelse($transactions as $transaction)
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <span class="px-2 py-1 text-xs font-medium rounded-full
                                 @if($transaction->type === 'income') bg-green-100 text-green-800
                                 @else bg-red-100 text-red-800 @endif">
                        {{ ucfirst($transaction->type) }}
                    </span>
                    <p class="mt-1 text-sm font-medium">{{ $transaction->category }}</p>
                </div>
                <p class="text-lg font-bold
                         @if($transaction->type === 'income') text-green-600 @else text-red-600 @endif">
                    ₱{{ number_format($transaction->amount, 2) }}
                </p>
            </div>
            <p class="text-sm text-gray-600 mb-2">{{ $transaction->description }}</p>
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-500">
                    {{ $transaction->transaction_date->format('M d, Y') }}
                </span>
                <div class="flex gap-2">
                    <button wire:click="$emit('editTransaction', {{ $transaction->id }})"
                            class="text-primary-600 text-sm">
                        Edit
                    </button>
                    <button wire:click="deleteTransaction({{ $transaction->id }})"
                            onclick="return confirm('Delete?')"
                            class="text-red-600 text-sm">
                        Delete
                    </button>
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-lg shadow p-12 text-center text-gray-500">
            No transactions found.
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $transactions->links() }}
    </div>
</div>
```

---

## Routing

```php
<?php
// routes/web.php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\TransactionController;
use App\Http\Controllers\Web\AnalyticsController;
use App\Http\Controllers\Web\GoalController;
use App\Http\Controllers\Web\StockController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ProfileController;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Owner routes
Route::middleware(['auth', 'role:owner'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
    Route::get('/calendar', [AnalyticsController::class, 'calendar'])->name('calendar');
    Route::get('/goals', [GoalController::class, 'index'])->name('goals');
    Route::get('/stock', [StockController::class, 'index'])->name('stock');
    Route::get('/products', [ProductController::class, 'index'])->name('products');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
```

---

## Error Handling

Livewire automatically handles validation errors and displays them inline. For other errors:

```php
// app/Exceptions/Handler.php

public function render($request, Throwable $exception)
{
    if ($request->wantsJson() || $request->is('livewire/*')) {
        return response()->json([
            'message' => $exception->getMessage(),
        ], 500);
    }

    return parent::render($request, $exception);
}
```

---

## Testing Strategy

**Unit Tests (PHPUnit):**
- Test Livewire component methods
- Test model relationships
- Test business logic in services

**Browser Tests (Laravel Dusk):**
- Test complete user flows
- Test responsive behavior
- Test Livewire interactions

**Example Test:**
```php
// tests/Browser/TransactionTest.php

public function testUserCanCreateTransaction()
{
    $this->browse(function (Browser $browser) {
        $browser->loginAs($this->owner)
                ->visit('/transactions')
                ->click('@add-transaction')
                ->waitFor('@transaction-modal')
                ->type('amount', '500')
                ->select('type', 'expense')
                ->click('@save-transaction')
                ->waitForText('Transaction created successfully');
    });
}
```

---

## Performance Optimization

1. **Livewire Lazy Loading**: `wire:init="loadData"`
2. **Pagination**: Limit to 20 items per page
3. **Query Optimization**: Eager load relationships
4. **Caching**: Cache financial calculations for 5 minutes
5. **Asset Optimization**: Vite minifies CSS/JS

---

## Security

1. **CSRF Protection**: All forms include `@csrf`
2. **Authorization**: Policies check user permissions
3. **Middleware**: Verify role on all routes
4. **Input Sanitization**: Livewire validates all inputs
5. **SQL Injection**: Eloquent uses prepared statements

---

## Deployment

**Production Checklist:**
1. Run `php artisan config:cache`
2. Run `php artisan route:cache`
3. Run `php artisan view:cache`
4. Run `npm run build` (Vite production build)
5. Set `APP_ENV=production` and `APP_DEBUG=false`
6. Configure SSL certificate
7. Set up Redis for caching
8. Configure queue worker for background jobs

---

## Summary

This design provides a complete Laravel Blade + Livewire web dashboard with full responsive support. The mobile-first approach ensures excellent UX on all devices, while Livewire provides reactive updates without writing JavaScript. The shared database with the API ensures data consistency across web and mobile platforms.
