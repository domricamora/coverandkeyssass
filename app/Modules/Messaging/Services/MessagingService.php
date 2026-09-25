<?php

namespace App\Modules\Messaging\Services;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Crm\Services\CrmService;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Messaging\Models\Message;
use App\Modules\Messaging\Models\Participant;
use App\Modules\Messaging\Models\Thread;
use App\Modules\Messaging\Notifications\NewMessage;
use App\Modules\Ordering\Models\Order;
use App\Modules\RestaurantManagement\Models\TableReservation;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Messaging (Phase 25). Threads are not tenant-scoped (guests and support
 * span businesses), so this service is the one place that decides who may
 * read and write a thread:
 *
 *   guest ↔ host / restaurant   the guest, and members of the business with messages.view (reply: messages.reply)
 *   guest ↔ support             the user who opened it, and platform admins
 *   staff ↔ management          only the listed participants (members of the business)
 */
class MessagingService
{
    public function __construct(private readonly CrmService $crm) {}

    public function canAccess(User $user, Thread $thread): bool
    {
        return match ($thread->kind) {
            Thread::SUPPORT => (int) $thread->guest_user_id === (int) $user->id || $user->isPlatformAdmin(),
            Thread::STAFF => $thread->participants()->where('user_id', $user->id)->exists() && $user->belongsToTenant($thread->tenant_id),
            default => (int) $thread->guest_user_id === (int) $user->id
                || ($user->belongsToTenant($thread->tenant_id) && $user->hasPermissionTo('messages.view', $thread->tenant_id)),
        };
    }

    public function canReply(User $user, Thread $thread): bool
    {
        if ($thread->status !== 'open' || ! $this->canAccess($user, $thread)) {
            return false;
        }

        return $thread->isGuestThread() && (int) $thread->guest_user_id !== (int) $user->id
            ? $user->hasPermissionTo('messages.reply', $thread->tenant_id)
            : true;
    }

    /** Which side of the conversation this user writes on. */
    public function side(User $user, Thread $thread): string
    {
        return match (true) {
            $thread->kind === Thread::STAFF => 'staff',
            (int) $thread->guest_user_id === (int) $user->id => 'guest',
            $thread->kind === Thread::SUPPORT => 'platform',
            default => 'business',
        };
    }

    /**
     * A guest writes to a business about a listing, a stay, an order or a
     * table. An open thread about the same thing is continued, not duplicated.
     */
    public function startWithBusiness(User $guest, Model $about, string $subject, string $body, array $files = []): Thread
    {
        [$tenantId, $kind] = match (true) {
            $about instanceof Property => [$about->tenant_id, Thread::GUEST_HOST],
            $about instanceof Booking => [$about->tenant_id, Thread::GUEST_HOST],
            default => [$about->tenant_id, Thread::GUEST_RESTAURANT],
        };

        $owned = ! ($about instanceof Booking || $about instanceof Order || $about instanceof TableReservation) || (int) $about->user_id === (int) $guest->id;
        $listed = ! ($about instanceof Property || $about instanceof Restaurant) || $about->isPublished();

        if (! $owned || ! $listed) {
            $this->fail('body', 'You cannot message about that.');
        }

        $thread = Thread::query()->where('guest_user_id', $guest->id)->where('about_type', $about->getMorphClass())->where('about_id', $about->getKey())->where('status', 'open')->first()
            ?? Thread::create(['tenant_id' => $tenantId, 'kind' => $kind, 'subject' => $subject, 'guest_user_id' => $guest->id, 'about_type' => $about->getMorphClass(), 'about_id' => $about->getKey(), 'created_by' => $guest->id]);

        $this->post($thread, $guest, $body, $files);

        return $thread;
    }

    public function startSupport(User $user, string $subject, string $body, array $files = []): Thread
    {
        $thread = Thread::create(['kind' => Thread::SUPPORT, 'subject' => $subject, 'guest_user_id' => $user->id, 'created_by' => $user->id]);
        $this->post($thread, $user, $body, $files);

        return $thread;
    }

    /** @param list<int> $userIds */
    public function startStaff(User $by, Tenant $tenant, array $userIds, string $subject, string $body): Thread
    {
        $members = $tenant->users()->wherePivot('status', 'active')->whereIn('users.id', $userIds)->pluck('users.id')->reject(fn ($id) => (int) $id === (int) $by->id);

        if ($members->isEmpty()) {
            $this->fail('participants', 'Pick at least one colleague.');
        }

        return DB::transaction(function () use ($by, $tenant, $members, $subject, $body) {
            $thread = Thread::create(['tenant_id' => $tenant->id, 'kind' => Thread::STAFF, 'subject' => $subject, 'created_by' => $by->id]);

            foreach ($members->push($by->id) as $userId) {
                $thread->participants()->create(['user_id' => $userId, 'side' => 'staff']);
            }

            $this->post($thread, $by, $body);

            return $thread;
        });
    }

    /** @param list<UploadedFile> $files */
    public function post(Thread $thread, User $author, string $body, array $files = []): Message
    {
        if (! $this->canReply($author, $thread)) {
            $this->fail('body', $thread->status === 'open' ? 'You cannot reply in this conversation.' : 'This conversation is closed.');
        }

        $message = DB::transaction(function () use ($thread, $author, $body, $files) {
            $message = $thread->messages()->create(['user_id' => $author->id, 'side' => $this->side($author, $thread), 'body' => $body]);

            foreach ($files as $file) {
                $path = $file->store('messages/'.$thread->id, 'local');
                $message->attachments()->create([
                    'disk' => 'local', 'path' => $path, 'alt' => Str::limit($file->getClientOriginalName(), 190, ''),
                    'kind' => str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'document',
                ]);
            }

            $thread->forceFill(['last_message_at' => now()])->save();
            $this->markRead($thread, $author);

            return $message;
        });

        $this->recipients($thread, $author)->each(fn (User $u) => $u->notify(new NewMessage($thread, $message)));
        $this->logToCrm($thread, $message);

        return $message;
    }

    public function markRead(Thread $thread, User $user): void
    {
        Participant::query()->updateOrCreate(['message_thread_id' => $thread->id, 'user_id' => $user->id], ['side' => $this->side($user, $thread), 'last_read_at' => now()]);
    }

    public function setStatus(Thread $thread, User $by, bool $open): void
    {
        if (! $this->canAccess($by, $thread) || ($thread->isGuestThread() && (int) $thread->guest_user_id !== (int) $by->id && ! $by->hasPermissionTo('messages.reply', $thread->tenant_id))) {
            $this->fail('status', 'You cannot change this conversation.');
        }

        $thread->forceFill(['status' => $open ? 'open' : 'closed'])->save();
    }

    // ------------------------------------------------------------------

    /** @return Collection<int, User> the other side, to notify */
    private function recipients(Thread $thread, User $author): Collection
    {
        $side = $this->side($author, $thread);

        $users = match (true) {
            $thread->kind === Thread::STAFF => $thread->participants()->with('user')->get()->pluck('user'),
            $side === 'guest' && $thread->kind === Thread::SUPPORT => Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->first()?->users()->get() ?? collect(),
            $side === 'guest' => Tenant::query()->find($thread->tenant_id)?->users()->wherePivot('status', 'active')->get()
                ->filter(fn (User $u) => $u->hasPermissionTo('messages.reply', $thread->tenant_id)) ?? collect(),
            default => collect([$thread->guest]),
        };

        return $users->filter()->reject(fn (User $u) => (int) $u->id === (int) $author->id)->unique('id')->values();
    }

    /** Guest conversations land in the business's CRM communication history. */
    private function logToCrm(Thread $thread, Message $message): void
    {
        if (! $thread->isGuestThread() || ! $thread->guest) {
            return;
        }

        app(TenantContext::class)->runAs($thread, function () use ($thread, $message): void {
            $contact = $this->crm->upsert($thread->guest->id, $thread->guest->name, $thread->guest->email, null, 'booking');
            $contact && $this->crm->log($contact, 'chat', $message->side === 'guest' ? 'inbound' : 'outbound', $thread->subject, $message->body, $message->side === 'guest' ? null : $message->author, 'message:'.$message->id);
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
