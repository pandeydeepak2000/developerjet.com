<?php

namespace Tests\Feature;

use App\Models\DocArticle;
use App\Models\DocCategory;
use App\Models\User;
use App\Support\DocRenderer;
use Database\Seeders\DocsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocsCenterTest extends TestCase
{
    use RefreshDatabase;

    /** Every topic the documentation centre must cover (slug => expected title fragment). */
    private const REQUIRED = [
        'account-registration' => 'Account Registration', 'email-verification' => 'Email Verification',
        'login-and-password-reset' => 'Login and Password Reset', 'customer-dashboard' => 'Customer Dashboard',
        'plan-selection' => 'Plan Selection', 'cart-and-checkout' => 'Cart and Checkout', 'payment-methods' => 'Payment Methods',
        'invoices-and-wallet' => 'Invoices and Wallet', 'billing-and-renewals' => 'Billing and Renewals', 'service-access' => 'Service Access',
        'hermes-cli' => 'Hermes CLI', 'hermes-workspace' => 'Hermes Workspace', 'n8n' => 'n8n', 'openclaw' => 'OpenClaw',
        'claude-code' => 'Claude Code Hosting', 'docker-vps' => 'Docker VPS', 'ai-all-access' => 'AI All-Access',
        'api-provider-configuration' => 'API Provider Configuration', 'custom-domain-setup' => 'Custom Domain Setup & HTTPS',
        'ssh-and-security' => 'SSH and Security', 'common-errors' => 'Common Errors', 'support-tickets' => 'Support Tickets',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DocsSeeder::class);
    }

    public function test_every_required_topic_is_published_with_substantial_content(): void
    {
        foreach (self::REQUIRED as $slug => $title) {
            $article = DocArticle::where('slug', $slug)->where('is_published', true)->first();
            $this->assertNotNull($article, "Missing article {$slug}");
            $this->assertStringContainsString($title, $article->title);
            $this->assertGreaterThan(900, strlen($article->body), "{$slug} is too short");
            $this->assertGreaterThanOrEqual(2, substr_count($article->body, "\n## "), "{$slug} needs sections");
            $this->assertNotEmpty($article->excerpt);
        }
    }

    public function test_article_title_is_rendered_exactly_once_as_the_only_h1(): void
    {
        foreach (array_keys(self::REQUIRED) as $slug) {
            $html = $this->get('/docs/'.$slug)->assertOk()->getContent();
            $title = e(DocArticle::where('slug', $slug)->value('title'));

            $this->assertSame(1, substr_count($html, '<h1'), "{$slug} must have exactly one h1");
            $this->assertSame(1, substr_count($html, '>'.$title.'</h1>'), "{$slug} h1 must be the title");
        }
    }

    public function test_leading_markdown_title_in_admin_content_is_not_duplicated(): void
    {
        $cat = DocCategory::first();
        DocArticle::create(['doc_category_id' => $cat->id, 'slug' => 'dup', 'title' => 'Duplicate Title', 'body' => "# Duplicate Title\n\nIntro.\n\n# Second level one\n\n## Part\n\ntext", 'is_published' => true]);

        $html = $this->get('/docs/dup')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, 'Duplicate Title</h1>'));
        $this->assertStringContainsString('<h2 id="second-level-one">', $html);
    }

    public function test_article_page_has_three_column_parts_breadcrumbs_toc_pager_and_support_link(): void
    {
        $this->get('/docs/cart-and-checkout')->assertOk()
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('class="dj-docs__sidebar"', false)
            ->assertSee('class="dj-docs__toc"', false)
            ->assertSee('On this page')
            ->assertSee('href="#checkout-step-by-step"', false)
            ->assertSee('id="checkout-step-by-step"', false)
            ->assertSee('rel="prev"', false)->assertSee('rel="next"', false)
            ->assertSee('Open a support ticket')
            ->assertSee(route('dashboard.tickets.create'), false)
            ->assertSee('data-doc-search', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_related_articles_are_limited_and_from_the_same_category(): void
    {
        $html = $this->get('/docs/hermes-cli')->assertOk()->getContent();
        preg_match('#<section class="dj-doc-related".*?</section>#s', $html, $m);
        $this->assertNotEmpty($m);
        $this->assertSame(3, substr_count($m[0], '<li>'));
        $this->assertStringContainsString('Hermes Workspace', $m[0]);
    }

    public function test_first_and_last_articles_have_one_sided_pagers(): void
    {
        $this->get('/docs/getting-started')->assertOk()->assertDontSee('rel="prev"', false)->assertSee('rel="next"', false);
        $this->get('/docs/support-tickets')->assertOk()->assertSee('rel="prev"', false)->assertDontSee('rel="next"', false);
    }

    public function test_callouts_diagrams_tables_and_code_blocks_render(): void
    {
        $this->get('/docs/custom-domain-setup')->assertOk()
            ->assertSee('dj-callout dj-callout--warning', false)
            ->assertSee('dj-doc-figure', false)
            ->assertSee('Connecting an existing domain')
            ->assertSee('<div class="dj-doc-table"><table>', false)
            ->assertSee('<pre><code class="language-bash">', false)
            ->assertDontSee('[diagram:', false);
    }

    public function test_unknown_diagram_shortcodes_render_nothing(): void
    {
        $html = (new DocRenderer)->render("Text\n\n[diagram: does-not-exist]\n\n[diagram: ../../secret]")['html'];
        $this->assertStringNotContainsString('does-not-exist', $html);
        $this->assertStringNotContainsString('<figure', $html);
    }

    public function test_search_matches_all_words_ranks_titles_first_and_highlights_snippets(): void
    {
        $response = $this->get('/docs?q=renewal+reminder')->assertOk()->assertSee('Billing and Renewals');
        $html = $response->getContent();
        $this->assertStringContainsString('<mark>', $html);
        $this->assertLessThan(strpos($html, 'Common Errors and Troubleshooting') ?: PHP_INT_MAX, strpos($html, 'Billing and Renewals'));

        $this->get('/docs?q=ssh+key')->assertOk()->assertSee('SSH and Security');
        $this->get('/docs?q=PuTTY')->assertOk()->assertSee('Hermes CLI');
    }

    public function test_search_escapes_input_and_snippets(): void
    {
        $cat = DocCategory::first();
        DocArticle::create(['doc_category_id' => $cat->id, 'slug' => 'amp', 'title' => 'Ampersand', 'body' => 'Tom & Jerry <b>bold</b> example', 'is_published' => true]);

        $this->get('/docs?q=%3Cscript%3Ealert(1)%3C/script%3E')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        $html = $this->get('/docs?q=example')->assertOk()->getContent();
        $this->assertStringContainsString('Tom &amp; Jerry', $html);
        $this->assertStringNotContainsString('<b>bold</b>', $html);
        $this->assertStringContainsString('<mark>example</mark>', $html);
    }

    public function test_index_has_categories_popular_guides_and_embedded_search_index(): void
    {
        $this->get('/docs')->assertOk()
            ->assertSee('Plans &amp; billing', false)->assertSee('Access &amp; security', false)->assertSee('Help &amp; support', false)
            ->assertSee('Popular guides')
            ->assertSee('data-doc-index', false)
            ->assertSee('class="dj-docs-cat"', false);
    }

    public function test_legacy_slugs_redirect_permanently(): void
    {
        foreach (config('docs.redirects') as $old => $new) {
            $this->get('/docs/'.$old)->assertStatus(301)->assertRedirect('/docs/'.$new);
        }
    }

    public function test_custom_domain_article_is_renamed_and_upgraded_from_the_previous_shipped_body(): void
    {
        $article = DocArticle::where('slug', 'custom-domain-setup')->firstOrFail();
        $this->assertSame('Custom Domain Setup & HTTPS', $article->title);
        $this->assertStringContainsString('## HTTPS for your domain', $article->body);
        $this->assertStringContainsString('not automated yet', $article->body);

        // A local copy still holding the previous shipped body is upgraded in place; staff edits are kept.
        $article->update(['title' => 'Custom Domain Setup', 'body' => str_replace('## HTTPS for your domain', 'x', $article->body)]);
        $this->seed(DocsSeeder::class);
        $this->assertSame('Custom Domain Setup', $article->fresh()->title, 'edited (unknown) body must be kept');
    }

    public function test_no_domain_sales_content(): void
    {
        $all = DocArticle::pluck('body')->implode("\n").' '.$this->get('/docs')->getContent();
        foreach (['register a domain', 'domain search', 'search for a domain', 'buy a domain from us', 'transfer your domain to'] as $phrase) {
            $this->assertStringNotContainsStringIgnoringCase($phrase, $all);
        }
        $this->get('/docs/custom-domain-setup')->assertOk()->assertSee('does <strong>not</strong> sell, register, renew or transfer domains', false);
    }

    public function test_seeder_upgrades_unedited_shipped_content_and_keeps_admin_edits(): void
    {
        DocArticle::query()->delete();
        $cat = DocCategory::where('slug', 'help')->first();
        $cat->update(['name' => 'Help']);
        // v0.1 shipped body (hash listed in SHIPPED_BODY_HASHES) under the old slug.
        DocArticle::create(['doc_category_id' => $cat->id, 'slug' => 'support', 'title' => 'Support', 'body' => "# Support\n\nOpen a ticket from your dashboard, choose a department and priority, and attach files if useful. Never include passwords or API keys in a ticket.", 'is_published' => true]);
        // An article that staff edited: must never be touched.
        DocArticle::create(['doc_category_id' => $cat->id, 'slug' => 'common-errors', 'title' => 'Our own errors page', 'body' => 'Custom text by staff', 'is_published' => false]);

        $this->seed(DocsSeeder::class);

        $this->assertSame('Help & support', $cat->fresh()->name);
        $this->assertNull(DocArticle::where('slug', 'support')->first());
        $upgraded = DocArticle::where('slug', 'support-tickets')->first();
        $this->assertSame('Support Tickets', $upgraded->title);
        $this->assertStringNotContainsString('# Support', $upgraded->body);

        $kept = DocArticle::where('slug', 'common-errors')->first();
        $this->assertSame('Custom text by staff', $kept->body);
        $this->assertFalse($kept->is_published);

        $count = DocArticle::count();
        $this->seed(DocsSeeder::class);
        $this->assertSame($count, DocArticle::count(), 'Seeder must be idempotent');
    }

    public function test_seed_files_never_start_with_a_markdown_h1(): void
    {
        foreach (array_keys(DocsSeeder::CATEGORIES) as $category) {
            foreach (DocsSeeder::articles($category) as $doc) {
                $this->assertDoesNotMatchRegularExpression('/\A\s*# /', $doc['body'], "{$doc['slug']} repeats its title as an H1");
            }
        }
    }

    public function test_docs_pages_render_on_layout_for_signed_in_customers_too(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/docs/support-tickets')->assertOk()->assertSee('Support Tickets');
    }
}
