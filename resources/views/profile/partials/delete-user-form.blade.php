<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Eliminar cuenta (RGPD)') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Al eliminar tu cuenta se borran <strong>permanentemente</strong> y de forma irreversible: tus métodos de pago guardados, el historial y saldo de tu billetera virtual, tus sesiones activas en todos los dispositivos, tus tokens de API y los mensajes de contacto. Los pedidos se conservan por obligación contable, pero se anonimizan (nombre, email, teléfono y dirección se eliminan). Esta acción no se puede deshacer.
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Delete Account') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('¿Seguro que quieres eliminar tu cuenta?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Esta acción borra todos tus datos personales conforme al RGPD y no se puede deshacer. Para confirmar, introduce tu contraseña y escribe <strong>ELIMINAR</strong> en el campo de confirmación.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Contraseña') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="confirm" value="{{ __('Escribe ELIMINAR para confirmar') }}" />
                <x-text-input
                    id="confirm"
                    name="confirm"
                    type="text"
                    class="mt-1 block w-3/4"
                    placeholder="ELIMINAR"
                    autocomplete="off"
                />
                <x-input-error :messages="$errors->userDeletion->get('confirm')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Delete Account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
