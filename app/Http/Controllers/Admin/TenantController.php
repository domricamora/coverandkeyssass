<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuditLogger;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    private const TYPES = ['hotel' => 'Hotel', 'resort' => 'Resort', 'bnb' => 'Bed & Breakfast', 'guesthouse' => 'Guesthouse', 'apartment' => 'Apartment', 'condo' => 'Condo', 'villa' => 'Villa', 'hostel' => 'Hostel', 'restaurant' => 'Restaurant'];

    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(Request $request)
    {
        $tenants = Tenant::query()
            ->withCount('users')
            ->when($request->string('q'), function ($query, $q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return \Inertia\Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants->through(fn (Tenant $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'type' => ucfirst((string) $t->business_type),
                'status' => $t->status,
                'verified' => (bool) $t->verified_at,
                'members' => $t->users_count,
                'href' => route('admin.tenants.show', $t),
            ]),
            'filters' => $request->only('q'),
            'urls' => ['create' => route('admin.tenants.create')],
        ]);
    }

    public function create()
    {
        return \Inertia\Inertia::render('Admin/Tenants/Create', [
            'types' => self::TYPES,
            'urls' => ['store' => route('admin.tenants.store'), 'back' => route('admin.tenants.index')],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'business_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'owner_email' => ['required', 'email', 'exists:users,email'],
        ]);

        $tenant = DB::transaction(function () use ($validated) {
            $tenant = Tenant::create([
                'name' => $validated['name'],
                'business_type' => $validated['business_type'],
                'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(5)),
                'status' => 'active',
            ]);

            app(\App\Support\TenantContext::class)->set($tenant);
            RoleSeeder::ensureTenantRoles($tenant->id);

            // Attach the designated owner.
            $owner = User::query()->where('email', \strtolower($validated['owner_email']))->firstOrFail();

            $tenant->users()->attach($owner->id, [
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $ownerRole = $tenant->roles()->where('slug', 'owner')->firstOrFail();
            $owner->assignTenantRole($tenant, $ownerRole);

            return $tenant;
        });

        app(\App\Support\TenantContext::class)->forget();

        $this->audit->log('platform.tenant.created', $tenant, null, ['name' => $tenant->name]);

        return redirect()->route('admin.tenants.show', $tenant)
            ->with('success', __('Tenant created.'));
    }

    public function show(Tenant $tenant)
    {
        $tenant->loadCount('users');

        $members = $tenant->tenantUsers()->with('user:id,name,email,status')->get();

        return \Inertia\Inertia::render('Admin/Tenants/Show', [
            'tenant' => [
                'name' => $tenant->name,
                'type' => ucfirst((string) $tenant->business_type),
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'members' => $tenant->users_count,
                'created' => $tenant->created_at?->format('M j, Y'),
                'verified' => $tenant->verified_at?->format('M j, Y'),
                'verificationNote' => $tenant->verification_note,
            ],
            'members' => $members->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'status' => $m->user?->status,
            ]),
            'urls' => [
                'back' => route('admin.tenants.index'),
                'modules' => route('admin.tenants.modules.edit', $tenant),
                'verify' => route('admin.tenants.verify', $tenant),
                'suspend' => route('admin.tenants.suspend', $tenant),
                'activate' => route('admin.tenants.activate', $tenant),
                'destroy' => route('admin.tenants.destroy', $tenant),
            ],
        ]);
    }

    public function suspend(Tenant $tenant)
    {
        $old = ['status' => $tenant->status];
        $tenant->update(['status' => 'suspended']);
        $this->audit->log('platform.tenant.suspended', $tenant, $old, ['status' => 'suspended']);

        return back()->with('success', __('Tenant suspended. Its members lose access immediately.'));
    }

    public function activate(Tenant $tenant)
    {
        $old = ['status' => $tenant->status];
        $tenant->update(['status' => 'active']);
        $this->audit->log('platform.tenant.activated', $tenant, $old, ['status' => 'active']);

        return back()->with('success', __('Tenant activated.'));
    }

    public function destroy(Tenant $tenant)
    {
        $old = ['name' => $tenant->name, 'slug' => $tenant->slug];
        $tenant->delete();
        $this->audit->log('platform.tenant.deleted', $tenant, $old, null);

        return redirect()->route('admin.tenants.index')->with('success', __('Tenant deleted.'));
    }
}
