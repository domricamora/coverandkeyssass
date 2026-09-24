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

        return view('wallet::host.index', [
            'wallet' => $wallet,
            'transactions' => $wallet->transactions()->paginate(20),
            'commissions' => Commission::query()->with('booking:id,reference')->latest('id')->limit(10)->get(),
            'payouts' => Payout::query()->latest('id')->limit(10)->get(),
            'minPayout' => WalletService::MIN_PAYOUT,
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
