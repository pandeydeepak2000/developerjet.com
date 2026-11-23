<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Support\ProductFamilies;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /** Hermes CLI, Hermes Workspace and n8n share the config-driven product template; plans come from the DB. */
    public function hermesCli(MarketingController $marketing): View
    {
        return $marketing->renderProduct('hermes-cli');
    }

    public function hermesWorkspace(MarketingController $marketing): View
    {
        return $marketing->renderProduct('hermes-workspace');
    }

    public function n8n(MarketingController $marketing): View
    {
        return $marketing->renderProduct('n8n');
    }

    /** All AI hosting plans, including those whose price is still pending. */
    public function index(): View
    {
        return view('pages.plans', [
            'categories' => ProductCategory::where('is_active', true)
                ->with(['plans' => fn ($q) => $q->active()->with(['prices', 'resourceProfile'])])
                ->orderBy('sort_order')->get(),
            'families' => ProductFamilies::all(),
        ]);
    }
}
