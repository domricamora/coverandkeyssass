<?php

namespace App\Modules\Reviews\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\Review;
use App\Modules\Reviews\Services\ReviewService;
use Illuminate\Http\Request;

/** Platform moderation (Phase 24, Super Admin): flagged reviews first; publish or reject. */
class AdminReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function index(Request $request)
    {
        $status = in_array($request->query('status'), [Review::STATUS_PUBLISHED, Review::STATUS_REJECTED, Review::STATUS_PENDING], true) ? $request->query('status') : null;

        return view('reviews::admin', [
            'reviews' => Review::query()->with(['user', 'reviewable' => fn ($q) => $q->withoutGlobalScope('tenant')])
                ->when($request->boolean('flagged'), fn ($q) => $q->whereNotNull('flagged_at'))
                ->when($status, fn ($q) => $q->where('status', $status))
                ->orderByRaw('flagged_at IS NULL')->latest()->paginate(30)->withQueryString(),
            'flagged' => Review::query()->whereNotNull('flagged_at')->count(),
            'title' => 'Review moderation',
        ]);
    }

    public function moderate(Request $request, string $review)
    {
        $validated = $request->validate(['publish' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:255']]);
        $this->reviews->moderate(Review::query()->findOrFail($review), (bool) $validated['publish'], $request->user(), $validated['note'] ?? null);

        return back()->with('success', $validated['publish'] ? 'Review published.' : 'Review hidden.');
    }
}
