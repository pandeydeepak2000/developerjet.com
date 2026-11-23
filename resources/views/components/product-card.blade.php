@props([
    'family',   // One entry from App\Support\ProductFamilies::all()
])

{{-- Product family card: what it is, who it is for, three capabilities, an approved starting price (if any) and a link to the plans. --}}
@php
    $soon = $family['status'] === 'coming_soon';
    $quote = $family['status'] === 'quote';
@endphp
<article class="dj-family" aria-labelledby="family-{{ $family['key'] }}">
    <div class="dj-family__top">
        <span class="dj-family__icon" aria-hidden="true"><x-icon :name="$family['icon']" :size="22" /></span>
        @if ($soon)
            <span class="dj-family__status">Coming soon</span>
        @elseif ($quote)
            <span class="dj-family__status dj-family__status--quote">Quoted per project</span>
        @endif
    </div>
    <h3 id="family-{{ $family['key'] }}">{{ $family['name'] }}</h3>
    <p class="dj-family__summary">{{ $family['summary'] }}</p>
    <p class="dj-family__for"><x-icon name="user" :size="16" /> <span><span class="sr-only">Who it is for: </span>{{ $family['audience'] }}</span></p>
    <ul class="dj-family__caps {{ $soon ? 'dj-family__caps--planned' : '' }}" @if ($soon) aria-label="Planned capabilities" @endif>
        @foreach (array_slice($family['capabilities'], 0, 3) as $cap)
            <li><x-icon :name="$soon ? 'clock' : 'check'" :size="16" /> {{ $soon ? 'Planned: ' : '' }}{{ $cap }}</li>
        @endforeach
    </ul>
    <div class="dj-family__foot">
        @if ($family['from_minor'] !== null)
            <span class="dj-family__price">From <strong>{{ \App\Support\Money::inr($family['from_minor']) }}</strong> / month</span>
        @endif
        <a class="dj-family__cta" href="{{ $family['url'] }}">{{ $soon ? 'See plans & request access' : 'Explore plans' }}<span class="sr-only"> for {{ $family['name'] }}</span> <x-icon name="arrow-right" :size="16" /></a>
    </div>
</article>
