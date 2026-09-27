<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\Media;
use App\Modules\Messaging\Models\Message;
use App\Modules\Messaging\Models\Thread;
use App\Modules\Messaging\Services\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Platform support inbox (Super Admin) and access-checked attachment downloads for every thread. */
class SupportController extends Controller
{
    public function __construct(private readonly MessagingService $messaging) {}

    public function index(Request $request)
    {
        return $this->inbox($request);
    }

    public function show(Request $request, string $thread)
    {
        $thread = Thread::query()->where('kind', Thread::SUPPORT)->findOrFail($thread);
        $this->messaging->markRead($thread, $request->user());

        return $this->inbox($request, $thread->load(['messages.author', 'messages.attachments', 'guest']));
    }

    /** Same two-pane inbox as the business side (Messages/Index): list + open conversation. */
    private function inbox(Request $request, ?Thread $open = null)
    {
        $user = $request->user();
        $status = in_array($request->query('status'), ['open', 'closed', 'all'], true) ? $request->query('status') : 'open';

        return \Inertia\Inertia::render('Messages/Index', [
            'title' => 'Support',
            'subtitle' => 'Guests and hosts writing to the platform.',
            'threads' => Thread::query()->where('kind', Thread::SUPPORT)->with(['guest', 'participants'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('last_message_at')->paginate(25)->withQueryString()
                ->through(fn (Thread $t) => [
                    'id' => $t->id,
                    'subject' => $t->subject,
                    'who' => $t->guest?->name ?? 'Guest',
                    'when' => $t->last_message_at?->diffForHumans(),
                    'unread' => $t->unreadFor($user),
                    'href' => route('admin.support.show', ['thread' => $t->id, 'status' => $status]),
                    'active' => $open?->id === $t->id,
                ]),
            'status' => $status,
            'members' => [],
            'thread' => $open ? [
                'id' => $open->id,
                'subject' => $open->subject,
                'meta' => ($open->guest?->name ?? '—').($open->guest?->email ? ' · '.$open->guest->email : ''),
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
                'urls' => ['reply' => route('admin.support.reply', $open->id), 'status' => route('admin.support.status', $open->id)],
            ] : null,
            'urls' => ['self' => route('admin.support.index'), 'staff' => null],
        ]);
    }

    public function reply(Request $request, string $thread)
    {
        $thread = Thread::query()->where('kind', Thread::SUPPORT)->findOrFail($thread);
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000'], 'files' => ['nullable', 'array', 'max:3'], 'files.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf']]);
        $this->messaging->post($thread, $request->user(), $validated['body'], $request->file('files', []));

        return back();
    }

    public function status(Request $request, string $thread)
    {
        $this->messaging->setStatus(Thread::query()->where('kind', Thread::SUPPORT)->findOrFail($thread), $request->user(), $request->boolean('open'));

        return back()->with('success', $request->boolean('open') ? 'Reopened.' : 'Closed.');
    }

    /** Anyone who can read the thread can download its files. */
    public function attachment(Request $request, string $thread, string $media)
    {
        $thread = Thread::query()->findOrFail($thread);
        abort_unless($this->messaging->canAccess($request->user(), $thread), 404);

        $file = Media::query()->where('mediable_type', (new Message)->getMorphClass())
            ->whereIn('mediable_id', $thread->messages()->pluck('id'))->findOrFail($media);

        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->response($file->path, $file->alt);
    }
}
