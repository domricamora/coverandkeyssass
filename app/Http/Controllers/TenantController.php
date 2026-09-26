<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Rules\NotConflictingSlug;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(Request $request)
    {
        $tenants = $request->user()->tenants()
            ->wherePivot('status', 'active')
            ->where('tenants.status', 'active')
            ->orderBy('tenants.name')
            ->get();

        // /tenants runs without the tenant.context middleware: the chosen business is in the session.
        $current = app(TenantContext::class)->id() ?? (int) $request->session()->get('tenant_id');

        return \Inertia\Inertia::render('Businesses/Index', [
            'businesses' => $tenants->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'type' => \Illuminate\Support\Str::headline((string) $t->business_type),
                'current' => $current === $t->id,
                'switch' => route('tenants.switch', $t),
            ]),
            'urls' => ['create' => route('tenants.create')],
        ]);
    }

    public function create()
    {
        return view('tenants.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'business_type' => ['required', Rule::in(['hotel', 'resort', 'bnb', 'guesthouse', 'apartment', 'condo', 'villa', 'hostel', 'restaurant'])],
        ]);

        $tenant = DB::transaction(function () use ($request, $validated) {
            $tenant = Tenant::create([
                'name' => $validated['name'],
                'business_type' => $validated['business_type'],
                'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(5)),
                'status' => 'active',
            ]);

            // Seed this tenant's system roles.
            app(TenantContext::class)->set($tenant);
            RoleSeeder::ensureTenantRoles($tenant->id);

            // Creator becomes tenant owner.
            $tenant->users()->attach($request->user()->id, [
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $ownerRole = $tenant->roles()->where('slug', 'owner')->firstOrFail();
            $request->user()->assignTenantRole($tenant, $ownerRole);

            return $tenant;
        });

        app(TenantContext::class)->forget();

        $this->audit->log('tenant.created', $tenant, null, ['name' => $tenant->name], $tenant->id);

        Session::put('tenant_id', $tenant->id);

        return redirect()->route('dashboard')
            ->with('success', __('Business created. Welcome aboard!'));
    }

    public function switch(Request $request, Tenant $tenant)
    {
        $this->authorize('view', $tenant);

        if ($tenant->status !== 'active' || ! $request->user()->belongsToTenant($tenant)) {
            abort(403);
        }

        Session::put('tenant_id', $tenant->id);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
