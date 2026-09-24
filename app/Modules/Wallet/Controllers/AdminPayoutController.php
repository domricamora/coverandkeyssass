<?php

namespace App\Modules\Wallet\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Models\Payout;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Super Admin payout queue (Phase 08): transfers are made manually, then marked paid or rejected. */
class AdminPayoutController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function index()
    {
        return view('wallet::admin.payouts', [
            'payouts' => Payout::query()->withoutGlobalScope('tenant')
                ->with('tenant:id,name')
                ->orderByRaw("status = 'requested' desc")
                ->latest('id')
                ->paginate(25),
        ]);
    }

    public function update(Request $request, string $payout)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([Payout::PAID, Payout::REJECTED])],
            'reference' => ['nullable', 'string', 'max:120', 'required_if:status,paid'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $payout = Payout::query()->withoutGlobalScope('tenant')->findOrFail($payout);

        $this->wallets->settlePayout($payout, $validated['status'], $request->user(), $validated['reference'] ?? null, $validated['note'] ?? null);

        return back()->with('success', 'Payout #'.$payout->id.' marked '.$validated['status'].'.');
    }
}
