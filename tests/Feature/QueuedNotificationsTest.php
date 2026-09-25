<?php

use App\Modules\Maintenance\Models\MaintenanceTicket;
use App\Modules\Maintenance\Notifications\TicketAssigned;
use App\Modules\Maintenance\Services\MaintenanceService;
use App\Modules\Notify\Models\NotificationPreference;
use App\Support\RestoreTenantContext;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;

/*
| Phase 35 (Deployment) — notifications are queued: in-app stays immediate,
| email waits for the worker; the worker runs each job inside the business it
| was dispatched for (RestoreTenantContext) and puts the context back.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    [$this->owner, $this->tenant, $this->property] = BookingFixtures::hotel();
    MarketplaceFixtures::asTenant($this->tenant);
    $opened = app(MaintenanceService::class)->open($this->property, $this->property->rooms()->first(), ['title' => 'Leaking tap'], $this->owner);
    $this->ticket = MaintenanceTicket::query()->findOrFail($opened->id);
    MarketplaceFixtures::asTenant(null);
});

it('sends in-app now, queues email, and the worker delivers it', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);
    NotificationPreference::create(['user_id' => $this->owner->id, 'event' => 'ticket_assigned', 'channel' => 'mail', 'enabled' => true]);

    $notification = new TicketAssigned($this->ticket);
    $this->owner->notify($notification);                         // dispatched with no tenant context, as a webhook would

    // The sender clones the notification before via(), so read what was queued.
    $queued = unserialize(json_decode(DB::table('jobs')->value('payload'), true)['data']['command']);

    expect($queued->notification->tenantId)->toBe($this->tenant->id) // captured from the ticket itself
        ->and($this->owner->notifications()->count())->toBe(1)        // in-app: sync
        ->and(DB::table('jobs')->count())->toBe(1);                   // email: queued

    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(1);
});

it('runs a job inside its business and always restores the previous context', function () {
    $context = app(TenantContext::class);
    $middleware = new RestoreTenantContext($this->tenant->id);

    $seen = $middleware->handle(new stdClass, fn () => $context->id());
    expect($seen)->toBe($this->tenant->id)->and($context->has())->toBeFalse();

    [, $other] = MarketplaceFixtures::business('Other Co');
    $context->set($other);
    expect(fn () => $middleware->handle(new stdClass, fn () => throw new RuntimeException('boom')))->toThrow(RuntimeException::class);
    expect($context->id())->toBe($other->id);                   // restored even when the job throws
});
