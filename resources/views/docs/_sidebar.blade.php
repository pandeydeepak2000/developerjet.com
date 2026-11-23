{{-- Collapsible category navigation. Uses <details> so it works without JavaScript. --}}
@php $current = $current ?? null; @endphp
<nav class="dj-doc-nav" aria-label="Documentation">
    <details class="dj-doc-nav__mobile" data-doc-nav open>
        <summary class="dj-doc-nav__toggle"><x-icon name="menu" :size="18" /> Browse documentation</summary>
        <div class="dj-doc-nav__inner">
            <a class="dj-doc-nav__home {{ $current ? '' : 'is-current' }}" href="{{ route('docs.index') }}" @unless($current) aria-current="page" @endunless><x-icon name="book" :size="16" /> Documentation home</a>
            @foreach ($categories as $category)
                @php $open = $current && $current->doc_category_id === $category->id; @endphp
                <details class="dj-doc-nav__group" @if ($open) open @endif>
                    <summary>{{ $category->name }} <span class="dj-doc-nav__count">{{ $category->articles->count() }}</span><x-icon name="chevron-down" :size="16" class="dj-doc-nav__chev" /></summary>
                    <ul>
                        @foreach ($category->articles as $a)
                            @php $isCurrent = $current && $current->id === $a->id; @endphp
                            <li><a href="{{ route('docs.show', $a->slug) }}" @class(['dj-doc-nav__link', 'is-current' => $isCurrent]) @if ($isCurrent) aria-current="page" @endif>{{ $a->title }}</a></li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
        </div>
    </details>
</nav>
