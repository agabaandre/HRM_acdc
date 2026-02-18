# APM Document Signature Verification

This guide describes the **Validate APM Document Signature Hashes** feature, which allows users to verify that signature hashes on APM documents (PDFs) match the system’s approval records.

## Overview

APM documents (memos, ARFs, activities, etc.) show a **verification hash** next to each signatory on the PDF. This hash is generated from the document ID, signatory staff ID, and approval date/time. The signature verification tool lets you:

1. **Look up** a document by document number and year to see all signatories and their hashes.
2. **Verify** a single hash with a document number to see which signatory and action it corresponds to.
3. **Validate an uploaded PDF** by extracting document number(s) and hashes from the file and checking them against the system (the file is not stored).

All verification methods submit via AJAX, show a progress indicator, and display results in a single **modal** with an option to **print**.

## Accessing the Feature

- **Navigation:** In the APM sidebar, under **Memos**, click **Validate APM Document Signature Hashes** (or go to `/signature-verify`).
- **Permissions:** Same as the rest of APM (session required).

## Document Number Format

Document numbers follow the format:

**`AU/CDC/{division}/IM/{type}/{counter}`**

Examples:

- `AU/CDC/SDI/IM/SM/011` — Single Memo (SM), counter 011
- `AU/CDC/DHIS/IM/SPM/002` — Special Memo (SPM)
- `AU/CDC/DHIS/IM/NT/001` — Non-Travel Memo (NT)
- `AU/CDC/DHIS/IM/ARF/003` — Request for ARF (ARF)
- `AU/CDC/DHIS/IM/CR/001` — Change Request (CR)
- `AU/CDC/DHIS/IM/SR/001` — Service Request (SR)
- `AU/CDC/DHIS/IM/QM/001` — Quarterly Matrix activity (QM)

The **type** segment identifies the document table (activities, special_memos, non_travel_memos, request_arfs, change_request, service_requests). Lookup uses this to query the correct table when the full document number is provided.

## Verification Methods

### 1. Validate Uploaded Document (first option)

- **Purpose:** Validate a PDF without storing it. The system reads the file, extracts document number(s) and hashes, then checks them against the database.
- **Steps:**
  1. Click **Validate uploaded document**.
  2. Choose a PDF (max 10 MB). Only PDF is accepted.
  3. Click **Validate document**.
- **Behaviour:**
  - File is processed in memory/temp and **not saved** on the server.
  - Text is extracted (requires `smalot/pdfparser`). If the library is missing, run: `composer require smalot/pdfparser`.
  - Extracted **document numbers** (e.g. `AU/CDC/.../IM/SM/011`) and **hashes** (16-character hex after “Verify Hash:” or “Hash:”) are validated.
  - If **multiple** document numbers are found (e.g. ARF + parent memo), the system finds **all** matching documents and merges their signatories. A hash is considered **valid** if it matches any signatory from any of those documents.
- **Result:** Modal shows:
  - **Final state: Valid** or **Not valid** (valid if at least one extracted hash matched).
  - Document number(s) and hash(es) found in the PDF.
  - For each matched document: type, document number, **activity title** (if present), creator, division, date created.
  - Hash validation table (each hash: Valid / No match, and signatory if matched).
  - Full signatories table with hashes (and document column when multiple documents).

### 2. Look Up Document & Signatory Hashes

- **Purpose:** Get all signatories and their verification hashes for a known document.
- **Steps:**
  1. Enter **document number** (e.g. `AU/CDC/SDI/IM/SM/011`).
  2. Enter **year of creation** (e.g. 2026).
  3. Click **Look up**.
- **Result:** Modal shows document metadata (type, number, activity title if any, creator, division, date created) and a table of all signatories with role, name, action, date/time, and **verify hash**.

### 3. Verify a Signature Hash

- **Purpose:** Check a single 16-character hash against a document to see which signatory and action it represents.
- **Steps:**
  1. Enter the **verification hash** (e.g. from a PDF).
  2. Enter **document number**.
  3. Optionally enter **year** (if omitted, all years are searched).
  4. Click **Verify hash**.
- **Result:** If the hash matches a signatory, the modal shows document metadata and the **matched signatory** (role, name, action, date, hash). If no match, the modal still shows the document and its signatories so you can compare.

## How the Hash Is Generated

The verification hash is computed in `App\Helpers\PrintHelper::generateVerificationHash()`:

- **Inputs:** document ID (e.g. activity id), staff ID (or OIC staff ID), and approval **date/time** (from the approval trail `created_at`).
- **Formula:** `strtoupper(substr(md5(sha1($itemId . $staffId . $dateTime)), 0, 16))`.
- **Date format:** Stored and compared as `Y-m-d H:i:s` (e.g. `2026-02-11 01:35:18`).

The same logic is used when generating hashes on PDFs (e.g. in `PrintHelper::renderBudgetSignature()` and related views), so hashes on the PDF and in the verification tool stay in sync.

## Document Types and Approval Trails

- **Activity (matrix memo / single memo):** Signatories are taken from **both** `approval_trails` (morph) and `activity_approval_trails`, so hashes from the main signature section and the budget section of the PDF can both be validated.
- **Special Memo, Non-Travel Memo, Change Request, Request ARF, Service Request:** Signatories come from the `approval_trails` morph table.

Metadata shown for each document includes **activity title** when the model has it (e.g. Activity, ChangeRequest, NonTravelMemo, SpecialMemo, RequestARF).

## Multiple Document Numbers (ARF / Service Request)

ARFs and service requests can carry both their own document number and the parent memo’s document number. When validating an **uploaded** PDF:

- **All** extracted document numbers are used to find matching documents.
- Signatories from **all** found documents are merged (deduplicated by hash).
- Each extracted hash is checked against this combined list.
- The modal shows **Documents matched (N)** when N > 1, with metadata (including activity title) per document, and a **Document** column in the signatories table when multiple documents are present.

## Modal and Print

- All results (lookup, verify, upload validation) are shown in one **centered modal**.
- **Print** prints only the modal result content (headers/footers hidden via print CSS).
- Submissions are **AJAX** with a **progress bar**; the page does not reload.

## Technical Notes

- **Routes:** `GET /signature-verify` (index), `POST /signature-verify/lookup`, `POST /signature-verify/verify`, `POST /signature-verify/validate-upload`.
- **Controller:** `App\Http\Controllers\SignatureVerificationController`.
- **PDF parsing:** `smalot/pdfparser`; optional. Without it, upload validation fails with a message to install the package.
- **JSON payload:** All three actions support `Accept: application/json` / AJAX and return a unified structure (document, metadata, signatories, hash_validations, etc.) for the modal.

## Troubleshooting

| Issue | What to do |
|-------|------------|
| “Could not read the PDF” on upload | Ensure the PDF is not encrypted and is a valid PDF. Run `composer require smalot/pdfparser` if the library is missing. |
| No signatories / no hashes on lookup | For activities, both `approval_trails` and `activity_approval_trails` are used; if both are empty, there are no signatories. For other types, check that the document has approval trail records. |
| Hash does not match | Confirm the hash is exactly 16 hex characters and matches the document number. Check that the PDF was generated by this system and not edited. |
| Modal not centered | The view includes centering classes and CSS for `#verificationResultModal`; if your theme overrides `.modal`, adjust the modal container styles. |

---

**Related documentation**

- [Document Numbering System](./DOCUMENT_NUMBERING_SYSTEM.md) — How document numbers are generated and parsed.
- [Approval Trail Management](./APPROVAL_TRAIL_MANAGEMENT.md) — How approval trails are stored and used.
