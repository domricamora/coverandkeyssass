<?php

namespace App\Modules\Housekeeping\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Housekeeping\Services\HousekeepingService;
use App\Modules\Maintenance\Models\MaintenanceTicket;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\Room;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Housekeeping board (Phase 15): room status grid, task queue, "my tasks",
 * inspections and issue reports for one property at a time. Rooms and
 * tasks resolve through the tenant scope, so foreign ids are a 404.
 */
class HousekeepingController extends Controller
{
    public function __construct(private readonly HousekeepingService $housekeeping) {}

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'housekeeping.view');

        $properties = Property::query()->orderBy('name')->get(['id', 'name', 'slug']);
        $property = $properties->firstWhere('slug', $request->query('property')) ?? $properties->first();
        $mine = $request->boolean('mine');

        $tasks = HousekeepingTask::query()
            ->when($property, fn ($q) => $q->where('property_id', $property->id))
            ->when($mine, fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->where(fn ($q) => $q->open()->orWhere(fn ($w) => $w->awaitingInspection()))
            ->with(['room', 'assignee'])
            ->orderByRaw("FIELD(priority, 'high', 'normal')")->orderBy('due_on')->orderBy('id')
            ->get();

        return view('housekeeping::board', [
            'properties' => $properties,
            'property' => $property,
            'mine' => $mine,
            'rooms' => $property ? Room::query()->where('property_id', $property->id)->where('status', '!=', Room::STATUS_INACTIVE)->orderBy('room_number')->get() : collect(),
            'tasks' => $tasks,
            'members' => app(TenantContext::class)->tenant()->users()->wherePivot('status', 'active')->orderBy('name')->get(['users.id', 'users.name']),
            'tickets' => $property ? MaintenanceTicket::query()->where('property_id', $property->id)->active()->with('room')->latest()->limit(10)->get() : collect(),
            'title' => 'Housekeeping',
        ]);
    }

    public function setStatus(Request $request, string $room)
    {
        $this->authorizeTo($request, 'housekeeping.manage');

        $validated = $request->validate(['housekeeping_status' => ['required', Rule::in(Room::HK_STATUSES)], 'reason' => ['nullable', 'string', 'max:200']]);
        $room = Room::query()->findOrFail($room);

        $this->housekeeping->setRoomStatus($room, $validated['housekeeping_status'], $request->user(), $validated['reason'] ?? null);

        return back()->with('success', 'Room '.$room->room_number.' is now '.str_replace('_', ' ', $room->housekeeping_status).'.');
    }

    public function storeTask(Request $request)
    {
        $this->authorizeTo($request, 'housekeeping.manage');

        $validated = $request->validate([
            'room_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(HousekeepingTask::TYPES)],
            'due_on' => ['required', 'date'],
            'priority' => ['nullable', Rule::in(['normal', 'high'])],
            'assigned_to' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->housekeeping->createTask(
            Room::query()->findOrFail($validated['room_id']),
            $validated['type'], $validated['due_on'], $request->user(),
            $this->member($validated['assigned_to'] ?? null),
            $validated['priority'] ?? 'normal', $validated['notes'] ?? null,
        );

        return back()->with('success', 'Task created.');
    }

    public function assign(Request $request, string $task)
    {
        $this->authorizeTo($request, 'housekeeping.manage');

        $task = HousekeepingTask::query()->findOrFail($task);
        $this->housekeeping->assign($task, $this->member($request->validate(['assigned_to' => ['nullable', 'integer']])['assigned_to'] ?? null), $request->user());

        return back()->with('success', 'Task assigned.');
    }

    public function start(Request $request, string $task)
    {
        $this->authorizeTo($request, 'housekeeping.work');
        $this->housekeeping->start(HousekeepingTask::query()->findOrFail($task), $request->user());

        return back()->with('success', 'Cleaning started.');
    }

    public function complete(Request $request, string $task)
    {
        $this->authorizeTo($request, 'housekeeping.work');
        $this->housekeeping->complete(HousekeepingTask::query()->findOrFail($task), $request->user());

        return back()->with('success', 'Room marked clean.');
    }

    public function inspect(Request $request, string $task)
    {
        $this->authorizeTo($request, 'housekeeping.manage');

        $validated = $request->validate(['passed' => ['required', 'boolean'], 'notes' => ['nullable', 'string', 'max:500']]);
        $redo = $this->housekeeping->inspect(HousekeepingTask::query()->findOrFail($task), $request->user(), (bool) $validated['passed'], $validated['notes'] ?? null);

        return back()->with('success', $redo ? 'Inspection failed — a re-clean was queued.' : 'Room inspected.');
    }

    public function cancel(Request $request, string $task)
    {
        $this->authorizeTo($request, 'housekeeping.manage');
        $this->housekeeping->cancel(HousekeepingTask::query()->findOrFail($task), $request->user());

        return back()->with('success', 'Task cancelled.');
    }

    public function reportIssue(Request $request, string $room)
    {
        $this->authorizeTo($request, 'housekeeping.work');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', Rule::in(MaintenanceTicket::PRIORITIES)],
            'out_of_order' => ['nullable', 'boolean'],
        ]);

        $ticket = $this->housekeeping->reportIssue(
            Room::query()->findOrFail($room), $validated['title'], $validated['description'] ?? null,
            $validated['priority'] ?? 'normal', $request->boolean('out_of_order'), $request->user(),
        );

        return back()->with('success', 'Maintenance ticket '.$ticket->reference.' opened.');
    }

    private function member(?int $userId): ?User
    {
        return $userId ? User::query()->findOrFail($userId) : null;
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
