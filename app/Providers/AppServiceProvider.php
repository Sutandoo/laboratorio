<?php

namespace App\Providers;

use App\Models\LoginAudit;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            $request = request();

            LoginAudit::create([
                'user_id' => $event->user->getAuthIdentifier(),
                'ip_address' => $request->ip(),
                'server_label' => config('laboratorio.server_label'),
                'user_agent' => $request->userAgent(),
            ]);
        });
    }
}
