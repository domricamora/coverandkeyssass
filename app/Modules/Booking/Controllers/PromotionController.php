<?php

namespace App\Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Promotion;
use App\Modules\Marketplace\Models\Property;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Promo codes for the booking engine (Phase 05). Tenant-scoped through
 * BelongsToTenant; codes are stored upper-case and unique per business.
 */
class PromotionController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermissionTo('promotions.manage'), 403);

        return \Inertia\Inertia::render('Bookings/Promotions', [
            'promotions' => Promotion::query()->where('applies_to', Promotion::FOR_STAYS)->latest()->get()->map(fn (Promotion $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'discount' => $p->label(),
                'valid' => ($p->starts_on?->format('M j, Y') ?? 'Any').' – '.($p->ends_on?->format('M j, Y') ?? 'Any'),
                'used' => $p->used_count.($p->max_uses ? ' / '.$p->max_uses : ''),
                'active' => (bool) $p->is_active,
                'toggle' => route('bookings.promotions.toggle', $p->id),
            ]),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name'])->map(fn ($p) => [$p->id, $p->name]),
            'urls' => ['store' => route('bookings.promotions.store'), 'bookings' => route('bookings.index')],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermissionTo('promotions.manage'), 403);

        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $validated = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('promotions')->where('tenant_id', app(TenantContext::class)->id())],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in([Promotion::TYPE_PERCENT, Promotion::TYPE_FIXED])],
            'value' => ['required', 'numeric', 'min:0.01', $request->input('type') === Promotion::TYPE_PERCENT ? 'max:100' : 'max:9999999'],
            'property_id' => ['nullable', 'integer', Rule::in(Property::query()->pluck('id'))],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'min_nights' => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
        ]);

        Promotion::create(array_merge($validated, ['min_nights' => $validated['min_nights'] ?? 1, 'is_active' => true]));

        return back()->with('success', 'Promo code '.$validated['code'].' created.');
    }

    public function toggle(Request $request, string $promotion)
    {
        abort_unless($request->user()->hasPermissionTo('promotions.manage'), 403);

        $promotion = Promotion::query()->findOrFail($promotion);
        $promotion->update(['is_active' => ! $promotion->is_active]);

        return back()->with('success', 'Promo code '.$promotion->code.' '.($promotion->is_active ? 'reactivated' : 'deactivated').'.');
    }
}
