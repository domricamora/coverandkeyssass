<?php

namespace App\Modules\Reviews\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\Review;
use App\Modules\Reviews\Services\ReviewService;
use App\Support\TenantContext;
use Illuminate\Http\Request;

/** The business's reviews (Phase 24): read, reply, report abuse. Scoped by reviews.tenant_id. */
class HostReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'reviews.view');
        $tenantId = app(TenantContext::class)->id();

        $base = Review::query()->where('tenant_id', $tenantId);

        return \Inertia\Inertia::render('Reviews/Index', [
            'reviews' => (clone $base)->with(['user', 'reviewable', 'itemRatings.menuItem', 'roomType'])
                ->when($request->query('filter') === 'unanswered', fn ($q) => $q->whereNull('host_response'))
                ->when($request->query('filter') === 'low', fn ($q) => $q->where('rating', '<=', 2))
                ->latest()->paginate(20)->withQueryString()
                ->through(fn (Review $r) => [
                    'id' => $r->id,
                    'guest' => $r->user?->name ?? 'Guest',
                    'rating' => (int) $r->rating,
                    'about' => collect([$r->reviewable?->name, $r->roomType?->name, $r->booking_id ? 'stay' : ($r->order_id ? 'order' : ($r->table_reservation_id ? 'table visit' : null)), $r->created_at->format('M j, Y')])->filter()->implode(' · '),
                    'status' => $r->status,
                    'flagged' => $r->flagged_at !== null,
                    'categories' => collect(Review::CATEGORIES)->filter(fn ($c) => $r->{'rating_'.$c})->map(fn ($c) => ucfirst($c).' '.$r->{'rating_'.$c})->values(),
                    'title' => $r->title,
                    'comment' => $r->comment,
                    'dishes' => $r->itemRatings->map(fn ($ir) => $ir->menuItem?->name.' '.$ir->rating.'★')->implode(' · '),
                    'reply' => $r->host_response,
                    'urls' => ['reply' => route('reviews.reply', $r->id), 'flag' => route('reviews.flag', $r->id)],
                ]),
            'average' => round((float) (clone $base)->published()->avg('rating'), 1),
            'unanswered' => (clone $base)->published()->whereNull('host_response')->count(),
            'filter' => (string) $request->query('filter', ''),
            'can' => ['reply' => $request->user()->hasPermissionTo('reviews.reply')],
            'urls' => ['self' => route('reviews.index')],
        ]);
    }

    public function reply(Request $request, string $review)
    {
        $this->authorizeTo($request, 'reviews.reply');
        $this->reviews->reply($this->find($review), $request->validate(['host_response' => ['required', 'string', 'max:2000']])['host_response'], $request->user());

        return back()->with('success', 'Reply posted.');
    }

    public function flag(Request $request, string $review)
    {
        $this->authorizeTo($request, 'reviews.reply');
        $this->reviews->flag($this->find($review), $request->validate(['flag_reason' => ['required', 'string', 'max:255']])['flag_reason']);

        return back()->with('success', 'Reported to the platform for moderation.');
    }

    private function find(string $id): Review
    {
        return Review::query()->where('tenant_id', app(TenantContext::class)->id())->findOrFail($id);
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
