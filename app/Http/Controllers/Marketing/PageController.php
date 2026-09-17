<?php

namespace App\Http\Controllers\Marketing;

use App\Models\Module;
use App\Modules\Marketplace\Services\MarketplaceSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;

/**
 * Public marketing site for the platform itself (`/`, `/features`,
 * `/pricing`, `/contact`).
 *
 * Everything shown here is real platform data — module catalogue and live
 * marketplace counts — so the site cannot drift from the product. Contact
 * details come from config/marketing.php; unset values are surfaced as a
 * placeholder instead of being invented.
 */
class PageController extends Controller
{
    public function __construct(private readonly MarketplaceSearchService $marketplace) {}

    public function home(): View
    {
        return view('marketing.home', [
            'title' => null,
            'stats' => $this->marketplace->stats(),
            'featured' => $this->marketplace->featuredProperties(3),
            'destinations' => $this->marketplace->destinations(6),
            'types' => $this->marketplace->filterOptions()['property_types'],
            'modules' => $this->modules(),
            'roadmap' => self::roadmap(),
        ]);
    }

    public function features(): View
    {
        return view('marketing.features', [
            'title' => 'Features',
            'modules' => $this->modules(),
            'grouped' => $this->modules()->groupBy('category'),
            'roadmap' => self::roadmap(),
        ]);
    }

    public function pricing(): View
    {
        $modules = $this->modules();

        $stack = ['property', 'booking', 'restaurant'];

        return view('marketing.pricing', [
            'title' => 'Pricing',
            'modules' => $modules,
            'grouped' => $modules->groupBy('category'),
            'sampleStack' => $modules->whereIn('slug', $stack),
            'sampleTotal' => (int) $modules->whereIn('slug', $stack)->sum('monthly_price_cents'),
            'roadmap' => self::roadmap(),
        ]);
    }

    public function contact(): View
    {
        return view('marketing.contact', [
            'title' => 'Contact',
            'contact' => config('marketing.contact'),
        ]);
    }

    /** Active modules with their monthly plan price (from the module engine). */
    private function modules(): Collection
    {
        return Module::query()
            ->active()
            ->ordered()
            ->with(['activePlans', 'features'])
            ->get()
            ->map(function (Module $module) {
                $plan = $module->activePlans->first();

                $module->setAttribute('monthly_price_cents', (int) ($plan->price_cents ?? 0));

                return $module;
            });
    }

    /**
     * Delivery roadmap shown on the marketing pages. Mirrors the master
     * plan phase list — statuses are updated as each phase actually ships.
     */
    public static function roadmap(): array
    {
        return [
            ['phase' => 1, 'title' => 'Foundation', 'status' => 'complete', 'summary' => 'Accounts, multi-tenancy, RBAC, audit trail, Super Admin.'],
            ['phase' => 2, 'title' => 'Module engine', 'status' => 'complete', 'summary' => 'Enable/disable modules per business, pricing, limits, dependencies.'],
            ['phase' => 3, 'title' => 'Marketplace', 'status' => 'current', 'summary' => 'Public search, destinations, property and restaurant detail pages, wish lists.'],
            ['phase' => 4, 'title' => 'Property management', 'status' => 'next', 'summary' => 'Host-side property, room type and room inventory management.'],
            ['phase' => 5, 'title' => 'Booking engine', 'status' => 'planned', 'summary' => 'Availability, reservations, rate plans — no double bookings.'],
            ['phase' => 7, 'title' => 'PayMongo payments', 'status' => 'planned', 'summary' => 'Payment intents, verified webhooks, refunds.'],
            ['phase' => 9, 'title' => 'Restaurant operations', 'status' => 'planned', 'summary' => 'Menus, table reservations, online ordering, room service.'],
            ['phase' => 15, 'title' => 'Housekeeping & maintenance', 'status' => 'planned', 'summary' => 'Room status board, cleaning tasks, maintenance tickets.'],
        ];
    }
}