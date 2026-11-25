<?php

use App\Models\User;
use App\Support\Totp;
use App\Support\TwoFactorAuthenticator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component
{
    #[Locked]
    public string $secret = '';

    #[Locked]
    public array $recoveryCodes = [];

    public string $code = '';

    public string $password = '';

    public string $disableCode = '';

    public function mount(): void
    {
        $user = Auth::user();

        if ($user->two_factor_confirmed_at === null) {
            $this->secret = $user->two_factor_secret ?? '';
        }
    }

    public function beginSetup(): void
    {
        $user = Auth::user();

        if ($user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages([
                'code' => 'La autenticación en dos pasos ya está activa.',
            ]);
        }

        $this->secret = app(Totp::class)->generateSecret();
        $user->forceFill([
            'two_factor_secret' => $this->secret,
            'two_factor_recovery_codes' => null,
            'two_factor_last_used_step' => null,
        ])->save();
    }

    public function confirmSetup(): void
    {
        $this->validate(['code' => ['required', 'string', 'regex:/\A\d{6}\z/']]);
        $user = Auth::user();
        $key = $this->rateLimitKey('setup');
        $this->ensureCodeAttemptsAvailable($key);

        $codes = app(TwoFactorAuthenticator::class)->confirmSetup($user, $this->code);

        if ($codes === null) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'code' => 'El código no es válido. Revisa la hora de tu teléfono e inténtalo de nuevo.',
            ]);
        }

        RateLimiter::clear($key);
        $this->recoveryCodes = $codes;
        $this->secret = '';
        $this->code = '';
    }

    public function cancelSetup(): void
    {
        $user = Auth::user();

        if ($user->two_factor_confirmed_at === null) {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_last_used_step' => null,
            ])->save();
        }

        $this->secret = '';
        $this->code = '';
    }

    public function disable(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
            'disableCode' => ['required', 'string', 'max:64'],
        ]);

        $user = Auth::user();

        if (! Hash::check($this->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'La contraseña no es correcta.',
            ]);
        }

        $key = $this->rateLimitKey('disable');
        $this->ensureCodeAttemptsAvailable($key, 'disableCode');

        if (! app(TwoFactorAuthenticator::class)->verifyAndConsume($user, $this->disableCode)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'disableCode' => 'El código no es válido o ya fue utilizado.',
            ]);
        }

        RateLimiter::clear($key);
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();

        $this->password = '';
        $this->disableCode = '';
    }

    private function ensureCodeAttemptsAvailable(string $key, string $field = 'code'): void
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                $field => 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.',
            ]);
        }
    }

    private function rateLimitKey(string $action): string
    {
        return 'two-factor-'.$action.'|'.Auth::id().'|'.request()->ip();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="Autenticación en dos pasos" subheading="Protege tu cuenta con Google Authenticator u otra app TOTP">
        @if (auth()->user()->two_factor_confirmed_at)
            <div class="my-6 space-y-4">
                <p class="text-sm text-green-700 dark:text-green-400">La autenticación en dos pasos está activa.</p>

                @if ($recoveryCodes)
                    <div class="space-y-2 rounded-lg border p-4">
                        <p class="font-medium">Guarda estos códigos de recuperación ahora. Cada uno se puede usar una sola vez.</p>
                        <ul class="grid grid-cols-2 gap-2 font-mono text-sm">
                            @foreach ($recoveryCodes as $recoveryCode)
                                <li>{{ $recoveryCode }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form wire:submit="disable" class="space-y-4">
                    <flux:input wire:model="password" label="Contraseña actual" type="password" autocomplete="current-password" required />
                    <flux:input wire:model="disableCode" label="Código de autenticación" type="text" autocomplete="one-time-code" required />
                    <flux:button variant="danger" type="submit">Desactivar autenticación en dos pasos</flux:button>
                </form>
            </div>
        @elseif ($secret)
            <div class="my-6 space-y-4">
                <p class="text-sm">En Google Authenticator, agrega una cuenta manualmente usando esta clave:</p>
                <code class="block break-all rounded-lg border p-3 font-mono">{{ $secret }}</code>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Usa el tipo de clave basada en tiempo (TOTP). Después escribe el código de seis dígitos para confirmar.</p>

                <form wire:submit="confirmSetup" class="space-y-4">
                    <flux:input wire:model="code" label="Código de seis dígitos" type="text" inputmode="numeric" autocomplete="one-time-code" required />
                    <div class="flex gap-3">
                        <flux:button variant="primary" type="submit">Confirmar configuración</flux:button>
                        <flux:button type="button" wire:click="cancelSetup">Cancelar</flux:button>
                    </div>
                </form>
            </div>
        @else
            <div class="my-6 space-y-4">
                <p class="text-sm">Usa una app compatible con TOTP, como Google Authenticator, para generar un código nuevo cada 30 segundos.</p>
                <flux:button variant="primary" type="button" wire:click="beginSetup">Configurar autenticador</flux:button>
            </div>
        @endif
    </x-settings.layout>
</section>
