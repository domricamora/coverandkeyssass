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

        return view('platform-admin::listings', ['kind' => $kind, 'listings' => $listings]);
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
