# Owner dashboard and analytics definitions

The owner overview and Analytics page use the authenticated owner's `business_id`. The browser cannot select a business ID. Product, order, stock, staff, and transaction detail routes apply that same business scope; the owner order pages are read-only.

## Financial figures

- **Recorded income / recorded expenses** are sums of non-deleted `transactions` rows whose `transaction_date` is within the displayed period. Order-linked sales and reversal entries already exist in that ledger and are not added a second time.
- **Net recorded movement** is recorded income minus recorded expenses. It is not accounting profit, gross/net sales, or a reconciled cash/bank balance. The application does not currently calculate complete COGS, taxes, accruals, or all accounting adjustments.
- **Confirmed order sales** are separate: `orders.status = completed` and `orders.payment_status = confirmed`. The amount comes from the order's saved `total`; the order summary does not also add this amount to the transaction ledger.
- **Today sales** uses `completed_at` during the current Laravel application-timezone calendar day. The schema has no per-business timezone, so multi-timezone businesses would need that field and a migration before their local-day totals could be exact. Orders without a reliable completion timestamp are not guessed into today's total.
- **Average order value** is eligible confirmed order sales divided by the eligible completed-order count for the month-to-date. Discounts are already reflected in the saved `total`. Orders pending payment, cancelled, awaiting verification, refund-pending, or refunded are excluded. Manual cash/cashless confirmation is not described as provider verification.
- A refund-pending order is excluded from confirmed-order sales immediately, but the original ledger transaction remains until the refund workflow is manually confirmed and writes its reversal. Therefore ledger income and eligible order sales can differ while a refund is pending.
- **Inventory value** is active on-hand stock quantity multiplied by the stock record's `unit_cost`; inactive and soft-deleted stock items are excluded. This is a recorded cost-basis estimate, not product retail value.
- **Out of stock** means active stock quantity is zero or below. **Low stock** means positive active quantity at or below `minimum_quantity`, so these counts do not overlap. The suggested replenishment amount only tops up to the configured minimum; it is not an optimized purchase recommendation.
- **Active staff** means non-deleted business users with `role = staff` and `status = active`. Other non-deleted staff statuses are counted as inactive/unavailable.

## Analytics comparison and date filters

Analytics defaults to month-to-date. The default comparison is the equivalent elapsed calendar dates in the previous month, clamped to that month's last day. A custom selected range is compared with the immediately preceding range of equal length. Date inputs are validated, cannot be future-dated, and are limited to one year. If the comparison amount/count is zero, growth is shown as **No previous-period baseline**, not 0%.

Transaction counts represent saved ledger entries, not distinct customers or orders. The daily charts use actual, business-scoped transaction rows; an empty period receives an explicit empty state.

## Payment caveat

A mobile GCash/Maya notification records a detected signal; it does not prove payment. No provider API/webhook verification is implemented here. Detection remains separate from confirmation, and the order detail page shows the saved payment history and confirming staff account where available. Older cashless orders may have historical ledger income entries from the previous behavior while their migrated payment status is `detected`; those existing accounting entries are preserved and require business-side reconciliation. Do not treat ledger income alone as proof a cashless provider payment cleared.

## Product management

The existing web Products controller and Blade interface are now reachable from owner navigation. Listing/filtering, create/update, availability toggling, and soft deletion use the authenticated business scope. Soft deletion preserves product rows referenced by historical order items. Products still remain available to staff ordering only when active and marked available.

## Setup and verification

From the `web` directory, apply the current schema with `php artisan migrate`. This dashboard work adds no migration. Run the focused coverage with:

```bash
php artisan test --filter=OwnerDashboardTest
php artisan test --filter=OwnerProductsTest
```

Run all backend tests with `php artisan test`. These checks require the PHP version declared in `composer.json` (PHP `^8.4.1`) and the installed Composer dependencies. A MySQL-compatible database is required for a local non-test application; feature tests use the configured in-memory SQLite connection.
