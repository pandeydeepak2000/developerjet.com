@extends('layouts.dashboard')

@section('title', 'Services')
@section('heading', 'Services')
@section('subheading', 'Your hosted services, their setup stage and their renewals.')

@section('dash')
    @if ($services->isEmpty())
        <x-empty-state title="No active services" text="Services appear here after an order is paid and the VPS has been set up by our team." action="Browse plans" :action-url="route('plans')" />
    @else
        <div class="data-table-wrap"><table class="data-table">
            <thead><tr><th scope="col">Service</th><th scope="col">Product / plan</th><th scope="col">Billing</th><th scope="col">Next renewal</th><th scope="col">Setup</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @foreach ($services as $service)
                @php $sub = $service->subscription; $open = $sub?->invoices->first(fn ($i) => $i->isUnpaid()); @endphp
                <tr>
                    <td><a href="{{ route('dashboard.services.show', $service) }}">{{ $service->label }}</a><br><small class="muted">Service #{{ $service->id }}</small></td>
                    <td>{{ $registry->recipe($service->kind)['label'] ?? $service->kind }}<br><small class="muted">{{ $service->plan?->name ?? 'Plan to be confirmed' }}</small></td>
                    <td>{{ $sub?->cycleLabel() ?? '—' }}</td>
                    <td>@if ($sub && in_array($sub->status, ['cancelled', 'expired'], true))—@else{{ $service->renews_at?->format('d M Y') ?? '—' }}@endif
                        @if ($open && $open->order_id)<br><small class="muted"><a href="{{ route('dashboard.orders.show', $open->order_id) }}">Invoice {{ $open->number }} due</a></small>@endif</td>
                    <td>{{ \App\Support\DeploymentProgress::stageLabel($service) }}</td>
                    <td><x-status-badge :status="$service->status" /></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $services->links() }}
    @endif
@endsection
