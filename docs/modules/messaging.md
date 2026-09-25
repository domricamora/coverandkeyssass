# messaging

STATUS: COMPLETE — Phase 25 (Messaging). Verified 2026-09-26: full Pest suite green (245 tests / 1477 assertions).

Code: `App\Modules\Messaging`. It is core (no catalogue module): every business can talk to its guests.

## Conversations

| Kind | Between | Access |
|---|---|---|
| `guest_host` | guest ↔ the property business (about a listing or the guest's booking) | the guest; business members with `messages.view` (`messages.reply` to answer / close) |
| `guest_restaurant` | guest ↔ the restaurant business (about a listing, the guest's order or table) | same |
| `support` | any user ↔ the platform | the user; platform Super Admins |
| `staff` | colleagues inside a business | **only the listed participants** (members of that business) |

- `message_threads` are **not tenant-scoped** (guests and support span businesses), so `MessagingService::canAccess()` / `canReply()` decides every read and write. Foreign threads return 404.
- **Starting a thread** names what it is about: a published listing, or the guest's own booking / order / table reservation (someone else's reference returns 404). An open thread about the same thing is continued, not duplicated.
- **Messages** carry a side (guest / business / platform / staff). **Attachments** (up to 3 × 5 MB, jpg / png / webp / pdf) are stored privately on the `local` disk as `media` and downloaded through `messages.attachment`, which runs the same access check.
- **Read status**: `message_participants.last_read_at` per user (business members join the first time they open a thread). Inboxes show "N new".
- **Notifications** (`NewMessage`, database) go to the other side: the guest, the business members who can reply, Super Admins for support, or the other staff participants.
- **CRM**: guest conversations are logged in the business's communication history (channel `chat`, inbound or outbound, `message:{id}`).
- **Close / reopen**: the guest or a business member with reply rights (support: admins). A closed thread takes no replies.

## Entry points

- Listing pages: "Message the host" / "Message the restaurant".
- Trip page: "Message the property". Order page: "Message the restaurant".
- `/account/messages`: guest inbox, new thread, contact support. The customer nav gains "Messages".
- `/dashboard/messages`: business inbox (open / closed / all) and "message colleagues". The sidebar gains "Messages".
- `/admin/support`: platform inbox, linked from the admin dashboard.

## Permissions

`messages.view`, `messages.reply` (owner, manager, front desk). Staff threads need only participation.

## Tests

`tests/Feature/MessagingTest.php` (4 tests): guest → host with attachment, notifications only to members who can reply, thread continued, unread count, host reply, attachment download, CRM log directions, closed thread refuses replies; access (stranger, someone else's booking, staff without permission, other business); support to Super Admins with reply, non-admin refused; staff thread limited to members and participants, with notifications.
