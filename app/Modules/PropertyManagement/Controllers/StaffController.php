<?php

namespace App\Modules\PropertyManagement\Controllers;

use App\Models\User;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Models\PropertyStaff;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Property staff (Phase 04): per-property operational assignments.
 * Only active members of the business can be assigned, and only one
 * assignment per user per property exists.
 */
class StaffController extends PropertyManagementController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, string $property)
    {
        $property = $this->resolveProperty($property);
        $this->authorizeProperty($request, $property, 'properties.staff.manage');

        $staff = $property->staff()->with('user')->orderBy('created_at')->get();

        return \Inertia\Inertia::render('Properties/Staff', [
            'property' => ['name' => $property->name],
            'tabs' => $this->tabs($property, 'staff'),
            'staff' => $staff->map(fn (PropertyStaff $m) => [
                'id' => $m->id,
                'name' => $m->user?->name ?? '—',
                'email' => $m->user?->email,
                'role' => $m->role,
                'since' => $m->created_at?->format('M j, Y'),
                'update' => route('properties.staff.update', [$property, $m]),
                'destroy' => route('properties.staff.destroy', [$property, $m]),
            ]),
            'roles' => PropertyStaff::roles(),
            'urls' => ['store' => route('properties.staff.store', $property)],
        ]);
    }

    public function store(Request $request, string $property)
    {
        $property = $this->resolveProperty($property);
        $this->authorizeProperty($request, $property, 'properties.staff.manage');

        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', 'in:'.implode(',', PropertyStaff::roles())],
        ]);

        $user = User::query()->where('email', $validated['email'])->firstOrFail();

        $tenant = app(TenantContext::class)->tenant();

        if (! $user->belongsToTenant($tenant)) {
            throw ValidationException::withMessages([
                'email' => 'This user is not an active member of the business. Add them to the team first.',
            ]);
        }

        $assignment = PropertyStaff::query()->updateOrCreate(
            ['property_id' => $property->getKey(), 'user_id' => $user->getKey()],
            ['role' => $validated['role'], 'assigned_by' => $request->user()->getKey()],
        );

        $this->audit->log('property.staff.assigned', $property, null, [
            'user_id' => $user->getKey(),
            'role' => $assignment->role,
        ]);

        return back()->with('success', $user->name.' assigned as '.$assignment->roleLabel().'.');
    }

    public function update(Request $request, string $property, string $propertyStaff)
    {
        $property = $this->resolveProperty($property);
        $this->authorizeProperty($request, $property, 'properties.staff.manage');

        $assignment = $property->staff()->findOrFail($propertyStaff);

        $validated = $request->validate([
            'role' => ['required', 'in:'.implode(',', PropertyStaff::roles())],
        ]);

        $was = $assignment->role;
        $assignment->update(['role' => $validated['role']]);

        $this->audit->log('property.staff.updated', $property, ['role' => $was], ['role' => $assignment->role]);

        return back()->with('success', $assignment->user->name.' is now '.$assignment->roleLabel().'.');
    }

    public function destroy(Request $request, string $property, string $propertyStaff)
    {
        $property = $this->resolveProperty($property);
        $this->authorizeProperty($request, $property, 'properties.staff.manage');

        $assignment = $property->staff()->findOrFail($propertyStaff);

        $name = $assignment->user?->name ?? 'member';
        $assignment->delete();

        $this->audit->log('property.staff.removed', $property, ['user_id' => $assignment->user_id], null);

        return back()->with('success', $name.' removed from this property.');
    }
}
