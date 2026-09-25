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
        $user = $request->user();
        $tenant = app(TenantContext::class)->tenant();
        $canGuests = $user->hasPermissionTo('messages.view');

        $threads = Thread::query()->where('tenant_id', $tenant->id)
            ->where(fn ($q) => $q
                ->when($canGuests, fn ($w) => $w->whereIn('kind', [Thread::GUEST_HOST, Thread::GUEST_RESTAURANT]))
                ->orWhere(fn ($w) => $w->where('kind', Thread::STAFF)->whereHas('participants', fn ($p) => $p->where('user_id', $user->id))))
            ->when($request->query('status', 'open') !== 'all', fn ($q) => $q->where('status', $request->query('status', 'open')))
            ->with(['guest', 'participants'])->latest('last_message_at')->paginate(25)->withQueryString();

        return view('messaging::business.index', [
            'threads' => $threads,
            'members' => $tenant->users()->wherePivot('status', 'active')->where('users.id', '!=', $user->id)->orderBy('name')->get(['users.id', 'users.name']),
            'title' => 'Messages',
        ]);
    }

    public function show(Request $request, string $thread)
    {
        $thread = $this->find($request, $thread);
        $this->messaging->markRead($thread, $request->user());

        return view('messaging::business.show', [
            'thread' => $thread->load(['messages.author', 'messages.attachments', 'guest', 'participants.user']),
            'canReply' => $this->messaging->canReply($request->user(), $thread),
            'title' => $thread->subject,
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
