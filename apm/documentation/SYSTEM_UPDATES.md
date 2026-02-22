# APM System Updates

This document lists notable features, improvements, and changes to the APM (Approvals Management) system.

---

## APM API (v1) and Swagger documentation

**Added:** REST API for the APM module with JWT authentication and OpenAPI/Swagger documentation.

### What’s new

- **APM API v1** (base path: `/api/apm/v1`):
  - **Auth:** Login (email + password, Argon2i-compatible), refresh token, logout, me. Login and refresh return JWT plus user data (user_id, auth_staff_id, email, name, division_id, role, status).
  - **Pending approvals:** `GET pending-approvals` and `GET pending-approvals/summary` — same data and rules as the web pending-approvals page; processed items excluded.
  - **Documents:** `GET documents/{type}/{id}` — document by type (special_memo, matrix, activity, non_travel_memo, service_request, arf, change_request) with **approval_trails** (same structure as matrices/37 and special-memo/19).
  - **Actions:** `POST actions` — approve, reject, return, or cancel (cancel = return when HOD for special memo). Body: type, id, action, optional comment and available_budget.
  - **Approved by me:** `GET approved-by-me` and `GET approved-by-me/average-time` — list of documents approved/rejected by the current user and average approval time (same as dashboard).
  - **Matrices:** `GET matrices/{matrixId}` (detail with activities and passed/pending metadata), `POST matrices/{matrixId}` (approve or return).
  - **Activities:** `GET matrices/{matrixId}/activities/{activityId}` (activity or single-memo detail), `POST` (passed, returned, convert_to_single_memo).
  - **Memo list:** `GET memo-list/pending` and `GET memo-list/approved` — memos for the **authenticated user’s division only**, with filters: year, quarter, memo_type (QM, SM, SPM, NT, CR, SR, ARF), title, document_number, per_page, page.

- **API users:** Synced from the staff app `user` table (same structure: user_id, auth_staff_id, password, name, role, status, etc.). Sync command `php artisan users:sync` runs hourly. API login uses email (from staff work_email) and password (Argon2i hashes supported).

- **Swagger / OpenAPI:**
  - **OpenAPI 3.0 spec:** `documentation/APM_API_OPENAPI.yaml` — full description of all endpoints, request/response schemas, and JWT security.
  - **Swagger UI:** Visit **`/docs`** (e.g. `http://localhost/staff/apm/docs`) to explore and try the API with the interactive UI. Use **Authorize** to set the JWT after login.

### Documentation

- OpenAPI spec: [APM_API_OPENAPI.yaml](./APM_API_OPENAPI.yaml).
- In-app docs: open `/docs` in the browser when the APM app is running.

---

## Signature verification and document validation

**Added:** Validate APM Document Signature Hashes feature.

### What’s new

- **Signature verification page** (nav: Memos → Validate APM Document Signature Hashes):
  - **Validate uploaded document:** Upload an APM PDF; the system extracts document number(s) and signature hashes, validates them against the database, and does **not** store the file. Supports multiple document numbers (e.g. ARF + parent memo).
  - **Look up document & signatory hashes:** Enter document number and year to see the document and all signatories with their verification hashes.
  - **Verify a signature hash:** Enter a hash and document number to see which signatory and action the hash corresponds to.
- All three methods use **AJAX** with a **progress bar** and show results in a single **centered modal** with a **Print** option.
- **Document number–based lookup:** Document numbers (e.g. `AU/CDC/SDI/IM/SM/011`) are parsed so the correct base table (activities, special_memos, non_travel_memos, request_arfs, change_request, service_requests) is queried.
- **Activity / matrix documents:** Signatories are built from both `approval_trails` (morph) and `activity_approval_trails`, so hashes from the main signature section and budget section of PDFs can be validated.
- **Multiple document numbers:** When a PDF contains more than one document number (e.g. ARF and parent memo), all are resolved and signatories are merged; validation succeeds if a hash matches any of those documents.
- **Metadata in results:** Document metadata includes creator, division, date created, and **activity title** (when available).
- **PDF dependency:** Upload validation uses `smalot/pdfparser` for text extraction; install with `composer require smalot/pdfparser` if needed.

### Documentation

- Full feature guide: [Signature Verification](./SIGNATURE_VERIFICATION.md).

---

*Add new entries above this line, with a clear heading and date or version if applicable.*
