# MONITORING.md

## Phase 37 — Logging and monitoring (2026-09-26)

Three log streams live in `storage/logs/`, one file per day:

| File | Retention | What |
|---|---|---|
| `laravel-*.log` (default stack) | per `LOG_*` | **Errors.** Every logged exception carries `tenant_id`, `user_id` and the URL. |
| `ops-*.log` | 30 days (`LOG_OPS_DAYS`) | **Business events:** bookings, orders, payments, refunds, payouts, subscription and billing changes, admin actions, webhooks |
| `security-*.log` | 90 days (`LOG_SECURITY_DAYS`) | **Attack and auth signals:** logins, failed logins, lockouts, failed API token requests, invalid webhook signatures |

### Coverage (master plan checklist)

| Area | Where | Source |
|---|---|---|
| Errors | laravel log | Exception handler, plus the tenant, user and URL context |
| Payments | ops | Audit mirror: `payment.checkout_started`, `payment.paid`, `payment.failed` (warning), `payment.amount_mismatch` (warning), `payment.paid_on_inactive_booking` (warning) |
| Bookings | ops | `booking.created` and `booking.<status>` from the state machine. Cancellations and no-shows log at notice level. |
| Orders | ops | `order.*` transitions |
| Webhooks | ops / security | `webhook.paymongo.received`, `.duplicate`, `.malformed` (warning); `.invalid_signature` in **security** |
| Login attempts | security | `auth.login`, `auth.login_failed` (email and whether the account exists, never the password), `auth.lockout` (alert), `api.token_failed` |
| Admin actions | ops | Every `platform.*` audit action |
| Subscription changes | ops | `billing.*` audit actions |
| Refunds | ops | `payment.refunded` and refund transitions (notice) |
| Payouts | ops | `payout.requested`, `payout.<status>` |

### How it works

- `AuditLogger::log()` still writes the full record to `audit_logs`, including old and new values, which the Super Admin audit viewer shows. Every entry is also mirrored to `ops` with **who, where and what only**: action, audit UUID, subject, tenant, user and IP. Old and new values can hold guest details and notes, so they stay in the database.
- Levels are derived from the action name:
  - warning: failed, mismatch, suspend, reject, denied, inactive
  - notice: refund, void, cancel, no-show, delete
  - info: everything else
- Tests (`MonitoringTest`) read the real log files. They check that a failed login is logged without the password, that a forged webhook is a security event, and that a cancelled booking is logged at notice level without the guest's email or notes.

### What to watch in production

- **Uptime:** point an external monitor (UptimeRobot, Better Stack, cPanel's) at `https://your-domain.com/up`, which returns 200 when the app boots.
- **Alerts worth wiring:**
  - `security-*.log`: repeated `auth.lockout` or `webhook.paymongo.invalid_signature` from one IP.
  - `ops-*.log`: any `payment.amount_mismatch`, or a spike in `payment.failed`.
- **Scheduler and backups:** `php artisan schedule:list` shows the next runs. A missing weekly `backup:verify` success line in the cron output means backups are not being proven.
- **Queue:** `php artisan queue:failed` lists failed jobs, which are kept 30 days and pruned daily.
- **Shipping logs off the server:** the channels are standard Monolog daily files. Any agent (Vector, Fluent Bit, Papertrail's remote_syslog) can tail `storage/logs/*.log`, or add a `papertrail` / `syslog` channel to the `LOG_STACK`.
