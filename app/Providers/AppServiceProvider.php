<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Billing\NullServiceSuspender;
use App\Services\Billing\ServiceSuspender;
use App\Services\CartService;
use App\Services\Provisioning\AutomaticProvisioner;
use App\Services\Provisioning\NullAutomaticProvisioner;
use App\Services\Provisioning\RemoteTransport;
use App\Services\Provisioning\SshTransport;
use App\Support\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CartService::class);
        $this->app->bind(AutomaticProvisioner::class, NullAutomaticProvisioner::class);
        $this->app->bind(RemoteTransport::class, SshTransport::class);
        // No supplier has a verified suspension/reactivation adapter yet: every such action becomes a staff task.
        $this->app->bind(ServiceSuspender::class, NullServiceSuspender::class);
    }

    public function boot(): void
    {
        // Admin capability (Phase 1 gate, retained).
        Gate::define('manage-catalog', fn (User $user) => $user->isAdmin() || $user->hasPermission('plans.edit'));

        // Permission abilities ("orders.view") resolve through staff roles, so @can works in Blade as well.
        Gate::before(function (?User $user, string $ability) {
            if ($user && str_contains($ability, '.') && ! str_contains($ability, ' ')) {
                return $user->hasPermission($ability) ?: null;
            }

            return null;
        });

        // Named limiter for the whole admin area. A plain `throttle:N,M` shares ONE counter per user/IP across every
        // route, so a group-wide 240/min limit would also consume the 10/min budget of sensitive routes. A named
        // limiter keeps its own counter.
        RateLimiter::for('staff-area', fn (Request $request) => Limit::perMinute(240)->by('staff-area|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Public Business Automation form: per IP (burst + hourly) and per submitted email address.
        RateLimiter::for('automation-requests', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by('ba-ip10|'.$request->ip()),
            Limit::perHour(15)->by('ba-ip60|'.$request->ip()),
            Limit::perHour(5)->by('ba-email|'.mb_strtolower(trim((string) $request->input('email')))),
        ]);

        View::composer(['layouts.app', 'layouts.dashboard'], function ($view) {
            $view->with('cartCount', app(CartService::class)->count());
        });

        $this->applyMailSettings();
    }

    /** Admin-configured SMTP overrides the environment mailer only when a host has been saved. */
    private function applyMailSettings(): void
    {
        if ($this->app->runningUnitTests() || ! Schema::hasTable('settings') || ! Setting::has('smtp.host')) {
            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', Setting::get('smtp.host'));
        Config::set('mail.mailers.smtp.port', (int) Setting::get('smtp.port', 587));
        Config::set('mail.mailers.smtp.username', Setting::get('smtp.username'));
        Config::set('mail.mailers.smtp.password', Setting::get('smtp.password'));
        $enc = Setting::get('smtp.encryption', 'tls');
        Config::set('mail.mailers.smtp.scheme', $enc === 'ssl' ? 'smtps' : 'smtp');
        if ($from = Setting::get('smtp.from_address')) {
            Config::set('mail.from.address', $from);
            Config::set('mail.from.name', Setting::get('smtp.from_name', 'DeveloperJet'));
        }
    }
}
