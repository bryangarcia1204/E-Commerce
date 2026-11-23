<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $wallets = Wallet::with('user:id,name,email')
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->whereHas('user', function ($u) use ($request) {
                    $u->where('name', 'like', '%' . $request->q . '%')
                      ->orWhere('email', 'like', '%' . $request->q . '%');
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Añadimos saldo calculado
        $wallets->getCollection()->transform(function ($w) {
            $w->balance_cents = $w->balance();
            return $w;
        });

        return view('admin.wallets.index', compact('wallets'));
    }

    public function show(Wallet $wallet)
    {
        $wallet->load('user:id,name,email');
        $entries = $wallet->entries()->orderByDesc('id')->paginate(50);

        return view('admin.wallets.show', [
            'wallet'  => $wallet,
            'balance' => $wallet->balance(),
            'entries' => $entries,
        ]);
    }

    public function adjust(Request $request, Wallet $wallet, WalletService $svc)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:100'],
        ]);

        $cents = (int) round($data['amount'] * 100);

        try {
            if ($cents > 0) {
                $svc->credit($wallet->user, $cents, 'ajuste-admin: ' . $data['reason'], meta: [
                    'admin_id' => auth()->id(),
                ]);
            } else {
                $svc->debit($wallet->user, abs($cents), 'ajuste-admin: ' . $data['reason'], meta: [
                    'admin_id' => auth()->id(),
                ]);
            }
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ajuste aplicado.');
    }
}