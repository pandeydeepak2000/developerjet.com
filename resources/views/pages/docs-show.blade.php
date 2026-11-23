@extends('layouts.app')

@section('title', $article->title.' — Documentation')
@section('meta_description', $article->meta_description ?? \Illuminate\Support\Str::limit($article->excerpt ?? strip_tags($html), 155))

@push('head')
    @vite('resources/css/docs.css')
@endpush

@section('content')
    <div class="dj-docs">
        <div class="dj-docs__bar">
            <div class="dj-container dj-docs__bar-inner">
                <a class="dj-docs__brand" href="{{ route('docs.index') }}"><x-icon name="book" :size="18" /> Documentation</a>
                @include('docs._search', ['term' => '', 'index' => $searchIndex])
            </div>
        </div>

        <div class="dj-container dj-docs__layout">
            <aside class="dj-docs__sidebar">
                @include('docs._sidebar', ['categories' => $categories, 'current' => $article])
            </aside>

            <article class="dj-docs__article" aria-labelledby="doc-title">
                <nav class="dj-doc-crumbs" aria-label="Breadcrumb">
                    <ol>
                        <li><a class="dj-doc-crumbs__link" href="{{ route('docs.index') }}">Documentation</a></li>
                        <li><a class="dj-doc-crumbs__link" href="{{ route('docs.index') }}#cat-{{ $article->category->slug }}">{{ $article->category->name }}</a></li>
                        <li aria-current="page">{{ $article->title }}</li>
                    </ol>
                </nav>

                <header class="dj-doc-head">
                    <h1 id="doc-title">{{ $article->title }}</h1>
                    @if ($article->excerpt)<p class="dj-doc-head__lede">{{ $article->excerpt }}</p>@endif
                    <p class="dj-doc-head__meta"><x-icon name="clock" :size="15" /> {{ $minutes }} min read · Updated {{ $article->updated_at?->format('j M Y') }}</p>
                </header>

                @if (count($toc) > 1)
                    <details class="dj-doc-toc-mobile">
                        <summary>On this page</summary>
                        <ul>@foreach ($toc as $h)<li class="lvl-{{ $h['level'] }}"><a class="dj-toc-link" href="#{{ $h['id'] }}">{{ $h['text'] }}</a></li>@endforeach</ul>
                    </details>
                @endif

                <div class="dj-doc-body">{!! $html !!}</div>

                @if ($related->isNotEmpty())
                    <section class="dj-doc-related" aria-labelledby="related-h">
                        <h2 id="related-h">Related articles</h2>
                        <ul>
                            @foreach ($related as $r)
                                <li><a class="dj-doc-related__link" href="{{ route('docs.show', $r->slug) }}">{{ $r->title }}</a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <nav class="dj-doc-pager" aria-label="Previous and next article">
                    @if ($previous)
                        <a class="dj-doc-pager__link" href="{{ route('docs.show', $previous->slug) }}" rel="prev"><small>← Previous</small><span>{{ $previous->title }}</span></a>
                    @else <span></span> @endif
                    @if ($next)
                        <a class="dj-doc-pager__link dj-doc-pager__link--next" href="{{ route('docs.show', $next->slug) }}" rel="next"><small>Next →</small><span>{{ $next->title }}</span></a>
                    @endif
                </nav>

                <aside class="dj-doc-support" aria-label="Support">
                    <x-icon name="headset" :size="22" />
                    <div>
                        <strong>Still need help?</strong>
                        <p>Open a support ticket from your dashboard and our team will reply there. Never include passwords or API keys in a ticket.</p>
                    </div>
                    <a class="dj-btn dj-btn--secondary" href="{{ route('dashboard.tickets.create') }}">Open a support ticket</a>
                </aside>
            </article>

            <aside class="dj-docs__toc" aria-label="On this page">
                @if (count($toc) > 1)
                    <p class="dj-docs__toc-title">On this page</p>
                    <ul data-doc-toc>@foreach ($toc as $h)<li class="lvl-{{ $h['level'] }}"><a class="dj-toc-link" href="#{{ $h['id'] }}">{{ $h['text'] }}</a></li>@endforeach</ul>
                @endif
                <a class="dj-docs__toc-help" href="{{ route('dashboard.tickets.create') }}"><x-icon name="chat" :size="16" /> Ask our team</a>
            </aside>
        </div>
    </div>
    <x-cta variant="docs" />
@endsection
