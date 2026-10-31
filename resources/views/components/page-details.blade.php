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

<section class="page-details-section section-shell">
    <div class="page-details-heading">
        <span class="eyebrow">{{ $eyebrow }}</span>
        <h2>{{ $title }}</h2>
        <p>{{ $description }}</p>
    </div>
    <div class="page-details-grid">
        @foreach ($items as $index => $item)
            <article class="page-details-card">
                <span class="page-details-index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                <span class="page-details-label">{{ $item['label'] }}</span>
                <h3>{{ $item['title'] }}</h3>
                <p>{{ $item['description'] }}</p>
            </article>
        @endforeach
    </div>
</section>

<section class="page-next-step section-shell">
    <div class="page-next-icon" aria-hidden="true">✳</div>
    <div>
        <span class="eyebrow">{{ $ctaTitle }}</span>
        <p>{{ $ctaDescription }}</p>
    </div>
    <a class="button-primary" href="{{ $ctaUrl }}">{{ $ctaLabel }} <span aria-hidden="true">↗</span></a>
</section>
