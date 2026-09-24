<?php

namespace App\Modules\Wallet\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Wallet\Models\Commission;
use App\Modules\Wallet\Models\CommissionRate;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Super Admin commission rates (Phase 08): global, per listing (property or
 * restaurant) and promotional (date window, optionally per listing), plus
 * platform revenue totals.
 */
class AdminCommissionController extends Controller
{
    private const LISTINGS = ['property' => Property::class, 'restaurant' => Restaurant::class];

    public function index()
    {
        $totals = Commission::query()->withoutGlobalScope('tenant')
            ->where('status', '!=', Commission::REVERSED)
            ->selectRaw('COALESCE(SUM(gross),0) gross, COALESCE(SUM(platform_fee),0) fee, COALESCE(SUM(host_amount),0) host')
            ->first();

        return view('wallet::admin.commissions', [
            'global' => CommissionRate::query()->where('kind', CommissionRate::GLOBAL)->latest('id')->first(),
            'defaultRate' => CommissionRate::defaultRate(),
            'rates' => CommissionRate::query()->where('kind', '!=', CommissionRate::GLOBAL)
                ->with(['rateable' => fn ($q) => $q->withoutGlobalScopes()])
                ->latest('id')->get(),
            'totals' => $totals,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::in([CommissionRate::GLOBAL, CommissionRate::LISTING, CommissionRate::PROMOTIONAL])],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'name' => ['nullable', 'string', 'max:120'],
            'listing_type' => ['nullable', Rule::in(array_keys(self::LISTINGS)), 'required_if:kind,listing'],
            'listing_slug' => ['nullable', 'string', 'max:200', 'required_if:kind,listing'],
            'starts_on' => ['nullable', 'date', 'required_if:kind,promotional'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on', 'required_if:kind,promotional'],
        ]);

        $attributes = ['kind' => $validated['kind'], 'rate' => $validated['rate'], 'name' => $validated['name'] ?? null];

        if ($validated['kind'] !== CommissionRate::GLOBAL && filled($validated['listing_slug'] ?? null)) {
            $class = self::LISTINGS[$validated['listing_type'] ?? 'property'];
            $listing = $class::query()->withoutGlobalScopes()->where('slug', $validated['listing_slug'])->first();

            if (! $listing) {
                return back()->withErrors(['listing_slug' => 'No '.($validated['listing_type'] ?? 'listing').' with that slug.'])->withInput();
            }

            $attributes += ['rateable_type' => $listing->getMorphClass(), 'rateable_id' => $listing->getKey()];
        }

        if ($validated['kind'] === CommissionRate::PROMOTIONAL) {
            $attributes += ['starts_on' => $validated['starts_on'], 'ends_on' => $validated['ends_on']];
        }

        $rate = $validated['kind'] === CommissionRate::GLOBAL
            ? CommissionRate::query()->updateOrCreate(['kind' => CommissionRate::GLOBAL], $attributes)
            : CommissionRate::create($attributes);

        app(AuditLogger::class)->log('commission_rate.saved', $rate, null, $attributes, null, Auth::id());

        return back()->with('success', 'Commission rate saved — applies to payments from now on.');
    }

    public function destroy(string $rate)
    {
        $rate = CommissionRate::query()->where('kind', '!=', CommissionRate::GLOBAL)->findOrFail($rate);
        $rate->delete();

        app(AuditLogger::class)->log('commission_rate.deleted', $rate, $rate->toArray(), null, null, Auth::id());

        return back()->with('success', 'Commission rate removed.');
    }
}
