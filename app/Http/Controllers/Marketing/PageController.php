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
    /** Shown on /pricing and emitted as FAQPage structured data from the same array. */
    public const PRICING_FAQ = [
        'Is there a free plan?' => 'Yes. The foundation (accounts, team roles, audit trail and a marketplace listing) is free for every business. You only pay for the operational modules you switch on.',
        'Can I try a module before paying?' => 'Modules with a trial period can be switched on without a payment method. You are invoiced only if you keep the module after the trial ends.',
        'Is yearly billing cheaper?' => 'Yearly billing costs ten times the monthly price, so two months are free.',
        'What happens if a payment fails?' => 'The invoice moves to past due and the module keeps working through a grace period. After that the module is suspended until the invoice is paid, and your data is kept.',
        'Do you take a commission on marketplace bookings?' => 'Online bookings and orders paid through the marketplace carry a platform commission. It is deducted before the balance reaches your host wallet, and every deduction is itemised.',
        'Which payment methods do guests have?' => 'Guests pay online by card, GCash or Maya through PayMongo. Restaurants can also accept cash on pickup and delivery orders.',
    ];

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
            'faqs' => self::PRICING_FAQ,
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
                $plan = $module->activePlans->firstWhere('billing_interval', 'monthly');

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