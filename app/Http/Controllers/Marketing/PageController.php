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

    /** Home page Q&A for guests and hosts; also emitted as FAQPage structured data. */
    public const HOME_FAQ = [
        'What is Cover & Keys?' => 'Cover & Keys is a Philippine hospitality platform. Guests use it to book hotels, resorts, villas, B&Bs and restaurant tables directly with the people who run them. Hotels and restaurants use the same platform to run their business: front desk, room chart, housekeeping, restaurant POS, payments and accounting.',
        'Where can I book a stay with Cover & Keys?' => 'Stays are listed across the Philippines, including Boracay, El Nido, Siargao, Cebu City and Baguio. Search by destination, stay type and number of guests, then book directly with the property.',
        'How do I pay for a booking?' => 'You can pay online by credit or debit card, GCash or Maya through PayMongo. Your bill is itemised night by night, and anything added during your stay, such as room service, is settled at the front desk.',
        'Is booking direct cheaper than an online travel agency?' => 'Booking direct means your reservation goes straight to the property’s own front desk with no reseller in between, so there is no extra reseller mark-up on the price you see.',
        'Can I reserve a restaurant table or order food online?' => 'Yes. Restaurants on Cover & Keys take table reservations and online orders for pickup, delivery or room service from the same menu their kitchen uses.',
        'What does Cover & Keys cost for a hotel or restaurant?' => 'The foundation, including your marketplace listing, team accounts and roles, is free. Operational modules such as property management, the booking engine, restaurant and point of sale are priced per month with a free trial, and a commission applies only to paid online bookings.',
    ];

    public function __construct(private readonly MarketplaceSearchService $marketplace) {}

    public function home(): View
    {
        return view('marketing.home', [
            'title' => 'Book Hotels, Resorts & Restaurants in the Philippines Direct',
            'faqs' => self::HOME_FAQ,
            'stats' => $this->marketplace->stats(),
            'featured' => $this->marketplace->featuredProperties(6),
            'restaurants' => $this->marketplace->featuredRestaurants(3),
            // Each destination card shows the cover of its best listing.
            'destinations' => $this->marketplace->destinations(12)->each(fn ($location) => $location->setAttribute(
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
            'title' => 'Hotel & Restaurant Management Software: PMS, Booking Engine and POS',
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
            'title' => 'Pricing: Hotel PMS, Booking Engine and Restaurant POS from ₱0',
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
            'title' => 'Contact Sales and Support',
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
