# Firebase Push Notifications (FCM) for Pending Approvals

API and mobile app users can receive push notifications for pending approvals. The app registers a Firebase Cloud Messaging (FCM) device token with the API; the server sends notifications using the Firebase HTTP v1 API (no third-party PHP SDK required).

---

## Setup

### 1. Firebase project

1. In [Firebase Console](https://console.firebase.google.com/), create or select a project.
2. Enable **Cloud Messaging** (no extra setup for HTTP v1).
3. Go to **Project settings** → **Service accounts** → **Generate new private key**. Download the JSON file.

### 2. Laravel configuration

Add to `.env`:

```env
FIREBASE_PROJECT_ID=your-firebase-project-id
FIREBASE_CREDENTIALS=/absolute/path/to/your-service-account.json
```

Or place the service account JSON at `storage/app/firebase-credentials.json` and set:

```env
FIREBASE_PROJECT_ID=your-firebase-project-id
# FIREBASE_CREDENTIALS defaults to storage/app/firebase-credentials.json
```

Config is in `config/services.php` under `firebase`.

### 3. Database

The `apm_api_users` table has a nullable `firebase_token` column (migration: `2026_03_09_000001_add_firebase_token_to_apm_api_users_table`). Run migrations if needed:

```bash
php artisan migrate
```

---

## API

- **Register/update token:** `PUT` or `POST` `/api/apm/v1/me/firebase-token` with JWT and body `{ "token": "FCM_DEVICE_TOKEN" }`.  
  Clear with `{ "token": "" }` or `{ "token": null }`.
- See [API_DOCUMENTATION.md](./API_DOCUMENTATION.md) and the OpenAPI spec for full details.

---

## Sending notifications

Run all commands from the **`apm/`** directory (where `artisan` lives).

### Option A: Test command (recommended for manual checks)

Use this to verify Firebase config, see who has tokens and pending counts, and **send pushes immediately** without a queue worker (default).

```bash
# Show help
php artisan notifications:test-fcm-pending-approvals --help

# List API users with FCM tokens and pending counts (no send)
php artisan notifications:test-fcm-pending-approvals --dry-run

# Send FCM now (sync) to every eligible user with pending > 0
php artisan notifications:test-fcm-pending-approvals

# Only one API user (apm_api_users.user_id)
php artisan notifications:test-fcm-pending-approvals --user=123

# Same as production: enqueue jobs (requires queue:work)
php artisan notifications:test-fcm-pending-approvals --queue
```

Implementation: `app/Console/Commands/TestFcmPendingApprovalsCommand.php`.

### Option B: Production batch command

Send “pending approvals” push to all API users who have a token and have pending items:

```bash
php artisan notifications:send-pending-approvals-fcm
```

Options:

- `--sync` — send immediately (no queue). Omit to dispatch queued jobs.
- `--user=123` — only send to API user with `user_id` 123.

Schedule (add to your app scheduler if not already present):

- Laravel 11: `bootstrap/app.php` → `withSchedule(...)`
- Or: `app/Providers/ScheduleServiceProvider.php` → `schedule()`

Example:

```php
$schedule->command('notifications:send-pending-approvals-fcm')
    ->dailyAt('09:00')
    ->timezone('Africa/Addis_Ababa')
    ->withoutOverlapping();
```

Ensure a queue worker is running when **not** using `--sync`:

```bash
php artisan queue:work
```

Also ensure **Laravel’s scheduler** runs every minute:

```bash
* * * * * cd /path/to/apm && php artisan schedule:run >> /dev/null 2>&1
```

### Option C: From code

Inject `App\Services\FirebaseMessagingService` and call:

- `sendToToken($token, $title, $body, $data)` for a custom message.
- `sendPendingApprovalsNotification($token, $count, $deepLink)` for the standard “You have N items waiting for your approval” message.

To dispatch a job for one user:

```php
use App\Jobs\SendPendingApprovalsFcmJob;
use App\Models\ApmApiUser;

$user = ApmApiUser::find($userId);
if ($user && $user->firebase_token) {
    SendPendingApprovalsFcmJob::dispatch($user);
}
```

---

## Payload

Pending-approvals notifications include:

- **Notification:** title “Pending Approvals”, body “You have N item(s) waiting for your approval.”
- **Data:** `type` = `pending_approvals`, `count` = string count, optional `url` = deep link (e.g. `https://yoursite/staff/apm/pending-approvals`).

The app can use `type` and `url` to open the pending-approvals screen.
