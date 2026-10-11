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
