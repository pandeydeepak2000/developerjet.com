<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramAlert;
use App\Models\NotificationLog;
use App\Services\Notifier;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Milestone 13: failed-delivery retry, encrypted payloads, no token leakage (fakes only, no real Telegram). */
class NotificationRetryTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        Setting::set('telegram.bot_token', '123456:SECRET-BOT-TOKEN', true);
        Setting::set('telegram.chat_id', '-1001');
    }

    public function test_failed_telegram_alert_is_logged_without_token_and_can_be_retried_once(): void
    {
        Http::fake(['api.telegram.org/*' => Http::sequence()->push('down', 500)->push('down', 500)->push('down', 500)->push(['ok' => true], 200)]);

        try {
            app(Notifier::class)->admin('ticket_opened', 'ticket:1', 'Ticket TKT000001 opened');
        } catch (\Throwable) {
            // sync queue: the job throws after recording the failure
        }
        $log = NotificationLog::where('channel', 'telegram')->firstOrFail();
        $this->assertSame('failed', $log->status);
        $this->assertStringNotContainsString('SECRET-BOT-TOKEN', (string) $log->error);
        $this->assertStringNotContainsString('TKT000001', (string) $log->getRawOriginal('payload')); // encrypted at rest
        $this->assertSame('[DeveloperJet] Ticket TKT000001 opened', $log->payload);

        $this->actingAs($this->staff())->get('/admin/r/notifications?status=failed')->assertOk()->assertSee('Retry')->assertDontSee('SECRET-BOT-TOKEN');

        Queue::fake();
        $this->post(route('admin.notifications.retry', $log))->assertSessionHas('success');
        Queue::assertPushed(SendTelegramAlert::class, fn ($job) => $job->logId === $log->id);
        $this->assertSame('pending', $log->fresh()->status);

        // Not failed any more → cannot be retried again (no duplicate sends).
        $this->post(route('admin.notifications.retry', $log))->assertStatus(422);
    }

    public function test_retry_needs_notifications_edit(): void
    {
        $log = NotificationLog::create(['dedupe_key' => 'x', 'event' => 'test', 'channel' => 'telegram', 'status' => 'failed', 'payload' => 'hello']);
        $this->actingAs($this->staff('admin'))->post(route('admin.notifications.retry', $log))->assertForbidden(); // admin has notifications.view only
    }
}
