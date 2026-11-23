<?php

namespace Tests\Feature;

use App\Models\DocArticle;
use App\Models\Milestone;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Project;
use App\Models\SeoReport;
use App\Models\SeoTask;
use App\Models\ServiceRequest;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class ProfessionalServicesTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
        Storage::fake('local');
    }

    private function wordpressRequest($user)
    {
        return $this->actingAs($user)->post('/services/wordpress-services-starter-site/request', ['req' => [
            'business_type' => 'Bakery', 'website_purpose' => 'Show menu', 'required_pages' => 'Home, Menu', 'domain_hosting_status' => 'need_both',
            'ecommerce' => 'no', 'content_ready' => 'partial',
        ], 'files' => [UploadedFile::fake()->image('ref.png')]]);
    }

    public function test_service_pages_are_database_driven_with_three_plans_each_and_no_invented_prices(): void
    {
        foreach (['wordpress' => 'WordPress Services', 'laravel-development' => 'Laravel Development', 'web-development' => 'Web Development', 'seo' => 'SEO Services'] as $page => $title) {
            $this->get("/services/$page")->assertOk()->assertSee($title)->assertSee('Quote on request')->assertDontSee('₹');
        }
        foreach (['wordpress', 'laravel', 'web', 'seo'] as $kind) {
            $this->assertSame(3, Plan::where('kind', $kind)->count());
        }
        $this->assertSame(0, Plan::whereIn('kind', ['wordpress', 'laravel', 'web', 'seo'])->where('is_purchasable', true)->count());
        $this->get('/services/seo')->assertSee('never guarantee');
    }

    public function test_quote_plans_cannot_be_added_to_cart(): void
    {
        $this->post('/cart', ['plan' => 'web-development-landing-page', 'billing_interval' => 'monthly'])->assertSessionHas('error');
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_requirement_form_validates_and_stores_files_privately(): void
    {
        $user = $this->customer();
        $this->actingAs($user)->post('/services/wordpress-services-starter-site/request', ['req' => ['business_type' => 'x']])->assertSessionHasErrors(['req.website_purpose', 'req.required_pages']);

        $this->wordpressRequest($user)->assertRedirect();
        $sr = ServiceRequest::firstOrFail();
        $this->assertSame('submitted', $sr->status);
        $this->assertSame('Bakery', $sr->requirements['business_type']);
        $this->assertCount(1, $sr->attachments);
        Storage::disk('local')->assertExists($sr->attachments->first()->path);
        $this->assertDatabaseCount('orders', 0); // no charge, no order
    }

    public function test_guests_cannot_submit_requests(): void
    {
        $this->post('/services/wordpress-services-starter-site/request', [])->assertRedirect('/login');
    }

    public function test_quote_flow_never_charges_and_acceptance_opens_one_project(): void
    {
        $customer = $this->customer();
        $this->wordpressRequest($customer);
        $sr = ServiceRequest::firstOrFail();
        $sales = $this->staff('sales-staff'); // can view but not quote

        $this->actingAs($sales)->post(route('admin.requests.quote', $sr), ['quote' => 5000])->assertForbidden();

        $admin = $this->staff('admin');
        $this->actingAs($admin)->post(route('admin.requests.quote', $sr), ['quote' => 12345.50, 'quote_note' => 'Scope'])->assertSessionHas('success');
        $this->assertSame(1234550, $sr->fresh()->quote_minor);
        $this->assertSame('quoted', $sr->fresh()->status);

        $this->actingAs($customer)->post(route('dashboard.requests.accept', $sr))->assertRedirect();
        $this->post(route('dashboard.requests.accept', $sr)); // replay
        $this->assertSame(1, Project::count());
        $this->assertSame('accepted', $sr->fresh()->status);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_customer_cannot_accept_without_a_quote_or_see_other_requests(): void
    {
        $customer = $this->customer();
        $this->wordpressRequest($customer);
        $sr = ServiceRequest::firstOrFail();

        $this->actingAs($customer)->post(route('dashboard.requests.accept', $sr))->assertForbidden();
        $this->actingAs($this->customer())->get(route('dashboard.requests.show', $sr))->assertNotFound();
        $this->post(route('dashboard.requests.decline', $sr))->assertNotFound();
    }

    private function project(): array
    {
        $customer = $this->customer();
        $this->wordpressRequest($customer);
        $sr = ServiceRequest::firstOrFail();
        $this->actingAs($this->staff('admin'))->post(route('admin.requests.quote', $sr), ['quote' => 1000]);
        $this->actingAs($customer)->post(route('dashboard.requests.accept', $sr));

        return [$customer, Project::firstOrFail()];
    }

    public function test_milestone_approval_workflow_and_progress(): void
    {
        [$customer, $project] = $this->project();
        $admin = $this->staff('admin');
        $this->actingAs($admin)->post(route('admin.projects.milestones.store', $project), ['title' => 'Design']);
        $m = Milestone::firstOrFail();

        // Customer cannot approve before approval is requested.
        $this->actingAs($customer)->post(route('dashboard.projects.milestone', [$project, $m]), ['decision' => 'approve'])->assertSessionHas('error');

        $this->actingAs($admin)->put(route('admin.projects.milestones.update', [$project, $m]), ['action' => 'start']);
        $this->put(route('admin.projects.milestones.update', [$project, $m]), ['action' => 'request_approval', 'files' => [UploadedFile::fake()->create('design.pdf', 50, 'application/pdf')]])->assertRedirect();
        $this->assertSame('awaiting_approval', $m->fresh()->status);
        $this->assertCount(1, $m->fresh()->attachments);

        // Revision requires feedback.
        $this->actingAs($customer)->post(route('dashboard.projects.milestone', [$project, $m]), ['decision' => 'revise'])->assertSessionHasErrors('feedback');
        $this->post(route('dashboard.projects.milestone', [$project, $m]), ['decision' => 'revise', 'feedback' => 'Bigger logo']);
        $this->assertSame('revision_requested', $m->fresh()->status);
        $this->assertSame('Bigger logo', $m->fresh()->client_feedback);

        $this->actingAs($admin)->put(route('admin.projects.milestones.update', [$project, $m]), ['action' => 'request_approval']);
        $this->actingAs($customer)->post(route('dashboard.projects.milestone', [$project, $m]), ['decision' => 'approve'])->assertSessionHas('success');
        $this->post(route('dashboard.projects.milestone', [$project, $m]), ['decision' => 'approve'])->assertSessionHas('error'); // already decided

        $this->assertSame('approved', $m->fresh()->status);
        $this->assertSame($customer->id, $m->fresh()->decided_by);
        $this->get(route('dashboard.projects.show', $project))->assertSee('100% of milestones approved');
    }

    public function test_project_data_is_isolated_between_customers_and_staff_scope_is_enforced(): void
    {
        [$owner, $project] = $this->project();
        $this->actingAs($this->staff('admin'))->post(route('admin.projects.milestones.store', $project), ['title' => 'M1']);
        $m = Milestone::firstOrFail();
        $stranger = $this->customer();

        $this->actingAs($stranger)->get(route('dashboard.projects.show', $project))->assertNotFound();
        $this->post(route('dashboard.projects.milestone', [$project, $m]), ['decision' => 'approve'])->assertNotFound();
        $this->post(route('dashboard.projects.message', $project), ['body' => 'hi'])->assertNotFound();

        // A developer only sees projects assigned to them.
        $dev = $this->staff('developer');
        $this->actingAs($dev)->get(route('admin.projects.show', $project))->assertForbidden();
        $project->forceFill(['assigned_to' => $dev->id])->save();
        $this->get(route('admin.projects.show', $project))->assertOk();
        $this->actingAs($owner)->get(route('admin.projects.show', $project))->assertNotFound();
    }

    public function test_only_staff_with_assign_permission_can_assign_developers(): void
    {
        [, $project] = $this->project();
        $dev = $this->staff('developer');
        $payload = ['status' => 'active', 'assigned_to' => $dev->id];

        $this->actingAs($this->staff('sales-staff'))->put(route('admin.projects.update', $project), $payload)->assertForbidden();
        $this->actingAs($this->staff('admin'))->put(route('admin.projects.update', $project), $payload)->assertSessionHas('success');
        $this->assertSame($dev->id, $project->fresh()->assigned_to);
        $this->assertDatabaseHas('project_messages', ['project_id' => $project->id, 'type' => 'history']);
    }

    public function test_fixed_scope_plan_when_approved_creates_project_after_verified_payment(): void
    {
        $plan = $this->approve('web-development-landing-page', 500);
        $plan->update(['fulfilment' => 'provisioned']);
        $user = $this->customer();
        WalletService::class;
        app(WalletService::class)->credit($user, 500000, 'topup', 'seed');
        $order = $this->placeOrder($user, 'web-development-landing-page', 'tw', ['use_wallet' => 1]);

        $this->assertSame('awaiting_provisioning', $order->status);
        $this->assertSame(1, Project::where('order_id', $order->id)->count());
        $this->assertDatabaseCount('services', 0); // not a VPS service
    }

    public function test_seo_workspace_shows_only_recorded_data_and_published_reports(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->post('/services/seo-services-seo-basic/request', ['req' => ['website_url' => 'https://example.com', 'business_type' => 'Shop', 'analytics_access' => 'none']]);
        $sr = ServiceRequest::firstOrFail();
        $this->actingAs($this->staff('admin'))->post(route('admin.requests.quote', $sr), ['quote' => 100]);
        $this->actingAs($customer)->post(route('dashboard.requests.accept', $sr));
        $project = Project::firstOrFail();
        $seo = $this->staff('seo-manager');

        $this->actingAs($seo)->post(route('admin.seo.task', $project), ['title' => 'Fix titles', 'kind' => 'task', 'period' => '2026-11']);
        $this->post(route('admin.seo.task', $project), ['title' => 'Add schema markup', 'kind' => 'recommendation']);
        $this->post(route('admin.seo.audit', $project), ['website_url' => 'https://example.com', 'type' => 'sitemap', 'findings' => "No sitemap.xml\nrobots ok"])->assertSessionHas('success');
        $this->post(route('admin.seo.report', $project), ['period' => '2026-11', 'title' => 'Nov report', 'file' => UploadedFile::fake()->create('r.pdf', 20, 'application/pdf')])->assertSessionHas('success');

        $this->actingAs($customer)->get(route('dashboard.projects.show', $project))
            ->assertSee('No search data source is connected')->assertSee('Add schema markup')->assertDontSee('Nov report')
            ->assertDontSee('guaranteed')->assertDontSee('ranking improved');

        $report = SeoReport::firstOrFail();
        $this->get(route('dashboard.projects.report', [$project, $report]))->assertNotFound(); // unpublished
        $this->actingAs($seo)->post(route('admin.seo.publish', [$project, $report]));
        $this->actingAs($customer)->get(route('dashboard.projects.show', $project))->assertSee('Nov report');
        $this->get(route('dashboard.projects.report', [$project, $report]))->assertOk();
        $this->actingAs($this->customer())->get(route('dashboard.projects.report', [$project, $report]))->assertNotFound();

        $rec = SeoTask::where('kind', 'recommendation')->firstOrFail();
        $this->actingAs($customer)->post(route('dashboard.projects.seo-task', [$project, $rec]), ['decision' => 'approved']);
        $this->assertSame('approved', $rec->fresh()->status);
    }

    public function test_docs_center_lists_search_related_and_seo_metadata(): void
    {
        $this->assertGreaterThanOrEqual(24, DocArticle::count());
        $this->get('/docs')->assertOk()->assertSee('Getting Started with DeveloperJet')->assertSee('Hermes CLI')->assertSee('Common Errors and Troubleshooting')->assertSee('Billing and Renewals');
        $this->get('/docs?q=wallet')->assertOk()->assertSee('Invoices and Wallet');
        $this->get('/docs?q=zzzznomatch')->assertOk()->assertSee('No matches');

        $this->get('/docs/hermes-cli')->assertOk()->assertSee('<meta name="description"', false)->assertSee('Related articles')->assertSee('<h1 id="doc-title">', false);
        $this->get('/docs/nonexistent')->assertNotFound();
    }

    public function test_docs_markdown_strips_raw_html_and_unpublished_articles_are_hidden(): void
    {
        $cat = DocArticle::first()->doc_category_id;
        DocArticle::create(['doc_category_id' => $cat, 'slug' => 'xss', 'title' => 'XSS', 'body' => "<script>alert(1)</script>\n\n[bad](javascript:alert(1))\n\nhello", 'is_published' => true]);
        DocArticle::create(['doc_category_id' => $cat, 'slug' => 'draft', 'title' => 'Draft one', 'body' => 'x', 'is_published' => false]);

        $this->get('/docs/xss')->assertOk()->assertDontSee('<script>alert(1)', false)->assertDontSee('href="javascript:', false)->assertSee('hello');
        $this->get('/docs/draft')->assertNotFound();
        $this->get('/docs?q=Draft')->assertDontSee('Draft one');
    }

    public function test_docs_are_managed_by_staff_with_docs_permission(): void
    {
        $cat = DocArticle::first()->doc_category_id;
        $payload = ['doc_category_id' => $cat, 'title' => 'New', 'slug' => 'new-article', 'body' => '# Hi', 'is_published' => 1];

        $this->actingAs($this->staff('sales-staff'))->post('/admin/r/docs', $payload)->assertForbidden();
        $this->actingAs($this->staff('admin'))->post('/admin/r/docs', $payload)->assertRedirect();
        $this->get('/docs/new-article')->assertOk();
    }

    public function test_admin_pages_render_for_super_admin(): void
    {
        $admin = $this->staff('super-admin');
        $this->actingAs($admin);

        foreach (['/admin', '/admin/orders', '/admin/plans', '/admin/plans/create', '/admin/wallet', '/admin/tickets', '/admin/staff', '/admin/settings', '/admin/projects', '/admin/seo',
            '/admin/r/customers', '/admin/r/categories', '/admin/r/addons', '/admin/r/servers', '/admin/r/server_pools', '/admin/r/payments', '/admin/r/invoices', '/admin/r/subscriptions',
            '/admin/r/deployments', '/admin/r/service_requests', '/admin/r/departments', '/admin/r/docs', '/admin/r/doc_categories', '/admin/r/notifications', '/admin/r/security_logs',
            '/admin/r/servers/create', '/admin/r/categories/create'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_plan_admin_cannot_approve_prices_without_permission_and_unapproved_never_purchasable(): void
    {
        $plan = Plan::where('slug', 'openclaw-starter')->firstOrFail();
        $base = ['product_category_id' => $plan->product_category_id, 'name' => 'Starter', 'slug' => $plan->slug, 'tax_class' => 'standard', 'visibility' => 'public', 'kind' => 'hosting',
            'fulfilment' => 'provisioned', 'monthly' => 999, 'yearly' => 9990, 'is_active' => 1, 'is_purchasable' => 1];

        // 'admin' role has plans.edit but not plans.approve.
        $this->actingAs($this->staff('admin'))->put(route('admin.plans.update', $plan), $base + ['price_status' => 'approved'])->assertRedirect();
        $this->assertSame('pending', $plan->fresh()->price_status);
        $this->assertFalse($plan->fresh()->is_purchasable);
        $this->assertSame(99900, $plan->fresh()->priceFor('monthly'));

        $this->actingAs($this->staff('super-admin'))->put(route('admin.plans.update', $plan), $base + ['price_status' => 'approved'])->assertRedirect();
        $this->assertTrue($plan->fresh()->is_purchasable);
        $this->assertTrue($plan->fresh()->canBePurchased());

        // Quote-based plans are never purchasable, even when approved.
        $this->put(route('admin.plans.update', $plan), array_merge($base, ['price_status' => 'approved', 'fulfilment' => 'quote']));
        $this->assertFalse($plan->fresh()->is_purchasable);
    }

    public function test_product_pages_read_plan_data_from_the_database(): void
    {
        Plan::where('slug', 'hermes-cli-launch')->update(['name' => 'Renamed From Admin']);

        $this->get('/hermes-cli')->assertOk()->assertSee('Renamed From Admin');
    }

    public function test_dashboard_overview_and_all_customer_pages_render(): void
    {
        $user = $this->customer();
        $this->actingAs($user);

        foreach (['/dashboard', '/dashboard/orders', '/dashboard/services', '/dashboard/projects', '/dashboard/requests', '/dashboard/wallet', '/dashboard/invoices', '/dashboard/tickets', '/dashboard/tickets/new', '/docs'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->assertSame(0, Order::count());
    }
}
