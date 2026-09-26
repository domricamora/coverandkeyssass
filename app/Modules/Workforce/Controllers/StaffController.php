<?php

namespace App\Modules\Workforce\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Workforce\Models\Department;
use App\Modules\Workforce\Models\Employee;
use App\Modules\Workforce\Models\Position;
use App\Modules\Workforce\Services\WorkforceService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Employees, departments and positions (Phase 17; React screens). */
class StaffController extends Controller
{
    public function __construct(private readonly WorkforceService $workforce) {}

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'staff.view');

        $employees = Employee::query()->with(['department', 'position', 'user'])
            ->when($request->query('department'), fn ($q, $d) => $q->where('department_id', $d))
            ->orderByRaw("status = 'terminated'")->orderBy('name')->get();

        return Inertia::render('Staff/Index', [
            'employees' => $employees->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'no' => $e->employee_no,
                'department' => $e->department?->name,
                'position' => $e->position?->name,
                'type' => Str::headline($e->employment_type),
                'account' => $e->user?->email,
                'terminated' => $e->status === Employee::TERMINATED,
                'href' => route('staff.show', $e->id),
            ]),
            'active' => $employees->where('status', Employee::ACTIVE)->count(),
            'departments' => Department::query()->withCount('employees')->orderBy('name')->get()
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'count' => $d->employees_count]),
            'positions' => Position::query()->with('department')->orderBy('name')->get()->map(fn ($p) => [
                'id' => $p->id,
                'text' => collect([$p->name, $p->department?->name, $p->hourly_rate ? '₱'.number_format((float) $p->hourly_rate, 2).'/h' : null])->filter()->implode(' · '),
            ]),
            'department' => $request->query('department'),
            'options' => $this->formOptions(),
            'can' => ['manage' => $request->user()->hasPermissionTo('staff.manage')],
            'tabs' => self::tabs('staff.index'),
            'urls' => ['self' => route('staff.index'), 'store' => route('staff.store'), 'departments' => route('staff.departments.store'), 'positions' => route('staff.positions.store')],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTo($request, 'staff.manage');

        $employee = $this->workforce->hire($request->validate($this->rules()), $request->user());

        return redirect()->route('staff.show', $employee->id)->with('success', $employee->name.' added as '.$employee->employee_no.'.');
    }

    public function show(Request $request, string $employee)
    {
        $this->authorizeTo($request, 'staff.view');
        $employee = Employee::query()->with(['department', 'position', 'user', 'property'])->findOrFail($employee);
        $role = $employee->user?->tenantRole(app(TenantContext::class)->tenant())?->display_name;

        return Inertia::render('Staff/Show', [
            'employee' => [
                'name' => $employee->name,
                'no' => $employee->employee_no,
                'summary' => collect([
                    $employee->position?->name ?? 'No position',
                    $employee->department?->name ?? 'No department',
                    $employee->user ? 'Access: '.($role ?? 'no role') : 'No login account',
                ])->implode(' · '),
                'linked' => (bool) $employee->user,
                'form' => [
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                    'department_id' => $employee->department_id,
                    'position_id' => $employee->position_id,
                    'employment_type' => $employee->employment_type,
                    'hire_date' => $employee->hire_date?->toDateString(),
                    'property_id' => $employee->property_id,
                    'user_id' => $employee->user_id,
                    'status' => $employee->status,
                ],
            ],
            'shifts' => $employee->shifts()->scheduled()->where('starts_at', '>=', today())->limit(10)->get()
                ->map(fn ($s) => ['id' => $s->id, 'day' => $s->starts_at->format('D, M j'), 'label' => $s->label()]),
            'attendances' => $employee->attendances()->limit(15)->get()->map(fn ($a) => [
                'id' => $a->id,
                'in' => $a->clock_in_at->format('M j, g:i A'),
                'out' => $a->clock_out_at?->format('g:i A'),
                'worked' => $a->hoursLabel(),
                'late' => $a->late_minutes ?: null,
            ]),
            'leave' => $employee->leaveRequests()->limit(10)->get()->map(fn ($l) => [
                'id' => $l->id,
                'text' => ucfirst($l->type).' · '.$l->starts_on->format('M j').'–'.$l->ends_on->format('M j').' ('.$l->days().'d)',
                'status' => $l->status,
            ]),
            'options' => $this->formOptions(),
            'can' => ['manage' => $request->user()->hasPermissionTo('staff.manage'), 'team' => $request->user()->hasPermissionTo('team.manage')],
            'tabs' => self::tabs('staff.index'),
            'urls' => ['index' => route('staff.index'), 'update' => route('staff.update', $employee->id), 'team' => route('team')],
        ]);
    }

    public function update(Request $request, string $employee)
    {
        $this->authorizeTo($request, 'staff.manage');
        $employee = Employee::query()->findOrFail($employee);

        $this->workforce->update($employee, $request->validate($this->rules() + ['status' => ['required', Rule::in([Employee::ACTIVE, Employee::TERMINATED])]]));

        return back()->with('success', 'Employee updated.');
    }

    public function storeDepartment(Request $request)
    {
        $this->authorizeTo($request, 'staff.manage');

        Department::create($request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('departments')->where('tenant_id', app(TenantContext::class)->id())],
            'description' => ['nullable', 'string', 'max:500'],
        ]));

        return back()->with('success', 'Department added.');
    }

    public function storePosition(Request $request)
    {
        $this->authorizeTo($request, 'staff.manage');
        $tenantId = app(TenantContext::class)->id();

        Position::create($request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('positions')->where('tenant_id', $tenantId)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ]));

        return back()->with('success', 'Position added.');
    }

    /** Sub-navigation shared by the staff screens (React `Tabs`). */
    public static function tabs(string $current): array
    {
        return collect([['staff.index', 'Employees'], ['staff.schedule', 'Schedule'], ['staff.attendance', 'Attendance'], ['staff.leave', 'Leave'], ['my-work.index', 'My work']])
            ->map(fn ($t) => ['label' => $t[1], 'href' => route($t[0]), 'active' => $t[0] === $current])->all();
    }

    private function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'hire_date' => ['nullable', 'date'],
            'employment_type' => ['required', Rule::in(Employee::TYPES)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->where('tenant_id', $tenantId)],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('tenant_id', $tenantId)],
            'user_id' => ['nullable', 'integer'],
        ];
    }

    /** Select options as [value, label] pairs. */
    private function formOptions(): array
    {
        $pairs = fn ($rows) => $rows->map(fn ($r) => [$r->id, $r->name])->values();

        return [
            'departments' => $pairs(Department::query()->orderBy('name')->get(['id', 'name'])),
            'positions' => $pairs(Position::query()->orderBy('name')->get(['id', 'name'])),
            'properties' => $pairs(Property::query()->orderBy('name')->get(['id', 'name'])),
            'members' => $pairs(app(TenantContext::class)->tenant()->users()->wherePivot('status', 'active')->orderBy('name')->get(['users.id', 'users.name'])),
            'types' => collect(Employee::TYPES)->map(fn ($t) => [$t, Str::headline($t)]),
        ];
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
