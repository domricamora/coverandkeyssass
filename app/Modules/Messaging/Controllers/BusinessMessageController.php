<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Messaging\Models\Thread;
use App\Modules\Messaging\Services\MessagingService;
use App\Support\TenantContext;
use Illuminate\Http\Request;

/**
 * The business inbox (Phase 25): guest conversations about its listings,
 * stays, orders and tables (messages.view / reply), plus staff threads the
 * member takes part in.
 */
class BusinessMessageController extends Controller
{
    public function __construct(private readonly MessagingService $messaging) {}

    public function index(Request $request)
    {
        return $this->inbox($request, null);
    }

    public function show(Request $request, string $thread)
    {
        $thread = $this->find($request, $thread);
        $this->messaging->markRead($thread, $request->user());

        return $this->inbox($request, $thread->load(['messages.author', 'messages.attachments', 'guest', 'participants.user']));
    }

    /** React inbox: thread list + the open conversation (two panes). */
    private function inbox(Request $request, ?Thread $open)
    {
        $user = $request->user();
        $tenant = app(TenantContext::class)->tenant();
        $canGuests = $user->hasPermissionTo('messages.view');
        $status = in_array($request->query('status'), ['open', 'closed', 'all'], true) ? $request->query('status') : 'open';

        $threads = Thread::query()->where('tenant_id', $tenant->id)
            ->where(fn ($q) => $q
                ->when($canGuests, fn ($w) => $w->whereIn('kind', [Thread::GUEST_HOST, Thread::GUEST_RESTAURANT]))
                ->orWhere(fn ($w) => $w->where('kind', Thread::STAFF)->whereHas('participants', fn ($p) => $p->where('user_id', $user->id))))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['guest', 'participants'])->latest('last_message_at')->paginate(25)->withQueryString();

        return \Inertia\Inertia::render('Messages/Index', [
            'threads' => $threads->through(fn (Thread $t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'who' => $t->kind === Thread::STAFF ? 'Staff · '.$t->participants->count().' people' : ($t->guest?->name ?? 'Guest'),
                'when' => $t->last_message_at?->diffForHumans(),
                'unread' => $t->unreadFor($user),
                'href' => route('messages.show', ['thread' => $t->id, 'status' => $status]),
                'active' => $open?->id === $t->id,
            ]),
            'status' => $status,
            'members' => $tenant->users()->wherePivot('status', 'active')->where('users.id', '!=', $user->id)->orderBy('name')->get(['users.id', 'users.name'])->map(fn ($m) => [$m->id, $m->name]),
            'thread' => $open ? [
                'id' => $open->id,
                'subject' => $open->subject,
                'meta' => ($open->kind === Thread::STAFF
                    ? 'Staff: '.$open->participants->pluck('user.name')->implode(', ')
                    : 'Guest: '.($open->guest?->name ?? '—').($open->guest?->email ? ' · '.$open->guest->email : ''))
                    .($open->about_type ? ' · about '.class_basename($open->about_type).' #'.$open->about_id : ''),
                'status' => $open->status,
                'messages' => $open->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'author' => $m->author?->name ?? 'Deleted user',
                    'side' => $m->side,
                    'when' => $m->created_at->format('M j, g:i A'),
                    'body' => $m->body,
                    'mine' => (int) $m->user_id === (int) $user->id,
                    'files' => $m->attachments->map(fn ($f) => ['id' => $f->id, 'name' => $f->alt ?? 'Attachment', 'url' => route('messages.attachment', [$open->id, $f->id])]),
                ]),
                'canReply' => $this->messaging->canReply($user, $open),
                'urls' => ['reply' => route('messages.reply', $open->id), 'status' => route('messages.status', $open->id)],
            ] : null,
            'urls' => ['self' => route('messages.index'), 'staff' => route('messages.staff.store')],
        ]);
    }

    public function reply(Request $request, string $thread)
    {
        $thread = $this->find($request, $thread);
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000'], 'files' => ['nullable', 'array', 'max:3'], 'files.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf']]);
        $this->messaging->post($thread, $request->user(), $validated['body'], $request->file('files', []));

        return back();
    }

    public function status(Request $request, string $thread)
    {
        $this->messaging->setStatus($this->find($request, $thread), $request->user(), $request->boolean('open'));

        return back()->with('success', $request->boolean('open') ? 'Reopened.' : 'Closed.');
    }

    public function storeStaff(Request $request)
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*' => ['integer'],
        ]);

        $thread = $this->messaging->startStaff($request->user(), app(TenantContext::class)->tenant(), $validated['participants'], $validated['subject'], $validated['body']);

        return redirect()->route('messages.show', $thread->id)->with('success', 'Conversation started.');
    }

    private function find(Request $request, string $id): Thread
    {
        $thread = Thread::query()->where('tenant_id', app(TenantContext::class)->id())->findOrFail($id);
        abort_unless($this->messaging->canAccess($request->user(), $thread), 404);

        return $thread;
    }
}
