# notifications

STATUS: COMPLETE — Phase 26 (Notifications). Verified 2026-09-26: full Pest suite green (251 tests).

Code: `App\Modules\Notify`. It is core: no catalogue module.

## How a notification is delivered

Every notification extends `App\Modules\Notify\ChannelNotification`. A subclass supplies `event()`, `message()`, and optionally `link()` and `data()`. The base class then picks the channels:

| Channel | When it is used |
|---|---|
| in-app (`database`) | always |
| email (`mail`) | the user has an email address, and their preference for the event, or the event's default, allows it |
| SMS (`SmsChannel`) | the user has a phone number and the channel is allowed. It is sent through `Marketing\Support\SmsSender` (`services.sms.driver`) |
| push (`PushChannel`) | the channel is allowed and the user has at least one row in `push_devices`. The message is written to the `push_messages` outbox |

The event catalogue, with each event's audience and default channels, is `Notify\Support\Events::ALL`. The in-app payload always holds `event`, `message` and `link`, plus the subclass's own keys. Keys that existed before (`booking_reference` etc.) are unchanged.

## Events

- **Guest events**: booking confirmed / cancelled, payment received / failed, order update / ready / delivery, table reservation update, review reply, new message.
- **Staff events**: new online order (to members with `orders.view`), housekeeping task, maintenance ticket, low stock, and module trial expiring (to owners).

What triggers the Phase 26 notifications:

- `Payments\Events\PaymentPaid` sends `PaymentReceived`.
- `Payments\Events\PaymentFailed` sends `PaymentFailed`. It is dispatched once, by `PaymentService::markFailed`.
- `Ordering\Events\OrderPlaced` sends `OrderReceived`. It is dispatched by `OrderService::place` (online checkout only, not POS).
- `php artisan notifications:trials` sends `TrialExpiring` to owners when a trial ends within 3 days. It sends at most once per owner, per subscription, per day.

## Screens and routes

- `/dashboard/notifications`: the staff notification centre. It has mark all read, and a sidebar link with an unread badge.
- `/notifications/{id}/open`: marks the notification read and redirects to its link. Only same-site links are followed, and only the owner's own rows are found (any other id is a 404).
- `/account/notification-settings`: an event × channel matrix. Staff events appear only for business members.
- `POST` / `DELETE /account/push-devices` (JSON: `token`, `platform` ios|android|web): lets the mobile app register or remove a device. A token moves to whoever last signed in on the device.

## Open items

- The push outbox has no sender yet. Add an FCM / APNs worker that reads `push_messages` rows where `sent_at` is null.
- Schedule `notifications:trials` to run daily.
