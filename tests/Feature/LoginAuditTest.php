<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LoginAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_creates_an_audit_with_ip_and_server(): void
    {
        $user = User::factory()->create();

        Volt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('login_audits', [
            'user_id' => $user->id,
            'ip_address' => request()->ip(),
            'server_label' => config('laboratorio.server_label'),
        ]);
    }

    public function test_failed_login_does_not_create_an_audit(): void
    {
        $user = User::factory()->create();

        Volt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'incorrect-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertDatabaseCount('login_audits', 0);
    }

    public function test_dashboard_only_displays_the_authenticated_users_audit_history(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $user->loginAudits()->create([
            'ip_address' => '198.51.100.10',
            'server_label' => 'ServidorPropio',
        ]);
        $otherUser->loginAudits()->create([
            'ip_address' => '203.0.113.20',
            'server_label' => 'ServidorAjeno',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('198.51.100.10')
            ->assertSee('ServidorPropio')
            ->assertDontSee('203.0.113.20')
            ->assertDontSee('ServidorAjeno');
    }

    public function test_dashboard_reads_the_server_label_from_configuration(): void
    {
        config()->set('laboratorio.server_label', 'ServidorDePrueba');

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('ServidorDePrueba');
    }

    public function test_forwarded_ip_is_accepted_from_the_configured_load_balancer(): void
    {
        $this->actingAs(User::factory()->create())
            ->withServerVariables(['REMOTE_ADDR' => '192.168.184.20'])
            ->withHeader('X-Forwarded-For', '198.51.100.42')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('198.51.100.42');
    }

    public function test_forwarded_ip_from_an_untrusted_source_is_ignored(): void
    {
        $this->actingAs(User::factory()->create())
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeader('X-Forwarded-For', '198.51.100.42')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('203.0.113.9')
            ->assertDontSee('198.51.100.42');
    }
}
