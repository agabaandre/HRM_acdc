STAFF PORTAL SECURITY GUIDE (NON-TECHNICAL)

Purpose of this document
This guide explains, in simple language, how the Staff Portal protects information, who can access what, and how access is controlled. The goal is to help users trust the system.

How your information is stored safely
1) Information is saved in a secured database on the organization server, not in public pages.
2) Only logged-in users can access protected portal pages.
3) Sensitive actions (like approvals, updates, and permission changes) are recorded so there is an audit trail.
4) File attachments are stored in controlled locations and are not meant to be openly browsed.
5) Upload rules check file type and size before files are accepted.
6) File access is permission-based: users should only view files for documents they are allowed to access.
7) Download/view links are protected (session or token checks in APM), not public file links.
8) If a file fails validation, it is rejected and not saved.
9) Session checks are used so expired or invalid sessions are blocked.

Protection against unauthorized access
The system uses multiple checks before opening protected pages:
- Sign-in check: if a user is not logged in, they are redirected to the login page.
- Permission check: each main portal area has required permission rules.
- Staff profile checks for certain modules: some pages also require a valid staff profile ID.
- Contract-status access check: login access is intended only for staff with contracts that are Active, Under Renewal, or Due.
- Automatic revocation on expiry: when a contract expires, user access is automatically revoked.
- Server-side enforcement: checks happen before the page logic runs, reducing risk of bypass.

In short: even if someone guesses a URL, they still cannot open protected pages unless they are both logged in and authorized.

How uploaded files are protected (simple view)
- Files are checked before upload (allowed formats and max size).
- Files are tied to their parent records (for example, memo or approval document), not shared publicly.
- The system checks who you are before showing attachments.
- Technical controls are used to reduce direct unauthorized browsing of upload folders.
- If access is removed from your role, file access is also removed.

How permissions are granted
The Staff Portal uses a role/group and permission model:
- A user belongs to a user group (role).
- Each group has specific permissions assigned.
- A user can also have user-level permissions where needed.
- Admin users manage this from the Permissions module.
- HR and authorized staff-admin roles can edit staff details as part of their approved responsibilities.
- Individual staff users can edit only their own personal details and cannot edit other staff records.

This means access is not random. It is granted intentionally based on job responsibility.

How sign-in works (plain language)
- By default, users sign in with Office 365 only.
- Only Office 365 accounts from the Africa CDC tenant are allowed.
- External tenants are blocked.
- Username/password login is disabled by default for all users.
- Username/password exists only as a controlled backup option for emergency and troubleshooting cases.

How tokens are managed (plain language)
- The API uses secure access tokens to verify who is making a request.
- Tokens are required for protected API actions and are checked on every call.
- Tokens are short-lived by design and should not be shared in email or chat.
- For browser-based file viewing, the system can use session login or token-protected URLs.
- If a token is invalid, expired, or missing, access is denied.

What roles exist in Staff Portal
Roles are managed as "User Groups" in the Permissions section.
Important: the system is configurable, so role names are not fixed in code.

The actual roles available in your portal are the groups created by administrators in:
Permissions -> User Groups

Examples in many organizations include groups such as Staff, Supervisor, HR, Finance, or Admin, but your live system list is defined by your administrators.

Can roles be assigned and unassigned by admin?
Yes.
Administrators can:
- Assign a user to a group (role)
- Move a user from one group to another
- Grant or remove permissions from a group
- Grant or remove user-specific permissions

So access can be enabled or removed quickly when responsibilities change.

How APM handles security (brief)
APM (the approvals module) follows the same security principles:
- Authentication is required for protected actions.
- Approval actions are permission-controlled and tracked in approval trails.
- Attachment viewing/downloading uses protected endpoints (session or token checks).
- Workflow steps ensure the right approver acts at the right stage.

What this means for users
- Your data is not visible to everyone.
- Access depends on approved role and permissions.
- Actions are traceable.
- Admin can quickly remove access when needed.

If you believe you should have access to a page but do not, contact your administrator to review your group and permissions.

----------------------------------------------------------------
TECHNICAL SECURITY NOTES (FOR IT / AUDIT / DEVELOPERS)
----------------------------------------------------------------

1) Application architecture (security-relevant)
- Staff Portal runs on CodeIgniter (HMVC modules).
- APM runs as a Laravel application under /staff/apm.
- Both applications enforce authenticated sessions before protected actions.

2) Access control model
- Primary authorization model is RBAC-style:
  - user table contains user identity and role/group reference.
  - user_groups defines role/group names.
  - permissions defines available actions/features.
  - user_permissions maps users (and group-level assignments via module logic) to permission IDs.
- Permissions are managed in the Permissions module:
  - Group-level permission assignment
  - User-level permission assignment
  - Add/remove/modify groups

3) Route/segment enforcement in Staff Portal
- A pre-controller hook enforces authorization by URI segment:
  - File: application/hooks/Portal_permission_guard.php
  - Mapping: application/config/portal_segment_permissions.php
- Enforcement behavior:
  - If no active session user: redirect to auth page.
  - If permission rule fails: redirect home with error message.
  - Rules support "all" and "any" permission matching plus extra staff-id checks.
- Benefit: controllers are not constructed for unauthorized segment access.

3.1) Staff-data edit boundary
- Staff profile edit operations are authorization-gated.
- HR (or explicitly authorized administrative roles) can update staff records within granted scope.
- Standard staff users are restricted to self-service updates only (their own profile/record).
- Cross-user profile edits by non-authorized users are denied by permission checks and ownership constraints.

4) Session and request protections
- Session checks are performed server-side.
- Security-sensitive changes (permissions, approvals) require authenticated context.
- CSRF token usage is applied on web form/API-like POST requests in UI modules.
- Validation is applied at controller level before data persistence.

4.1) Identity provider and account-access policy
- Primary authentication is Microsoft Office 365 (Azure AD / Entra ID) and is tenant-restricted to Africa CDC.
- External Azure AD tenants are denied by policy.
- Local username/password authentication is disabled by default system-wide.
- Local credential auth is retained as a controlled fallback path for emergency operations and troubleshooting.
- Access eligibility is tied to contract state: Active, Under Renewal, and Due are allowed states.
- On contract expiry, access revocation is automatic (account no longer authorized to access protected modules).

4.2) Token lifecycle and handling (technical)
- API endpoints require authenticated context established from bearer token and/or validated token query parameter where explicitly supported.
- Token validation is performed server-side per request; invalid/expired tokens are rejected with unauthorized responses.
- Tokens must be treated as secrets in transit and at rest (do not log full tokens in client logs, tickets, or screenshots).
- For web flows, session authentication remains primary; token query usage is limited to controlled scenarios like inline file rendering in browser contexts.
- Revocation/expiry behavior should be aligned with central identity/session policy so terminated or expired-contract users lose effective access.

5) Data handling and persistence safeguards
- Input is validated on create/update endpoints.
- Structured fields (for example JSON payloads) are cast/normalized before storage.
- Attachments are validated by MIME type and size before accepting upload.
- Permission changes and approval actions are recorded (auditability).

5.1) Uploaded file safeguards (technical detail)
- Server-side validation is enforced on upload endpoints (allowed extension/MIME and size limits).
- File metadata is stored with business records (for example attachment arrays/JSON), creating linkage to owning document and workflow.
- APM attachment retrieval is served through guarded controller endpoints (session/JWT/token-aware flows), not unrestricted direct file serving.
- Attachment URLs in API payloads are generated application-side and rely on authenticated access paths.
- Authorization checks at document level are expected before file access is granted (same access boundary as parent document).
- Operational hardening includes restrictive upload-folder web server rules (for example .htaccess policies where configured), to reduce direct execution/browsing risk.

5.2) API file-security controls (technical detail)
- Files are not intended to be accessed by direct public path enumeration; access is mediated by application endpoints.
- Attachment endpoints validate requester identity first, then resolve the parent document and attachment index before streaming.
- Response behavior follows fail-closed patterns:
  - unauthorized if auth/session/token is missing,
  - not found when document or attachment index does not exist,
  - invalid request if attachment path metadata is malformed.
- File paths are normalized and checked to prevent traversal patterns (for example parent-directory escapes).
- File streaming uses controlled content headers and original filename metadata while keeping authorization checks in place.

6) APM-specific controls (high level)
- API and web routes require auth context (session and/or token depending on endpoint).
- Approval operations are workflow-driven and role/actor checked.
- Approval trail entries preserve who acted, what action was taken, and when.
- Attachment access is served via controlled endpoints rather than direct open directory listing.

7) Administrative control points
- Admins can:
  - create/update/delete groups (roles),
  - assign or remove group permissions,
  - assign or remove user-specific permissions,
  - move users between groups.
- This supports least-privilege operations and quick revocation.

8) Security operations recommendations
- Enforce strong password policy and MFA where possible.
- Apply periodic permission review (quarterly minimum).
- Remove stale accounts quickly (offboarding SLA).
- Restrict database root access and avoid app-level use of DB root credentials.
- Keep server patches, PHP, framework dependencies, and SSL/TLS certificates current.
- Centralize logs and alert on repeated denied access attempts.

9) Scope and limitations
- This document is an operational overview and not a full penetration test report.
- For compliance evidence, pair this guide with:
  - current role/permission export,
  - access review sign-off,
  - incident response runbook,
  - backup/restore test records.
