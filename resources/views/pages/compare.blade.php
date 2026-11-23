@extends('layouts.app')

@section('title', $c['title'])
@section('meta_description', $c['intro'])

@section('content')
    <section class="dj-page-hero" aria-labelledby="cmp-title">
        <div class="dj-container">
            <div class="dj-page-hero__inner">
                <p class="dj-eyebrow"><a class="dj-link" href="{{ route('compare.index') }}">Compare products</a></p>
                <h1 id="cmp-title">{{ $c['title'] }}</h1>
                <p class="dj-lede">{{ $c['intro'] }}</p>
            </div>
        </div>
    </section>

    <section class="dj-section" aria-labelledby="cmp-choose-title">
        <div class="dj-container">
            <div class="dj-table-wrap" role="region" aria-label="{{ $c['title'] }}" tabindex="0">
                <table class="dj-table dj-table--two">
                    <caption class="sr-only">{{ $c['title'] }}</caption>
                    <thead><tr><th scope="col"><span class="sr-only">Aspect</span></th><th scope="col">{{ $c['left'] }}</th><th scope="col">{{ $c['right'] }}</th></tr></thead>
                    <tbody>
                        @foreach ($c['rows'] as [$aspect, $l, $r])
                            <tr><th scope="row">{{ $aspect }}</th><td>{{ $l }}</td><td>{{ $r }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="dj-choose">
                <div>
                    <p class="dj-eyebrow">Decision guide</p>
                    <h2 id="cmp-choose-title">How to choose</h2>
                </div>
                <ul class="dj-checks">
                    @foreach ($c['choose'] as $i)<li><x-icon name="check" :size="18" /> {{ $i }}</li>@endforeach
                </ul>
            </div>

            <div class="dj-actions dj-actions--top">
                <a class="dj-btn dj-btn--primary" href="{{ route('plans') }}">View plans</a>
                <a class="dj-btn dj-btn--secondary" href="{{ route('compare.index') }}">All comparisons</a>
            </div>
        </div>
    </section>
    <x-cta variant="hosting" />
@endsection
