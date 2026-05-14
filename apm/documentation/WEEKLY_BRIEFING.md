# Weekly brief (Weekly briefing module)

This guide describes the **Weekly brief** feature in APM: ISO-week–based division and directorate reporting, PDFs, reminders, compiled email packs, and access rules.

## Purpose

- Units (divisions or directorates) submit a structured **weekly brief** for a **reporting week** (Monday–Sunday, identified by ISO week numbers in the database). The **default filing week** on the hub (index tab, Start links, reminders, compiled send) is either the **current** or **next** ISO week — see **Weekly briefing settings** (`filing_iso_week_offset`).
- **Contributors** file drafts; after the submission deadline, drafts are **locked** unless an administrative unlock applies.
- **Directorate directors** (`directorates.director_id`) may review submitted briefs for configured reporting units in their directorate (including legacy `d-*` keys mapped via `divisions.directorate_id`).
- Central recipients receive a **compiled** summary email and PDFs according to settings.

## Who can see the module

Access is enforced by `App\Services\DivisionWeeklyBriefGate` (nav, routes, and report actions).

| Audience | Access |
|----------|--------|
| **System admin** (role `10`) | Full access, including **Weekly briefing settings**. |
| **Configured contributors** | Rows in `weekly_briefing_contributors` for their `staff_id` and `contribution_key`. |
| **Report viewers** | `report_viewer_staff_ids` on settings: read all configured units’ reports (and compiled exports where allowed). |
| **Directorate directors** | When **`division_directors_can_access_module`** is enabled (name is legacy): staff listed as **`directorates.director_id`** on at least one **active** directorate. Session staff id is resolved from `staff_id` or `auth_staff_id`. |

The top menu item **Weekly brief** and the home dashboard card are shown when `DivisionWeeklyBriefGate::canAccessModule()` is true (`App\Providers\AppServiceProvider` passes `showDivisionWeeklyBriefNav` into `layouts.partials.nav`).

## Routes (web)

| Method | Path (relative to APM base) | Name | Notes |
|--------|-----------------------------|------|--------|
| GET | `weekly-briefing` | `weekly-briefing.index` | Tabs: **This reporting week** (uses configured filing ISO week) / **All reports**; filters and pagination. |
| GET | `weekly-briefing/create` | `weekly-briefing.create` | Start a report (`contribution_key` query). |
| GET/PUT | `weekly-briefing/{report}/edit`, `weekly-briefing/{report}` | `weekly-briefing.edit`, `weekly-briefing.update` | Draft / submitted editing per rules. |
| POST | `weekly-briefing/{report}/director-review` | `weekly-briefing.director-review` | Record director review when applicable. |
| GET | `weekly-briefing/{report}/pdf` | `weekly-briefing.pdf` | Single-unit PDF. |
| GET | `weekly-briefing/compiled/{year}/{week}/pdf` | `weekly-briefing.compiled-pdf` | Organisation-wide compiled PDF (allowed roles). |
| GET | `weekly-briefing/compiled/{year}/{week}/completion-pdf` | `weekly-briefing.completion-summary-pdf` | Completion summary PDF. |
| GET | `weekly-briefing/directorate-combined/{year}/{week}/{directorate_id}/pdf` | `weekly-briefing.directorate-combined-pdf` | Director-scoped combined PDF. |
| GET/PUT | `weekly-briefing/settings` | `weekly-briefing.settings.edit`, `weekly-briefing.settings.update` | **Admin only** (role 10). |

## Contribution keys

- **Division brief**: `d-{apm_division_id}` (matches `divisions.id` / APM division).
- **Directorate brief**: `dr-{directorate_id}`.

Contributors and listing keys are driven by **`weekly_briefing_contributors`** linked to **`weekly_briefing_settings`**. Each hub row is one contributor record. **Division** type always uses **`d-{division_id}`** from the contributing division. **Directorate** type uses **`d-{division_id}`** when a contributing division is selected (validated against the directorate), or **`dr-{directorate_id}`** when the division is left blank **and** a **PDF display name** is set (single directorate-level brief). Use **PDF display name** to override labels on the hub and PDFs.

## Settings (admin)

**Route:** `weekly-briefing/settings` (`WeeklyBriefingSettingsController`).

Typical options include:

- **Submission weekday** and times: HoD reminder, submission close, compiled/summary send time.
- **HoD / contributor deadline reminders**: `hod_reminder_days_before_deadline` (JSON list of whole days **before** the submission deadline, e.g. `1, 0`) and `hod_reminder_clock` (`submission_close_time` or `hod_reminder_time`) — the scheduler matches **calendar day** and **H:i** on that clock column. Default offsets **1** and **0** (day before and deadline day).
- **Director review deadline reminders**: `director_review_reminder_days_before_deadline` and `director_review_reminder_clock` — same pattern for emails to **directorate directors** about **submitted** briefs still pending review; stops after the deadline.
- **Compiled PDF filter**: `compiled_exclude_unreviewed_director_divisions` — when enabled, the organisation-wide compiled PDF and central compiled attachment omit submitted briefs (`d-*` and `dr-*`) that require director review but are not yet marked reviewed (default **off** / include all).
- **Default reporting week (HoDs file for)**: `filing_iso_week_offset` — **0** = ISO week that contains “today”; **1** = the **next** ISO week (e.g. brief ahead for the coming week). Drives the index “This reporting week” tab, default `create` targets, `weekly-briefing:hod-reminders`, `weekly-briefing:director-review-reminders`, and `weekly-briefing:compiled-summary` week selection. When **1**, `WeeklyBriefingReport::submissionDeadline()` is the **Friday immediately before** that reporting week’s Monday (same clock as **Submission closes**), not the **submission weekday** inside the reporting week.
- **Reminders** on/off.
- **Directorate director access** (setting key `division_directors_can_access_module`): allow **directorate directors** to open the module and review configured units.
- **Report viewers**: JSON list of `staff_id` values with read-only access across units.
- **Compiled recipients** and whether to CC division HoDs on the compiled send.
- **Late submission unlock** (administrative): optional window to allow edits/submissions past the normal lock (all units or one division).

Settings row selection uses `WeeklyBriefingSetting::current()` (deterministic: prefers the most recently updated row when duplicates exist).

## Report lifecycle (summary)

1. **Draft** — contributor (or permitted director edit) works on the brief.
2. **Submitted** — contributor submits before the deadline (or during an active unlock).
3. **Locked** — after `submissionDeadline($settings)`; `weekly-briefing:lock-drafts` sets draft rows to locked unless an unlock applies to that report.
4. **Director review** — when the **directorate** has `directorates.director_id` set and the brief is submitted, that director may record review via the UI / `director-review` endpoint; trail fields on `weekly_briefing_reports` store audit data.

Statuses and helpers live on `App\Models\WeeklyBriefingReport`.

## PDFs and services

- **Division / directorate PDF**: `WeeklyBriefingController::pdf` and views under `resources/views/weekly-briefing/`.
- **Compiled pack** and **completion summary**: `WeeklyBriefingCompletionSummary`, compiled PDF views.
- **Director combined PDF** (submitted `dr-*` and `d-*` briefs under a directorate for the **directorate director**): `App\Services\WeeklyBriefingDirectorateCombined`.

## Email

- Templates use `App\Support\WeeklyBriefingMailTemplate` and Blade under `resources/views/emails/` (e.g. weekly briefing notification).
- Compiled / reminder behaviour is implemented in `WeeklyBriefingCompiledSummaryCommand`, `WeeklyBriefingHodRemindersCommand`, and `WeeklyBriefingDirectorReviewRemindersCommand` (see below).
- When a **contributor** submits a brief that **requires director review** (the **directorate** for the reporting unit has `directorates.director_id`), `WeeklyBriefingController::update` dispatches `SendWeeklyBriefingDirectorReviewReminderJob`, which emails that director’s `work_email` via `sendEmail` + `WeeklyBriefingMailTemplate` (`WeeklyBriefingDirectorSubmitNotifier`). Skipped if submitter is the same as the director or the director has no email. Applies to both `d-*` (via `divisions.directorate_id`) and `dr-*` keys.

## Artisan commands

| Command | Purpose |
|---------|---------|
| `weekly-briefing:hod-reminders` | Reminds contributors (and related staff) about **missing** briefs for the configured filing ISO week. Without `--force`, respects `reminders_enabled`, **deadline-relative days** (`hod_reminder_days_before_deadline`), and **`hod_reminder_clock`** (which stored time column to match at minute precision). Sends stop after the submission deadline. |
| `weekly-briefing:director-review-reminders` | Reminds **directorate directors** about **submitted** briefs still pending director review. Without `--force`, respects `reminders_enabled`, `director_review_reminder_days_before_deadline`, and `director_review_reminder_clock`; stops after the deadline. |
| `weekly-briefing:lock-drafts` | Locks **draft** reports past their submission deadline (skips rows covered by **report unlock override**). |
| `weekly-briefing:compiled-summary` | Sends compiled / summary emails per settings; without `--force`, respects **`reminders_enabled`**, the **calendar day of the filing week submission deadline** (same rule as `WeeklyBriefingSetting::filingSubmissionDeadline()` / advance filing), and **`summary_send_time`**. Organisation-wide compiled PDF honours **`compiled_exclude_unreviewed_director_divisions`**. |
| `weekly-briefing:test-notifications` | Sends **sample** weekly-brief emails to a given address to verify SMTP. |

## Scheduler (Laravel)

In **`bootstrap/app.php`**, the scheduler runs (each with overlap protection):

- `weekly-briefing:hod-reminders` — every minute (command self-gates on deadline-relative day + time).
- `weekly-briefing:director-review-reminders` — every minute (same pattern; only when there is pending director review work).
- `weekly-briefing:lock-drafts` — every minute.
- `weekly-briefing:compiled-summary` — every minute.

Production still requires **`php artisan schedule:run`** in cron (see [Cron Setup](./CRON_SETUP.md)).

## Jobs screen (admin)

- **POST** `jobs/weekly-briefing` (`jobs.weekly-briefing`) — triggers test or operational weekly-brief mail actions from the Jobs UI (test inbox, forced HoD reminders, forced director review reminders, forced compiled summary; whitelist / role checks in `JobsController`).

## PDF footer: document URL and QR (global `generate_pdf`)

All PDFs generated through **`generate_pdf()`** / **`mpdf_print()`** in `app/Helpers/home_helper.php` share the same HTML footer:

- **Document URL** for the QR is taken from, in order: options **`document_url`** or **`source_url`**, else the current HTTP **`request()->url()`**, else **`config('app.url')`**.
- Activity memo PDFs pass **`document_url`** explicitly (`ActivityController::buildActivityMemoPdfForOutput`) so the QR encodes the canonical **memo PDF** URL even when the PDF is built from another route (e.g. email).
- The footer layout is a **two-column** outer table (postal address | meta + QR). The meta block uses an **inner table**: **QR on the left**, **Source / Generated on / By** on the right; that block is **right-aligned** in the cell via an `inline-block` wrapper. QR display size is tuned in `home_helper.php` (Endroid `QrCode` `size` and CSS `mm` width/height).

If QR generation fails (e.g. missing GD), the footer falls back to a small **plain-text URL** in the QR column.

## Key code locations

| Area | Path |
|------|------|
| Gate / listing keys | `app/Services/DivisionWeeklyBriefGate.php` |
| Directorate combined PDF logic | `app/Services/WeeklyBriefingDirectorateCombined.php` |
| Completion summary rows | `app/Services/WeeklyBriefingCompletionSummary.php` |
| Deadline / unlock | `app/Services/WeeklyBriefingWindowService.php` |
| HTTP controller | `app/Http/Controllers/WeeklyBriefingController.php` |
| Settings | `app/Http/Controllers/WeeklyBriefingSettingsController.php` |
| Artisan (HoD / director / compiled mail) | `app/Console/Commands/WeeklyBriefingHodRemindersCommand.php`, `WeeklyBriefingDirectorReviewRemindersCommand.php`, `WeeklyBriefingCompiledSummaryCommand.php` |
| Models | `app/Models/WeeklyBriefingSetting.php`, `WeeklyBriefingReport.php`, `WeeklyBriefingContributor.php` |
| Nav composer | `app/Providers/AppServiceProvider.php` |

## Troubleshooting

- **Director does not see Weekly brief in the nav**  
  - Enable **directorate director access** (`division_directors_can_access_module`) in settings.  
  - Confirm `staff_id` / `auth_staff_id` in session matches **`directorates.director_id`** on an active directorate.  
  - Ensure **`WeeklyBriefingSetting::current()`** is the row you edit (avoid duplicate settings rows with conflicting flags).

- **Reminders or compiled mail never fire**  
  - Check **`reminders_enabled`**, **times**, and for HoD/director mails the **days-before-deadline** list and **clock** (submission close vs HoD reminder time); use `--force` on the Artisan command once to verify mail outside the window.

---

**See also:** [Jobs and Commands](./JOBS_AND_COMMANDS_DOCUMENTATION.md) (scheduler overview), [README index](./README.md).
