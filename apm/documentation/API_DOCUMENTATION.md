# APM API Documentation

REST API for the Approvals Management (APM) module. Used by approver apps and integrations to list pending approvals, view documents with approval trails, perform actions (approve/reject/return), and access memo lists.

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
- **Full base URL** depends on where the app is installed, for example:
  - Local: `http://localhost/staff/apm/api/apm/v1`
  - Production: `https://your-domain.example.com/apm/api/apm/v1`

The Swagger UI at `/docs` uses the correct server URL for the host you open it on.

---

## Authentication

- **Method:** JWT (Bearer token).
- **API users** are synced from the staff app `user` table into `apm_api_users` (command: `php artisan users:sync`). Login uses **email** (from staff work email) and **password** (Argon2i hashes, same as CodeIgniter).

### Login

```bash
curl -X POST 'http://localhost/staff/apm/api/apm/v1/auth/login' \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"your_password"}'
```

**Response:** `success`, `data.access_token`, `data.token_type` (bearer), `data.expires_in`, `data.user` (user_id, auth_staff_id, email, name, division_id, role, status).

### Using the token

Send the token in the `Authorization` header for all protected endpoints:

```bash
curl -X GET 'http://localhost/staff/apm/api/apm/v1/auth/me' \
  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
```

### Refresh token

```bash
curl -X POST 'http://localhost/staff/apm/api/apm/v1/auth/refresh' \
  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
```

Returns a new access token and user data.

### Logout

Logout can be called **with or without** a token. Always returns 200 so clients can clear the token safely.

```bash
curl -X POST 'http://localhost/staff/apm/api/apm/v1/auth/logout' \
  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
# or without token (still returns 200)
curl -X POST 'http://localhost/staff/apm/api/apm/v1/auth/logout'
```

---

## Endpoints overview

| Area | Method | Path | Description |
|------|--------|------|-------------|
| **Auth** | POST | `/auth/login` | Login (email + password) |
| **Auth** | POST | `/auth/refresh` | Refresh JWT |
| **Auth** | POST | `/auth/logout` | Logout (optional token) |
| **Auth** | GET | `/auth/me` | Current user |
| **Pending** | GET | `/pending-approvals` | Pending items **with approval_trails**, grouped by category |
| **Pending** | GET | `/pending-approvals/summary` | Summary counts only |
| **Documents** | GET | `/documents/{type}/{id}` | Full document **with approval_trails** |
| **Actions** | POST | `/actions` | Apply action (approve/reject/return/cancel) to one document |
| **Approved by me** | GET | `/approved-by-me` | Documents approved/rejected by current user |
| **Approved by me** | GET | `/approved-by-me/average-time` | Average approval time |
| **Matrices** | GET | `/matrices/{matrixId}` | Matrix detail |
| **Matrices** | POST | `/matrices/{matrixId}` | Approve or return matrix |
| **Activities** | GET | `/matrices/{matrixId}/activities/{activityId}` | Activity/single-memo detail |
| **Activities** | POST | `/matrices/{matrixId}/activities/{activityId}` | Pass, return, or convert to single memo |
| **Memo list** | GET | `/memo-list/pending` | Pending memos (division-scoped) |
| **Memo list** | GET | `/memo-list/approved` | Approved memos (division-scoped) |

---

## Pending approvals and approval trails

- **GET /pending-approvals** returns memos (and other document types) pending the current user’s action. **Each item includes `approval_trails`**: the full history of who approved/returned/rejected and when.
- **GET /documents/{type}/{id}** returns a single document with full detail and **`approval_trails`** in the same shape (id, action, remarks, approval_order, staff_id, staff_name, oic_staff_id, oic_staff_name, role, created_at, is_archived).

**Document types:** `special_memo`, `matrix`, `activity`, `non_travel_memo`, `service_request`, `arf`, `change_request`.

---

## Applying an action

**POST /actions** applies one action to **one** document. It does **not** return the document; use **GET /documents/{type}/{id}** to fetch the updated document and approval trails after an action.

**Body (JSON):**

- `type` (required): document type (e.g. `special_memo`, `matrix`, `activity`).
- `id` (required): document ID.
- `action` (required): `approved`, `rejected`, `returned`, or `cancelled` (cancelled = HOD return for special memo only).
- `comment` (optional): remarks, max 1000 characters.
- `available_budget` (optional): for activities with budget.

**Example:**

```bash
curl -X POST 'http://localhost/staff/apm/api/apm/v1/actions' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
  -d '{"type":"special_memo","id":19,"action":"approved","comment":"Approved as requested."}'
```

---

## Memo list (division-scoped)

- **GET /memo-list/pending** and **GET /memo-list/approved** return memos for the **authenticated user’s division only**.
- Query parameters: `year`, `quarter`, `memo_type` (QM, SM, SPM, NT, CR, SR, ARF), `title`, `document_number`, `per_page`, `page`.

---

## Full specification

For request/response schemas, validation rules, and all parameters, see:

- **[APM_API_OPENAPI.yaml](./APM_API_OPENAPI.yaml)** – OpenAPI 3.0 specification.
- **Swagger UI** at `/docs` – try the API from the browser (e.g. `http://localhost/staff/apm/docs`). Use **Authorize** to set the JWT after login.

---

## Related documentation

- [System Updates](./SYSTEM_UPDATES.md) – API summary and changelog.
- [Approval Trail Management](./APPROVAL_TRAIL_MANAGEMENT.md) – How approval trails are stored and used.
- [Signature Verification](./SIGNATURE_VERIFICATION.md) – Validating document signature hashes.
