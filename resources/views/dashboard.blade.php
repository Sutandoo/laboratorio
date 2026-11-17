<x-layouts.app>
    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-8">
        <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Sesión autenticada</p>
                    <h1 class="mt-1 text-2xl font-semibold text-neutral-900 dark:text-white">
                        Bienvenido, {{ auth()->user()->name }}
                    </h1>
                    <p class="mt-1 text-neutral-600 dark:text-neutral-300">{{ auth()->user()->email }}</p>
                </div>
                <span class="inline-flex w-fit items-center rounded-full bg-emerald-100 px-3 py-1 text-sm font-medium text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                    {{ $serverLabel }}
                </span>
            </div>

            <dl class="mt-6 grid gap-4 border-t border-neutral-200 pt-5 sm:grid-cols-2 dark:border-neutral-700">
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">Servidor que atendió esta petición</dt>
                    <dd class="mt-1 font-medium text-neutral-900 dark:text-white">{{ $serverLabel }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">IP observada por Laravel</dt>
                    <dd class="mt-1 font-medium text-neutral-900 dark:text-white">{{ $visitorIp ?? 'No disponible' }}</dd>
                </div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-zinc-900">
            <div class="border-b border-neutral-200 px-6 py-4 dark:border-neutral-700">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">Mis inicios de sesión</h2>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Historial de accesos de esta cuenta.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-neutral-600 dark:bg-zinc-800 dark:text-neutral-300">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-medium">Fecha y hora</th>
                            <th scope="col" class="px-6 py-3 font-medium">Dirección IP</th>
                            <th scope="col" class="px-6 py-3 font-medium">Servidor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @forelse ($loginAudits as $loginAudit)
                            <tr class="text-neutral-700 dark:text-neutral-200">
                                <td class="whitespace-nowrap px-6 py-4">{{ $loginAudit->created_at->format('d/m/Y H:i:s') }}</td>
                                <td class="whitespace-nowrap px-6 py-4">{{ $loginAudit->ip_address ?? 'No disponible' }}</td>
                                <td class="whitespace-nowrap px-6 py-4">{{ $loginAudit->server_label }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-neutral-500 dark:text-neutral-400">
                                    Aún no hay inicios de sesión registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($loginAudits->hasPages())
                <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
                    {{ $loginAudits->links() }}
                </div>
            @endif
        </section>
    </div>
</x-layouts.app>
