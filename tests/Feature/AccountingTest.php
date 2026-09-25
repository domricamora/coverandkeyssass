<?php

use App\Models\Module;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\LedgerAccount;
use App\Modules\Accounting\Services\BooksService;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Folio\Services\FolioService;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Pos\Services\PosService;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Support\ModuleService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PayMongoFake;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 20 (Accounting) — double-entry ledger (balanced, idempotent,
| reversals), automatic postings from folios / orders / POS / PayMongo /
| commissions / stock / purchasing, expenses with VAT, invoices, supplier
| payments, P&L / trial balance / VAT, screens and isolation.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    PayMongoFake::configure();
    $this->travelTo(CarbonImmutable::parse('2030-09-05 15:00'));
    [$this->owner, $this->tenant, $this->property, $this->type] = BookingFixtures::hotel();
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'finance')->firstOrFail(), $this->tenant);
    MarketplaceFixtures::asTenant($this->tenant);
});

function ledger(): LedgerService
{
    return app(LedgerService::class);
}

function syncBooks(): void
{
    app(PostingService::class)->sync();
}

it('only accepts balanced entries, once per source, and reverses cleanly', function () {
    expect(fn () => ledger()->post('2030-09-05', 'Broken', [['cash', 100, 0], ['equity', 0, 90]]))->toThrow(ValidationException::class);

    ledger()->post('2030-09-05', 'Owner capital', [['cash', 1000, 0], ['equity', 0, 1000]], 'capital:1');
    ledger()->post('2030-09-05', 'Owner capital', [['cash', 1000, 0], ['equity', 0, 1000]], 'capital:1'); // replay
    expect(JournalEntry::query()->count())->toBe(1)->and(ledger()->balance('cash'))->toBe(1000.0);

    ledger()->reverse('capital:1', 'capital:1:reverse', '2030-09-06', 'Undo');
    expect(ledger()->balance('cash'))->toBe(0.0)->and(ledger()->balance('equity'))->toBe(0.0);
});

it('books a hotel stay from its folio: room revenue, desk payments, extras and voids', function () {
    $stay = BookingFixtures::reserve($this->property, $this->type); // Thu–Sun: 3,500 + 4,500 + 4,500
    app(BookingService::class)->transition($stay, Booking::CHECKED_IN);
    $folio = app(FolioService::class);
    $folio->sync($stay);
    $folio->addCharge($stay, 'transport', 'Airport transfer', 800, 1, $this->owner);
    $mistake = $folio->addCharge($stay, 'minibar', 'Wrong room', 300, 1, $this->owner);
    $folio->recordPayment($stay, 'cash', 5000, $this->owner);
    $folio->recordPayment($stay, 'card', 8300, $this->owner);
    $folio->void($mistake, 'Posted to wrong folio', $this->owner);

    syncBooks();
    syncBooks(); // idempotent

    $pnl = ledger()->profitAndLoss('2030-09-01', '2030-09-30');
    expect(collect($pnl['revenue'])->pluck('amount', 'name')->all())->toBe(['Room revenue' => 12500.0, 'Other revenue' => 800.0])
        ->and(ledger()->balance('cash'))->toBe(5000.0)
        ->and(ledger()->balance('bank'))->toBe(8300.0)
        ->and(ledger()->balance('guest_receivables'))->toBe(0.0);

    $tb = ledger()->trialBalance();
    expect($tb['debits'])->toBe($tb['credits']);
});

it('tracks PayMongo money in the platform wallet net of commission', function () {
    [$guest, $booking] = PayMongoFake::paidBooking(); // ₱12,500 online, 10% commission
    MarketplaceFixtures::asListing($booking);

    syncBooks();

    expect(ledger()->balance('platform_wallet'))->toBe(11250.0)
        ->and(collect(ledger()->profitAndLoss('2000-01-01', '2100-01-01')['expenses'])->firstWhere('name', 'Platform commissions')['amount'])->toBe(1250.0);

    app(BookingService::class)->transition($booking->refresh(), Booking::CANCELLED);
    app(BookingService::class)->transition($booking->refresh(), Booking::REFUNDED);
    syncBooks();

    expect(ledger()->balance('platform_wallet'))->toBe(0.0)->and(ledger()->balance('guest_receivables'))->toBe(0.0);
});

it('books register sales with output VAT, cost of goods and refunds', function () {
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'pos')->firstOrFail(), $this->tenant);
    $restaurant = MarketplaceFixtures::restaurant($this->tenant, $this->owner, ['status' => Restaurant::STATUS_PUBLISHED, 'tax_rate' => 12, 'tax_inclusive' => true]);
    MarketplaceFixtures::asTenant($this->tenant);
    $kitchen = StockLocation::create(['name' => 'Kitchen']);
    $restaurant->forceFill(['stock_location_id' => $kitchen->id])->save();
    $beef = InventoryItem::create(['sku' => 'BEEF', 'name' => 'Beef', 'unit' => 'kg']);
    $burger = MenuItem::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Mains'])->id, 'name' => 'Burger', 'price' => 280]);
    app(InventoryService::class)->setIngredient($burger, $beef, 200, 'g');
    app(InventoryService::class)->receive($beef, $kitchen, 10, 400, $this->owner); // no PO → paid in cash

    $pos = app(PosService::class);
    $pos->openSession($restaurant->refresh(), 0, $this->owner);
    $order = app(OrderService::class)->placeAtRegister($restaurant, [['item_id' => $burger->id, 'quantity' => 1]], null, $this->owner);
    $pos->pay($order, 'cash', 280, $this->owner, tendered: 300);
    $pos->close($order->refresh());
    syncBooks();

    $pnl = ledger()->profitAndLoss('2030-09-01', '2030-09-30');
    expect(collect($pnl['revenue'])->firstWhere('name', 'Food & beverage revenue')['amount'])->toBe(250.0)
        ->and(ledger()->vat('2030-09-01', '2030-09-30')['output'])->toBe(30.0)
        ->and(collect($pnl['expenses'])->firstWhere('name', 'Cost of goods sold')['amount'])->toBe(80.0) // 0.2 kg × ₱400
        ->and(ledger()->balance('inventory'))->toBe(3920.0)
        ->and(ledger()->balance('cash'))->toBe(-3720.0); // −4,000 stock + 280 sale

    $pos->refund($order->refresh(), 'cash', 'Complaint', $this->owner);
    syncBooks();
    expect(ledger()->vat('2030-09-01', '2030-09-30')['output'])->toBe(0.0)
        ->and(ledger()->balance('cash'))->toBe(-4000.0);
});

it('runs purchasing, expenses, invoices and supplier payments through payables and receivables', function () {
    $books = app(BooksService::class);
    $inv = app(InventoryService::class);
    $supplier = Supplier::create(['name' => 'Island Meats']);
    $store = StockLocation::create(['name' => 'Store']);
    $beef = InventoryItem::create(['sku' => 'BEEF', 'name' => 'Beef', 'unit' => 'kg']);

    $po = $inv->createPurchaseOrder($supplier, $store, [['inventory_item_id' => $beef->id, 'quantity' => 20, 'unit_cost' => 420]], $this->owner);
    $inv->markOrdered($po);
    $inv->receivePurchaseOrder($po->refresh(), [$po->lines()->value('id') => 20], $this->owner);
    syncBooks();

    expect(ledger()->payablesBySupplier()[$supplier->id])->toBe(8400.0)
        ->and(fn () => $books->paySupplier($supplier, 9000, 'bank', '2030-09-06', $this->owner))->toThrow(ValidationException::class);
    $books->paySupplier($supplier, 5000, 'bank', '2030-09-06', $this->owner, 'TRX-9');
    expect(ledger()->payablesBySupplier()[$supplier->id])->toBe(3400.0);

    $utilities = LedgerAccount::query()->where('system_key', 'general_expense')->firstOrFail();
    $books->recordExpense($utilities, 'Meralco', '2030-09-05', 1120, 120, 'bank', $this->owner, 'OR-1');
    expect(fn () => $books->recordExpense(LedgerAccount::query()->where('system_key', 'cash')->first(), 'X', '2030-09-05', 10, 0, 'cash', $this->owner))->toThrow(ValidationException::class);

    $invoice = $books->createInvoice(['customer_name' => 'Acme Corp', 'issue_date' => '2030-09-05', 'due_date' => '2030-10-05', 'tax_rate' => 12],
        [['description' => 'Conference room', 'quantity' => 2, 'unit_price' => 500], ['description' => '', 'quantity' => 1, 'unit_price' => 1]], $this->owner);
    expect((float) $invoice->total)->toBe(1120.0)->and($invoice->lines()->count())->toBe(1);

    $books->issue($invoice);
    $books->receivePayment($invoice->refresh(), 500, 'bank', '2030-09-10');
    expect(ledger()->balance('receivables'))->toBe(620.0)
        ->and(fn () => $books->void($invoice->refresh()))->toThrow(ValidationException::class)
        ->and(fn () => $books->receivePayment($invoice, 700, 'bank', '2030-09-11'))->toThrow(ValidationException::class);
    $books->receivePayment($invoice, 620, 'cash', '2030-09-11');
    expect($invoice->refresh()->status)->toBe(Invoice::PAID);

    $voided = $books->createInvoice(['customer_name' => 'Beta', 'issue_date' => '2030-09-05', 'due_date' => '2030-09-20', 'tax_rate' => 0], [['description' => 'Event', 'quantity' => 1, 'unit_price' => 3000]], $this->owner);
    $books->issue($voided);
    $books->void($voided->refresh());

    $vat = ledger()->vat('2030-09-01', '2030-09-30');
    expect($vat)->toBe(['output' => 120.0, 'input' => 120.0, 'payable' => 0.0])
        ->and(ledger()->balance('bank'))->toBe(-5620.0) // −5,000 supplier −1,120 bill +500 invoice
        ->and(ledger()->balance('receivables'))->toBe(0.0);

    $tb = ledger()->trialBalance();
    expect($tb['debits'])->toBe($tb['credits']);
});

it('runs the accounting screens with permissions, gating and isolation', function () {
    PropertyManagementFixtures::login($this->owner, $this->tenant);

    $this->post(route('accounting.invoices.store'), ['customer_name' => 'Acme', 'issue_date' => '2030-09-05', 'due_date' => '2030-09-30', 'tax_rate' => 12, 'lines' => [['description' => 'Retreat package', 'quantity' => 1, 'unit_price' => 10000]]])->assertRedirect();
    $invoice = Invoice::query()->firstOrFail();
    $this->post(route('accounting.invoices.issue', $invoice->id))->assertSessionHasNoErrors();
    $this->post(route('accounting.expenses.store'), ['ledger_account_id' => LedgerAccount::query()->where('system_key', 'maintenance_expense')->value('id'), 'vendor' => 'Plumber', 'expense_date' => '2030-09-05', 'amount' => 1500, 'paid_from' => 'cash'])->assertSessionHasNoErrors();

    $this->get(route('accounting.index', ['from' => '2030-09-01', 'to' => '2030-09-30']))->assertOk()->assertSee('₱10,000.00')->assertSee('₱1,500.00');
    $this->get(route('accounting.trial-balance'))->assertOk()->assertSee('balanced');
    $this->get(route('accounting.journal'))->assertOk()->assertSee('Invoice '.$invoice->number);
    $this->get(route('accounting.payables'))->assertOk()->assertSee($invoice->number);
    $this->get(route('accounting.invoices.show', $invoice->id))->assertOk()->assertSee('Retreat package');

    PropertyManagementFixtures::login(MarketplaceFixtures::member($this->tenant, 'front_desk'), $this->tenant);
    $this->get(route('accounting.index'))->assertForbidden();

    [$ownerB, $tenantB] = MarketplaceFixtures::business('Hotel B');
    PropertyManagementFixtures::login($ownerB, $tenantB);
    $this->get(route('accounting.index'))->assertForbidden(); // no finance module
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'finance')->firstOrFail(), $tenantB);
    $this->get(route('accounting.invoices.show', $invoice->id))->assertNotFound();
    $this->get(route('accounting.journal'))->assertOk()->assertDontSee($invoice->number);
    app(TenantContext::class)->set($tenantB);
    expect(ledger()->balance('receivables'))->toBe(0.0);
});
