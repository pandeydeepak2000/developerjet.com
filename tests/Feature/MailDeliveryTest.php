<?php

namespace Tests\Feature;

use App\Services\MailDeliveryService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class MailDeliveryTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    public function test_missing_smtp_settings_are_reported_exactly_and_nothing_is_sent(): void
    {
        Mail::fake();
        $mail = app(MailDeliveryService::class);
        $this->assertSame(['smtp.host', 'smtp.username', 'smtp.password', 'smtp.from_address'], $mail->missingSmtpSettings());
        $result = $mail->testConnection();
        $this->assertFalse($result['ok']);
        $this->assertSame(['smtp.host', 'smtp.username', 'smtp.password', 'smtp.from_address'], $result['missing']);
        Mail::assertNothingSent();
    }

    public function test_env_smtp_values_count_when_config_is_cached(): void
    {
        // Simulates production after `php artisan config:cache`: values live only in config,
        // env() would return null. A filled .env must not be reported as missing.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'mail.example.net',
            'mail.mailers.smtp.username' => 'support@example.net',
            'mail.mailers.smtp.password' => 'not-a-real-password',
            'mail.from.address' => 'support@example.net',
        ]);
        $mail = app(MailDeliveryService::class);
        $this->assertSame([], $mail->missingSmtpSettings());
        $this->assertTrue($mail->deliversToInbox());
    }

    public function test_log_mailer_is_never_reported_as_a_sent_otp(): void
    {
        config(['mail.default' => 'log']);
        $user = $this->customer(['email' => 'otp-log@example.test']);
        $this->assertSame(OtpService::LOGGED_ONLY, app(OtpService::class)->issueWithStatus($user));
    }

    public function test_otp_security_is_preserved_code_is_hashed_expiring_and_verifiable(): void
    {
        Mail::fake();
        $user = $this->customer(['email' => 'otp-ok@example.test']);
        $this->assertNotSame(OtpService::FAILED, app(OtpService::class)->issueWithStatus($user));
        $otp = $user->otps()->latest('id')->firstOrFail();
        $this->assertNotEmpty($otp->code_hash);
        $this->assertTrue($otp->expires_at->isFuture());
        $this->assertGreaterThan(0, app(OtpService::class)->waitSeconds($user));
    }

    public function test_smtp_connection_test_is_super_admin_only_and_sends_no_email(): void
    {
        Mail::fake();
        $this->actingAs($this->staff('admin'))->post(route('admin.settings.test-smtp'))->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_registration_with_log_mailer_reports_not_delivered_not_sent(): void
    {
        config(['mail.default' => 'log']);
        $this->post('/register', [
            'name' => 'Asha Dev',
            'email' => 'asha-log@example.com',
            'phone_country' => '+91',
            'phone' => '98765 43210',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertRedirect(route('verification.notice'))
            ->assertSessionHas('error')
            ->assertSessionMissing('status');
    }

    public function test_customer_resend_with_log_mailer_reports_not_delivered(): void
    {
        config(['mail.default' => 'log']);
        $user = $this->customer(['email_verified_at' => null]);
        $this->actingAs($user)->post('/email/verify/resend')
            ->assertSessionHas('error')
            ->assertSessionMissing('status');
    }

    public function test_staff_first_sign_in_with_log_mailer_reports_not_delivered(): void
    {
        config(['mail.default' => 'log']);
        $staff = $this->staff('admin', ['password' => 'Correct-Horse-9!', 'email_verified_at' => null]);
        $this->post('/staff/developerjet/login', ['email' => $staff->email, 'password' => 'Correct-Horse-9!'])
            ->assertRedirect('/staff/developerjet/verify-email')
            ->assertSessionHas('error');
    }
}
