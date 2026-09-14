# Business Units, AI categorization, anonymous Internal Oversight

## Approved approach
- High-level **Business Unit** layer; issue categories belong to a BU.
- Seed BUs: IT & MIS (existing categories), Knowledge Management, Human Resource, Finance, Internal Oversight.
- Routing remains category-based when category is set.
- Setting `show_issue_category_on_request_form` (default off): off → AI categorizes async; on → user picks BU then category.
- Internal Oversight: optional anonymous submit (no requester PII stored).
- Categories gain `ai_description` for AI criteria.
- Image upload: fix storage writability + surface real API errors.

## Out of scope
- Changing agent category routing rules themselves (still category-scoped).
