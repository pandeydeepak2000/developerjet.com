<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\Department;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** Milestone 5: support team workflow (queue, assignment, notes, priority, SLA, escalation, isolation). */
class SupportWorkflowTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        Mail::fake();
    }

    private function dept(string $slug): Department
    {
        return Department::where('slug', $slug)->firstOrFail();
    }

    private function agent(string $deptSlug, string $role = 'technical-support'): User
    {
        $agent = $this->staff($role);
        $agent->departments()->sync([$this->dept($deptSlug)->id]);

        return $agent;
    }

    private function open(User $customer, string $deptSlug = 'technical-support', string $priority = 'normal'): Ticket
    {
        return app(TicketService::class)->open($customer, $this->dept($deptSlug), 'Help please', 'Body text', $priority, null);
    }

    public function test_sla_targets_are_empty_until_configured_then_applied(): void
    {
        $customer = $this->customer();
        $t = $this->open($customer);
        $this->assertNull($t->first_response_due_at);
        $this->assertNull($t->resolution_due_at);

        Setting::set('support.sla.urgent.first_response_hours', '2');
        Setting::set('support.sla.urgent.resolution_hours', '24');
        $u = $this->open($customer, 'technical-support', 'urgent');
        $this->assertEqualsWithDelta(now()->addHours(2)->timestamp, $u->first_response_due_at->timestamp, 5);
        $this->assertEqualsWithDelta(now()->addHours(24)->timestamp, $u->resolution_due_at->timestamp, 5);

        // A department target overrides the priority default.
        $this->dept('billing')->update(['sla_first_response_hours' => 8]);
        $b = $this->open($customer, 'billing', 'urgent');
        $this->assertEqualsWithDelta(now()->addHours(8)->timestamp, $b->first_response_due_at->timestamp, 5);
    }

    public function test_queue_is_department_scoped_and_filterable(): void
    {
        $customer = $this->customer(['email' => 'findme@example.test']);
        $tech = $this->open($customer, 'technical-support', 'high');
        $billing = $this->open($this->customer(), 'billing');
        $agent = $this->agent('technical-support');

        $this->actingAs($agent)->get('/admin/tickets')->assertOk()->assertSee($tech->number)->assertDontSee($billing->number);
        $this->get('/admin/tickets/'.$billing->id)->assertForbidden();
        $this->get('/admin/tickets?priority=low')->assertDontSee($tech->number);
        $this->get('/admin/tickets?priority=high')->assertSee($tech->number);
        $this->get('/admin/tickets?q=findme')->assertSee($tech->number);
        $this->get('/admin/tickets?assignee=unassigned')->assertSee($tech->number);
        $this->get('/admin/tickets?assignee=mine')->assertDontSee($tech->number);

        // Closed tickets drop out of the default (active) queue but are reachable with status=all.
        $tech->update(['status' => 'closed']);
        $this->get('/admin/tickets')->assertDontSee($tech->number);
        $this->get('/admin/tickets?status=all')->assertSee($tech->number);
    }

    public function test_assignment_is_limited_to_staff_who_can_see_the_department_and_alerts_the_assignee(): void
    {
        $ticket = $this->open($this->customer());
        $lead = $this->agent('technical-support', 'admin'); // admin role holds tickets.assign
        $inDept = $this->agent('technical-support');
        $outside = $this->agent('billing');

        $this->actingAs($lead)->post(route('admin.tickets.assign', $ticket), ['assigned_to' => $outside->id])->assertSessionHas('error');
        $this->assertNull($ticket->fresh()->assigned_to);

        $this->post(route('admin.tickets.assign', $ticket), ['assigned_to' => $inDept->id])->assertSessionHas('success');
        $this->assertSame($inDept->id, $ticket->fresh()->assigned_to);
        Mail::assertQueued(TemplatedMail::class, fn ($m) => $m->template === 'staff_ticket_assigned' && $m->hasTo($inDept->email));
        $this->assertDatabaseHas('ticket_events', ['ticket_id' => $ticket->id, 'type' => 'assigned']);

        // Agents without tickets.assign cannot assign.
        $this->actingAs($inDept)->post(route('admin.tickets.assign', $ticket), ['assigned_to' => null])->assertForbidden();
    }

    public function test_replies_private_notes_first_response_and_customer_isolation(): void
    {
        $customer = $this->customer();
        $ticket = $this->open($customer);
        $agent = $this->agent('technical-support');

        $this->actingAs($agent)->post(route('admin.tickets.reply', $ticket), ['body' => 'Internal only', 'private' => 1])->assertSessionHas('success');
        $this->assertNull($ticket->fresh()->first_response_at); // a private note is not a response
        $this->post(route('admin.tickets.reply', $ticket), ['body' => 'Public answer'])->assertSessionHas('success');
        $this->assertNotNull($ticket->fresh()->first_response_at);
        $this->assertSame('answered', $ticket->fresh()->status);

        $this->actingAs($customer, 'web')->get('/dashboard/tickets/'.$ticket->id)->assertOk()->assertSee('Public answer')->assertDontSee('Internal only')->assertDontSee('First staff response');
        $this->actingAs($this->customer(), 'web')->get('/dashboard/tickets/'.$ticket->id)->assertForbidden();
    }

    public function test_dedicated_reply_permission_works_without_edit(): void
    {
        $role = Role::create(['slug' => 'responder', 'name' => 'Responder']);
        $role->permissions()->createMany([['permission' => 'tickets.view'], ['permission' => 'tickets.reply']]);
        $agent = $this->customer();
        $agent->forceFill(['role' => 'staff', 'role_id' => $role->id])->save();
        $agent->departments()->sync([$this->dept('technical-support')->id]);
        $ticket = $this->open($this->customer());

        $this->actingAs($agent)->post(route('admin.tickets.reply', $ticket), ['body' => 'Hi'])->assertSessionHas('success');
        $this->post(route('admin.tickets.status', $ticket), ['status' => 'closed'])->assertForbidden();
        $this->post(route('admin.tickets.priority', $ticket), ['priority' => 'urgent'])->assertForbidden();
    }

    public function test_status_priority_transfer_and_escalation_are_logged(): void
    {
        $ticket = $this->open($this->customer());
        $lead = $this->agent('technical-support', 'admin');

        $this->actingAs($lead)->post(route('admin.tickets.status', $ticket), ['status' => 'on_hold'])->assertSessionHas('success');
        $this->post(route('admin.tickets.status', $ticket), ['status' => 'bogus'])->assertSessionHasErrors('status');
        $this->post(route('admin.tickets.priority', $ticket), ['priority' => 'high'])->assertSessionHas('success');
        $this->post(route('admin.tickets.escalate', $ticket), ['reason' => 'Customer down', 'priority' => 'urgent'])->assertSessionHas('success');
        $fresh = $ticket->fresh();
        $this->assertSame(1, $fresh->escalation_level);
        $this->assertSame('urgent', $fresh->priority);

        // Transfer to a department the lead cannot see: they are sent back to the queue.
        $this->post(route('admin.tickets.transfer', $ticket), ['department_id' => $this->dept('billing')->id])->assertRedirect(route('admin.tickets.index'));
        $this->assertSame($this->dept('billing')->id, $ticket->fresh()->department_id);
        $this->get('/admin/tickets/'.$ticket->id)->assertForbidden();

        $types = $ticket->events()->pluck('type')->all();
        foreach (['opened', 'status', 'priority', 'escalated', 'department'] as $t) {
            $this->assertContains($t, $types);
        }
    }

    public function test_sla_check_flags_breaches_once_and_can_auto_escalate(): void
    {
        Setting::set('support.sla.normal.first_response_hours', '1');
        Setting::set('support.auto_escalate_on_breach', '1');
        $ticket = $this->open($this->customer());

        $this->assertSame(0, app(TicketService::class)->slaCheck());
        $this->travel(2)->hours();
        $this->artisan('support:sla-check')->expectsOutputToContain('1')->assertSuccessful();
        $this->assertSame(0, app(TicketService::class)->slaCheck()); // idempotent
        $fresh = $ticket->fresh();
        $this->assertNotNull($fresh->sla_breached_at);
        $this->assertSame(1, $fresh->escalation_level);
        $this->assertDatabaseHas('ticket_events', ['ticket_id' => $ticket->id, 'type' => 'sla_breached']);
    }

    public function test_inactive_departments_do_not_accept_new_tickets_and_department_config_needs_assign(): void
    {
        $dept = $this->dept('sales');
        $dept->update(['is_active' => false]);
        $customer = $this->customer();
        $this->actingAs($customer, 'web')->get('/dashboard/tickets/new')->assertOk()->assertDontSee('>Sales<', false);
        $this->post('/dashboard/tickets', ['department_id' => $dept->id, 'subject' => 'x', 'body' => 'y', 'priority' => 'normal'])->assertSessionHasErrors('department_id');

        $this->actingAs($this->agent('technical-support'))->get('/admin/r/departments/'.$dept->id.'/edit')->assertForbidden();
        $this->actingAs($this->agent('technical-support', 'admin'))->get('/admin/r/departments/'.$dept->id.'/edit')->assertOk()->assertSee('SLA first-response');
    }

    public function test_support_roles_do_not_get_secrets_wallet_or_settings(): void
    {
        $agent = $this->agent('technical-support');
        $this->actingAs($agent);
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/admin/settings/staff-portal')->assertForbidden();
        $this->post('/admin/wallet/adjust', ['user_id' => 1, 'amount' => 100])->assertForbidden();
        $this->get('/admin/staff')->assertForbidden();
        $this->get('/admin/r/payments')->assertForbidden();
    }
}
