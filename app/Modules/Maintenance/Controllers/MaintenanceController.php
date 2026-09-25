<?php

namespace App\Modules\Maintenance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Maintenance\Models\MaintenanceTicket;
use App\Modules\Maintenance\Services\MaintenanceService;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\Room;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Maintenance tickets screens (Phase 16): list + filters, new ticket, detail with notes / files / cost / workflow. */
class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenance) {}

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'maintenance.view');

        $filters = $request->validate([
            'status' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::in(MaintenanceTicket::PRIORITIES)],
            'property' => ['nullable', 'integer'],
            'mine' => ['nullable', 'boolean'],
        ]);

        $tickets = MaintenanceTicket::query()
            ->with(['property', 'room', 'assignee'])
            ->when(($filters['status'] ?? 'active') === 'active', fn ($q) => $q->active(), fn ($q) => $q->when($filters['status'] ?? null, fn ($w, $s) => $w->where('status', $s)))
            ->when($filters['priority'] ?? null, fn ($q, $p) => $q->where('priority', $p))
            ->when($filters['property'] ?? null, fn ($q, $p) => $q->where('property_id', $p))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")->latest()
            ->paginate(25)->withQueryString();

        return view('maintenance::index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'rooms' => Room::query()->orderBy('room_number')->get(['id', 'property_id', 'room_number']),
            'title' => 'Maintenance',
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTo($request, 'maintenance.work');

        $validated = $request->validate([
            'property_id' => ['required', 'integer'],
            'room_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(MaintenanceTicket::CATEGORIES)],
            'priority' => ['required', Rule::in(MaintenanceTicket::PRIORITIES)],
        ]);

        $ticket = $this->maintenance->open(
            Property::query()->findOrFail($validated['property_id']),
            isset($validated['room_id']) ? Room::query()->findOrFail($validated['room_id']) : null,
            $validated,
            $request->user(),
        );

        return redirect()->route('maintenance.show', $ticket->reference)->with('success', 'Ticket '.$ticket->reference.' opened.');
    }

    public function show(Request $request, string $ticket)
    {
        $this->authorizeTo($request, 'maintenance.view');
        $ticket = $this->find($ticket)->load(['property', 'room', 'reporter', 'assignee', 'notes.author', 'attachments']);

        return view('maintenance::show', [
            'ticket' => $ticket,
            'members' => app(TenantContext::class)->tenant()->users()->wherePivot('status', 'active')->orderBy('name')->get(['users.id', 'users.name']),
            'title' => 'Ticket '.$ticket->reference,
        ]);
    }

    public function assign(Request $request, string $ticket)
    {
        $this->authorizeTo($request, 'maintenance.manage');

        $userId = $request->validate(['assigned_to' => ['nullable', 'integer']])['assigned_to'] ?? null;
        $this->maintenance->assign($this->find($ticket), $userId ? User::query()->findOrFail($userId) : null, $request->user());

        return back()->with('success', 'Ticket assigned.');
    }

    public function transition(Request $request, string $ticket)
    {
        $this->authorizeTo($request, 'maintenance.work');

        $validated = $request->validate(['status' => ['required', 'string'], 'note' => ['nullable', 'string', 'max:1000']]);

        // Closing and reopening are a supervisor's call.
        if ($validated['status'] === MaintenanceTicket::CLOSED) {
            $this->authorizeTo($request, 'maintenance.manage');
        }

        $this->maintenance->transition($this->find($ticket), $validated['status'], $request->user(), $validated['note'] ?? null);

        return back()->with('success', 'Ticket updated.');
    }

    public function cost(Request $request, string $ticket)
    {
        $this->authorizeTo($request, 'maintenance.manage');

        $this->maintenance->setCost($this->find($ticket), (float) $request->validate(['cost' => ['required', 'numeric', 'min:0', 'max:99999999']])['cost'], $request->user());

        return back()->with('success', 'Cost saved.');
    }

    public function note(Request $request, string $ticket)
    {
        $this->authorizeTo($request, 'maintenance.work');

        $this->maintenance->addNote($this->find($ticket), $request->validate(['body' => ['required', 'string', 'max:5000']])['body'], $request->user());

        return back()->with('success', 'Note added.');
    }

    public function attach(Request $request, string $ticket)
    {
        $this->authorizeTo($request, 'maintenance.work');

        $file = $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf']])['file'];
        $this->maintenance->attach($this->find($ticket), $file, $request->user());

        return back()->with('success', 'File attached.');
    }

    /** Attachments are private: only members who can view the ticket can download them. */
    public function download(Request $request, string $ticket, string $media)
    {
        $this->authorizeTo($request, 'maintenance.view');

        $file = $this->find($ticket)->attachments()->whereKey($media)->firstOrFail();

        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->response($file->path, $file->alt);
    }

    private function find(string $reference): MaintenanceTicket
    {
        return MaintenanceTicket::query()->where('reference', $reference)->firstOrFail();
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
