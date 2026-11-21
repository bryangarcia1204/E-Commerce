<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Billetera MODA
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Saldo disponible para usar en el checkout. Se alimenta de crédito promocional, cashback de afiliación y reembolsos. Cada movimiento queda registrado e inmutable en el ledger.
        </p>
    </header>

    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-6 bg-gradient-to-br from-indigo-50 to-white dark:from-gray-800 dark:to-gray-900">
        <p class="text-xs uppercase text-gray-500">Saldo actual</p>
        <p class="text-3xl font-black text-gray-900 dark:text-gray-100 mt-1">
            {{ number_format((float) $user->wallet_balance, 2, ',', '.') }} €
        </p>
    </div>

    @php
        $transactions = $user->walletTransactions()->latest()->take(20)->get();
    @endphp
    @if($transactions->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Concepto</th>
                        <th class="py-2 pr-4 text-right">Importe</th>
                        <th class="py-2 text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($transactions as $tx)
                        <tr>
                            <td class="py-2 pr-4 text-gray-500">{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pr-4">{{ $tx->description ?? ucfirst(str_replace('_', ' ', $tx->type)) }}</td>
                            <td class="py-2 pr-4 text-right font-medium {{ $tx->direction === 'credit' ? 'text-green-600' : 'text-red-600' }}">
                                {{ $tx->direction === 'credit' ? '+' : '−' }}{{ number_format((float) $tx->amount, 2, ',', '.') }} €
                            </td>
                            <td class="py-2 text-right text-gray-600">{{ number_format((float) $tx->balance_after, 2, ',', '.') }} €</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500">Sin movimientos todavía.</p>
    @endif
</section>
