<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\ProductCategory;
use App\Models\ServiceRequest;
use App\Services\AttachmentStore;
use App\Services\Notifier;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public Phase 4 service pages (database-driven) and the authenticated requirement/quote request flow. */
class ServicesController extends Controller
{
    public const PAGES = [
        'wordpress' => ['category' => 'wordpress-services', 'kind' => 'wordpress', 'title' => 'WordPress Services', 'lead' => 'Professionally built WordPress websites, scoped around your requirements.'],
        'laravel-development' => ['category' => 'laravel-development', 'kind' => 'laravel', 'title' => 'Laravel Development', 'lead' => 'Custom Laravel applications, APIs and dashboards delivered as managed projects.'],
        'web-development' => ['category' => 'web-development', 'kind' => 'web', 'title' => 'Web Development', 'lead' => 'Landing pages, business websites and custom web development.'],
        'seo' => ['category' => 'seo-services', 'kind' => 'seo', 'title' => 'SEO Services', 'lead' => 'Transparent, report-driven SEO. We never promise guaranteed rankings.'],
    ];

    public function show(string $page): View
    {
        $def = self::PAGES[$page] ?? abort(404);
        $category = ProductCategory::where('slug', $def['category'])->firstOrFail();
        $plans = Plan::with('prices', 'category')->where('product_category_id', $category->id)->where('is_active', true)->where('visibility', 'public')->orderBy('sort_order')->get();

        return view('pages.service-category', ['page' => $page, 'def' => $def, 'plans' => $plans, 'category' => $category]);
    }

    public function create(Plan $plan): View
    {
        abort_unless($plan->is_active && $plan->visibility === 'public' && config('service_forms.'.$plan->kind), 404);

        return view('dashboard.requests.create', ['plan' => $plan, 'form' => config('service_forms.'.$plan->kind)]);
    }

    public function store(Request $request, Plan $plan, Notifier $notifier): RedirectResponse
    {
        abort_unless($plan->is_active && $plan->visibility === 'public' && ($form = config('service_forms.'.$plan->kind)), 404);

        $rules = [];
        foreach ($form['fields'] as $f) {
            $rules['req.'.$f[0]] = explode('|', $f[3]);
        }
        if ($form['files'] ?? true) {
            $rules['files'] = ['nullable', 'array', 'max:5'];
            $rules['files.*'] = AttachmentStore::rules();
        }

        $data = $request->validate($rules);
        $requirements = collect($form['fields'])->mapWithKeys(fn ($f) => [$f[0] => $data['req'][$f[0]] ?? null])->all();

        $sr = ServiceRequest::create([
            'number' => 'TMP-'.bin2hex(random_bytes(5)), 'user_id' => $request->user()->id, 'plan_id' => $plan->id,
            'kind' => $plan->kind, 'requirements' => $requirements,
        ]);
        $sr->update(['number' => 'QR'.str_pad((string) $sr->id, 5, '0', STR_PAD_LEFT)]);
        if ($form['files'] ?? true) {
            AttachmentStore::store($sr, $request->file('files', []), $request->user());
        }

        Audit::log('request.submitted', $sr);
        $notifier->admin('quote_requested', "request:{$sr->id}", ($form['label'] ?? 'Quote request')." {$sr->number} ({$plan->name}) from {$request->user()->email}");

        return redirect()->route('dashboard.requests.show', $sr)->with('success', $form['success'] ?? 'Requirements received. Our team will review them and send a quote — nothing is charged until you accept one.');
    }
}
