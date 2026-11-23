<?php

namespace Tests\Feature;

use App\Jobs\SendLeadEmail;
use App\Jobs\SendTelegramAlert;
use App\Mail\TemplatedMail;
use App\Models\AutomationRequest;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Plan;
use App\Support\Setting;
use Database\Seeders\BusinessAutomationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class BusinessAutomationTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        $this->seed(BusinessAutomationSeeder::class);
        RateLimiter::clear('ba-ip10|127.0.0.1');
    }

    private function token(int $age = 30, ?string $id = null): string
    {
        return Crypt::encryptString(json_encode(['t' => time() - $age, 'id' => $id ?? (string) Str::uuid()]));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'form_token' => $this->token(),
            'name' => 'Asha Verma',
            'email' => 'asha@example.test',
            'phone_country' => '+91',
            'phone' => '98765 43210',
            'business_type' => 'Dental clinic',
            'requirements' => 'Send appointment reminders on WhatsApp and add new enquiries to our CRM automatically.',
            'consent' => '1',
        ], $overrides);
    }

    // ------------------------------------------------------------------------------------------ page + plans

    public function test_landing_page_has_required_sections_and_three_draft_plans_from_the_database(): void
    {
        $html = $this->get('/business-automation')->assertOk()->getContent();

        foreach (['id="problems"', 'id="industries"', 'id="examples"', 'id="plans"', 'id="how"', 'id="request"', 'id="faq"', 'Contact Sales'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
        foreach (['Automation Starter', 'Automation Growth', 'Automation Enterprise', 'Lead Capture Automation', 'n8n Workflow Development', 'Request Quote'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        $this->assertStringNotContainsString('₹', strip_tags(Str::between($html, 'id="plans"', 'id="how"')), 'No invented prices');
        $this->assertStringNotContainsString('type="password"', $html, 'Never ask for passwords');

        $plans = Plan::whereHas('category', fn ($q) => $q->where('slug', 'business-automation'))->get();
        $this->assertCount(3, $plans);
        foreach ($plans as $plan) {
            $this->assertSame('quote', $plan->fulfilment);
            $this->assertSame('pending', $plan->price_status);
            $this->assertFalse($plan->canBePurchased());
        }
    }

    public function test_plans_are_editable_from_the_existing_admin_plan_backend(): void
    {
        $plan = Plan::where('slug', 'automation-growth')->firstOrFail();
        $this->actingAs($this->staff())->get(route('admin.plans.edit', $plan))->assertOk()->assertSee('Business Automation (custom scope, quote-based)');

        $plan->update(['name' => 'Automation Growth Plus']);
        $this->get('/business-automation')->assertSee('Automation Growth Plus');
    }

    // ------------------------------------------------------------------------------------------ form

    public function test_required_fields_and_safe_validation(): void
    {
        Queue::fake();
        $this->from('/business-automation')->post('/business-automation', ['form_token' => $this->token()])
            ->assertSessionHasErrors(['name', 'email', 'phone', 'business_type', 'requirements', 'consent']);

        $this->post('/business-automation', $this->payload(['phone' => '12abc', 'email' => 'not-an-email', 'website' => 'javascript:alert(1)']))
            ->assertSessionHasErrors(['phone', 'email', 'website']);
        $this->post('/business-automation', $this->payload(['requirements' => 'too short']))->assertSessionHasErrors('requirements');
        $this->post('/business-automation', $this->payload(['budget' => 'a-million']))->assertSessionHasErrors('budget');

        $this->assertSame(0, AutomationRequest::count());
    }

    public function test_guest_submission_creates_a_lead_with_reference_and_queues_all_notifications(): void
    {
        Queue::fake();
        Setting::set('sales.notify_email', 'sales@example.test');
        Setting::set('telegram.bot_token', '123456:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi', true);
        Setting::set('telegram.chat_id', '-100123');

        $this->post('/business-automation', $this->payload(['company' => 'Smile Care', 'plan' => 'automation-starter']))
            ->assertRedirect(route('business-automation.thanks'));

        $lead = AutomationRequest::sole();
        $this->assertMatchesRegularExpression('/^BA-\d{6}-[A-Z2-9]{6}$/', $lead->reference);
        $this->assertNull($lead->user_id);
        $this->assertSame('new', $lead->status);
        $this->assertSame('+919876543210', $lead->phone);
        $this->assertSame(Plan::where('slug', 'automation-starter')->value('id'), $lead->plan_id);
        $this->assertNotSame('127.0.0.1', $lead->ip_hash);

        $this->followRedirects($this->get(route('business-automation.thanks')))->assertOk();
        $this->withSession(['lead_confirmation' => ['reference' => $lead->reference, 'duplicate' => false, 'redacted' => false, 'account' => false, 'id' => $lead->id]])
            ->get(route('business-automation.thanks'))->assertSee($lead->reference)->assertSee('Nothing has been charged');

        Queue::assertPushed(SendLeadEmail::class, fn ($j) => $j->to === 'asha@example.test' && $j->template === 'automation_ack' && $j->vars['reference'] === $lead->reference);
        Queue::assertPushed(SendLeadEmail::class, fn ($j) => $j->to === 'sales@example.test' && $j->template === 'staff_automation_new'
            && str_contains($j->vars['url'], '/admin/leads/'.$lead->id) && $j->vars['phone'] === '+91 98765 43210');
        Queue::assertPushed(SendTelegramAlert::class, fn ($j) => str_contains($j->text, 'New Business Automation Lead')
            && str_contains($j->text, $lead->reference) && str_contains($j->text, 'Dental clinic') && str_contains($j->text, '/admin/leads/'.$lead->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'lead.submitted']);
        $this->assertSame(0, Order::count(), 'A request never creates an order');
    }

    public function test_missing_sales_email_is_recorded_as_skipped_not_sent(): void
    {
        Queue::fake();
        $this->post('/business-automation', $this->payload());

        $this->assertDatabaseHas('notification_logs', ['event' => 'automation_staff', 'status' => 'skipped']);
        $this->assertDatabaseHas('notification_logs', ['event' => 'automation_request', 'channel' => 'telegram', 'status' => 'skipped']);
        Queue::assertNotPushed(SendTelegramAlert::class);
    }

    public function test_authenticated_submission_is_attached_to_the_customer_account(): void
    {
        Queue::fake();
        $customer = $this->customer(['email' => 'owner@example.test']);

        $this->actingAs($customer, 'web')->get('/business-automation')->assertSee('value="owner@example.test"', false);
        $this->post('/business-automation', $this->payload(['email' => 'owner@example.test']));

        $lead = AutomationRequest::sole();
        $this->assertSame($customer->id, $lead->user_id);
        $this->get(route('dashboard.automation'))->assertOk()->assertSee($lead->reference);
        $this->get(route('dashboard.automation.show', $lead))->assertOk()->assertSee('Request received.');
    }

    public function test_duplicate_submissions_are_suppressed(): void
    {
        Queue::fake();
        $token = $this->token(30, 'same-render');

        $this->post('/business-automation', $this->payload(['form_token' => $token]));
        $this->post('/business-automation', $this->payload(['form_token' => $token])); // double click
        $this->assertSame(1, AutomationRequest::count());

        // Same email + same requirements from a fresh form within 24 h: no second lead.
        $this->post('/business-automation', $this->payload(['requirements' => '  Send appointment reminders on WhatsApp and add new enquiries to our CRM   automatically. ']))
            ->assertSessionHas('lead_confirmation', fn ($c) => $c['duplicate'] === true);
        $this->assertSame(1, AutomationRequest::count());
        Queue::assertPushed(SendLeadEmail::class, fn ($j) => $j->template === 'automation_ack', 1);
    }

    public function test_spam_protection_honeypot_timing_tampering_and_rate_limit(): void
    {
        Queue::fake();
        $this->post('/business-automation', $this->payload(['fax_number' => 'bot']))->assertSessionHasErrors('form');
        $this->post('/business-automation', $this->payload(['form_token' => $this->token(0)]))->assertSessionHasErrors('form');
        $this->post('/business-automation', $this->payload(['form_token' => 'tampered']))->assertSessionHasErrors('form');
        $this->post('/business-automation', $this->payload(['form_token' => $this->token(90000)]))->assertSessionHasErrors('form');
        $this->assertSame(0, AutomationRequest::count());

        foreach (range(1, 5) as $i) {
            $this->post('/business-automation', $this->payload(['email' => "user{$i}@example.test"]));
        }
        $this->post('/business-automation', $this->payload(['email' => 'user9@example.test']))->assertStatus(429);
    }

    public function test_input_is_sanitised_and_credentials_are_redacted_before_storage_and_notifications(): void
    {
        Queue::fake();
        Setting::set('telegram.bot_token', '123456:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi', true);
        Setting::set('telegram.chat_id', '-100123');

        $this->post('/business-automation', $this->payload([
            'name' => '<b>Ravi</b> <script>alert(1)</script>Kumar',
            'requirements' => 'Connect our CRM. Our admin password: Hunter2!xyz and key sk-proj-abcdefghijklmnopqrstuvwx1234 — नमस्ते, उपयोग करें।',
        ]))->assertSessionHas('lead_confirmation', fn ($c) => $c['redacted'] === true);

        $lead = AutomationRequest::sole();
        $this->assertTrue($lead->redacted);
        $this->assertStringNotContainsString('<', $lead->name);
        $this->assertStringNotContainsString('Hunter2', $lead->requirements);
        $this->assertStringNotContainsString('sk-proj-abcdefghij', $lead->requirements);
        $this->assertStringContainsString('नमस्ते', $lead->requirements, 'Unicode text is preserved');
        Queue::assertPushed(SendTelegramAlert::class, fn ($j) => ! str_contains($j->text, 'Hunter2') && ! str_contains($j->text, 'sk-proj'));
    }

    public function test_ordinary_prose_with_security_words_is_not_redacted(): void
    {
        Queue::fake();
        $this->post('/business-automation', $this->payload(['requirements' => 'We use a token system for our queue and need a password reset reminder email for customers.']));
        $this->assertFalse(AutomationRequest::sole()->redacted);
    }

    // ------------------------------------------------------------------------------------------ jobs (fake transports)

    public function test_customer_acknowledgement_and_admin_email_jobs_send_and_log(): void
    {
        Mail::fake();
        Setting::set('sales.notify_email', 'sales@example.test');

        $this->post('/business-automation', $this->payload()); // QUEUE_CONNECTION=sync runs the jobs
        $lead = AutomationRequest::sole();

        Mail::assertSent(TemplatedMail::class, function (TemplatedMail $m) use ($lead) {
            [$subject, $body] = TemplatedMail::renderTemplate($m->template, $m->vars);

            return $m->hasTo('asha@example.test') && str_contains($subject, $lead->reference)
                && str_contains($body, 'Asha Verma') && str_contains($body, 'What happens next') && str_contains($body, '+91 98765 43210');
        });
        Mail::assertSent(TemplatedMail::class, function (TemplatedMail $m) use ($lead) {
            [, $body] = TemplatedMail::renderTemplate($m->template, $m->vars);

            return $m->hasTo('sales@example.test') && str_contains($body, $lead->reference) && str_contains($body, 'asha@example.test')
                && str_contains($body, 'Dental clinic') && str_contains($body, route('admin.leads.show', $lead));
        });
        $this->assertSame(2, NotificationLog::where('channel', 'mail')->where('status', 'sent')->count());
    }

    public function test_email_job_failure_is_logged_without_marking_sent_and_retries_never_double_send(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp.example: auth failed for secret-user'));
        $log = NotificationLog::create(['dedupe_key' => 'mail:automation_ack:x', 'event' => 'automation_ack', 'channel' => 'mail', 'status' => 'pending']);

        try {
            (new SendLeadEmail($log->id, 'a@example.test', 'automation_ack', []))->handle();
            $this->fail('Expected exception');
        } catch (\RuntimeException) {
        }
        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertStringNotContainsString('secret-user', (string) $log->error);

        $log->update(['status' => 'sent']);
        (new SendLeadEmail($log->id, 'a@example.test', 'automation_ack', []))->handle(); // no exception: skipped
        $this->assertSame(1, $log->fresh()->attempts);
    }

    public function test_telegram_job_uses_a_fake_transport_and_records_delivery(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Setting::set('telegram.bot_token', '123456:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi', true);
        Setting::set('telegram.chat_id', '-100123');

        $this->post('/business-automation', $this->payload());

        Http::assertSent(fn ($r) => str_contains($r['text'], 'New Business Automation Lead') && $r['chat_id'] === '-100123');
        $this->assertDatabaseHas('notification_logs', ['event' => 'automation_request', 'channel' => 'telegram', 'status' => 'sent']);
    }

    public function test_admin_can_send_a_test_to_the_sales_mailbox(): void
    {
        Mail::fake();
        $admin = $this->staff();
        $this->actingAs($admin)->post(route('admin.settings.test-notification'), ['channel' => 'sales_email'])->assertSessionHas('error');

        Setting::set('sales.notify_email', 'sales@example.test');
        $this->post(route('admin.settings.test-notification'), ['channel' => 'sales_email'])->assertSessionHas('success');
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->hasTo('sales@example.test') && $m->template === 'test_notification');
    }
}
