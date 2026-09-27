<?php

namespace App\Modules\Marketplace\Controllers;

use App\Modules\Marketplace\Services\MarketplaceSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * Marketplace landing page (`/stays`): curated stays, destinations,
 * property categories and restaurants — all read-only public data.
 */
class HomeController extends Controller
{
    public function __construct(private readonly MarketplaceSearchService $search) {}

    public function index(): View
    {
        return view('marketplace::home', [
            'featured' => $this->search->featuredProperties(6),
            'destinations' => $this->search->destinations(12),
            'types' => $this->search->propertyTypes(),
            'restaurants' => $this->search->featuredRestaurants(3),
            'stats' => $this->search->stats(),
            'title' => 'Stays and Restaurants in the Philippines',
        ]);
    }
}