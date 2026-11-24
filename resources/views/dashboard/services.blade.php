@extends('layouts.dashboard')

@section('title', 'Services')
@section('heading', 'Services')
@section('subheading', 'Your hosted services, their setup stage and their renewals.')

@section('dash-actions')
    <a class="button-primary dash-btn" href="{{ route('plans') }}"><x-icon name="spark" :size="18" /> New service</a>
@endsection

@section('dash')
    @if ($services->isEmpty())
        <x-empty-state icon="server" title="No active services" text="Services appear here after an order is paid and the VPS has been set up by our team." action="Browse plans" :action-url="route('plans')" />
    @else
        <div class="dash-service-list">
            @foreach ($services as $service)
                @php
                    $sub = $service->subscription;
                    $open = $sub?->invoices->first(fn ($i) => $i->isUnpaid());
                    $ended = $sub && in_array($sub->status, ['cancelled', 'expired'], true);
                    $profile = $service->plan?->resourceProfile;
                @endphp
                <article class="dash-service-card">
                    <div class="dash-service-card__top">
                        <span class="dash-service__icon"><x-icon name="server" :size="20" /></span>
                        <div class="dash-service-card__title">
                            <a href="{{ route('dashboard.services.show', $service) }}">{{ $service->label }}</a>
                            <small>{{ $registry->recipe($service->kind)['label'] ?? $service->kind }} · {{ $service->plan?->name ?? 'Plan to be confirmed' }} · Service #{{ $service->id }}</small>
                        </div>
                        <x-status-badge :status="$service->status" />
                    </div>
                    <dl class="dash-service-card__meta">
                        <div><dt>Setup</dt><dd>{{ \App\Support\DeploymentProgress::stageLabel($service) }}</dd></div>
                        <div><dt>Billing</dt><dd>{{ $sub?->cycleLabel() ?? '—' }}</dd></div>
                        <div><dt>Next renewal</dt><dd>{{ $ended ? '—' : ($service->renews_at?->format('d M Y') ?? '—') }}</dd></div>
                        <div><dt>Size</dt><dd>{{ $profile?->vcpu ? $profile->vcpu.' vCPU · '.$profile->ram_gb.' GB' : '—' }}</dd></div>
                    </dl>
                    <div class="dash-service-card__actions">
                        @if ($open && $open->order_id)<a class="dash-chip-link is-warn" href="{{ route('dashboard.orders.show', $open->order_id) }}">Invoice {{ $open->number }} due · Pay now</a>@endif
                        <a class="dash-chip-link" href="{{ route('dashboard.services.show', $service) }}">Manage <x-icon name="arrow-right" :size="16" /></a>
                    </div>
                </article>
            @endforeach
        </div>
        {{ $services->links() }}
    @endif
@endsection
