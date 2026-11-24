@props(['count' => 0, 'label' => 'Plans'])
{{--
    Wraps a product's plan cards. Up to 3 plans: the normal centred grid.
    More than 3: a horizontal carousel with previous/next arrows, a "02–05 / 10 plans" counter and
    a progress bar. Without JS it is still a scrollable, swipeable row (scroll-snap).
--}}
@if ($count > 3)
    <div class="dj-plan-carousel" data-plan-carousel>
        <div class="dj-plan-carousel__bar">
            <p class="dj-plan-carousel__count"><strong>{{ $count }} plans</strong> <span>· Prices in INR</span></p>
            <div class="dj-plan-carousel__nav">
                <button type="button" class="dj-plan-carousel__btn" data-carousel-prev aria-label="Previous plans" disabled>&larr;</button>
                <button type="button" class="dj-plan-carousel__btn" data-carousel-next aria-label="Next plans">&rarr;</button>
            </div>
        </div>
        <div class="dj-plan-grid dj-plan-grid--carousel" data-carousel-track tabindex="0" role="region" aria-label="{{ $label }}">
            {{ $slot }}
        </div>
        <div class="dj-plan-carousel__foot">
            <div class="dj-plan-carousel__progress" aria-hidden="true"><span data-carousel-progress></span></div>
            <p class="dj-plan-carousel__range" data-carousel-range aria-live="polite">01–{{ str_pad((string) min(4, $count), 2, '0', STR_PAD_LEFT) }} / {{ $count }} plans</p>
        </div>
    </div>
@else
    <div class="dj-plan-grid dj-plan-grid--{{ min(max($count, 1), 3) }}">
        {{ $slot }}
    </div>
@endif
