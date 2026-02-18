# APM System Updates

This document lists notable features, improvements, and changes to the APM (Approvals Management) system.

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
