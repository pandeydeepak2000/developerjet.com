@extends('layouts.app')

@section('title', 'Digital services')
@section('meta_description', 'WordPress, Laravel and web development, SEO, custom AI applications, maintenance and deployment from DeveloperJet.')

@section('content')
    @php
        $meta = [
            'WordPress Development' => ['globe', 'Business websites and stores built on WordPress.', 'wordpress'],
            'Laravel Development' => ['code', 'Custom applications, APIs and dashboards.', 'laravel'],
            'Web Development' => ['browser', 'Landing pages, business websites and custom builds.', 'web-development'],
            'SEO Services' => ['search', 'Audits, task checklists and monthly reports. We never guarantee rankings.', 'seo'],
            'Custom AI Application Development' => ['cpu', 'AI-powered tools scoped around your requirements.', 'custom-ai'],
            'Website Maintenance' => ['wrench', 'Ongoing care for an existing website, on request.', 'maintenance'],
            'Website and Application Deployment' => ['rocket', 'Getting your site or application live on a server.', 'deployment'],
            'Business Automation' => ['workflow', 'Custom workflows connecting your leads, tools and reports.', 'business-automation'],
        ];
    @endphp

    <section class="dj-page-hero" aria-labelledby="ds-title">
        <div class="dj-container">
            <div class="dj-page-hero__inner">
                <p class="dj-eyebrow">Digital services</p>
                <h1 id="ds-title">Build, launch and grow with our team</h1>
                <p class="dj-lede">Project-based services delivered by the DeveloperJet team. Services are quoted from your requirements; nothing is charged until you accept a quote and receive a separate invoice.</p>
            </div>
        </div>
    </section>

    <section class="dj-section" id="services" aria-labelledby="ds-list-title">
        <div class="dj-container">
            <h2 id="ds-list-title" class="sr-only">Services</h2>
            <div class="dj-grid-3">
                @foreach ($services as [$name, $route, $page])
                    <a class="dj-linkcard" id="{{ $meta[$name][2] ?? \Illuminate\Support\Str::slug($name) }}" href="{{ $route ? route($route, $page ?? []) : route('contact') }}">
                        <span class="dj-card__icon"><x-icon :name="$meta[$name][0] ?? 'grid'" :size="22" /></span>
                        <h3>{{ $name }}</h3>
                        <p>{{ $meta[$name][1] ?? '' }} {{ $route ? 'View packages and request a quote.' : 'Available on request — contact us to discuss scope.' }}</p>
                        <span class="dj-cat__cta">{{ $route ? 'View packages' : 'Contact us' }} <x-icon name="arrow-right" :size="16" /></span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    <x-cta variant="services" primary-url="#services" />
@endsection
