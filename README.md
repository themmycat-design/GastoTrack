# GastoTrack

GastoTrack is a staff-facing React Native POS and financial-recording app backed by a Laravel API. Staff can browse products, place orders, track inventory, record income and expenses, scan receipts with on-device OCR, and ask the business-scoped AI assistant for read-only guidance. Business owners use the Laravel web dashboard; the mobile app does not provide owner management screens.

After an online sign-in, staff can record manual transactions, cash orders, and stock adjustments while disconnected. The Android app stores these actions in an account-scoped SQLite outbox and retries them when the app is open and the Laravel API becomes reachable. They do not enter shared sales totals or deduct shared stock until the server confirms them. Captured GCash/Maya notifications use a separate device queue.

## Staff offline recording

- Open the app online at least once to sign in and cache products, transaction options, and the first page of stock items. The cached catalog can be stale; product photos may still need the server.
- Manual income/expense entries, cash orders, and adjustments to loaded stock items are saved to device SQLite first. The Home screen shows waiting or rejected records. Pull down to retry immediately; the open app also retries on foreground and every 30 seconds. Successful offline orders and stock movements retain their original device-recorded time (within 30 days).
- Cash orders remain **unconfirmed locally**. Laravel checks the saved product price and current ingredient stock when syncing. If either changed, the record stays visible as rejected for staff review; it is not silently recorded at a new price or with negative stock. Successful offline cash orders retain their original device-recorded sale time (within 30 days) for daily reporting.
- GCash/Maya payment confirmation, AI suggestions/chat, account changes, product edits, receipt transaction submission, and server reports still require the API. On-device OCR can read a picture offline, but AI categorization and saving it cannot complete offline.
- Offline sync runs while the app is open or when staff reopen it; it is not guaranteed to run while Android has stopped the app. A new APK containing the native SQLite module and the `2026_10_21_000004` and `2026_10_21_000005` Laravel migrations are required before using this feature. Back up and stage-test migrations before applying them to a live database.

## Demo deployment: Laravel on alwaysdata, Android APK via EAS Build

This is a low-cost/academic demo path, not a production recommendation for shops' real financial data. The alwaysdata free plan is restricted to personal/non-profit use and has small resource/storage limits; confirm its current [plan terms and limits](https://www.alwaysdata.com/en/offers/) before creating an account or uploading business data. Its database service is MariaDB, which Laravel connects to through its MySQL driver (`DB_CONNECTION=mysql`); it is MySQL-compatible, but it is not Oracle MySQL. If the requirement is specifically Oracle MySQL, or the deployment is commercial, choose a host that explicitly provides that instead. See alwaysdata's [database and technical details](https://help.alwaysdata.com/en/docs/technical-specifications/login-details/).

### 1. Create the demo hosting resources

1. Create an alwaysdata account and a MariaDB database/user in its control panel. Save the database name, username, password, and the exact database hostname shown there. Do not paste credentials into GitHub, this README, or chat.
2. Create a PHP website using the `alwaysdata.net` domain, select PHP 8.4, enable HTTPS, and set the website's document root to the Laravel **public** directory (the full account path ending in `/GastoTrack/web/public`). Never point the website root at `/GastoTrack/web` or the repository root; `.env`, source, and private storage must stay outside the web root. alwaysdata documents [PHP version selection](https://help.alwaysdata.com/en/docs/web-hosting/languages/php/configuration/).
3. In the account's SSH settings, add a read-only GitHub deploy key if the repository is private. Otherwise, upload the repository over SFTP. Keep the deployed checkout and Laravel storage in the account's persistent home directory.

### 2. Deploy Laravel and its web assets

The commands below assume the repository is at `~/GastoTrack` and that `php -v` reports PHP 8.4. Use the PHP 8.4 CLI command configured for your account if the shell's `php` points to a different version. alwaysdata provides [Composer](https://help.alwaysdata.com/en/languages/php/packages/); Laravel 13 in this project requires PHP `^8.4.1`.

```sh
cd ~/GastoTrack/web
composer install --no-dev --prefer-dist --optimize-autoloader
cp .env.example .env
```

Edit `web/.env` on the host (never commit or send it) and set at least:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-ACCOUNT.alwaysdata.net
DB_CONNECTION=mysql
DB_HOST=THE-DATABASE-HOST-SHOWN-IN-ALWAYSDATA
DB_PORT=3306
DB_DATABASE=THE-DATABASE-NAME
DB_USERNAME=THE-DATABASE-USER
DB_PASSWORD=THE-DATABASE-PASSWORD
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
BUSINESS_TIMEZONE=Asia/Manila
```

Keep the existing `.env.example` values for other app settings unless you have deliberately configured them. Configure a real SMTP provider before owner registration so verification and security emails can be delivered; `MAIL_MAILER=log` only writes mail to application logs. `GEMINI_API_KEY` is optional and must remain server-side. For product images, this project uses Laravel's public storage disk; private bug-report screenshots use local private storage. The host filesystem must remain persistent, and the free plan's storage quota applies to uploaded files.

The public Contact form sends submissions to `GASTOTRACK_CONTACT_EMAIL` (default: the project email in `.env.example`) using the same configured mailer. Set `MAIL_FROM_ADDRESS` to the authenticated sender address accepted by your SMTP provider. Visitors' email addresses are used as Reply-To. The form reports delivery as unavailable when the configured mailer is `log`, `array`, or the current `failover` setup (which falls back to logging). The form is limited to three submissions per minute per IP address.

Build the website assets on your Windows development PC (the `public/build` directory is git-ignored), then upload the generated `web/public/build` directory to the same path on the host:

```powershell
Set-Location C:\Users\Chris\GastoTrack\web
npm.cmd ci
npm.cmd run build
```

Back on the host, finish initial setup. Run the key-generation command **once only**; the key protects encrypted app data, including owner MFA secrets. Keep it backed up privately and never regenerate it on an existing deployment. Create a blank database before migrating; do not import an old SQL dump or run `migrate:fresh` here.

```sh
cd ~/GastoTrack/web
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=SuperAdminSeeder --force
php artisan storage:link
php artisan config:cache
php artisan view:cache
```

Set `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL`, and a unique `SUPER_ADMIN_PASSWORD` in the host `.env` before the seeder command. The seeder creates/updates only the Super Admin; it does not load demo businesses or financial records. Run it once with the intended administrator email, and avoid rerunning it after changing credentials unless you mean to reset that account's password. Confirm the site loads over HTTPS, then test owner verification/MFA and staff API sign-in. On later code updates, preserve `.env` and `storage`, upload the new `public/build`, run `composer install --no-dev --prefer-dist --optimize-autoloader`, then `php artisan migrate --force` and clear/rebuild Laravel caches as appropriate. Never delete the persistent `storage` directory during an update.

### 3. Schedule Laravel and back up data

The application has a daily goal-deadline notification task. In alwaysdata's [Scheduled Tasks](https://help.alwaysdata.com/en/docs/web-hosting/tasks/) panel, run this command every minute (the Laravel scheduler decides when the daily task is due):

```sh
cd ~/GastoTrack/web && php artisan schedule:run
```

Set up and test a separate database and uploaded-file backup before entering any non-demo data. The free plan's backup/retention and disk limits are not a substitute for a verified recovery plan.

### 4. Point the Android release at the hosted API

After the hosted Laravel API works over HTTPS, configure `MOBILE_API_URL` in the EAS **production** environment to `https://YOUR-ACCOUNT.alwaysdata.net/api/v1` (or the public HTTPS URL of your AWS-hosted API). EAS builds the APK; the website, API, and database remain on your chosen web host.

Do not use localhost, `127.0.0.1`, an emulator/ADB tunnel, or a private LAN address in a release build. Do not point the APK at the API until the deployed HTTPS endpoint is reachable from the phone.

## Build and share the Android APK with EAS Build

This is a React Native CLI app with native Android modules. EAS Build compiles the existing `android` project into a release APK; it does not move the Laravel API or database to Expo. Use the `demo` profile in `eas.json` to produce an installable APK without Metro. EAS's free plan has a limited monthly build allowance; check the current [Expo pricing](https://expo.dev/pricing).

### One-time setup in the VS Code PowerShell terminal

1. Create an Expo account, then run these commands from `C:\Users\Chris\GastoTrack`:

   ```powershell
   npx.cmd eas-cli@latest login
   npx.cmd eas-cli@latest init
   ```

   `eas init` links this repository to your Expo project and may add a project ID to `app.json`. Keep the Android application ID `com.gastotrack`.
2. **Import the existing release keystore** into EAS before building. If you already installed a release-signed APK, a new keystore will not update it. Keep the original `.keystore` file and its passwords private. Run `npx.cmd eas-cli@latest credentials -p android`, select the `demo` profile, and choose to upload your existing Android keystore. If the original keystore is unavailable, you must use a new key and uninstall old release builds before installing the EAS APK; first sync any device-only offline records. Do not use the debug keystore as the release key.
3. In the Expo project dashboard, under **Environment variables > production**, set these non-secret values:
   - `MOBILE_API_URL`: your hosted public HTTPS API base URL ending in `/api/v1`.
   - `ANDROID_VERSION_CODE`: a positive whole number **greater than every previously distributed release APK's versionCode**. Increase it for every new APK.
   - `ANDROID_VERSION_NAME`: optional display version, such as `1.0.0`.

   The EAS post-install hook validates the API URL and writes the ignored `.env.production` file used by `react-native-config`. Do not put credentials or API keys in the mobile URL or the APK. The release build will fail if the URL or version code is missing. The keystore and passwords must never be committed.

### Create and install a release

```powershell
Set-Location C:\Users\Chris\GastoTrack
npx.cmd eas-cli@latest build --platform android --profile demo
```

After the build completes, open its EAS build link and download the `.apk`, or share that build link with your testers. Install it on an Android phone and confirm staff sign-in reaches the hosted API over mobile data. Increase `ANDROID_VERSION_CODE` before the next build. EAS Build's APK link is for distributing test builds; if you want a stable public landing-page download, host the signed APK at your own HTTPS URL.

The earlier GitHub Actions/Firebase App Distribution workflow remains in `.github/workflows/android-firebase-distribution.yml` as a manual legacy option; version tags no longer trigger it. It is not needed for EAS builds.

### Show an APK download on the landing page

The landing page's **Download APK** button opens a public installation guide. Until a public APK URL is configured, the guide tells testers how to request the current EAS build link. You can also share an EAS build link directly.

The guide includes steps for testers whose phone blocks a sideloaded APK through Google Play Protect. Keep Play Protect enabled; a blocked tester should contact the project team and use a Google Play internal testing invitation if one is available. The team should review the app's permissions and appeal an incorrect block rather than asking testers to disable protection.

To show a direct **Download APK** button on the guide, place a signed release APK on a public HTTPS file host that you control, then set `GASTOTRACK_APK_DOWNLOAD_URL` in `web/.env` on the web host to that exact APK URL. Reload Laravel configuration if it is cached (`php artisan config:cache`). The guide displays the direct button only for a valid HTTPS URL; setting the variable does not upload or publish the APK. Replace the URL when you release a newer build.

If the phone currently has a debug-signed development app, Android will not update it in place with the release-signed APK. Sync pending notification captures first; uninstalling the debug app clears device-local data, while server-saved business records remain on the server.

After the first EAS build, compare its signing certificate to the existing release certificate before distributing it as an update. The debug build and release build use different keys, so a release APK cannot update a debug installation.

## Quick access (Windows)

GastoTrack has two entry points: owners use the website, and staff use the Android app. For local development, keep Laravel running while using either one.

### Owner website (this PC)

1. Make sure MySQL is running and the database settings in `web/.env` are correct.
2. Open a VS Code terminal with **Command Prompt** and start Laravel:

   ```cmd
   C:\Users\Chris\GastoTrack\scripts\start-api.cmd
   ```

3. On this PC, open [the GastoTrack website](http://127.0.0.1:8000). Sign in at [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login), or create an account at [http://127.0.0.1:8000/register](http://127.0.0.1:8000/register).

Leave this terminal open while using the website or mobile app. This local website address is for this PC; it is not a public hosted link.

If your VS Code terminal is already in `C:\Users\Chris\GastoTrack\web`, run `.\start-api.cmd` instead. The startup script enables the 5 MB product and staff profile photo uploads advertised in the app. PHP's default 2 MB limit rejects larger photos before Laravel can save them. Stop any old server on port 8000 before starting this script. The script uses PHP 8.4 from Herd when available, or a compatible PHP on PATH; you can pass the full path to another PHP 8.4 executable as its first argument. CGI/FastCGI deployments use web/public/.user.ini; if your host overrides these settings, configure upload_max_filesize=6M and post_max_size=8M in its PHP settings.

### Staff app (real Android phone)

After pairing and connecting the phone over Wireless debugging, run these commands in a VS Code **Command Prompt** terminal. Replace `DEVICE_SERIAL` with the exact current serial from `adb devices -l`:

```cmd
cd /d C:\Users\Chris\GastoTrack
adb devices -l
adb -s DEVICE_SERIAL reverse tcp:8000 tcp:8000
adb -s DEVICE_SERIAL reverse tcp:8081 tcp:8081
npm.cmd run android -- --device DEVICE_SERIAL
```

Keep the Laravel terminal above running. The ADB reverse commands connect the selected phone to Laravel on port `8000` and Metro on port `8081`; the Android development build uses `http://127.0.0.1:8000/api/v1` through that tunnel. The Android command builds, installs, and opens the app. If more than one device is listed, use the exact serial for your phone, not the emulator.

## First-time wireless setup

The development API defaults to `http://127.0.0.1:8000/api/v1`. ADB reverse tunnels that phone-local address to this PC, so you do not need to enter the PC's changing Wi-Fi IP into the app. The debug build allows HTTP; release builds require HTTPS.

1. Put the phone and PC on the same Wi-Fi. On Android, open **Developer options > Wireless debugging > Pair device with pairing code**.
2. In the VS Code terminal, run the `adb pair` command shown on the phone (it looks like `adb pair PHONE_IP:PAIRING_PORT`) and enter the displayed pairing code.
3. On the main Wireless debugging screen, use the other address/port to connect (not the pairing port): `adb connect PHONE_IP:DEBUG_PORT`. Then continue with **Staff app (real Android phone)** above.

Pairing is usually a one-time step. If Wireless debugging reconnects with a new port, run `adb connect` again and use the new serial from `adb devices -l`. Replace `DEVICE_SERIAL` in all three commands above each time the debugging port changes.

If the phone shows a Metro HTTP 500 mentioning `DependencyGraph.js` or `Cannot read properties of undefined (reading 'get')`, stop the existing Metro process with **Ctrl+C**, then restart Metro from the repository root with `npm.cmd start -- --reset-cache`. Wait for **Dev server ready**, keep that terminal open, and tap **Reload** in the app. This is a Metro bundle failure; rebuilding or reinstalling the APK is not normally needed.

Quick checks:

```cmd
npm.cmd test -- --runInBand
npm.cmd run lint
cd /d C:\Users\Chris\GastoTrack\android
gradlew.bat :app:assembleDebug
```

The project intentionally does not include real payment-provider integration or release signing credentials. Do not use manual payment confirmation as proof from GCash/Maya.

Staff daily workspace: Today views refresh when the device date changes and when the screen is reopened. An unfinished cart is cleared on the next local day, except when an order submission has an unresolved retry identifier; that cart remains available to prevent a duplicate sale. Cancelled cashless orders disappear from Today, but remain in History with their audit trail. Pending cashless payments are never automatically cancelled or treated as paid: staff must check the official wallet and cancel an unpaid order when the customer changes their mind. Inventory and owner records are not reset.

Set `BUSINESS_TIMEZONE=Asia/Manila` in `web/.env` (the default is Asia/Manila), then run `php artisan config:clear` or rebuild the Laravel configuration cache. Keep the staff device in the same local timezone as the shop; daily sale dates and manual-transaction validation use the configured shop timezone. Existing historical transaction dates are not rewritten.

The owner Transactions Activity has Daily and Raw views. Daily shows order sales and manually recorded income/expenses; Raw shows GCash/Maya Android notification captures separately. Staff see their daily records and any rejected wallet captures that need retry. Wallet notification text can contain private information, so grant Android notification access only on a shop-managed device and keep device/account access controlled. Phone numbers and common OTP patterns are redacted before storage, but staff should still review privacy requirements before enabling capture.

To enable Raw transaction, apply the Laravel migrations in your own deployment process, rebuild and reinstall the Android app (`npm.cmd run android` from the repository root on Windows), sign in as staff, and grant GastoTrack notification access in Android settings. Keep Laravel reachable from the device; captures queue privately on the device and retry syncing when the signed-in app is running. Rejected captures appear in staff Transactions for review and retry. Captures start with notifications received after this version is installed and a staff account is active; old device notifications cannot be recovered. iOS does not provide this Android notification listener. A cashless order still confirms automatically after an exact wallet/amount/reference match, but only when the signal was saved on the staff device and accepted by the API. This is not independent verification by GCash or Maya: wallet-app checks and reconciliation remain necessary, and misleading or spoofed notifications remain a financial risk.

Owner weekly and monthly sales goals: open **Goals** in the owner website to set a target for the current or a future week/month. Weeks run Monday through Sunday in `BUSINESS_TIMEZONE`. The overview and Goals page show current-period progress, calculated from completed, payment-confirmed orders whose sale was committed; pending/detected wallet notifications do not count. A pending refund stays counted until staff records its confirmed manual refund, after which the original period's progress decreases. Cancelled orders do not count. These targets are not net profit or total ledger income, and they do not reset or delete owner records. Existing manual goals remain separate and retain their prior values.

Inventory quantities and ingredient unit costs accept up to four decimal places; monetary totals are rounded to two decimals after multiplying quantity by unit cost. Stock adjustment notes accept up to 500 characters. Apply the `2026_10_21_000001` through `2026_10_21_000005` migrations to a backed-up staging database before production. The server-side `staff_offline` SQLite connection and its existing data file are preserved, but the unsafe `staff:sync` command and dual-database setup scripts are not part of mobile sync. The Android outbox is a separate device SQLite database; Laravel saves confirmed records into its configured database. Archiving a business retains its records; the separate Super Admin permanent-delete action removes its associated records.

### Platform administration

The Super Admin console has a global user directory, admin activity log, manual subscription controls on each business detail page, and page-scoped bulk business actions. Apply `2026_10_28_000001_add_platform_subscriptions_and_admin_activity_logs.php` with `php artisan migrate --force` after backing up and testing on a staging database. This migration does not change existing orders or transactions. Existing businesses default to an active Free plan with no end date. Free permits two staff accounts; Basic, Pro, and Enterprise require a subscription end date when activated. When a plan is expired or suspended, the owner can still read records but cannot change business data, and staff API access is blocked until renewal. Pending business approval remains separate from subscription activation. Multi-branch and inventory forecasting are not yet implemented, so changing a plan does not create those features.

Bulk Select All applies only to the current business-list page, never to all filtered pages. The action checks all selected records before updating any, limits each request to 100 distinct businesses, and exports only selected platform-safe business fields. A Super Admin password-reset action sends a reset link, requires the target to change their password, and revokes their mobile tokens; the configured mail service must be working. Admin activity records action, administrator ID, target ID, IP, and time; it does not include passwords or tenant financial rows. Do not run `migrate:fresh` on an existing installation.
