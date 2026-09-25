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
use Illuminate\Validation\Rule;

/** Employees, departments and positions (Phase 17). */
class StaffController extends Controller
{
    public function __construct(private readonly WorkforceService $workforce) {}

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'staff.view');

        return view('workforce::staff.index', [
            'employees' => Employee::query()->with(['department', 'position', 'user'])
                ->when($request->query('department'), fn ($q, $d) => $q->where('department_id', $d))
                ->orderByRaw("status = 'terminated'")->orderBy('name')->get(),
            'departments' => Department::query()->withCount('employees')->orderBy('name')->get(),
            'positions' => Position::query()->with('department')->orderBy('name')->get(),
        ] + $this->formOptions() + ['title' => 'Staff']);
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

        return view('workforce::staff.show', [
            'employee' => $employee,
            'role' => $employee->user?->tenantRole(app(TenantContext::class)->tenant())?->display_name,
            'attendances' => $employee->attendances()->limit(15)->get(),
            'leave' => $employee->leaveRequests()->limit(10)->get(),
            'shifts' => $employee->shifts()->scheduled()->where('starts_at', '>=', today())->limit(10)->get(),
        ] + $this->formOptions() + ['title' => $employee->name]);
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

    private function formOptions(): array
    {
        return [
            'allDepartments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'allPositions' => Position::query()->orderBy('name')->get(['id', 'name']),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'members' => app(TenantContext::class)->tenant()->users()->wherePivot('status', 'active')->orderBy('name')->get(['users.id', 'users.name']),
        ];
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
