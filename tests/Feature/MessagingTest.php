<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Crm\Models\Interaction;
use App\Modules\Messaging\Models\Message;
use App\Modules\Messaging\Models\Thread;
use App\Modules\Messaging\Notifications\NewMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 25 (Messaging) — guest ↔ host / restaurant, guest ↔ support, staff
| threads; attachments; read status; notifications; CRM logging; access.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    Storage::fake('local');
    [$this->owner, $this->tenant, $this->property, $this->type] = BookingFixtures::hotel();
    MarketplaceFixtures::asTenant($this->tenant);
    $this->property->publish();
    $this->manager = MarketplaceFixtures::member($this->tenant, 'manager');
    $this->housekeeper = MarketplaceFixtures::member($this->tenant, 'staff');
    MarketplaceFixtures::asTenant(null);
    $this->guest = User::factory()->create(['name' => 'Maria']);
});

function newMessages(User $user): int
{
    return $user->notifications()->where('type', NewMessage::class)->count();
}

it('lets a guest message the host, and the host answer, with read status and CRM logging', function () {
    $this->actingAs($this->guest)->get(route('account.messages.create', ['property' => $this->property->slug]))->assertOk()->assertSee('Question about');
    $this->post(route('account.messages.store', ['property' => $this->property->slug]), [
        'subject' => 'Early check-in?', 'body' => 'Can we arrive at 10am?', 'files' => [UploadedFile::fake()->image('passport.jpg')],
    ])->assertRedirect();

    $thread = Thread::query()->firstOrFail();
    expect($thread->kind)->toBe(Thread::GUEST_HOST)->and($thread->tenant_id)->toBe($this->tenant->id)
        ->and(newMessages($this->owner))->toBe(1)->and(newMessages($this->manager))->toBe(1)
        ->and(newMessages($this->housekeeper))->toBe(0); // no messages.reply

    // Asking again about the same listing continues the thread.
    $this->post(route('account.messages.store', ['property' => $this->property->slug]), ['subject' => 'Also', 'body' => 'And parking?'])->assertRedirect();
    expect(Thread::query()->count())->toBe(1)->and($thread->messages()->count())->toBe(2);

    PropertyManagementFixtures::login($this->owner, $this->tenant);
    $this->get(route('messages.index'))->assertOk()->assertSee('Early check-in?')->assertSee('2 new');
    $this->get(route('messages.show', $thread->id))->assertOk()->assertSee('Can we arrive at 10am?')->assertSee('passport.jpg');
    $this->post(route('messages.reply', $thread->id), ['body' => 'Yes, from 11am.'])->assertRedirect();
    expect(newMessages($this->guest))->toBe(1)->and($thread->refresh()->unreadFor($this->owner))->toBe(0);

    $file = Message::query()->first()->attachments()->first();
    $this->get(route('messages.attachment', [$thread->id, $file->id]))->assertOk();

    MarketplaceFixtures::asTenant($this->tenant);
    expect(Interaction::query()->where('channel', 'chat')->pluck('direction')->all())->toBe(['inbound', 'inbound', 'outbound']);
    MarketplaceFixtures::asTenant(null);

    $this->actingAs($this->guest)->get(route('account.messages.show', $thread->id))->assertOk()->assertSee('Yes, from 11am.');
    $this->post(route('account.messages.close', $thread->id));
    $this->post(route('account.messages.reply', $thread->id), ['body' => 'One more thing'])->assertSessionHasErrors('body');
});

it('keeps conversations to the people in them', function () {
    $booking = BookingFixtures::reserve($this->property, $this->type, [], Booking::SOURCE_MARKETPLACE, $this->guest);
    MarketplaceFixtures::asTenant(null);

    $this->actingAs($this->guest)->post(route('account.messages.store', ['booking' => $booking->reference]), ['subject' => 'About my stay', 'body' => 'Hi'])->assertRedirect();
    $thread = Thread::query()->firstOrFail();

    // Another guest can neither read it nor message about someone else's booking.
    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get(route('account.messages.show', $thread->id))->assertNotFound();
    $this->get(route('messages.attachment', [$thread->id, 1]))->assertNotFound();
    $this->post(route('account.messages.store', ['booking' => $booking->reference]), ['subject' => 'x', 'body' => 'x'])->assertNotFound();

    // Staff without messages.view see nothing; another business sees nothing.
    PropertyManagementFixtures::login($this->housekeeper, $this->tenant);
    $this->get(route('messages.index'))->assertOk()->assertDontSee('About my stay');
    $this->get(route('messages.show', $thread->id))->assertNotFound();

    [$ownerB, $tenantB] = MarketplaceFixtures::business('Hotel B');
    PropertyManagementFixtures::login($ownerB, $tenantB);
    $this->get(route('messages.show', $thread->id))->assertNotFound();
});

it('routes support conversations to platform admins', function () {
    $admin = User::factory()->create(['name' => 'Platform Admin']);
    $admin->roles()->syncWithoutDetaching([Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->firstOrFail()->id => ['tenant_id' => null]]);

    $this->actingAs($this->guest)->post(route('account.messages.store'), ['subject' => 'Refund question', 'body' => 'Where is my refund?'])->assertRedirect();
    $thread = Thread::query()->where('kind', Thread::SUPPORT)->firstOrFail();
    expect(newMessages($admin))->toBe(1);

    $this->actingAs($admin)->get(route('admin.support.index'))->assertOk()->assertSee('Refund question');
    $this->post(route('admin.support.reply', $thread->id), ['body' => 'It is on its way.'])->assertRedirect();
    expect(newMessages($this->guest))->toBe(1);

    $this->actingAs($this->owner)->get(route('admin.support.index'))->assertForbidden();
});

it('runs private staff conversations inside a business', function () {
    PropertyManagementFixtures::login($this->owner, $this->tenant);
    $outsider = User::factory()->create();

    $this->post(route('messages.staff.store'), ['subject' => 'Room 101 leak', 'body' => 'Can you check?', 'participants' => [$this->housekeeper->id, $outsider->id]])->assertRedirect();
    $thread = Thread::query()->where('kind', Thread::STAFF)->firstOrFail();

    expect($thread->participants()->pluck('user_id')->sort()->values()->all())->toBe(collect([$this->owner->id, $this->housekeeper->id])->sort()->values()->all())
        ->and(newMessages($this->housekeeper))->toBe(1);

    PropertyManagementFixtures::login($this->housekeeper, $this->tenant);
    $this->get(route('messages.index'))->assertOk()->assertSee('Room 101 leak');
    $this->post(route('messages.reply', $thread->id), ['body' => 'On it.'])->assertRedirect();
    expect(newMessages($this->owner))->toBe(1);

    PropertyManagementFixtures::login($this->manager, $this->tenant); // not a participant
    $this->get(route('messages.show', $thread->id))->assertNotFound();
});
