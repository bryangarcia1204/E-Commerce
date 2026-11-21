<section class="space-y-4">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Sesión
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Estado de tu sesión actual. Por tu seguridad, no compartimos estos datos con otros usuarios ni siquiera el admin ve tu sesión individual.
        </p>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs uppercase text-gray-500">Sesión iniciada</p>
            <p class="mt-1 flex items-center gap-2 font-medium">
                @if($user->is_online)
                    <span class="w-2.5 h-2.5 rounded-full bg-green-500 inline-block"></span>
                    <span class="text-green-700 dark:text-green-400">Activa ahora</span>
                @else
                    <span class="w-2.5 h-2.5 rounded-full bg-gray-300 inline-block"></span>
                    <span class="text-gray-500">Inactiva</span>
                @endif
            </p>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs uppercase text-gray-500">Tiempo transcurrido</p>
            <p class="mt-1 font-medium">{{ $user->session_elapsed_for_humans }}</p>
            <p class="text-xs text-gray-400">Desde {{ $user->session_started_at?->format('d/m/Y H:i') ?? '—' }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-xs uppercase text-gray-500">Último login</p>
            <p class="mt-1 font-medium">{{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }}</p>
        </div>
    </div>
</section>
