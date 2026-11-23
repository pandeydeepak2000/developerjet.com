@extends('layouts.admin')

@section('title', 'Products & plans')
@section('heading', 'Products & plans')
@section('actions')@can('plans.create')<a class="admin-btn" href="{{ route('admin.plans.create') }}">+ New plan</a>@endcan @endsection

@section('content')
    <form class="admin-filters" method="GET" action="{{ route('admin.plans.index') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search plans…" aria-label="Search plans">
        <select name="category" aria-label="Category"><option value="">Category: all</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select>
        <select name="status" aria-label="Status"><option value="">Status: all</option><option value="purchasable" @selected(request('status') === 'purchasable')>Purchasable</option><option value="pending" @selected(request('status') === 'pending')>Price not approved</option></select>
        <button class="admin-btn admin-btn-quiet" type="submit">Filter</button>
    </form>
    <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Plan</th><th>Category</th><th>Resources</th><th>Monthly</th><th>Yearly</th><th>Price status</th><th>Purchasable</th><th><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        @foreach ($plans as $plan)
            <tr>
                <td data-label="Plan">{{ $plan->name }} <small>{{ $plan->slug }}</small></td>
                <td data-label="Category">{{ $plan->category->name }}</td>
                <td data-label="Resources">{{ $plan->resourceProfile?->summary() ?? 'Pending' }}</td>
                <td data-label="Monthly">{{ \App\Support\Money::inr($plan->priceFor('monthly')) }}</td>
                <td data-label="Yearly">{{ \App\Support\Money::inr($plan->priceFor('yearly')) }}</td>
                <td data-label="Price status"><x-status-badge :status="$plan->price_status" /></td>
                <td data-label="Purchasable">{{ $plan->is_purchasable ? 'Yes' : 'No' }}</td>
                <td class="admin-row-actions">
                    @can('plans.edit')<a href="{{ route('admin.plans.edit', $plan) }}">Edit</a>@endcan
                    @can('plans.delete')<form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" onsubmit="return confirm('Delete or deactivate this plan?')">@csrf @method('DELETE')<button class="admin-link-danger" type="submit">Delete</button></form>@endcan
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    {{ $plans->links() }}
@endsection
