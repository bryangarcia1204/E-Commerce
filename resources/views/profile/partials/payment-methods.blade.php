<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Métodos de pago
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Guarda tus tarjetas o PayPal para acelerar el checkout. Nunca almacenamos el número completo ni el CVV: sólo los últimos 4 dígitos y un token de la pasarela.
        </p>
    </header>

    {{-- Listado --}}
    @if($user->paymentMethods->isNotEmpty())
        <ul class="divide-y divide-gray-200 dark:divide-gray-700 border border-gray-200 dark:border-gray-700 rounded-lg">
            @foreach($user->paymentMethods as $pm)
                <li class="flex items-center justify-between p-4">
                    <div class="text-sm">
                        <p class="font-medium text-gray-900 dark:text-gray-100">
                            {{ $pm->label }}
                            @if($pm->is_default)
                                <span class="ml-2 inline-block text-[10px] uppercase bg-green-100 text-green-800 px-1.5 py-0.5 rounded">Predeterminado</span>
                            @endif
                            @if($pm->is_expired)
                                <span class="ml-2 inline-block text-[10px] uppercase bg-red-100 text-red-800 px-1.5 py-0.5 rounded">Caducada</span>
                            @endif
                        </p>
                        @if($pm->type === 'card' && $pm->expires_at)
                            <p class="text-xs text-gray-500">Caduca {{ $pm->expires_at }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @if(!$pm->is_default)
                            <form method="post" action="{{ route('payment-methods.default', $pm) }}">
                                @csrf
                                <button type="submit" class="text-xs text-indigo-600 hover:underline">Marcar predeterminado</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('payment-methods.destroy', $pm) }}" onsubmit="return confirm('¿Eliminar este método de pago?')">
                            @csrf
                            @method('delete')
                            <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-sm text-gray-500">Aún no tienes métodos de pago guardados.</p>
    @endif

    {{-- Formulario de alta --}}
    <details class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <summary class="cursor-pointer text-sm font-medium text-gray-900 dark:text-gray-100">+ Añadir método de pago</summary>
        <form method="post" action="{{ route('payment-methods.store') }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold mb-1">Tipo</label>
                <select name="type" class="block w-full border-gray-300 rounded-md text-sm" onchange="
                    this.form.querySelector('[data-card-fields]').classList.toggle('hidden', this.value !== 'card');
                    this.form.querySelector('[data-paypal-fields]').classList.toggle('hidden', this.value !== 'paypal');
                ">
                    @foreach(\App\Models\PaymentMethod::TYPES as $slug => $label)
                        <option value="{{ $slug }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div data-card-fields class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold mb-1">Marca</label>
                    <select name="provider" class="block w-full border-gray-300 rounded-md text-sm">
                        <option value="visa">Visa</option>
                        <option value="mastercard">Mastercard</option>
                        <option value="amex">American Express</option>
                        <option value="maestro">Maestro</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Últimos 4 dígitos</label>
                    <input type="text" name="last4" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="4242"
                           class="block w-full border-gray-300 rounded-md text-sm">
                    <p class="text-[11px] text-gray-400 mt-1">En producción, este campo lo devuelve la pasarela tras tokenizar la tarjeta en su iframe seguro.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Caducidad (MM/YY)</label>
                    <input type="text" name="expires_at" pattern="(0[1-9]|1[0-2])/\d{2}" placeholder="12/27" maxlength="5"
                           class="block w-full border-gray-300 rounded-md text-sm">
                </div>
            </div>

            <div data-paypal-fields class="space-y-4 hidden">
                <div>
                    <label class="block text-xs font-semibold mb-1">Email de PayPal</label>
                    <input type="email" name="email" placeholder="tu@email.com"
                           class="block w-full border-gray-300 rounded-md text-sm">
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_default" value="1"> Marcar como predeterminado
            </label>

            <x-primary-button>{{ __('Guardar') }}</x-primary-button>
            @error('last4')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('expires_at')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </form>
    </details>
</section>
