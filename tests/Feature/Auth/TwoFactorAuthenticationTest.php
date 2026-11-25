<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Totp;
use App\Support\TwoFactorAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_totp_matches_the_rfc_6238_sha1_vector(): void
    {
        $totp = app(Totp::class);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', $totp->codeAt($secret, 59));
        $this->assertSame(1, $totp->verify($secret, '287082', window: 0, timestamp: 59));
        $this->assertNull($totp->verify($secret, '287083', window: 0, timestamp: 59));
    }

    public function test_login_requires_a_valid_second_factor_before_authenticating(): void
    {
        $secret = app(Totp::class)->generateSecret();
        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        Volt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('login.two-factor', absolute: false));

        $this->assertGuest();

        Volt::test('auth.two-factor-challenge')
            ->set('code', app(Totp::class)->codeAt($secret, time()))
            ->call('verify')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_totp_codes_cannot_be_replayed_and_recovery_codes_are_one_time(): void
    {
        $totp = app(Totp::class);
        $authenticator = app(TwoFactorAuthenticator::class);
        $secret = $totp->generateSecret();
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => $secret])->save();

        $recoveryCodes = $authenticator->confirmSetup($user, $totp->codeAt($secret, time()));

        $this->assertCount(8, $recoveryCodes);
        $this->assertTrue($authenticator->verifyAndConsume($user, $recoveryCodes[0]));
        $this->assertFalse($authenticator->verifyAndConsume($user, $recoveryCodes[0]));
        $this->assertFalse($authenticator->verifyAndConsume($user, $totp->codeAt($secret, time())));
    }

    public function test_user_can_enable_two_factor_authentication_from_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Volt::test('settings.two-factor')->call('beginSetup');
        $secret = $component->get('secret');

        $component
            ->set('code', app(Totp::class)->codeAt($secret, time()))
            ->call('confirmSetup')
            ->assertHasNoErrors();

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertCount(8, $component->get('recoveryCodes'));
    }
}
