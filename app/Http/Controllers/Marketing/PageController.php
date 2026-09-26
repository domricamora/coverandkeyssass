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
            'featured' => $this->marketplace->featuredProperties(6),
            'restaurants' => $this->marketplace->featuredRestaurants(3),
            // Each destination card shows the cover of its best listing.
            'destinations' => $this->marketplace->destinations(6)->each(fn ($location) => $location->setAttribute(
                'cover',
                \App\Modules\Marketplace\Models\Property::publicQuery()->where('location_id', $location->id)->ranked()->first()?->galleryUrls()[0] ?? null,
            )),
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
            // Standard marketplace rate: the global rule, else the platform default.
            'commission' => (float) (\App\Modules\Wallet\Models\CommissionRate::query()->where('kind', \App\Modules\Wallet\Models\CommissionRate::GLOBAL)->latest('id')->value('rate')
                ?? \App\Modules\Wallet\Models\CommissionRate::defaultRate()),
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
     * Delivery roadmap shown on the marketing pages, grouped from the master
     * plan phases. Update the statuses when a phase actually ships.
     */
    public static function roadmap(): array
    {
        return [
            ['phases' => '01-08', 'title' => 'Stays and payments', 'status' => 'complete', 'summary' => 'Marketplace, property and room inventory, booking engine, guest portal, PayMongo, host wallet and payouts.'],
            ['phases' => '09-14', 'title' => 'Restaurants', 'status' => 'complete', 'summary' => 'Menus, table reservations, online ordering, delivery, room service and the guest folio.'],
            ['phases' => '15-19', 'title' => 'Operations', 'status' => 'complete', 'summary' => 'Housekeeping, maintenance, staff rosters, inventory and purchasing, point of sale.'],
            ['phases' => '20-27', 'title' => 'Back office and guests', 'status' => 'complete', 'summary' => 'Double-entry accounting, CRM, campaigns, loyalty, reviews, messaging, notifications and SaaS billing.'],
            ['phases' => '28-30', 'title' => 'Platform', 'status' => 'complete', 'summary' => 'Super Admin control centre, marketplace placement and moderation, SEO and structured data.'],
            ['phases' => '31', 'title' => 'Public API', 'status' => 'next', 'summary' => 'Token-authenticated REST API for channel managers, apps and integrations.'],
            ['phases' => '32-38', 'title' => 'Hardening and launch', 'status' => 'planned', 'summary' => 'Security audit, performance, deployment, backups and monitoring.'],
        ];
    }
}
