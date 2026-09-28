# GastoTrack Mobile App (Staff Edition)

GastoTrack Mobile is the staff-facing companion for day-to-day business operations. It provides staff access to the dashboard, transactions, receipt scanning, order queue, stock, and profile screens.

Business owners use the [GastoTrack web dashboard](https://gastotrack.com). The mobile app no longer includes owner navigation or owner management screens.

## Run locally

Install dependencies, then start Metro and launch the app:

```sh
npm install
npm start
npm run android
```

For iOS, install CocoaPods dependencies first:

```sh
bundle install
bundle exec pod install
npm run ios
```

## Quality checks

```sh
npm run lint
npm test
```

## Authentication integration

This prototype currently has no login or auth root navigator. When authentication is added, route accounts whose role is `owner` to `src/screens/auth/OwnerBlockedScreen.js`; it provides a link to the web dashboard and accepts an optional `onLogout` callback.
