<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->registerGates();
        $this->registerNotificationChannels();
    }

    /**
     * Register custom notification channels.
     */
    protected function registerNotificationChannels(): void
    {
        Notification::extend('sms', fn (): SmsChannel => app(SmsChannel::class));
    }

    /**
     * Register application gates.
     */
    protected function registerGates(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->isSystemAdmin()) {
                return true;
            }

            return null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
