<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Booking;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Messaging\Models\Thread;
use App\Modules\Messaging\Services\MessagingService;
use App\Modules\Ordering\Models\Order;
use App\Modules\RestaurantManagement\Models\TableReservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The customer's inbox (Phase 25): conversations with hosts, restaurants
 * and platform support. Starting a thread names what it is about — a
 * published listing, or the guest's own booking / order / table.
 */
class GuestMessageController extends Controller
{
    public function __construct(private readonly MessagingService $messaging) {}

    public function index(Request $request)
    {
        return $this->inbox($request, null);
    }

    public function create(Request $request)
    {
        $about = $this->about($request);
        $support = $about === null;

        return \Inertia\Inertia::render('Account/MessageNew', [
            'title' => $support ? 'How can we help?' : 'About '.($about->name ?? $about->reference),
            'subject' => $support ? '' : 'Question about '.($about->name ?? $about->reference),
            'support' => $support,
            'tabs' => \App\Modules\Customer\Controllers\AccountController::nav('account.messages.index'),
            'urls' => ['store' => route('account.messages.store', $request->only(['property', 'restaurant', 'booking', 'order', 'reservation'])), 'inbox' => route('account.messages.index')],
        ]);
    }

    /** React inbox for guests: conversations + the open one (two panes). */
    private function inbox(Request $request, ?Thread $open)
    {
        $user = $request->user();

        return \Inertia\Inertia::render('Account/Messages', [
            'threads' => Thread::query()->where('guest_user_id', $user->id)->with(['tenant'])->latest('last_message_at')->paginate(20)->through(fn (Thread $t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'who' => $t->tenant?->name ?? 'Cover & Keys support',
                'when' => $t->last_message_at?->diffForHumans(),
                'unread' => $t->unreadFor($user),
                'closed' => $t->status === 'closed',
                'href' => route('account.messages.show', $t->id),
                'active' => $open?->id === $t->id,
            ]),
            'thread' => $open ? [
                'id' => $open->id,
                'subject' => $open->subject,
                'who' => $open->tenant?->name ?? 'Cover & Keys support',
                'status' => $open->status,
                'messages' => $open->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'author' => $m->author?->name ?? 'Deleted user',
                    'when' => $m->created_at->format('M j, g:i A'),
                    'body' => $m->body,
                    'mine' => (int) $m->user_id === (int) $user->id,
                    'files' => $m->attachments->map(fn ($f) => ['id' => $f->id, 'name' => $f->alt ?? 'Attachment', 'url' => route('messages.attachment', [$open->id, $f->id])]),
                ]),
                'canReply' => $this->messaging->canReply($user, $open),
                'urls' => ['reply' => route('account.messages.reply', $open->id), 'close' => route('account.messages.close', $open->id)],
            ] : null,
            'tabs' => \App\Modules\Customer\Controllers\AccountController::nav('account.messages.index'),
            'urls' => ['inbox' => route('account.messages.index'), 'support' => route('account.messages.create')],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules() + ['subject' => ['required', 'string', 'max:160']]);
        $files = $request->file('files', []);
        $about = $this->about($request);

        $thread = $about
            ? $this->messaging->startWithBusiness($request->user(), $about, $validated['subject'], $validated['body'], $files)
            : $this->messaging->startSupport($request->user(), $validated['subject'], $validated['body'], $files);

        return redirect()->route('account.messages.show', $thread->id)->with('success', 'Message sent.');
    }

    public function show(Request $request, string $thread)
    {
        $thread = $this->find($request, $thread);
        $this->messaging->markRead($thread, $request->user());

        return $this->inbox($request, $thread->load(['messages.author', 'messages.attachments', 'tenant']));
    }

    public function reply(Request $request, string $thread)
    {
        $thread = $this->find($request, $thread);
        $this->messaging->post($thread, $request->user(), $request->validate($this->rules())['body'], $request->file('files', []));

        return back();
    }

    public function close(Request $request, string $thread)
    {
        $this->messaging->setStatus($this->find($request, $thread), $request->user(), false);

        return back()->with('success', 'Conversation closed.');
    }

    private function find(Request $request, string $id): Thread
    {
        $thread = Thread::query()->findOrFail($id);
        abort_unless((int) $thread->guest_user_id === (int) $request->user()->id, 404);

        return $thread;
    }

    /** The listing or record a new thread is about (null = platform support). */
    private function about(Request $request): ?Model
    {
        $user = $request->user();

        return match (true) {
            $request->filled('property') => Property::publicQuery()->where('slug', $request->input('property'))->firstOrFail(),
            $request->filled('restaurant') => Restaurant::publicQuery()->where('slug', $request->input('restaurant'))->firstOrFail(),
            $request->filled('booking') => Booking::forCustomer($user)->where('reference', $request->input('booking'))->firstOrFail(),
            $request->filled('order') => Order::forCustomer($user)->where('reference', $request->input('order'))->firstOrFail(),
            $request->filled('reservation') => TableReservation::forCustomer($user)->where('reference', $request->input('reservation'))->firstOrFail(),
            default => null,
        };
    }

    private function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:3'],
            'files.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }
}
