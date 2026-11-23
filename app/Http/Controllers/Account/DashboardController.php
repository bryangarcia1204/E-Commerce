<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $ordersCount   = $user->orders()->count();
        $ordersTotal   = (float) $user->orders()->where('status', '!=', 'cancelled')->sum('total');
        $lastOrder     = $user->orders()->latest()->first();
        $pendingOrders = $user->orders()->where('status', 'pending')->count();

        return view('account.dashboard', compact(
            'user', 'ordersCount', 'ordersTotal', 'lastOrder', 'pendingOrders'
        ));
    }

    public function wallet(Request $request, WalletService $wallet)
    {
        $user = $request->user();
        $balance = $wallet->balance($user);

        // Historial de movimientos (el paquete expone las transacciones)
        $transactions = $user->wallets()->first()?->transactions()->latest()->paginate(20) ?? collect();

        return view('account.wallet.index', compact('balance', 'transactions'));
    }

    public function editProfile(Request $request)
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,' . $user->id],
        ]);

        $user->update($data);

        return back()->with('success', 'Datos actualizados correctamente.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update([
            'password' => Hash::make($data['password']),
        ]);

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }
    
    public function destroyAccount(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        auth()->logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Tu cuenta ha sido eliminada.');
    }
}