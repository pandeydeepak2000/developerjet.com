@props([
    'eyebrow',
    'title',
    'description',
    'items',
    'ctaTitle',
    'ctaDescription',
    'ctaLabel',
    'ctaUrl',
])

<section class="dj-section dj-section--surface page-details-section">
    <div class="dj-container">
        <div class="dj-section-head">
            <p class="dj-eyebrow">{{ $eyebrow }}</p>
            <h2>{{ $title }}</h2>
            <p class="dj-section-head__text">{{ $description }}</p>
        </div>
        <div class="dj-grid-3">
            @foreach ($items as $index => $item)
                <article class="dj-card">
                    <span class="dj-card__label">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }} · {{ $item['label'] }}</span>
                    <h3>{{ $item['title'] }}</h3>
                    <p class="dj-muted">{{ $item['description'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<x-cta class="page-next-step" variant="general" :title="$ctaTitle" :text="$ctaDescription" :primary-url="$ctaUrl" :primary-label="$ctaLabel" />
