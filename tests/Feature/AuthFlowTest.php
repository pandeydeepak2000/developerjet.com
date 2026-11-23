<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $overrides = []): void
    {
        $this->post('/register', $overrides + [
            'name' => 'Asha Dev',
            'email' => 'asha@example.com',
            'phone_country' => '+91',
            'phone' => '98765 43210',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ]);
    }

    private function capturedCode(): string
    {
        $code = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    public function test_registration_creates_unverified_user_and_emails_otp(): void
    {
        Mail::fake();

        $this->register();

        $user = User::where('email', 'asha@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('customer', $user->role);
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame('Secret123', $user->password);
        Mail::assertSent(OtpMail::class, 1);

        // The plain code is never stored.
        $this->assertStringNotContainsString($this->capturedCode(), EmailOtp::first()->code_hash);
    }

    public function test_registration_validates_input_and_cannot_set_role(): void
    {
        $this->post('/register', ['name' => '', 'email' => 'bad', 'password' => 'short'])
            ->assertSessionHasErrors(['name', 'email', 'password']);

        Mail::fake();
        $this->register(['role' => 'admin']);
        $this->assertSame('customer', User::first()->role);

        $this->post('/logout');
        $this->post('/register', ['name' => 'Dup', 'email' => 'ASHA@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'])
            ->assertSessionHasErrors('email');
    }

    public function test_correct_otp_verifies_email(): void
    {
        Mail::fake();
        $this->register();

        $this->post('/email/verify', ['code' => $this->capturedCode()])->assertRedirect(route('dashboard'));

        $this->assertNotNull(User::first()->email_verified_at);
        $this->get('/dashboard')->assertOk();
    }

    public function test_wrong_otp_does_not_verify_and_attempts_are_limited(): void
    {
        Mail::fake();
        $this->register();
        $real = $this->capturedCode();
        $wrong = $real === '000000' ? '111111' : '000000';

        for ($i = 0; $i < OtpService::MAX_ATTEMPTS; $i++) {
            $this->post('/email/verify', ['code' => $wrong])->assertSessionHasErrors('code');
        }

        // Even the correct code is now refused until a new one is requested.
        $this->post('/email/verify', ['code' => $real])->assertSessionHasErrors('code');
        $this->assertNull(User::first()->email_verified_at);
    }

    public function test_expired_otp_is_rejected(): void
    {
        Mail::fake();
        $this->register();
        $code = $this->capturedCode();

        $this->travel(OtpService::TTL_MINUTES + 1)->minutes();

        $this->post('/email/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNull(User::first()->email_verified_at);
    }

    public function test_resend_is_rate_limited_and_invalidates_old_code(): void
    {
        Mail::fake();
        $this->register();
        $old = $this->capturedCode();

        // Immediately after registration the cooldown applies.
        $this->post('/email/verify/resend')->assertSessionHas('error');
        Mail::assertSent(OtpMail::class, 1);

        $this->travel(61)->seconds();
        $this->post('/email/verify/resend')->assertSessionHas('status');
        Mail::assertSent(OtpMail::class, 2);

        $this->post('/email/verify', ['code' => $old])->assertSessionHasErrors('code');
    }

    public function test_hourly_resend_cap(): void
    {
        Mail::fake();
        $this->register();

        for ($i = 0; $i < OtpService::MAX_SENDS_PER_HOUR - 1; $i++) {
            $this->travel(61)->seconds();
            $this->post('/email/verify/resend');
        }
        $this->travel(61)->seconds();
        $this->post('/email/verify/resend')->assertSessionHas('error');
        Mail::assertSent(OtpMail::class, OtpService::MAX_SENDS_PER_HOUR);
    }

    public function test_unverified_user_cannot_reach_checkout_or_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/checkout')->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get('/email/verify')->assertOk();
    }

    public function test_login_logout_and_bad_credentials(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'bad']);
        }

        // Correct password is refused while locked out.
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guest_routes_redirect_authenticated_users_and_dashboard_requires_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/checkout')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect(route('dashboard'));
    }

    public function test_logout_requires_post_and_csrf_protected_forms_exist(): void
    {
        $this->get('/logout')->assertStatus(405);
        $this->get('/login')->assertSee('name="_token"', false);
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'OldSecret1']);

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        // Unknown email returns the same message (no enumeration) and sends nothing.
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class, 1);

        $token = Password::createToken($user);
        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => 'NewSecret1', 'password_confirmation' => 'NewSecret1',
        ])->assertRedirect(route('login'));

        $this->post('/login', ['email' => $user->email, 'password' => 'NewSecret1'])->assertRedirect(route('dashboard'));
    }

    public function test_password_reset_rejects_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => 'invalid', 'email' => $user->email,
            'password' => 'NewSecret1', 'password_confirmation' => 'NewSecret1',
        ])->assertSessionHasErrors('email');
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
