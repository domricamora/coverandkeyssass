<?php

namespace App\Modules\Wallet\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Models\Commission;
use App\Modules\Wallet\Models\Payout;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Host wallet (Phase 08): balances, ledger, commissions and payouts of the active business. */
class WalletController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermissionTo('wallet.view'), 403);

        $wallet = $this->wallets->wallet();

        $payouts = Payout::query()->latest('id')->limit(10)->get();

        return \Inertia\Inertia::render('Wallet/Index', [
            'currency' => $wallet->currency,
            'balances' => [
                'available' => (float) $wallet->available_balance,
                'pending' => (float) $wallet->pending_balance,
                'inProgress' => (float) $payouts->where('status', Payout::REQUESTED)->sum('amount'),
            ],
            'transactions' => $wallet->transactions()->paginate(20)->through(fn ($tx) => [
                'id' => $tx->id,
                'date' => $tx->created_at->format('M j, Y H:i'),
                'description' => $tx->description,
                'bucket' => $tx->bucket,
                'amount' => (float) $tx->amount,
                'after' => (float) $tx->balance_after,
            ]),
            'commissions' => Commission::query()->with(['booking:id,reference', 'order:id,reference'])->latest('id')->limit(10)->get()->map(fn ($c) => [
                'id' => $c->id,
                'source' => $c->sourceLabel(),
                'gross' => (float) $c->gross,
                'fee' => (float) $c->platform_fee,
                'rate' => (float) $c->rate,
                'net' => (float) $c->host_amount,
                'status' => $c->status,
            ]),
            'payouts' => $payouts->map(fn ($p) => [
                'id' => $p->id,
                'date' => $p->created_at->format('M j, Y'),
                'amount' => (float) $p->amount,
                'to' => ucfirst($p->method).' ····'.substr((string) $p->account_number, -4),
                'status' => $p->status,
                'reference' => $p->reference,
            ]),
            'methods' => Payout::METHODS,
            'minPayout' => WalletService::MIN_PAYOUT,
            'can' => ['request' => $request->user()->hasPermissionTo('payouts.request')],
            'urls' => ['payout' => route('wallet.payouts.store')],
        ]);
    }

    public function requestPayout(Request $request)
    {
        abort_unless($request->user()->hasPermissionTo('payouts.request'), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'method' => ['required', Rule::in(Payout::METHODS)],
            'account_name' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'string', 'max:60'],
        ]);

        $payout = $this->wallets->requestPayout((float) $validated['amount'], [
            'method' => $validated['method'],
            'account_name' => $validated['account_name'],
            'account_number' => $validated['account_number'],
        ], $request->user());

        return back()->with('success', 'Payout of '.number_format((float) $payout->amount, 2).' requested — the platform team will transfer it shortly.');
    }
}
