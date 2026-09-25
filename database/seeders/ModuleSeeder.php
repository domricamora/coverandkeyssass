<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * The canonical module list for Cover & Keys.
     *
     * Slugs are stable identifiers referenced by dependency metadata
     * and tenant module records — they must not change once created.
     */
    public static function moduleDefinitions(): array
    {
        return [
            [
                'slug' => 'core',
                'name' => 'Core',
                'description' => 'Platform foundation — tenant management, team, roles, and audit.',
                'category' => 'foundation',
                'icon' => 'shield-check',
                'is_core' => true,
                'trial_days' => 0,
                'sort_order' => 0,
            ],
            [
                'slug' => 'property',
                'name' => 'Property Management',
                'description' => 'Properties, rooms, room types, and inventory.',
                'category' => 'operations',
                'icon' => 'building-2',
                'is_core' => false,
                'trial_days' => 14,
                'sort_order' => 10,
                'metadata' => ['dependencies' => ['core']],
            ],
            [
                'slug' => 'booking',
                'name' => 'Booking Engine',
                'description' => 'Reservations, availability calendar, and rate management.',
                'category' => 'operations',
                'icon' => 'calendar',
                'is_core' => false,
                'trial_days' => 14,
                'sort_order' => 20,
                'metadata' => ['dependencies' => ['core', 'property']],
            ],
            [
                'slug' => 'workforce',
                'name' => 'Workforce',
                'description' => 'Staff management, scheduling, and attendance.',
                'category' => 'operations',
                'icon' => 'users',
                'is_core' => false,
                'trial_days' => 14,
                'sort_order' => 30,
                'metadata' => ['dependencies' => ['core']],
            ],
            [
                'slug' => 'restaurant',
                'name' => 'Restaurant',
                'description' => 'Menu management, ordering, and table reservations.',
                'category' => 'operations',
                'icon' => 'utensils',
                'is_core' => false,
                'trial_days' => 14,
                'sort_order' => 40,
                'metadata' => ['dependencies' => ['core']],
            ],
            [
                'slug' => 'pos',
                'name' => 'Point of Sale',
                'description' => 'Dine-in tickets, kitchen display, cashier, cash sessions and daily closing.',
                'category' => 'operations',
                'icon' => 'receipt',
                'is_core' => false,
                'trial_days' => 14,
                'sort_order' => 45,
                'metadata' => ['dependencies' => ['restaurant']],
            ],
            [
                'slug' => 'inventory',
                'name' => 'Inventory',
                'description' => 'Stock tracking, purchase orders, and suppliers.',
                'category' => 'operations',
                'icon' => 'package',
                'is_core' => false,
                'trial_days' => 14,
                'sort_order' => 50,
                'metadata' => ['dependencies' => ['core']],
            ],
            [
                'slug' => 'finance',
                'name' => 'Finance',
                'description' => 'Invoicing, expenses, and financial reporting.',
                'category' => 'finance',
                'icon' => 'wallet',
                'is_core' => false,
                'trial_days' => 14,
                'sort_order' => 60,
                'metadata' => ['dependencies' => ['core']],
            ],
            [
                'slug' => 'crm',
                'name' => 'CRM',
                'description' => 'Customer profiles, communication, and loyalty.',
                'category' => 'marketing',
                'icon' => 'heart',
                'is_core' => false,
                'trial_days' => 14,
                'sort_order' => 70,
                'metadata' => ['dependencies' => ['core']],
            ],
            [
                'slug' => 'analytics',
                'name' => 'Analytics',
                'description' => 'Dashboards, reports, and business intelligence.',
                'category' => 'intelligence',
                'icon' => 'chart-bar',
                'is_core' => false,
                'trial_days' => 14,
                'sort_order' => 80,
                'metadata' => ['dependencies' => ['core']],
            ],
        ];
    }

    /**
     * Commercial catalogue: monthly price (in cents) and the feature bullets
     * shown on the marketing site and in the Super Admin module screens.
     *
     * Kept separate from moduleDefinitions() so those arrays only ever carry
     * `modules` columns.
     */
    public static function moduleCatalog(): array
    {
        return [
            'core' => [
                'price_cents' => 0,
                'features' => [
                    ['Multi-tenant accounts', 'One login per business, hard-isolated data.', true],
                    ['Roles & permissions', 'Granular RBAC with per-property permission sets.', true],
                    ['Audit trail', 'Every privileged action recorded with actor and IP.', false],
                ],
            ],
            'property' => [
                'price_cents' => 149900,
                'features' => [
                    ['Properties & room inventory', 'Hotels, resorts, B&Bs and rentals in one registry.', true],
                    ['Room types & rates', 'Room type → room → rate plan structure.', true],
                    ['Amenities & policies', 'Reusable catalogue for listings and filters.', false],
                    ['Photo galleries', 'Cover image and unlimited gallery uploads.', false],
                ],
            ],
            'booking' => [
                'price_cents' => 199900,
                'features' => [
                    ['Live availability calendar', 'Backend-authoritative availability, never client-trusted.', true],
                    ['Zero double bookings', 'Transactional inventory locking on every reservation.', true],
                    ['Multi-room & group stays', 'Book several rooms on one reservation.', false],
                    ['Deposits, holds & no-shows', 'Full reservation lifecycle with audit.', false],
                ],
            ],
            'workforce' => [
                'price_cents' => 69900,
                'features' => [
                    ['Staff & roles', 'Front desk, housekeeping, kitchen and maintenance roles.', true],
                    ['Shift scheduling', 'Weekly rosters with coverage checks.', false],
                    ['Task assignment', 'Housekeeping and maintenance queues.', false],
                ],
            ],
            'restaurant' => [
                'price_cents' => 149900,
                'features' => [
                    ['Menus & modifiers', 'Categories, items, add-ons and pricing.', true],
                    ['Table reservations', 'Time slots with overbooking protection.', true],
                    ['Online ordering', 'Pickup, delivery and hotel room service.', false],
                ],
            ],
            'pos' => [
                'price_cents' => 79900,
                'features' => [
                    ['Tables & tickets', 'Dine-in orders per table, split payments, receipts.', true],
                    ['Cash sessions', 'Opening float, cash count, variance and Z-report.', true],
                ],
            ],
            'inventory' => [
                'price_cents' => 99900,
                'features' => [
                    ['Stock tracking', 'Per-store quantities with reorder levels.', true],
                    ['Purchase orders', 'Suppliers, receiving and cost history.', false],
                ],
            ],
            'finance' => [
                'price_cents' => 129900,
                'features' => [
                    ['Guest folios', 'Room, food and service charges on one bill.', true],
                    ['Invoices & payouts', 'Host wallet, commissions and payout runs.', true],
                ],
            ],
            'crm' => [
                'price_cents' => 89900,
                'features' => [
                    ['Guest profiles', 'Stay history, preferences and contact data.', true],
                    ['Loyalty & campaigns', 'Points, coupons and automated follow-ups.', false],
                ],
            ],
            'analytics' => [
                'price_cents' => 79900,
                'features' => [
                    ['Occupancy & ADR', 'Revenue KPIs that update as bookings land.', true],
                    ['Custom reports', 'Exportable operational and financial reports.', false],
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::moduleDefinitions() as $definition) {
            $module = Module::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                $definition,
            );

            $this->seedCatalog($module);
        }
    }

    /** Per-business caps included with each module (Super Admin can override per business). */
    public const PLAN_LIMITS = [
        'core' => ['staff' => 25],
        'property' => ['properties' => 5, 'rooms' => 150],
        'restaurant' => ['restaurants' => 3],
    ];

    /** Seeds the module's monthly and yearly plans and feature bullets (idempotent). */
    private function seedCatalog(Module $module): void
    {
        $catalog = self::moduleCatalog()[$module->slug] ?? null;

        if ($catalog === null) {
            return;
        }

        // Yearly = 10 × monthly (two months free). Limits feed Billing\Support\Usage (Phase 27).
        foreach (['Monthly' => ['monthly', 1], 'Yearly' => ['yearly', 10]] as $name => [$interval, $multiplier]) {
            $module->plans()->updateOrCreate(
                ['name' => $name],
                [
                    'price_cents' => $catalog['price_cents'] * $multiplier,
                    'currency' => 'PHP',
                    'billing_interval' => $interval,
                    'limits' => self::PLAN_LIMITS[$module->slug] ?? null,
                    'is_active' => true,
                ],
            );
        }

        foreach ($catalog['features'] as $index => [$name, $description, $highlighted]) {
            $module->features()->updateOrCreate(
                ['name' => $name],
                [
                    'description' => $description,
                    'is_highlighted' => $highlighted,
                    'sort_order' => $index,
                ],
            );
        }
    }
}