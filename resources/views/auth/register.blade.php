<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Phone (opcional) -->
        <div class="mt-4">
            <x-input-label for="phone" :value="__('Teléfono (opcional)')" />
            <x-text-input id="phone" class="block mt-1 w-full" type="tel" name="phone" :value="old('phone')" autocomplete="tel" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <!-- Dirección de envío preferida (opcional, acelera el checkout) -->
        <div class="mt-4">
            <x-input-label for="default_shipping_address" :value="__('Dirección de envío preferida (opcional)')" />
            <textarea id="default_shipping_address" name="default_shipping_address" rows="2"
                      class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                      autocomplete="street-address">{{ old('default_shipping_address') }}</textarea>
            <x-input-error :messages="$errors->get('default_shipping_address')" class="mt-2" />
        </div>

        <!-- Método de pago preferido (opcional) -->
        <div class="mt-4">
            <x-input-label for="preferred_payment_method" :value="__('Método de pago preferido (opcional)')" />
            <select id="preferred_payment_method" name="preferred_payment_method"
                    class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                @foreach (\App\Models\User::PAYMENTS as $slug => $label)
                    <option value="{{ $slug }}" @selected(old('preferred_payment_method', 'card') === $slug)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('preferred_payment_method')" class="mt-2" />
            <p class="text-xs text-gray-500 mt-1">Podrás añadir tarjetas/PayPal desde tu perfil. Nunca guardamos los datos completos de tu tarjeta.</p>
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
