<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\ProductFamilies;
use Illuminate\View\View;

/** Config-driven product, comparison and digital-services pages. Plans, prices and resources always come from the DB. */
class MarketingController extends Controller
{
    /** Product family => route of its product page (used to link featured plans). */
    public const PRODUCT_ROUTES = [
        'hermes-cli' => ['hermes.cli', null],
        'hermes-workspace' => ['hermes.workspace', null],
        'n8n' => ['n8n', null],
        'claude-code' => ['product.show', 'claude-code'],
        'docker-vps' => ['product.show', 'docker-vps'],
        'openclaw' => ['product.show', 'openclaw'],
        'ai-complete-bundle' => ['product.show', 'ai-complete-bundle'],
        'ai-all-access' => ['ai-all-access', null],
    ];

    public function home(): View
    {
        return view('pages.home', ['families' => ProductFamilies::all(homeOnly: true), 'matrix' => config('product_matrix')]);
    }

    public function product(string $product): View
    {
        return $this->renderProduct($product);
    }

    /** Shared renderer for every product page (Hermes CLI/Workspace and n8n are routed here from CatalogController). */
    public function renderProduct(string $key): View
    {
        $page = config('product_pages.'.$key) ?? abort(404);

        $plans = Plan::with(['prices', 'resourceProfile'])->active()
            ->whereHas('category', fn ($q) => $q->where('slug', $page['category'])->where('is_active', true))
            ->orderBy('sort_order')->get();

        return view('pages.product', ['page' => $page, 'slug' => $key, 'plans' => $plans, 'matrix' => config('product_matrix')]);
    }

    /** AI All-Access (BYOK workspace, coming soon). Draft plans come from the DB; none can be bought. */
    public function aiAllAccess(): View
    {
        $plans = Plan::with(['prices', 'resourceProfile'])->active()->where('visibility', 'public')
            ->whereHas('category', fn ($q) => $q->where('slug', 'ai-all-access')->where('is_active', true))
            ->orderBy('sort_order')->get();

        return view('pages.ai-all-access', ['plans' => $plans, 'matrix' => config('product_matrix')]);
    }

    public function compareIndex(): View
    {
        return view('pages.compare-index', ['comparisons' => config('comparisons')]);
    }

    public function compare(string $slug): View
    {
        $comparison = config('comparisons.'.$slug) ?? abort(404);

        return view('pages.compare', ['slug' => $slug, 'c' => $comparison]);
    }

    public function digitalServices(): View
    {
        return view('pages.digital-services', ['services' => [
            ['WordPress Development', 'services.show', 'wordpress'],
            ['Laravel Development', 'services.show', 'laravel-development'],
            ['Web Development', 'services.show', 'web-development'],
            ['SEO Services', 'services.show', 'seo'],
            ['Custom AI Application Development', null, null],
            ['Website Maintenance', null, null],
            ['Website and Application Deployment', null, null],
            ['Business Automation', 'business-automation', null],
        ]]);
    }

    /** AI Application Hosting: custom-scoped (no plans exist), so the page explains scope and routes to Sales. */
    public function aiApplicationHosting(): View
    {
        return view('pages.ai-app-hosting');
    }
}
