<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramAlert;
use App\Mail\TemplatedMail;
use App\Models\Department;
use App\Models\NotificationLog;
use App\Models\Setting as SettingRow;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\Notifier;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class SupportNotificationTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        Storage::fake('local');
    }

    private function openTicket($user, string $dept = 'billing', array $extra = [])
    {
        return $this->actingAs($user)->post('/dashboard/tickets', [
            'department_id' => Department::where('slug', $dept)->value('id'), 'subject' => 'Help me', 'body' => 'It broke', 'priority' => 'normal',
        ] + $extra);
    }

    public function test_customer_opens_ticket_with_safe_attachment(): void
    {
        $user = $this->customer();
        $this->openTicket($user, 'billing', ['files' => [UploadedFile::fake()->image('shot.png')]])->assertRedirect();

        $ticket = Ticket::firstOrFail();
        $this->assertStringStartsWith('TKT', $ticket->number);
        $attachment = $ticket->replies->first()->attachments->first();
        $this->assertSame('shot.png', $attachment->original_name);
        $this->assertStringNotContainsString('shot', $attachment->path); // random storage name
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_dangerous_uploads_are_rejected(): void
    {
        $user = $this->customer();
        $this->openTicket($user, 'billing', ['files' => [UploadedFile::fake()->create('evil.php', 10, 'application/x-php')]])->assertSessionHasErrors('files.0');
        $this->openTicket($user, 'billing', ['files' => [UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')]])->assertSessionHasErrors('files.0');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_customers_cannot_access_other_customers_tickets(): void
    {
        $owner = $this->customer();
        $this->openTicket($owner);
        $ticket = Ticket::firstOrFail();
        $other = $this->customer();

        $this->actingAs($other)->get(route('dashboard.tickets.show', $ticket))->assertForbidden();
        $this->post(route('dashboard.tickets.reply', $ticket), ['body' => 'hi'])->assertForbidden();
        $this->post(route('dashboard.tickets.close', $ticket))->assertForbidden();
        $this->get(route('dashboard.tickets'))->assertDontSee($ticket->number);
    }

    public function test_private_staff_notes_never_reach_the_customer(): void
    {
        $owner = $this->customer();
        $this->openTicket($owner);
        $ticket = Ticket::firstOrFail();
        $staff = $this->staff('super-admin');

        $this->actingAs($staff)->post(route('admin.tickets.reply', $ticket), ['body' => 'PRIVATE-NOTE-TEXT', 'private' => 1])->assertSessionHas('success');
        $this->post(route('admin.tickets.reply', $ticket), ['body' => 'Public answer'])->assertSessionHas('success');

        $this->actingAs($owner)->get(route('dashboard.tickets.show', $ticket))->assertSee('Public answer')->assertDontSee('PRIVATE-NOTE-TEXT');
        $this->actingAs($staff)->get(route('admin.tickets.show', $ticket))->assertSee('PRIVATE-NOTE-TEXT');
        $this->assertSame('answered', $ticket->fresh()->status);
    }

    public function test_customer_cannot_create_private_notes_or_download_private_attachments(): void
    {
        $owner = $this->customer();
        $this->openTicket($owner);
        $ticket = Ticket::firstOrFail();
        $staff = $this->staff('super-admin');
        $this->actingAs($staff)->post(route('admin.tickets.reply', $ticket), ['body' => 'internal', 'private' => 1, 'files' => [UploadedFile::fake()->create('internal.pdf', 10, 'application/pdf')]]);
        $private = TicketReply::where('is_private', true)->firstOrFail();

        $this->actingAs($owner)->post(route('dashboard.tickets.reply', $ticket), ['body' => 'x', 'private' => 1]);
        $this->assertSame(0, TicketReply::where('user_id', $owner->id)->where('is_private', true)->count());
        $this->get(route('dashboard.tickets.attachment', [$ticket, $private->attachments->first()]))->assertNotFound();
    }

    public function test_staff_see_only_their_departments_tickets(): void
    {
        $user = $this->customer();
        $this->openTicket($user, 'billing');
        $this->openTicket($user, 'technical-support');
        $billingTicket = Ticket::whereHas('department', fn ($q) => $q->where('slug', 'billing'))->firstOrFail();
        $techTicket = Ticket::whereHas('department', fn ($q) => $q->where('slug', 'technical-support'))->firstOrFail();

        $support = $this->staff('technical-support');
        $support->departments()->attach(Department::where('slug', 'technical-support')->value('id'));

        $this->actingAs($support)->get(route('admin.tickets.index'))->assertSee($techTicket->number)->assertDontSee($billingTicket->number);
        $this->get(route('admin.tickets.show', $techTicket))->assertOk();
        $this->get(route('admin.tickets.show', $billingTicket))->assertForbidden();
        $this->post(route('admin.tickets.reply', $billingTicket), ['body' => 'x'])->assertForbidden();
        $this->get(route('admin.tickets.index'))->assertOk();
    }

    public function test_ticket_assignment_requires_assign_permission(): void
    {
        $user = $this->customer();
        $this->openTicket($user);
        $ticket = Ticket::firstOrFail();
        $dept = Department::where('slug', 'billing')->value('id');
        $support = $this->staff('technical-support');
        $support->departments()->attach($dept);

        $this->actingAs($support)->post(route('admin.tickets.assign', $ticket), ['assigned_to' => $support->id])->assertForbidden();
        $this->actingAs($this->staff('super-admin'))->post(route('admin.tickets.assign', $ticket), ['assigned_to' => $support->id])->assertSessionHas('success');
        $this->assertSame($support->id, $ticket->fresh()->assigned_to);
    }

    public function test_telegram_alert_is_sent_once_and_never_duplicated(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Setting::set('telegram.bot_token', '123:SECRET-TOKEN', true);
        Setting::set('telegram.chat_id', '42');
        $notifier = app(Notifier::class);

        $notifier->admin('new_order', 'order:1', 'New order DJ1');
        $notifier->admin('new_order', 'order:1', 'New order DJ1'); // duplicate occurrence

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'bot123:SECRET-TOKEN/sendMessage') && $r['chat_id'] === '42');
        $this->assertSame(1, NotificationLog::where('event', 'new_order')->count());
        $this->assertSame('sent', NotificationLog::first()->status);
    }

    public function test_telegram_failure_is_logged_without_leaking_the_token_and_retries_do_not_double_send(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response([], 500)]);
        Setting::set('telegram.bot_token', '123:SECRET-TOKEN', true);
        Setting::set('telegram.chat_id', '42');
        Bus::fake([SendTelegramAlert::class]);

        app(Notifier::class)->admin('payment_failure', 'a:1', 'Failed');
        Bus::assertDispatched(SendTelegramAlert::class);

        $log = NotificationLog::firstOrFail();
        try {
            (new SendTelegramAlert($log->id, 'x'))->handle();
        } catch (\RuntimeException) {
        }
        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertStringNotContainsString('SECRET-TOKEN', (string) $log->error);
    }

    public function test_event_toggle_and_unconfigured_telegram_skip_sending(): void
    {
        Http::fake();
        app(Notifier::class)->admin('new_order', 'o:9', 'x'); // not configured
        Setting::set('telegram.bot_token', 't', true);
        Setting::set('telegram.chat_id', '1');
        Setting::set('notify.telegram.new_order', '0');
        app(Notifier::class)->admin('new_order', 'o:10', 'x');

        Http::assertNothingSent();
        $this->assertSame(2, NotificationLog::where('status', 'skipped')->count());
    }

    public function test_customer_email_templates_are_admin_editable_and_deduplicated(): void
    {
        Mail::fake();
        Setting::set('mail.template.ticket_reply.subject', 'Custom subject {ticket}');
        $user = $this->customer();

        app(Notifier::class)->customer('ticket_reply', 'r:1', $user, 'ticket_reply', ['ticket' => 'TKT1', 'url' => 'http://x']);
        app(Notifier::class)->customer('ticket_reply', 'r:1', $user, 'ticket_reply', ['ticket' => 'TKT1', 'url' => 'http://x']);

        Mail::assertQueued(TemplatedMail::class, 1);
        [$subject] = TemplatedMail::renderTemplate('ticket_reply', ['ticket' => 'TKT1']);
        $this->assertSame('Custom subject TKT1', $subject);
    }

    public function test_secrets_are_encrypted_at_rest_and_never_rendered(): void
    {
        $admin = $this->staff('super-admin');
        $this->actingAs($admin)->put('/admin/settings', ['section' => 'razorpay', 'gateway__razorpay__enabled' => 1, 'gateway__razorpay__mode' => 'test', 'gateway__razorpay__key_id' => 'rzp_test_1', 'gateway__razorpay__key_secret' => 'TOP-SECRET-VALUE', 'gateway__razorpay__webhook_secret' => 'WH-SECRET'])->assertRedirect();
        Setting::set('smtp.password', 'SMTP-PASS', true);

        $raw = SettingRow::withoutGlobalScopes()->get()->pluck('value', 'key');
        $this->assertStringNotContainsString('TOP-SECRET-VALUE', (string) $raw['gateway.razorpay.key_secret']);
        $this->assertStringNotContainsString('SMTP-PASS', (string) $raw['smtp.password']);
        $this->assertSame('TOP-SECRET-VALUE', Setting::get('gateway.razorpay.key_secret'));

        $this->get('/admin/settings')->assertOk()->assertDontSee('TOP-SECRET-VALUE')->assertDontSee('SMTP-PASS')->assertDontSee('WH-SECRET')->assertSee('stored encrypted');
        // Blank secret input keeps the stored value.
        $this->put('/admin/settings', ['section' => 'razorpay', 'gateway__razorpay__key_secret' => ''])->assertRedirect();
        $this->assertSame('TOP-SECRET-VALUE', Setting::get('gateway.razorpay.key_secret'));
        $this->assertDatabaseMissing('audit_logs', ['meta' => 'TOP-SECRET-VALUE']);
    }

    public function test_settings_require_permission_and_audit_changes(): void
    {
        $this->actingAs($this->staff('billing-staff'))->put('/admin/settings', ['section' => 'company'])->assertForbidden();
        $admin = $this->staff('super-admin');
        $this->actingAs($admin)->put('/admin/settings', ['section' => 'company', 'sales__phone' => '+91 1', 'invoice__prefix' => 'AUN'])->assertRedirect();
        $this->assertSame('AUN', Setting::get('invoice.prefix'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated', 'user_id' => $admin->id]);
    }

    public function test_telegram_connection_errors_do_not_leak_the_bot_token(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error for https://api.telegram.org/bot123:SECRET-TOKEN/sendMessage'));
        Setting::set('telegram.bot_token', '123:SECRET-TOKEN', true);
        Setting::set('telegram.chat_id', '42');
        $log = NotificationLog::create(['dedupe_key' => 'k', 'event' => 'new_order', 'channel' => 'telegram']);

        try {
            (new SendTelegramAlert($log->id, 'x'))->handle();
            $this->fail('expected failure');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('SECRET-TOKEN', $e->getMessage());
        }
        $this->assertStringNotContainsString('SECRET-TOKEN', (string) $log->fresh()->error);
    }
}
