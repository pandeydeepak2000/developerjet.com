@extends('layouts.admin')

@section('title', $plan->exists ? 'Edit '.$plan->name : 'New plan')
@section('heading', $plan->exists ? 'Edit plan — '.$plan->name : 'New plan')

@section('content')
    @php
        $rp = $plan->resourceProfile;
        $v = fn ($k, $d = null) => old($k, $d);
        $comparison = collect($plan->comparison ?? [])->map(fn ($val, $k) => "$k: $val")->implode("\n");
        $canApprove = auth()->user()->hasPermission('plans.approve');
    @endphp
    <form class="admin-card admin-form" method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" data-loading-form>
        @csrf @if ($plan->exists) @method('PUT') @endif

        <h2>Basics</h2>
        <div class="admin-form-grid">
            <label>Product title<input name="name" value="{{ $v('name', $plan->name) }}" required maxlength="120"></label>
            <label>Slug<input name="slug" value="{{ $v('slug', $plan->slug) }}" required maxlength="80"></label>
            <label>Category<select name="product_category_id">@foreach ($categories as $c)<option value="{{ $c->id }}" @selected($v('product_category_id', $plan->product_category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></label>
            <label>Service type<select name="kind">@foreach (['hosting' => 'AI hosting (VPS)', 'wordpress' => 'WordPress', 'laravel' => 'Laravel development', 'web' => 'Web development', 'seo' => 'SEO', 'automation' => 'Business Automation (custom scope, quote-based)', 'ai_access' => 'AI All-Access (BYOK workspace · coming soon, never sellable)'] as $k => $l)<option value="{{ $k }}" @selected($v('kind', $plan->kind) === $k)>{{ $l }}</option>@endforeach</select></label>
            <label>Tagline<input name="tagline" value="{{ $v('tagline', $plan->tagline) }}" maxlength="200"></label>
            <label>Highlight<input name="highlight" value="{{ $v('highlight', $plan->highlight) }}" maxlength="200"></label>
            <label class="span-2">Description<textarea name="description" rows="3">{{ $v('description', $plan->description) }}</textarea></label>
            <label>Features (one per line)<textarea name="features" rows="5">{{ $v('features', implode("\n", $plan->features ?? [])) }}</textarea></label>
            <label>Not included (one per line)<textarea name="not_included" rows="5">{{ $v('not_included', implode("\n", $plan->not_included ?? [])) }}</textarea></label>
            <label class="span-2">Plan comparison details (<code>Label: value</code> per line)<textarea name="comparison" rows="4">{{ $v('comparison', $comparison) }}</textarea></label>
        </div>

        <h2>Resources</h2>
        <p class="muted small">Pick a shared profile, or enter values to create a profile dedicated to this plan. Resources stay “pending” until verified by someone with approval permission.</p>
        <div class="admin-form-grid">
            <label>Existing resource profile<select name="resource_profile_id"><option value="">None / pending</option>@foreach ($profiles as $p)<option value="{{ $p->id }}" @selected($v('resource_profile_id', $plan->resource_profile_id) == $p->id)>{{ $p->name }} ({{ $p->status }})</option>@endforeach</select></label>
            <span></span>
            <label>vCPU<input type="number" name="vcpu" value="{{ $v('vcpu') }}" min="1" placeholder="{{ $rp?->vcpu }}"></label>
            <label>RAM (GB)<input type="number" name="ram_gb" value="{{ $v('ram_gb') }}" min="1" placeholder="{{ $rp?->ram_gb }}"></label>
            <label>Storage (GB)<input type="number" name="disk_gb" value="{{ $v('disk_gb') }}" min="1" placeholder="{{ $rp?->disk_gb }}"></label>
            <label>Bandwidth (TB)<input type="number" name="bandwidth_tb" value="{{ $v('bandwidth_tb') }}" min="0" placeholder="{{ $rp?->bandwidth_tb }}"></label>
            @if ($canApprove)<label class="admin-check"><input type="checkbox" name="resources_verified" value="1"> Resources verified against real inventory</label>@endif
        </div>

        <h2>Pricing</h2>
        <p class="muted small">Each billing period is priced independently. A period can be sold only when it is <strong>available</strong>, has a price, and is <strong>approved</strong> (and the plan price status below is Approved). Leave a price blank to not offer that period. Existing orders and subscriptions keep the price the customer agreed to.</p>
        <div class="admin-table-wrap">
            <table class="admin-table admin-period-table">
                <thead><tr><th scope="col">Period</th><th scope="col">Price (₹)</th><th scope="col">Discount %</th><th scope="col">Setup fee (₹)</th><th scope="col">Currency</th><th scope="col">Available</th><th scope="col">Approved</th></tr></thead>
                <tbody>
                    @foreach (\App\Support\BillingCycle::LABELS as $interval => $label)
                        @php
                            $row = $plan->exists ? $plan->prices->firstWhere('billing_interval', $interval) : null;
                            $p = fn ($k, $d) => old("periods.$interval.$k", $d);
                            $approvedNow = $row && $row->amount_minor !== null && $row->isApproved() && $plan->isApproved();
                        @endphp
                        <tr>
                            <th scope="row" data-label="Period">{{ $label }}</th>
                            <td data-label="Price (₹)"><input type="number" step="0.01" min="0" name="periods[{{ $interval }}][price]" aria-label="{{ $label }} price" value="{{ $p('price', $row?->amount_minor !== null ? $row->amount_minor / 100 : '') }}"></td>
                            <td data-label="Discount %"><input type="number" min="0" max="100" name="periods[{{ $interval }}][discount]" aria-label="{{ $label }} discount percent" placeholder="plan" value="{{ $p('discount', $row?->discount_percent) }}"></td>
                            <td data-label="Setup fee (₹)"><input type="number" step="0.01" min="0" name="periods[{{ $interval }}][setup]" aria-label="{{ $label }} setup fee" placeholder="plan" value="{{ $p('setup', $row?->setup_fee_minor !== null ? $row->setup_fee_minor / 100 : '') }}"></td>
                            <td data-label="Currency">INR<input type="hidden" name="periods[{{ $interval }}][currency]" value="INR"></td>
                            <td data-label="Available"><input type="checkbox" name="periods[{{ $interval }}][available]" value="1" aria-label="{{ $label }} available" @checked($p('available', $row ? $row->is_active : $interval === 'monthly'))></td>
                            <td data-label="Approved">
                                @if ($canApprove)
                                    <input type="checkbox" name="periods[{{ $interval }}][approved]" value="1" aria-label="{{ $label }} approved" @checked($p('approved', $row && $row->approval_status !== 'pending' && $row->amount_minor !== null))>
                                @else
                                    <span class="muted small">{{ $row?->approval_status === 'pending' ? 'Pending' : ($approvedNow ? 'Approved' : '—') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="admin-form-grid">
            <label>Default setup fee (₹)<input type="number" step="0.01" min="0" name="setup_fee" value="{{ $v('setup_fee', $plan->setup_fee_minor ? $plan->setup_fee_minor / 100 : '') }}"></label>
            <label>Default discount (%)<input type="number" min="0" max="100" name="discount_percent" value="{{ $v('discount_percent', $plan->discount_percent ?? 0) }}"></label>
            <label>Tax class<select name="tax_class">@foreach ($taxClasses as $k => $l)<option value="{{ $k }}" @selected($v('tax_class', $plan->tax_class) === $k)>{{ $l }}</option>@endforeach</select></label>
            <label>Price status
                <select name="price_status">@foreach (['pending' => 'Pending', 'illustrative' => 'Illustrative (display only)', 'approved' => 'Approved (chargeable)'] as $k => $l)<option value="{{ $k }}" @disabled($k === 'approved' && ! $canApprove && $plan->price_status !== 'approved') @selected($v('price_status', $plan->price_status) === $k)>{{ $l }}</option>@endforeach</select>
            </label>
        </div>

        <h2>Availability</h2>
        <div class="admin-form-grid">
            <label>Fulfilment<select name="fulfilment"><option value="provisioned" @selected($v('fulfilment', $plan->fulfilment) === 'provisioned')>Checkout (provisioned/fixed scope)</option><option value="quote" @selected($v('fulfilment', $plan->fulfilment) === 'quote')>Quote required</option></select></label>
            <label>Visibility<select name="visibility"><option value="public" @selected($v('visibility', $plan->visibility) === 'public')>Public</option><option value="hidden" @selected($v('visibility', $plan->visibility) === 'hidden')>Hidden</option></select></label>
            <label>Stock / resource availability (blank = unlimited)<input type="number" min="0" name="stock" value="{{ $v('stock', $plan->stock) }}"></label>
            <label>Sort order<input type="number" min="0" name="sort_order" value="{{ $v('sort_order', $plan->sort_order ?? 0) }}"></label>
            <label class="admin-check"><input type="checkbox" name="is_active" value="1" @checked($v('is_active', $plan->is_active))> Active</label>
            <label class="admin-check"><input type="checkbox" name="is_featured" value="1" @checked($v('is_featured', $plan->is_featured))> Featured</label>
            <label class="admin-check span-2"><input type="checkbox" name="is_purchasable" value="1" @checked($v('is_purchasable', $plan->is_purchasable))> Purchasable online <small>(requires an approved price and a monthly price; quote-based plans are never purchasable)</small></label>
            <fieldset class="span-2"><legend>Available add-ons</legend>
                @foreach ($addons as $a)<label class="admin-check"><input type="checkbox" name="addon_ids[]" value="{{ $a->id }}" @checked(in_array($a->id, old('addon_ids', $plan->exists ? $plan->addons->pluck('id')->all() : [])))> {{ $a->name }} <small>({{ $a->price_minor === null ? 'price pending' : \App\Support\Money::inr($a->price_minor) }})</small></label>@endforeach
            </fieldset>
        </div>
        <div class="admin-form-actions"><button class="admin-btn" type="submit">Save plan</button><a class="admin-link-btn" href="{{ route('admin.plans.index') }}">Cancel</a></div>
    </form>
@endsection
