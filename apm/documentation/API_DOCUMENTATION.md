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
4. [Request/response conventions](#requestresponse-conventions)
5. [Full specification](#full-specification)

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

`data.user` includes: `user_id`, `auth_staff_id`, `email`, `name`, `division_id`, `associated_divisions` (array of division IDs), `role`, `status`, `is_division_head`, `is_admin_assistant`, `is_director`, `is_finance_officer`.

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
| GET | `/pending-approvals` | Pending items with approval trails (optional `?category=all`) |
| GET | `/pending-approvals/summary` | Summary counts only |
| GET | `/documents/{type}/{status}` | List documents by type and status (filters: year, quarter, title, document_number, per_page, page) |
| GET | `/documents/{type}/{id}` | Single document with approval trails and attachments |
| GET | `/documents/attachments/{type}/{id}/{index}` | Download one attachment (use `url` from document response) |
| POST | `/actions` | Apply action (approved, rejected, returned, cancelled) to one document |
| GET | `/approved-by-me` | Documents approved/rejected by current user |
| GET | `/approved-by-me/average-time` | Average approval time |
| GET | `/matrices/{matrixId}` | Matrix detail (includes division_schedule and activities with internal_participants as lists with names) |
| POST | `/matrices/{matrixId}` | Approve or return matrix |
| GET | `/matrices/{matrixId}/activities/{activityId}` | Activity detail |
| POST | `/matrices/{matrixId}/activities/{activityId}` | Pass, return, or convert to single memo |
| GET | `/memo-list/pending` | Pending memos for user’s divisions (primary + associated) |
| GET | `/memo-list/approved` | Approved memos for user’s divisions (primary + associated) |
| GET | `/reference-data` | Lookup data (divisions, staff, fund codes, etc.). Optional `?include=divisions,staff` |

**Document types:** `special_memo`, `matrix`, `activity`, `non_travel_memo`, `service_request`, `arf`, `change_request`.

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
