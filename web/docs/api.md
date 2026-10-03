# GastoTrack API

Base URL: `/api/v1`. Legacy `/api` routes remain available for the current mobile build.

Send `Authorization: Bearer <token>` and `Accept: application/json` on protected requests.

## Authentication

- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/logout`
- `GET /auth/me`
- `POST /auth/refresh`
- `POST /auth/change-password`

## Business resources

- `GET|PUT /business`
- CRUD: `/transactions`, `/products`, `/stock`, `/orders`, `/goals`
- `POST /transactions/batch`
- `GET /transactions/summary`
- `POST /stock/{id}/adjust`
- `GET /stock/{id}/movements`
- `POST /goals/{id}/complete`
- Owner staff management: `/staff`

## Notifications and analytics

- `GET /notifications`
- `POST /notifications/{id}/read`
- `POST /notifications/read-all`
- `GET /analytics/summary`
- `GET /analytics/category-breakdown`
- `GET /analytics/top-products`
- `GET /analytics/stock-value`
- `GET /analytics/trends`

## Offline synchronization

`POST /sync` accepts up to 100 offline transactions with UUID `client_id` values and an optional `last_sync_timestamp`. The response contains client/server ID mappings, server changes, conflicts, and a new sync timestamp.

Set `EXPO_PUBLIC_API_URL` in the mobile build to the server API URL. Set `CORS_ALLOWED_ORIGINS` as a comma-separated production allowlist. Never use `*` in production.
