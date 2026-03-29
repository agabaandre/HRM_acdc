# APM API Documentation

REST API for the Approvals Management (APM) module. Used by the mobile app, approver apps, and integrations for authentication (email/password and Microsoft SSO), pending approvals, documents, actions, memo lists, and reference data.

---

## Quick links

| Resource | Location |
|----------|----------|
| **OpenAPI 3.0 spec** | [APM_API_OPENAPI.yaml](./APM_API_OPENAPI.yaml) |
| **Interactive docs (Swagger UI)** | `/docs` when the app is running (e.g. `http://localhost/staff/apm/docs`) |
| **Main docs index** | [README.md](./README.md) |

---

## Base URL and version

- **Base path:** `/api/apm/v1`
- **Full base URL** depends on deployment, for example:
  - Local: `http://localhost/staff/apm/api/apm/v1`
  - Production: `https://cbp.africacdc.org/demo_staff/apm/api/apm/v1`

All endpoints below are relative to this base unless noted.

---

## Table of contents

1. [Authentication](#authentication)
2. [Public endpoints (no JWT)](#public-endpoints-no-jwt)
3. [Protected endpoints (JWT required)](#protected-endpoints-jwt-required)
4. [Web app URLs (attachments and print)](#web-app-urls-attachments-and-print)
5. [Request/response conventions](#requestresponse-conventions)
6. [Full specification](#full-specification)

---

## Authentication

- **Method:** JWT Bearer token.
- **API users** are stored in `apm_api_users` (synced from staff app). Login accepts **email** (staff work email) and **password**, or **Microsoft SSO** (access token or authorization code).

### Login (email + password)

**POST** `/auth/login`

Request body (JSON):

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `email` | string | Yes | User email (e.g. staff work email) |
| `password` | string | Yes | Password |

**Response (200):** `success`, `data.access_token`, `data.token_type` (`bearer`), `data.expires_in` (seconds), `data.user`, `data.divisions`.

`data.user` includes: `user_id`, `auth_staff_id`, `email`, `name`, `division_id`, `associated_divisions` (array of division IDs), `role`, `status`, `is_division_head`, `is_admin_assistant`, `is_director`, `is_finance_officer`, `job` (`job_name`, `title`, `grade` from APM staff), `supervisors` (array of `{ staff_id, name, email, job_name, title }` from `staff.supervisor_id`), and when a staff photo can be resolved: `staff_photo_url` (absolute URL—**GET** with `Authorization: Bearer <token>`, or `?token=` on that GET). No base64 in the login payload.

`data.divisions` is the list of divisions the user can access (primary + associated), each with `id`, `division_name`, `division_short_name`, `division_head`, `focal_person`, `admin_assistant`, `finance_officer`, `director_id`, `directorate_id`, `category`.

**Example:**

```bash
curl -X POST 'http://localhost/staff/apm/api/apm/v1/auth/login' \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@africacdc.org","password":"your_password"}'
```

---

### Microsoft SSO login (mobile app)

**POST** `/auth/microsoft`

Use this when the user signs in with Microsoft (Azure AD / Entra). The mobile app can send either the **Microsoft access token** (from MSAL) or the **authorization code** (after redirect).

**Request body (JSON) – option A (recommended for mobile):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `access_token` | string | Yes* | Microsoft Graph access token (from MSAL with scope `User.Read` or `openid profile email`) |

**Request body (JSON) – option B (authorization code):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `code` | string | Yes* | Authorization code from Microsoft redirect |
| `redirect_uri` | string | Yes* | Same redirect_uri used in the authorization request (must match Azure app registration) |

\* Either `access_token` or both `code` and `redirect_uri` must be provided.

**Flow:** The API validates the token (or exchanges the code for a token) and calls Microsoft Graph `GET /v1.0/me` to get the user’s email (`mail` or `userPrincipalName`). It then finds an APM user by that email (or by staff `work_email`) and, if found and active, issues an APM JWT and returns the same payload as email/password login.

**Response (200):** Same as login: `data.access_token` (APM JWT), `data.user`, `data.divisions`.

**Response (400):** Invalid or expired code, or could not get user identity from Microsoft.

**Response (403):** Staff profile missing or inactive (email not linked to an APM user).

**Example (access token):**

```bash
curl -X POST 'http://localhost/staff/apm/api/apm/v1/auth/microsoft' \
  -H 'Content-Type: application/json' \
  -d '{"access_token":"eyJ0eXAiOiJKV1QiLCJhbGc..."}'
```

---

### Using the token

Send the JWT in the `Authorization` header for all protected endpoints:

```bash
curl -X GET 'http://localhost/staff/apm/api/apm/v1/auth/me' \
  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
```

---

### Refresh token

**POST** `/auth/refresh`  
**Auth:** Bearer token required.

Returns a new access token and current user + divisions (same shape as login).

```bash
curl -X POST 'http://localhost/staff/apm/api/apm/v1/auth/refresh' \
  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
```

---

### Logout

**POST** `/auth/logout`  
**Auth:** Optional. Can be called with or without a token; always returns 200 so clients can clear the token safely.

```bash
curl -X POST 'http://localhost/staff/apm/api/apm/v1/auth/logout' \
  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
```

---

### Current user

**GET** `/auth/me`  
**Auth:** Bearer token required.

Returns the authenticated user and divisions (same shape as login): `data.user`, `data.divisions`.

---

## Public endpoints (no JWT)

These do not require a Bearer token.

| Method | Path | Description |
|--------|------|-------------|
| POST | `/auth/login` | Login with email and password |
| POST | `/auth/microsoft` | Login with Microsoft SSO (access_token or code + redirect_uri) |
| POST | `/auth/logout` | Logout (optional token) |
| GET | `/settings` | App settings (branding, app name, currency, etc.). Optional `?group=branding` or `?group=app,locale` |
| POST | `/documents/verify` | Verify APM document signature via PDF upload (see [Document verification](#document-verification)) |
| POST | `/api/documents/verify` | Same as above (legacy path for clients that use this URL) |

---

## Protected endpoints (JWT required)

All of these require the `Authorization: Bearer <token>` header.

| Method | Path | Description |
|--------|------|-------------|
| POST | `/auth/refresh` | Refresh JWT |
| GET | `/auth/me` | Current user and divisions |
| GET | `/me/photo` | Staff profile image (binary); Bearer or `?token=` (same as attachments) |
| PUT or POST | `/me/firebase-token` | Register or update FCM device token for push notifications |
| GET | `/me/notifications` | In-app notifications (default unread only; `?unread_only=false`, `per_page`, `page`) |
| POST or PATCH | `/me/notifications/read-all` | Mark all unread notifications as read for the current staff member |
| PATCH | `/me/notifications/{id}/read` | Mark one notification as read (must belong to current staff) |
| GET | `/pending-approvals` | Pending items with approval trails (optional `?category=all`) |
| GET | `/pending-approvals/summary` | Summary counts only |
| GET | `/documents/{type}/{status}` | List documents by type and status (filters: year, quarter, title, document_number, per_page, page) |
| GET | `/documents/{type}/{id}` | Single document with approval trails and attachments |
| GET | `/documents/attachments/{type}/{id}/{index}` | Stream one attachment (Bearer token in header or `?token=` for browser) |
| POST | `/actions` | Apply action (approved, rejected, returned, cancelled) to one document |
| GET | `/approved-by-me` | Documents approved/rejected by current user |
| GET | `/approved-by-me/average-time` | Average approval time |
| GET | `/matrices/{matrixId}` | Matrix detail (includes division_schedule and activities with internal_participants as lists with names) |
| POST | `/matrices/{matrixId}` | Approve or return matrix |
| GET | `/matrices/{matrixId}/activities/{activityId}` | Activity detail |
| POST | `/matrices/{matrixId}/activities` | Activity action (bulk): body `activity_ids[]`, `action` (passed/returned/convert_to_single_memo), optional `comment`, `available_budget` |
| POST | `/matrices/{matrixId}/activities/{activityId}` | Activity action (single): body `action`, optional `comment`, `available_budget` |
| GET | `/memo-list/pending` | Pending memos for user’s divisions (primary + associated) |
| GET | `/memo-list/approved` | Approved memos for user’s divisions (primary + associated) |
| GET | `/reference-data` | Lookup data (divisions, staff, fund codes, etc.). Optional `?include=divisions,staff` |
| GET | `/fund-codes` | List fund codes (funder & partner only; no activities). Optional filters: `is_active`, `year`, `division_id`, `funder_id`, `partner_id`, `per_page` |
| GET | `/sap_budgets` | SAP-style budgets feed from `fund_codes` (`fund_center`, `released_budget_balance`). Optional filters: `year`, `min_balance` |
| GET | `/fund-codes/{id}` | Single fund code with funder and partner |
| POST | `/fund-codes` | Create fund code |
| PUT / PATCH | `/fund-codes/{id}` | Update fund code |
| GET | `/directorates` | List directorates. Optional: `is_active`, `per_page` |
| GET | `/directorates/{id}` | Single directorate |
| POST | `/directorates` | Create directorate |
| PUT / PATCH | `/directorates/{id}` | Update directorate |
| GET | `/divisions` | List divisions. Optional: `directorate_id`, `per_page` |
| GET | `/divisions/{id}` | Single division |
| POST | `/divisions` | Create division |
| PUT / PATCH | `/divisions/{id}` | Update division |

**Document types:** `special_memo`, `matrix`, `activity`, `non_travel_memo`, `service_request`, `arf`, `change_request`.

### Firebase device token (push notifications)

**PUT** or **POST** `/me/firebase-token`  
**Auth:** JWT required.

Register the device’s FCM token so the server can send push notifications (e.g. “You have N pending approvals”). Call this after login and whenever the FCM token refreshes on the device. To clear the token (e.g. on logout), send an empty string or `"token": null`.

**Request body (JSON):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `token` | string \| null | No | FCM device token; omit or `""` or `null` to clear |

**Response (200):** `{ "success": true, "message": "Firebase token updated." }` or `"Firebase token cleared."`

**Example:**

```bash
curl -X PUT 'http://localhost/staff/apm/api/apm/v1/me/firebase-token' \
  -H 'Authorization: Bearer YOUR_JWT' \
  -H 'Content-Type: application/json' \
  -d '{"token":"dFCM_DEVICE_TOKEN_FROM_CLIENT"}'
```

**Server-side testing (Artisan):** After `.env` has `FIREBASE_PROJECT_ID` and credentials, run from `apm/`:

- `php artisan notifications:test-fcm-pending-approvals --dry-run` — who would receive a push (requires pending > 0 to actually send).
- `php artisan notifications:test-fcm-pending-approvals` — send test pushes synchronously.

See [FIREBASE_PUSH_NOTIFICATIONS.md](./FIREBASE_PUSH_NOTIFICATIONS.md) for full setup and scheduling.

### In-app notifications

**GET** `/me/notifications`  
**Auth:** JWT required.

Lists notifications stored for the staff profile linked to the token (`auth_staff_id`). By default only **unread** items are returned. Query parameters:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `unread_only` | boolean | `true` | `false` to include read notifications |
| `per_page` | integer | `20` | Page size (max 100) |
| `page` | integer | `1` | Page number |

**Response (200):** `success`, `data` (array of items with `id`, `title`, `message`, `type`, `is_read`, `read_at`, `model_id`, `model_type`, `created_at`), `pagination`, `filters`. If `title` is missing in storage, the API returns **`APM Approval Notification`**.

**POST** or **PATCH** `/me/notifications/read-all`  
Marks every unread notification for that staff member as read. **Response (200):** `success`, `message`, `marked_count` (number of rows updated).

**PATCH** `/me/notifications/{id}/read`  
Marks a single notification as read if it belongs to the current staff. **404** if not found or not owned.

**Example:**

```bash
curl -s 'http://localhost/staff/apm/api/apm/v1/me/notifications?per_page=10' \
  -H 'Authorization: Bearer YOUR_JWT'

curl -s -X POST 'http://localhost/staff/apm/api/apm/v1/me/notifications/read-all' \
  -H 'Authorization: Bearer YOUR_JWT'
```

---

## Web app URLs (attachments and print)

These URLs are served under the **web app root** (e.g. `http://localhost/staff/apm`), not under `/api/apm/v1`. They accept **session (web login) or JWT** so mobile and in-browser can use the same URL.

**Authentication (either):**
- **Web session** — user is logged in via the web app (cookies).
- **JWT** — `Authorization: Bearer <token>` header, or query `?token=<token>` (e.g. for opening in browser or mobile).

| Purpose | Path (relative to web app root) | Example |
|--------|----------------------------------|--------|
| Stream attachment | `GET /documents/attachments/{type}/{id}/{index}` | `.../documents/attachments/activity/418/0` or `.../0?token=eyJ...` |
| Print service request | `GET /service-requests/{id}/print` | `.../service-requests/4/print?token=eyJ...` |
| Print special memo | `GET /special-memo/{id}/print` | `.../special-memo/22/print` |
| Print non-travel memo | `GET /non-travel/{id}/print` | `.../non-travel/5/print` |
| Print request ARF | `GET /request-arf/{id}/print` | `.../request-arf/3/print` |
| Print change request | `GET /change-requests/{id}/print` | `.../change-requests/7/print` |
| Print single memo | `GET /single-memos/{id}/print` | `.../single-memos/12/print` |

Use the **url** or **web_view_url** from `GET /documents/{type}/{id}` (in `attachments[].url` or `attachments[].web_view_url`). For mobile, append `?token=<access_token>` if the client cannot send the Bearer header (e.g. in-app browser).

---

## Settings

**GET** `/settings`  
**Auth:** None.

Returns system settings (branding, app name, default currency, etc.) so the app can show theme and labels before login.

**Query parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `group` | string | Optional. Comma-separated groups to return: `branding`, `app`, `locale`, `ui`. If omitted, all groups are returned. |

**Response (200):** `success`, `data` (object keyed by group, e.g. `data.branding`, `data.app`, `data.locale`, `data.ui`). Each group is an object of key–value pairs (e.g. `primary_color`, `application_name`, `default_currency`).

**Example:**

```bash
curl -X GET 'http://localhost/staff/apm/api/apm/v1/settings'
curl -X GET 'http://localhost/staff/apm/api/apm/v1/settings?group=branding,app'
```

---

## Memo list (division-scoped)

**GET** `/memo-list/pending` and **GET** `/memo-list/approved`  
**Auth:** Bearer required.

Return memos for the **authenticated user’s divisions**: primary division plus **associated divisions** (from staff profile). So users with multiple divisions see memos from all of them.

**Query parameters:** `year`, `quarter`, `memo_type` (QM, SM, SPM, NT, CR, SR, ARF), `title`, `document_number`, `per_page` (default 20), `page` (default 1).

**Response (200):** `success`, `data.memos` (array), `data.division_id` (primary), `data.division_ids` (array of all division IDs used), `data.status`, `data.filters`, `data.total`, `data.per_page`, `data.current_page`, `data.last_page`.

---

## Fund codes (light: funder and partner only)

Fund code endpoints return **funder** and **partner** relations only (no activities) so responses stay light for external systems.

**GET** `/fund-codes`  
**Auth:** Bearer required.

List fund codes with optional filters.

**Query parameters:**

| Parameter | Type | Description |
|----------|------|-------------|
| `is_active` | 0 \| 1 | Filter by active flag |
| `year` | integer | Filter by year |
| `division_id` | integer | Filter by division |
| `funder_id` | integer | Filter by funder |
| `partner_id` | integer | Filter by partner |
| `per_page` | integer | Items per page (default 50, max 100) |
| `page` | integer | Page number |

**Response (200):** `success`, `data` (array of fund code objects with `funder` and `partner` nested), `pagination` (`current_page`, `last_page`, `per_page`, `total`).

**GET** `/fund-codes/{id}`  
**Auth:** Bearer required.

Single fund code with funder and partner.

**Response (200):** `success`, `data` (fund code with `funder`, `partner`).  
**Response (404):** Fund code not found.

**POST** `/fund-codes`  
**Auth:** Bearer required.

Create a fund code.

**Request body (JSON):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `code` | string | Yes | Unique code |
| `activity` | string | No | Activity description |
| `year` | integer | Yes | 2020–2100 |
| `funder_id` | integer | No | Funder ID (must exist) |
| `partner_id` | integer | No | Partner ID (must exist) |
| `fund_type_id` | integer | No | Fund type ID |
| `division_id` | integer | No | Division ID |
| `cost_centre` | string | No | |
| `amert_code` | string | No | |
| `fund` | string | No | |
| `budget_balance` | string | No | |
| `approved_budget` | string | No | |
| `uploaded_budget` | string | No | |
| `is_active` | boolean | No | Default true |

**Response (201):** `success`, `message`, `data` (created fund code with funder and partner).  
**Response (422):** Validation error (e.g. duplicate `code`).

**PUT** or **PATCH** `/fund-codes/{id}`  
**Auth:** Bearer required.

Update a fund code. Send only fields to change.

**Response (200):** `success`, `message`, `data` (updated fund code).  
**Response (404):** Fund code not found.

---

## SAP budgets

**GET** `/sap_budgets`  
**Auth:** Bearer required.

Returns SAP-style budget balances from `fund_codes`:
- `fund_center` = `fund_codes.code`
- `released_budget_balance` = `fund_codes.budget_balance`

**Query parameters:**

| Parameter | Type | Description |
|----------|------|-------------|
| `year` | integer | Optional year filter |
| `min_balance` | number | Optional minimum balance threshold (default `0`) |

**Response (200):** `success`, `data` (array), `count`, `filters`.

---

## Directorates

**GET** `/directorates`  
**Auth:** Bearer required.

List directorates. Query: `is_active` (0|1), `per_page`, `page`.

**Response (200):** `success`, `data` (array), `pagination`.

**GET** `/directorates/{id}`  
**Auth:** Bearer required.

Single directorate. **Response (404):** Not found.

**POST** `/directorates`  
**Auth:** Bearer required.

Create a directorate.

**Request body (JSON):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Directorate name |
| `is_active` | boolean | No | Default true |

**Response (201):** `success`, `message`, `data`.

**PUT** or **PATCH** `/directorates/{id}`  
**Auth:** Bearer required.

Update a directorate. **Response (200):** `success`, `message`, `data`. **Response (404):** Not found.

---

## Divisions

**GET** `/divisions`  
**Auth:** Bearer required.

List divisions. Query: `directorate_id`, `per_page`, `page`.

**Response (200):** `success`, `data` (array), `pagination`.

**GET** `/divisions/{id}`  
**Auth:** Bearer required.

Single division. **Response (404):** Not found.

**POST** `/divisions`  
**Auth:** Bearer required.

Create a division.

**Request body (JSON):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `division_name` | string | Yes | Max 150 chars |
| `division_short_name` | string | No | Max 100 chars |
| `division_head` | integer | No | Staff ID (exists in staff) |
| `focal_person` | integer | No | Staff ID |
| `admin_assistant` | integer | No | Staff ID |
| `finance_officer` | integer | No | Staff ID |
| `directorate_id` | integer | No | Directorate ID |
| `head_oic_id` | integer | No | Staff ID |
| `head_oic_start_date` | date | No | |
| `head_oic_end_date` | date | No | |
| `director_id` | integer | No | Staff ID |
| `director_oic_id` | integer | No | Staff ID |
| `director_oic_start_date` | date | No | |
| `director_oic_end_date` | date | No | |
| `category` | string | No | Programs, Operations, Other, or empty |

**Response (201):** `success`, `message`, `data`.  
**Response (422):** Validation error.

**PUT** or **PATCH** `/divisions/{id}`  
**Auth:** Bearer required.

Update a division. Send only fields to change. **Response (200):** `success`, `message`, `data`. **Response (404):** Not found.

---

## Create matrix and activities

**POST** `/matrices`  
**Auth:** Bearer required.

Create a new matrix (draft) and optionally one or more activities. Only one matrix per division per year/quarter is allowed. **focal_person_id** defaults to the authenticated user's staff_id and must belong to the same division.

**Request body (JSON):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `division_id` | integer | Yes | Division ID (must exist). |
| `year` | integer | Yes | 2020–2030. |
| `quarter` | string | Yes | Q1, Q2, Q3, or Q4. |
| `key_result_area` | array | Yes | At least one item; each has `description` (string). |
| `focal_person_id` | integer | No | Staff ID; defaults to authenticated user. Must belong to division. |
| `activities` | array | No | Optional list of activities to create. |

**Each activity object:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `activity_title` | string | Yes | Max 500 chars. |
| `responsible_person_id` | integer | Yes | Staff ID (must exist). |
| `request_type_id` | integer | No | Default 1. |
| `fund_type_id` | integer | No | Default 1. |
| `date_from` | string (date) | No | Default today. |
| `date_to` | string (date) | No | Default same as date_from. |
| `total_participants` | integer | No | Default 1. |
| `background` | string | No | |
| `activity_request_remarks` | string | No | |
| `internal_participants` | object | No | Keyed by staff_id; values: participant_start, participant_end, participant_days, international_travel. |
| `location_id` | array of integers | No | Location IDs. |
| `budget_id` | array | No | Fund code IDs. |
| `budget_breakdown` | object | No | Budget items. |

**Response (201):** `success`, `message`, `data.matrix_id`, `data.division_id`, `data.year`, `data.quarter`, `data.overall_status` (draft), `data.activities_count`, `data.activities` (array of `{ id, activity_title, document_number }`).

**Errors:** 401 Unauthenticated; 422 validation or matrix already exists for division/year/quarter; 500 server error.

---

## Matrix detail

**GET** `/matrices/{matrixId}`  
**Auth:** Bearer required.

Returns matrix with activities. Notable shape:

- **`division_schedule`:** Array of division-schedule participants. Each item has `staff_id`, `name`, `participant_name`, `participant_days`, `is_home_division`, `division_id`, `quarter`, `year`.
- **`activities`:** Each activity has **`internal_participants`** as an **array** (not keyed by ID). Each participant has `staff_id`, `name`, `participant_name`, `participant_start`, `participant_end`, `participant_days`, `international_travel`.

---

## Document verification

**POST** `/documents/verify` or **POST** `/api/documents/verify`  
**Auth:** None (public).  
**Base URL:** Same as other APM v1 endpoints (e.g. `.../api/apm/v1/documents/verify`). The path `api/documents/verify` is also supported for legacy clients (e.g. `.../api/apm/v1/api/documents/verify`).

Upload an APM-generated PDF to verify document number and signature hashes. **PDF only**; max 10MB. File is not stored.

**Request:** `multipart/form-data` with a single file field named **`document`** (PDF).

**Response (200):** JSON with `success`, `result_type` (`upload_validation`), `valid` (true if at least one extracted hash matched a signatory), `extracted_document_numbers`, `extracted_hashes`, `hash_validations`, `documents`, `signatories`, `document` (metadata).

**Response (422):** Validation error (e.g. file missing, not PDF, or too large).

**Example:**

```bash
curl -X POST 'http://localhost/staff/apm/api/apm/v1/documents/verify' \
  -H 'Accept: application/json' \
  -F 'document=@/path/to/document.pdf'
```

---

## Document attachments

**GET** `/documents/attachments/{type}/{id}/{index}`  
**Auth:** Bearer token (header or query).

Stream a single attachment file (e.g. PDF) for a document. The **url** and **web_view_url** for each attachment are returned in **GET** `/documents/{type}/{id}` under `data.attachments[]`.

**Authentication (use one):**

- **Authorization header:** `Authorization: Bearer <access_token>` (recommended for API clients).
- **Query parameter:** `?token=<access_token>` — use when opening the URL in a browser (e.g. `window.open(attachment.url + '?token=' + accessToken)`) so the file streams and displays inline without sending headers.

**Response:** File stream with `Content-Disposition: inline` and the original filename (PDFs and images display in the browser).

**Attachment fields in document response:**

| Field | Description |
|-------|-------------|
| `url` | API URL to stream the file. Use with Bearer token (header or `?token=`). |
| `web_view_url` | Web URL to stream when the user is logged in via the web app (session auth). Use in the browser from the web UI; no JWT needed. |

---

## Applying an action

**POST** `/actions`  
**Auth:** Bearer required.

Apply one action to **one** document. Does not return the document; use **GET** `/documents/{type}/{id}` to fetch the updated document and approval trails after the action.

**Body (JSON):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | Yes | Document type (e.g. `special_memo`, `matrix`, `activity`) |
| `id` | integer | Yes | Document ID |
| `action` | string | Yes | `approved`, `rejected`, `returned`, or `cancelled` (cancelled = HOD return for special memo only) |
| `comment` | string | No | Remarks (max 1000 characters) |
| `available_budget` | number | No | For activities with budget |

**Example:**

```bash
curl -X POST 'http://localhost/staff/apm/api/apm/v1/actions' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
  -d '{"type":"special_memo","id":19,"action":"approved","comment":"Approved as requested."}'
```

---

## Request/response conventions

- **Content-Type:** Use `Content-Type: application/json` for request bodies when specified.
- **Success:** Successful responses use HTTP 200 (or 201 where applicable) and typically include `"success": true` and a `data` object or array.
- **Errors:** Validation errors return 422 with `message` and optional `errors`. Unauthorized returns 401; forbidden returns 403; not found returns 404.
- **Pagination:** List endpoints that support pagination return `total`, `per_page`, `current_page`, `last_page`, and the current page’s items.

---

## Full specification

For full request/response schemas, validation rules, and all parameters:

- **[APM_API_OPENAPI.yaml](./APM_API_OPENAPI.yaml)** – OpenAPI 3.0 specification.
- **Swagger UI** at `/docs` – try the API from the browser (e.g. `http://localhost/staff/apm/docs`). Use **Authorize** to set the JWT after login.

---

## Related documentation

- [System Updates](./SYSTEM_UPDATES.md) – API summary and changelog.
- [Approval Trail Management](./APPROVAL_TRAIL_MANAGEMENT.md) – How approval trails are stored and used.
- [Signature Verification](./SIGNATURE_VERIFICATION.md) – Validating document signature hashes.
