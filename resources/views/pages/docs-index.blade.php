@extends('layouts.app')

@section('title', $results !== null ? 'Search: '.$term.' — Documentation' : 'Documentation')
@section('meta_description', 'DeveloperJet documentation: account and sign-in, plans and checkout, billing and renewals, product guides, service access, custom domains, SSH security and support.')

@push('head')
    @vite('resources/css/docs.css')
@endpush

@section('content')
    <div class="dj-docs">
        <section class="dj-docs-hero">
            <div class="dj-container">
                @if ($results !== null)
                    <nav class="dj-doc-crumbs dj-doc-crumbs--light" aria-label="Breadcrumb"><ol><li><a class="dj-doc-crumbs__link" href="{{ route('docs.index') }}">Documentation</a></li><li aria-current="page">Search</li></ol></nav>
                @endif
                <p class="dj-docs-hero__eyebrow">Documentation</p>
                <h1>{{ $results !== null ? 'Search results' : 'How can we help?' }}</h1>
                <p class="dj-docs-hero__lede">Step-by-step guides for your account, billing, every DeveloperJet product and secure access to your service.</p>
                @include('docs._search', ['term' => $term, 'index' => $searchIndex, 'large' => true])
            </div>
        </section>

        <div class="dj-container dj-docs__layout dj-docs__layout--index">
            <aside class="dj-docs__sidebar">
                @include('docs._sidebar', ['categories' => $categories, 'current' => null])
            </aside>

            <div class="dj-docs__main">
                @if ($results !== null)
                    <h2 class="dj-docs__results-title" aria-live="polite">{{ $results->count() }} {{ \Illuminate\Support\Str::plural('result', $results->count()) }} for “{{ $term }}”</h2>
                    @forelse ($results as $r)
                        <a class="dj-doc-result" href="{{ route('docs.show', $r->slug) }}">
                            <small>{{ $r->category->name }}</small>
                            <strong>{{ $r->title }}</strong>
                            <span>{!! $r->snippet !!}</span>
                        </a>
                    @empty
                        <div class="dj-doc-empty">
                            <h3>No matches</h3>
                            <p>Try fewer or different words — for example “invoice”, “renew”, “SSH” or “domain”. You can also browse the categories, or open a support ticket and we will help.</p>
                            <a class="dj-btn dj-btn--secondary" href="{{ route('dashboard.tickets.create') }}">Contact support</a>
                        </div>
                    @endforelse
                @else
                    @if ($popular->isNotEmpty())
                        <section aria-labelledby="popular-h" class="dj-docs-popular">
                            <h2 id="popular-h">Popular guides</h2>
                            <ul>@foreach ($popular as $p)<li><a class="dj-docs-popular__link" href="{{ route('docs.show', $p->slug) }}"><x-icon name="arrow-right" :size="16" /> {{ $p->title }}</a></li>@endforeach</ul>
                        </section>
                    @endif

                    <div class="dj-docs-cats">
                        @foreach ($categories as $category)
                            @php $meta = config('docs.categories.'.$category->slug, []); @endphp
                            <section class="dj-docs-cat" id="cat-{{ $category->slug }}" aria-labelledby="cat-h-{{ $category->id }}">
                                <span class="dj-docs-cat__icon"><x-icon :name="$meta['icon'] ?? 'book'" :size="22" /></span>
                                <h2 id="cat-h-{{ $category->id }}">{{ $category->name }}</h2>
                                @if (! empty($meta['description']))<p>{{ $meta['description'] }}</p>@endif
                                <ul>@foreach ($category->articles as $a)<li><a class="dj-docs-cat__link" href="{{ route('docs.show', $a->slug) }}">{{ $a->title }}</a></li>@endforeach</ul>
                            </section>
                        @endforeach
                    </div>
                @endif

                <aside class="dj-doc-support" aria-label="Support">
                    <x-icon name="headset" :size="22" />
                    <div>
                        <strong>Can’t find what you need?</strong>
                        <p>Open a support ticket from your dashboard. Choose the right department so it reaches the correct team first time.</p>
                    </div>
                    <a class="dj-btn dj-btn--secondary" href="{{ route('dashboard.tickets.create') }}">Open a support ticket</a>
                </aside>
            </div>
        </div>
    </div>
    <x-cta variant="docs" primary-url="#docs-search" />
@endsection
