<?php

use App\Models\Module;
use App\Modules\Crm\Models\Contact;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyProgram;
use App\Modules\Loyalty\Models\Reward;
use App\Modules\Marketing\Models\Campaign;
use App\Support\ModuleService;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 34 (Testing) — tenant isolation for the guest-facing back-office
| modules that had no cross-tenant test: another business's loyalty members,
| rewards and campaigns are 404s, even for an owner with every permission.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    [$this->ownerA, $this->tenantA] = BookingFixtures::hotel(name: 'Alpha');
    [$this->ownerB, $this->tenantB] = BookingFixtures::hotel(name: 'Bravo');

    foreach ([$this->tenantA, $this->tenantB] as $tenant) {
        app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'crm')->firstOrFail(), $tenant, ['trial_days' => 0]);
    }

    MarketplaceFixtures::asTenant($this->tenantA);
    LoyaltyProgram::create(['enabled' => true, 'pesos_per_point' => 100, 'referral_points' => 200]);
    $contact = Contact::create(['name' => 'Alpha Guest', 'email' => 'alpha.guest@example.test', 'source' => 'manual']);
    $this->account = LoyaltyAccount::create(['crm_contact_id' => $contact->id, 'referral_code' => 'ALPHA1']);
    $this->reward = Reward::create(['name' => 'Free breakfast', 'points_cost' => 50, 'kind' => 'credit', 'credit_amount' => 300]);
    $this->campaign = Campaign::create(['name' => 'Alpha secret sale', 'channel' => 'email', 'audience' => 'all', 'subject' => 'Sale', 'body' => 'Hi {name}']);
    MarketplaceFixtures::asTenant(null);

    PropertyManagementFixtures::login($this->ownerB, $this->tenantB);
});

it('hides another business\'s loyalty members and rewards', function () {
    $this->get(route('loyalty.members.show', $this->account->id))->assertNotFound();
    $this->post(route('loyalty.members.adjust', $this->account->id), ['points' => 1000, 'reason' => 'x'])->assertNotFound();
    $this->post(route('loyalty.rewards.toggle', $this->reward->id))->assertNotFound();

    $this->get(route('loyalty.index'))->assertOk()->assertDontSee('Alpha Guest')->assertDontSee('Free breakfast');
});

it('hides another business\'s marketing campaigns', function () {
    $this->get(route('marketing.campaigns.show', $this->campaign->id))->assertNotFound();
    $this->post(route('marketing.campaigns.send', $this->campaign->id))->assertNotFound();

    $this->get(route('marketing.index'))->assertOk()->assertDontSee('Alpha secret sale');
    expect($this->campaign->refresh()->status)->not->toBe('sent');
});
