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
        $threads = Thread::query()->where('guest_user_id', $request->user()->id)->with(['tenant', 'participants'])->latest('last_message_at')->paginate(20);

        return view('messaging::account.index', ['threads' => $threads]);
    }

    public function create(Request $request)
    {
        $about = $this->about($request);

        return view('messaging::account.create', ['about' => $about, 'support' => $about === null]);
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

        return view('messaging::account.show', [
            'thread' => $thread->load(['messages.author', 'messages.attachments', 'tenant']),
            'canReply' => $this->messaging->canReply($request->user(), $thread),
        ]);
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
