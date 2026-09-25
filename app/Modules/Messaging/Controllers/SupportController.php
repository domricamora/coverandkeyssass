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
        return view('messaging::admin.index', [
            'threads' => Thread::query()->where('kind', Thread::SUPPORT)->with(['guest', 'participants'])
                ->when($request->query('status', 'open') !== 'all', fn ($q) => $q->where('status', $request->query('status', 'open')))
                ->latest('last_message_at')->paginate(25)->withQueryString(),
            'title' => 'Support',
        ]);
    }

    public function show(Request $request, string $thread)
    {
        $thread = Thread::query()->where('kind', Thread::SUPPORT)->findOrFail($thread);
        $this->messaging->markRead($thread, $request->user());

        return view('messaging::admin.show', [
            'thread' => $thread->load(['messages.author', 'messages.attachments', 'guest']),
            'canReply' => $this->messaging->canReply($request->user(), $thread),
            'title' => $thread->subject,
        ]);
    }

    public function reply(Request $request, string $thread)
    {
        $thread = Thread::query()->where('kind', Thread::SUPPORT)->findOrFail($thread);
        $this->messaging->post($thread, $request->user(), $request->validate(['body' => ['required', 'string', 'max:5000']])['body']);

        return back();
    }

    public function status(Request $request, string $thread)
    {
        $this->messaging->setStatus(Thread::query()->where('kind', Thread::SUPPORT)->findOrFail($thread), $request->user(), $request->boolean('open'));

        return back();
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
