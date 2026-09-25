<?php

namespace App\Modules\PlatformAdmin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Marketplace\Models\Amenity;
use App\Modules\Marketplace\Models\Cuisine;
use App\Modules\Marketplace\Models\Location;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\PropertyType;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\PlatformAdmin\Models\ContentReport;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Marketplace administration (Phase 29): placement (featured, sponsored,
 * ranking boost), verification of listings and hosts, categories and
 * locations, and the queue of guest reports.
 */
class MarketplaceAdminController extends Controller
{
    private const LISTINGS = ['properties' => Property::class, 'restaurants' => Restaurant::class];

    /** type => [model, label, in-use check (table, column) or pivot] */
    private const TAXONOMY = [
        'locations' => [Location::class, 'Locations', [['properties', 'location_id'], ['restaurants', 'location_id']]],
        'property-types' => [PropertyType::class, 'Property categories', [['properties', 'property_type_id']]],
        'cuisines' => [Cuisine::class, 'Cuisines', [['cuisine_restaurant', 'cuisine_id']]],
        'amenities' => [Amenity::class, 'Amenities', [['property_amenity', 'amenity_id']]],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    // ---- Public: a signed-in guest reports a listing --------------------

    public function report(Request $request, string $kind, string $slug)
    {
        $model = self::LISTINGS[$kind] ?? abort(404);
        $listing = $model::publicQuery()->where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(ContentReport::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        // One open report per person per listing.
        ContentReport::query()->firstOrCreate(
            ['user_id' => $request->user()->id, 'reportable_type' => $listing->getMorphClass(), 'reportable_id' => $listing->id, 'status' => 'open'],
            ['reason' => $data['reason'], 'details' => $data['details'] ?? null],
        );

        return back()->with('success', 'Thanks — our team will review this listing.');
    }

    // ---- Placement and verification --------------------------------------

    public function promote(Request $request, string $kind, int $id)
    {
        $model = self::LISTINGS[$kind] ?? abort(404);
        $data = $request->validate([
            'is_featured' => ['boolean'],
            'featured_until' => ['nullable', 'date', 'after:today'],
            'sponsored_until' => ['nullable', 'date', 'after:today'],
            'ranking_boost' => ['required', 'integer', 'between:-50,50'],
            'verified' => ['boolean'],
        ]);

        $listing = $model::query()->withoutGlobalScope('tenant')->findOrFail($id);
        $before = $listing->only(['is_featured', 'featured_until', 'sponsored_until', 'ranking_boost', 'verified_at']);

        $listing->forceFill([
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'featured_until' => $data['featured_until'] ?? null,
            'sponsored_until' => $data['sponsored_until'] ?? null,
            'ranking_boost' => $data['ranking_boost'],
            'verified_at' => ($data['verified'] ?? false) ? ($listing->verified_at ?? now()) : null,
        ])->save();

        $this->audit->log('platform.listing.placement', $listing, $before, $listing->only(array_keys($before)), $listing->tenant_id);

        return back()->with('success', $listing->name.' updated.');
    }

    public function verifyHost(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['verified' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:250']]);

        $tenant->forceFill(['verified_at' => $data['verified'] ? ($tenant->verified_at ?? now()) : null, 'verification_note' => $data['note'] ?? null])->save();
        $this->audit->log('platform.host.'.($data['verified'] ? 'verified' : 'unverified'), $tenant, null, ['note' => $data['note'] ?? null], $tenant->id);

        return back()->with('success', $tenant->name.($data['verified'] ? ' is now a verified host.' : ' is no longer verified.'));
    }

    // ---- Categories and locations ----------------------------------------

    public function taxonomy(string $type = 'locations')
    {
        [$model, $label] = self::TAXONOMY[$type] ?? abort(404);

        return view('platform-admin::taxonomy', [
            'type' => $type,
            'label' => $label,
            'types' => collect(self::TAXONOMY)->map(fn ($t) => $t[1]),
            'items' => $model::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function saveTaxonomy(Request $request, string $type, ?int $id = null)
    {
        [$model] = self::TAXONOMY[$type] ?? abort(404);
        $table = (new $model)->getTable();

        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'max:120', Rule::unique($table, 'slug')->ignore($id)],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'flag' => ['boolean'], // is_featured for locations, is_active otherwise
            'region' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:40'],
        ]);

        $item = $id ? $model::query()->findOrFail($id) : new $model;
        $attributes = [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            $type === 'locations' ? 'is_featured' : 'is_active' => (bool) ($data['flag'] ?? false),
        ];
        if ($type === 'locations') {
            $attributes['region'] = $data['region'] ?? null;
        }
        if (in_array($type, ['property-types', 'amenities'], true) && filled($data['category'] ?? null)) {
            $attributes['category'] = $data['category'];
        }
        $item->forceFill($attributes)->save();

        $this->audit->log('platform.taxonomy.saved', $item, null, ['type' => $type, 'name' => $item->name]);

        return back()->with('success', $item->name.' saved.');
    }

    public function deleteTaxonomy(string $type, int $id)
    {
        [$model, , $uses] = self::TAXONOMY[$type] ?? abort(404);
        $item = $model::query()->findOrFail($id);

        foreach ($uses as [$table, $column]) {
            if (DB::table($table)->where($column, $id)->exists()) {
                throw ValidationException::withMessages(['taxonomy' => $item->name.' is used by listings. Hide it instead of deleting.']);
            }
        }

        $item->delete();
        $this->audit->log('platform.taxonomy.deleted', $item, null, ['type' => $type, 'name' => $item->name]);

        return back()->with('success', $item->name.' deleted.');
    }

    // ---- Reported content -------------------------------------------------

    public function moderation(Request $request)
    {
        $status = in_array($request->query('status'), ['open', 'resolved', 'dismissed'], true) ? $request->query('status') : 'open';

        return view('platform-admin::moderation', [
            'status' => $status,
            'reports' => ContentReport::query()->with(['reportable', 'user:id,name,email'])->where('status', $status)->latest('id')->paginate(30)->withQueryString(),
            'flaggedReviews' => \App\Modules\Marketplace\Models\Review::query()->withoutGlobalScope('tenant')->whereNotNull('flagged_at')->whereNull('moderated_by')->count(),
        ]);
    }

    public function resolve(Request $request, ContentReport $report)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['dismiss', 'resolve', 'suspend'])],
            'note' => ['nullable', 'string', 'max:250', 'required_if:action,suspend'],
        ]);

        if ($report->status !== 'open') {
            throw ValidationException::withMessages(['report' => 'This report is already closed.']);
        }

        if ($data['action'] === 'suspend' && $listing = $report->reportable) {
            $listing->forceFill(['status' => $listing::STATUS_SUSPENDED])->save();
            $this->audit->log('platform.listing.suspended', $listing, null, ['reason' => $data['note'], 'report_id' => $report->id], $listing->tenant_id);
        }

        // Close every open report on the same listing with the same outcome.
        ContentReport::query()->where('reportable_type', $report->reportable_type)->where('reportable_id', $report->reportable_id)->where('status', 'open')
            ->update([
                'status' => $data['action'] === 'dismiss' ? 'dismissed' : 'resolved',
                'resolved_by' => $request->user()->id,
                'resolution_note' => $data['note'] ?? null,
                'resolved_at' => now(),
            ]);

        return back()->with('success', 'Report closed.');
    }
}
