<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request, WalletService $wallet)
    {
        $user   = $request->user();
        $wallet = $wallet->walletFor($user);

        $entries = $wallet->entries()
            ->orderByDesc('id')
            ->paginate(20);

        return view('account.wallet.index', [
            'wallet'  => $wallet,
            'balance' => $wallet->balance(),
            'entries' => $entries,
        ]);
    }
}