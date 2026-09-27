<?php

namespace App\Modules\PlatformAdmin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Super Admin (Phase 28): every property and restaurant across businesses.
 * Approve (pending → published), suspend or reinstate, with a reason in the audit log.
 */
class ListingController extends Controller
{
    private const KINDS = ['properties' => Property::class, 'restaurants' => Restaurant::class];

    public function index(Request $request, string $kind)
    {
        $model = self::KINDS[$kind] ?? abort(404);

        $listings = $model::query()->withoutGlobalScope('tenant')->with('tenant')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderByRaw("status = 'pending' desc")->latest('id')
            ->paginate(30)->withQueryString();

        return \Inertia\Inertia::render('Admin/Listings', [
            'kind' => $kind,
            'listings' => $listings->through(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'business' => $l->tenant?->name,
                'status' => $l->status,
                'view' => $l->status === 'published' ? route($kind === 'properties' ? 'marketplace.properties.show' : 'marketplace.restaurants.show', $l->slug) : null,
                'sponsored' => $l->isSponsored(),
                'featured' => $l->isFeaturedNow(),
                'placement' => [
                    'is_featured' => (bool) $l->is_featured,
                    'featured_until' => $l->featured_until?->toDateString() ?? '',
                    'sponsored_until' => $l->sponsored_until?->toDateString() ?? '',
                    'ranking_boost' => (int) $l->ranking_boost,
                    'verified' => $l->isVerified(),
                ],
                'urls' => ['status' => route('admin.listings.status', [$kind, $l->id]), 'placement' => route('admin.listings.placement', [$kind, $l->id])],
            ]),
            'filters' => $request->only('q', 'status'),
        ]);
    }

    public function status(Request $request, string $kind, int $id, AuditLogger $audit)
    {
        $model = self::KINDS[$kind] ?? abort(404);
        $data = $request->validate([
            'status' => ['required', Rule::in([$model::STATUS_PUBLISHED, $model::STATUS_SUSPENDED, $model::STATUS_DRAFT])],
            'reason' => ['nullable', 'string', 'max:250', Rule::requiredIf($request->input('status') === $model::STATUS_SUSPENDED)],
        ]);

        $listing = $model::query()->withoutGlobalScope('tenant')->findOrFail($id);
        $from = $listing->status;
        $listing->forceFill(['status' => $data['status']])->save();

        $audit->log('platform.listing.'.$data['status'], $listing, ['status' => $from], ['status' => $data['status'], 'reason' => $data['reason'] ?? null], $listing->tenant_id);

        return back()->with('success', $listing->name.' is now '.$data['status'].'.');
    }
}
