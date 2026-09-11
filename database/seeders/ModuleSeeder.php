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

    public function run(): void
    {
        foreach (self::moduleDefinitions() as $definition) {
            Module::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                $definition,
            );
        }
    }
}