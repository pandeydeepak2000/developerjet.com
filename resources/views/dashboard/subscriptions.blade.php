@extends('layouts.dashboard')

@section('title', 'Subscriptions & renewals')
@section('heading', 'Subscriptions & renewals')
@section('subheading', 'Billing period, paid-through date and next renewal for each service.')

@section('dash')
    <p class="muted">Renewal invoices are issued before the due date and emailed to you. You pay them yourself — saved payment details are never charged automatically.</p>
    @if ($subscriptions->isEmpty())
        <x-empty-state title="No subscriptions yet" text="Recurring hosting services appear here after your first order is paid." action="Browse plans" :action-url="route('plans')" />
    @else
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th scope="col">Service</th><th scope="col">Billing</th><th scope="col">Price</th><th scope="col">Paid through</th><th scope="col">Next due</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            @foreach ($subscriptions as $sub)
                @php $open = $sub->invoices->first(fn ($i) => $i->isUnpaid()); @endphp
                <tr>
                    <td><a href="{{ route('dashboard.subscriptions.show', $sub) }}">{{ $sub->service?->label ?? $sub->plan?->name ?? 'Subscription #'.$sub->id }}</a></td>
                    <td>{{ $sub->cycleLabel() }}</td>
                    <td>{{ $sub->price_minor !== null ? \App\Support\Money::inr($sub->price_minor) : '—' }}<small class="muted"> + tax where applicable</small></td>
                    <td>{{ $sub->period_end?->format('d M Y') ?? '—' }}</td>
                    <td>{{ in_array($sub->status, ['cancelled', 'expired'], true) ? '—' : ($sub->next_due_at?->format('d M Y') ?? '—') }}</td>
                    <td>
                        <x-status-badge :status="$sub->status" />
                        @if ($sub->status === 'grace_period' && $sub->grace_ends_at)<br><small class="muted">Grace until {{ $sub->grace_ends_at->format('d M Y') }}</small>@endif
                        @if ($sub->status === 'reactivation_pending')<br><small class="muted">Payment received — our team is reactivating your service</small>@endif
                    </td>
                    <td class="table-actions">
                        @if ($open && $open->order_id)
                            <a class="button-primary button-sm" href="{{ route('dashboard.orders.show', $open->order_id) }}">Renew now</a>
                            <a href="{{ route('dashboard.invoices.download', $open) }}">Invoice {{ $open->number }}</a>
                        @else
                            <a href="{{ route('dashboard.subscriptions.show', $sub) }}">Details</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $subscriptions->links() }}
    @endif

    @if ($ended->isNotEmpty())
        <section class="panel ended-list" aria-labelledby="ended-heading">
            <h2 id="ended-heading">Cancelled and expired services</h2>
            <p class="muted">Ended subscriptions stay here for your records. Invoices remain downloadable.</p>
            <div class="data-table-wrap"><table class="data-table">
                <thead><tr><th scope="col">Service</th><th scope="col">Status</th><th scope="col">Ended</th><th scope="col"><span class="sr-only">Details</span></th></tr></thead>
                <tbody>@foreach ($ended as $e)
                    <tr><td>{{ $e->service?->label ?? $e->plan?->name ?? 'Subscription #'.$e->id }}</td><td><x-status-badge :status="$e->status" /></td>
                        <td>{{ ($e->cancelled_at ?? $e->expired_at ?? $e->updated_at)?->format('d M Y') }}</td><td><a href="{{ route('dashboard.subscriptions.show', $e) }}">Details</a></td></tr>
                @endforeach</tbody>
            </table></div>
        </section>
    @endif
@endsection
