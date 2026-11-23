<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\ProductCategory;
use App\Models\ResourceProfile;
use App\Services\TaxService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(Request $request): View
    {
        $this->allow($request, 'view');

        $query = Plan::with('category', 'prices', 'resourceProfile')->orderBy('product_category_id')->orderBy('sort_order');

        if ($cat = $request->query('category')) {
            $query->where('product_category_id', $cat);
        }
        if ($request->query('status') === 'purchasable') {
            $query->where('is_purchasable', true);
        } elseif ($request->query('status') === 'pending') {
            $query->where('price_status', '!=', 'approved');
        }
        if ($term = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')->orWhere('slug', 'like', '%'.addcslashes($term, '%_\\').'%'));
        }

        return view('admin.plans.index', ['plans' => $query->paginate(20)->withQueryString(), 'categories' => ProductCategory::orderBy('sort_order')->get()]);
    }

    public function create(Request $request): View
    {
        $this->allow($request, 'create');

        return $this->form(new Plan(['kind' => 'hosting', 'fulfilment' => 'provisioned', 'visibility' => 'public', 'tax_class' => 'standard', 'is_active' => true, 'price_status' => 'pending']));
    }

    public function edit(Request $request, Plan $plan): View
    {
        $this->allow($request, 'edit');

        return $this->form($plan->load('prices', 'resourceProfile', 'addons'));
    }

    private function form(Plan $plan): View
    {
        return view('admin.plans.form', [
            'plan' => $plan, 'categories' => ProductCategory::orderBy('sort_order')->get(),
            'profiles' => ResourceProfile::orderBy('name')->get(), 'addons' => Addon::orderBy('name')->get(), 'taxClasses' => TaxService::CLASSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow($request, 'create');
        $plan = new Plan;
        $this->save($request, $plan);

        return redirect()->route('admin.plans.index')->with('success', 'Plan created.');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $this->allow($request, 'edit');
        $this->save($request, $plan);

        return redirect()->route('admin.plans.index')->with('success', 'Plan saved.');
    }

    public function destroy(Request $request, Plan $plan): RedirectResponse
    {
        $this->allow($request, 'delete');

        if ($plan->orderItemsExist()) {
            // Historical orders keep their snapshots, but we never delete a sold plan.
            $plan->update(['is_active' => false, 'is_purchasable' => false]);

            return back()->with('success', 'Plan has order history, so it was deactivated instead of deleted.');
        }

        $plan->delete();
        Audit::log('admin.plan.deleted', null, ['slug' => $plan->slug]);

        return redirect()->route('admin.plans.index')->with('success', 'Plan deleted.');
    }

    private function allow(Request $request, string $action): void
    {
        abort_unless($request->user()->hasPermission('plans.'.$action), 403, 'You do not have permission to do that.');
    }

    private function save(Request $request, Plan $plan): void
    {
        $user = $request->user();
        $canApprove = $user->hasPermission('plans.approve');

        $data = $request->validate([
            'product_category_id' => ['required', 'exists:product_categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:80', Rule::unique('plans', 'slug')->ignore($plan->id)],
            'tagline' => ['nullable', 'string', 'max:200'],
            'highlight' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'features' => ['nullable', 'string', 'max:3000'],
            'not_included' => ['nullable', 'string', 'max:3000'],
            'comparison' => ['nullable', 'string', 'max:3000'],
            'resource_profile_id' => ['nullable', 'exists:resource_profiles,id'],
            'vcpu' => ['nullable', 'integer', 'min:1', 'max:512'],
            'ram_gb' => ['nullable', 'integer', 'min:1', 'max:4096'],
            'disk_gb' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'bandwidth_tb' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'setup_fee' => ['nullable', 'numeric', 'min:0'],
            'monthly' => ['nullable', 'numeric', 'min:0'],
            'yearly' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'integer', 'between:0,100'],
            'periods' => ['nullable', 'array'],
            'periods.*.price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'periods.*.discount' => ['nullable', 'integer', 'between:0,100'],
            'periods.*.setup' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'periods.*.currency' => ['nullable', 'in:INR'],
            'tax_class' => ['required', Rule::in(array_keys(TaxService::CLASSES))],
            'visibility' => ['required', 'in:public,hidden'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'kind' => ['required', 'in:hosting,wordpress,laravel,web,seo,ai_access,automation'],
            'fulfilment' => ['required', 'in:provisioned,quote'],
            'price_status' => ['required', 'in:pending,illustrative,approved'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:addons,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Approving prices is a separate permission: others cannot flip a plan to "approved".
        $priceStatus = $data['price_status'];
        if (! $canApprove && $priceStatus === 'approved' && $plan->price_status !== 'approved') {
            $priceStatus = $plan->price_status ?: 'pending';
        }

        $periods = (array) $request->input('periods', []);
        $monthly = $this->minor($periods['monthly']['price'] ?? $data['monthly'] ?? null);

        $purchasable = $request->boolean('is_purchasable');
        // Never purchasable without an approved price, a monthly price and the provisioned/quote rule.
        // Coming-soon kinds (AI All-Access) have no delivery backend yet, so they are never sellable.
        if ($priceStatus !== 'approved' || $monthly === null || $data['fulfilment'] === 'quote' || in_array($data['kind'], Plan::NOT_SELLABLE_KINDS, true)) {
            $purchasable = false;
        }

        $profileId = $data['resource_profile_id'] ?? null;
        if (! empty($data['vcpu']) || ! empty($data['ram_gb']) || ! empty($data['disk_gb'])) {
            $profile = ResourceProfile::firstOrNew(['slug' => 'plan-'.$data['slug']]);
            $profile->fill(['name' => $data['name'].' resources', 'vcpu' => $data['vcpu'] ?? null, 'ram_gb' => $data['ram_gb'] ?? null, 'disk_gb' => $data['disk_gb'] ?? null, 'bandwidth_tb' => $data['bandwidth_tb'] ?? null])
                ->forceFill(['status' => $request->boolean('resources_verified') && $canApprove ? 'verified' : 'pending'])->save();
            $profileId = $profile->id;
        }

        $plan->forceFill([
            'product_category_id' => $data['product_category_id'], 'name' => $data['name'], 'slug' => $data['slug'],
            'tagline' => $data['tagline'] ?? null, 'highlight' => $data['highlight'] ?? null, 'description' => $data['description'] ?? null,
            'features' => $this->lines($data['features'] ?? ''), 'not_included' => $this->lines($data['not_included'] ?? ''),
            'comparison' => $this->pairs($data['comparison'] ?? ''), 'resource_profile_id' => $profileId,
            'setup_fee_minor' => $this->minor($data['setup_fee'] ?? null) ?? 0, 'discount_percent' => $data['discount_percent'] ?? 0,
            'tax_class' => $data['tax_class'], 'visibility' => $data['visibility'], 'stock' => $data['stock'] ?? null,
            'kind' => $data['kind'], 'fulfilment' => $data['fulfilment'], 'price_status' => $priceStatus,
            'is_active' => $request->boolean('is_active'), 'is_featured' => $request->boolean('is_featured'),
            'is_purchasable' => $purchasable, 'sort_order' => $data['sort_order'] ?? 0,
        ])->save();

        $this->savePeriods($request, $plan, $periods, $data, $canApprove);

        $plan->addons()->sync($data['addon_ids'] ?? []);
        Audit::log($plan->wasRecentlyCreated ? 'admin.plan.created' : 'admin.plan.updated', $plan, ['price_status' => $priceStatus, 'purchasable' => $purchasable]);
    }

    /**
     * Four independent billing periods (1/3/6/12 months), each with price, discount, setup fee, availability and
     * approval. Legacy "monthly"/"yearly" fields still work (approval then inherits the plan price status).
     * Changing a period's money values without plans.approve puts that period back to "pending".
     * Existing orders and subscriptions keep their own price snapshots, so nothing here changes what a customer
     * already agreed to pay.
     */
    private function savePeriods(Request $request, Plan $plan, array $periods, array $data, bool $canApprove): void
    {
        foreach (Plan::INTERVALS as $interval) {
            $input = $periods[$interval] ?? null;
            $legacy = ! is_array($input) && in_array($interval, ['monthly', 'yearly'], true);
            if (! is_array($input) && ! $legacy) {
                continue; // period not on the form: leave it untouched
            }

            $row = $plan->prices()->firstOrNew(['billing_interval' => $interval]);
            $values = $legacy
                ? ['amount_minor' => $this->minor($data[$interval] ?? null), 'discount_percent' => $row->discount_percent, 'setup_fee_minor' => $row->setup_fee_minor]
                : ['amount_minor' => $this->minor($input['price'] ?? null),
                    'discount_percent' => ($input['discount'] ?? '') === '' ? null : (int) $input['discount'],
                    'setup_fee_minor' => $this->minor($input['setup'] ?? null)];
            $moneyChanged = ! $row->exists || $row->amount_minor !== $values['amount_minor']
                || $row->discount_percent !== $values['discount_percent'] || $row->setup_fee_minor !== $values['setup_fee_minor'];

            if ($legacy) {
                $approval = $canApprove ? null : ($moneyChanged ? 'pending' : $row->approval_status);
                $available = $values['amount_minor'] !== null;
            } else {
                $wantsApproved = ! empty($input['approved']);
                $approval = $canApprove ? ($wantsApproved ? 'approved' : 'pending') : ($moneyChanged ? 'pending' : $row->approval_status);
                $available = ! empty($input['available']) && $values['amount_minor'] !== null;
            }

            $row->fill($values + ['currency' => 'INR', 'is_active' => $available, 'approval_status' => $approval])->save();
        }
        $plan->unsetRelation('prices');
    }

    private function minor(mixed $rupees): ?int
    {
        return $rupees === null || $rupees === '' ? null : (int) round($rupees * 100);
    }

    private function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $text))));
    }

    /** "Label: value" lines → ['Label' => 'value'] for the comparison table. */
    private function pairs(string $text): array
    {
        $out = [];
        foreach ($this->lines($text) as $line) {
            [$k, $v] = array_pad(explode(':', $line, 2), 2, '');
            if (trim($k) !== '') {
                $out[trim($k)] = trim($v);
            }
        }

        return $out;
    }
}
