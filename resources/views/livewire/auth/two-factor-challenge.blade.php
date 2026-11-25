<?php

use App\Models\User;
use App\Support\TwoFactorAuthenticator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component
{
    #[Validate('required|string|max:64')]
    public string $code = '';

    public function mount(): void
    {
        $user = User::find(Session::get('two_factor_login_user_id'));

        if ($user === null || $user->two_factor_confirmed_at === null) {
            Session::forget(['two_factor_login_user_id', 'two_factor_login_remember']);
            $this->redirectRoute('login', navigate: true);
        }
    }

    public function verify(): void
    {
        $this->validate();

        $userId = Session::get('two_factor_login_user_id');
        $user = User::find($userId);

        if ($user === null || $user->two_factor_confirmed_at === null) {
            Session::forget(['two_factor_login_user_id', 'two_factor_login_remember']);
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $key = $this->rateLimitKey((string) $userId);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.',
            ]);
        }

        if (! app(TwoFactorAuthenticator::class)->verifyAndConsume($user, $this->code)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'code' => 'El código no es válido o ya fue utilizado.',
            ]);
        }

        RateLimiter::clear($key);
        $remember = (bool) Session::pull('two_factor_login_remember', false);
        Session::forget('two_factor_login_user_id');
        Auth::login($user, $remember);
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    private function rateLimitKey(string $userId): string
    {
        return 'two-factor-login|'.$userId.'|'.request()->ip();
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Verificación en dos pasos" description="Ingresa el código de tu app de autenticación o un código de recuperación" />

    <form wire:submit="verify" class="flex flex-col gap-6">
        <flux:input
            wire:model="code"
            label="Código de autenticación"
            type="text"
            name="code"
            required
            autofocus
            autocomplete="one-time-code"
            inputmode="numeric"
            placeholder="123456 o código de recuperación"
        />

        <flux:button variant="primary" type="submit" class="w-full">Verificar</flux:button>
    </form>

    <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
        <a href="{{ route('login') }}" class="underline">Volver al inicio de sesión</a>
    </div>
</div>
