# GastoTrack

GastoTrack is a staff-facing React Native POS and financial-recording app backed by a Laravel API. Staff can browse products, place orders, track inventory, record income and expenses, scan receipts with on-device OCR, and ask the business-scoped AI assistant for read-only guidance. Business owners use the Laravel web dashboard; the mobile app does not provide owner management screens.

Start with [docs/ALPHA_SETUP.md](docs/ALPHA_SETUP.md) for backend and mobile setup, device networking, demo accounts, Gemini configuration, tests, and Android signing. The app requires a live API for account data and order/transaction changes; a saved cart can be restored, but this is not offline synchronization.

Quick checks:

```powershell
npm.cmd test -- --runInBand
npm.cmd run lint
Set-Location android
.\gradlew.bat :app:assembleDebug
```

The project intentionally does not include real payment-provider integration or release signing credentials. Do not use manual payment confirmation as proof from GCash/Maya; see the alpha limitations in the setup guide.

Staff daily workspace: Today views refresh when the device date changes and when the screen is reopened. An unfinished cart is cleared on the next local day, except when an order submission has an unresolved retry identifier; that cart remains available to prevent a duplicate sale. Cancelled cashless orders disappear from Today, but remain in History with their audit trail. Pending cashless payments are never automatically cancelled or treated as paid: staff must check the official wallet and cancel an unpaid order when the customer changes their mind. Inventory and owner records are not reset.

Set `BUSINESS_TIMEZONE=Asia/Manila` in `web/.env` (the default is Asia/Manila), then run `php artisan config:clear` or rebuild the Laravel configuration cache. Keep the staff device in the same local timezone as the shop; daily sale dates and manual-transaction validation use the configured shop timezone. Existing historical transaction dates are not rewritten.

Transactions Activity has two views on both the staff app and owner website. Daily transaction shows order sales and manually recorded income/expenses. Raw transaction shows GCash/Maya Android notification captures separately; these are **unverified signals**, never sales or payment confirmations. The owner sees captures from the business's staff devices; each staff account sees its own captures. Notification text can contain private wallet information, so grant Android notification access only on a shop-managed device and keep device/account access controlled. Phone numbers and common OTP patterns are redacted before storage, but staff should still review privacy requirements before enabling capture.

To enable Raw transaction, run `php artisan migrate` in `web`, rebuild and reinstall the Android app (`npm.cmd run android` from the repository root on Windows), sign in as staff, and grant GastoTrack notification access in Android settings. Keep Laravel reachable from the device; captures queue privately on the device and retry syncing when the signed-in app is running. Captures start with notifications received after this version is installed and a staff account is active; old device notifications cannot be recovered. iOS does not provide this Android notification listener. Raw entries do not replace checking the official wallet account before authorized manual payment confirmation.

Owner monthly sales goal: after running `php artisan migrate` with PHP 8.4.1 or newer, open **Goals** in the owner website and set a target for the current or a future month. The overview shows the current month's progress. Progress is read from completed, payment-confirmed orders whose sale was committed, using `BUSINESS_TIMEZONE` for month boundaries; pending/detected wallet notifications do not count. A pending refund stays counted until staff records its confirmed manual refund, after which the original month's progress decreases. Cancelled orders do not count. This goal is not net profit or total ledger income, and it does not reset or delete owner records. Existing manual goals remain separate and retain their prior values.
