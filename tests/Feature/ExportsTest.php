<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\ExportService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Milestone 9: authorised CSV exports without secrets or unnecessary PII. */
class ExportsTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        Mail::fake();
    }

    private function csv($response): string
    {
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        return $response->streamedContent();
    }

    public function test_every_type_needs_its_own_export_permission(): void
    {
        $this->actingAs($this->staff('technical-support'))->get('/admin/exports')->assertForbidden();
        foreach (array_keys(ExportService::TYPES) as $type) {
            $this->get('/admin/exports/'.$type)->assertForbidden();
        }
        $this->actingAs($this->customer(), 'web')->get('/admin/exports/customers')->assertNotFound();

        // Billing staff hold invoices.export by default but not customers.export.
        $billing = $this->staff('billing-staff');
        $this->actingAs($billing)->get('/admin/exports')->assertOk()->assertSee('Invoices')->assertDontSee('Staff activity');
        $this->get('/admin/exports/invoices')->assertOk();
        $this->get('/admin/exports/customers')->assertForbidden();
        $this->get('/admin/exports/unknown')->assertForbidden();
    }

    public function test_customer_export_excludes_secrets_and_phone_and_is_audited(): void
    {
        $customer = $this->customer(['name' => 'Asha Rao', 'email' => 'asha@example.test', 'phone' => '+919876543210']);
        $body = $this->csv($this->actingAs($this->staff())->get('/admin/exports/customers'));

        $this->assertStringContainsString('asha@example.test', $body);
        $this->assertStringNotContainsString('+919876543210', $body);
        $this->assertStringNotContainsString($customer->getRawOriginal('password'), $body);
        $this->assertStringNotContainsString((string) $customer->remember_token, $body);
        $this->assertStringNotContainsString('Password', $body);
        $this->assertDatabaseHas('audit_logs', ['action' => 'export.downloaded']);
    }

    public function test_cells_are_protected_against_formula_injection(): void
    {
        $this->customer(['name' => '=HYPERLINK("http://evil.test")', 'email' => 'f@example.test']);
        $body = $this->csv($this->actingAs($this->staff())->get('/admin/exports/customers'));
        $this->assertStringContainsString("'=HYPERLINK", $body);
        $this->assertSame('-5', ExportService::safeCell('-5')); // plain numbers untouched
        $this->assertSame("'@cmd", ExportService::safeCell('@cmd'));
    }

    public function test_ticket_export_respects_department_scope_and_never_includes_message_bodies(): void
    {
        $tickets = app(TicketService::class);
        $tech = Department::where('slug', 'technical-support')->firstOrFail();
        $billing = Department::where('slug', 'billing')->firstOrFail();
        $a = $tickets->open($this->customer(), $tech, 'VPS slow', 'SECRET-BODY-TEXT', 'normal', null);
        $b = $tickets->open($this->customer(), $billing, 'Invoice query', 'Other body', 'normal', null);

        Role::where('slug', 'technical-support')->first()->permissions()->create(['permission' => 'tickets.export']);
        $agent = $this->staff('technical-support');
        $agent->departments()->sync([$tech->id]);

        $body = $this->csv($this->actingAs($agent)->get('/admin/exports/tickets'));
        $this->assertStringContainsString($a->number, $body);
        $this->assertStringNotContainsString($b->number, $body);
        $this->assertStringNotContainsString('SECRET-BODY-TEXT', $body);
    }

    public function test_filters_and_large_exports_stream_in_chunks(): void
    {
        $this->approve('hermes-cli-launch');
        foreach (range(1, 3) as $i) {
            $this->placeOrder($this->customer(), 'hermes-cli-launch', 'tok-e'.$i);
        }
        $super = $this->staff();
        $body = $this->csv($this->actingAs($super)->get('/admin/exports/orders?status=pending_payment'));
        $this->assertSame(1 + 3, count(array_filter(explode("\n", trim($body)))));
        $body = $this->csv($this->get('/admin/exports/orders?status=paid'));
        $this->assertSame(1, count(array_filter(explode("\n", trim($body)))));
        $this->get('/admin/exports/orders?from=2026-12-01&to=2026-11-01')->assertSessionHasErrors('to');

        // Chunked streaming covers more rows than one chunk.
        foreach (range(1, 520) as $i) {
            User::factory()->create();
        }
        $body = $this->csv($this->get('/admin/exports/customers'));
        $this->assertGreaterThan(520, count(explode("\n", trim($body))));
    }

    public function test_staff_activity_export_contains_staff_actions_only(): void
    {
        $super = $this->staff();
        $this->actingAs($super)->get('/admin/exports/customers');
        $body = $this->csv($this->get('/admin/exports/staff_activity'));
        $this->assertStringContainsString('export.downloaded', $body);
        $this->assertStringContainsString($super->name, $body);
    }
}
