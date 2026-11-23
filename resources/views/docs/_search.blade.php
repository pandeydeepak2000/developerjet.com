{{-- Documentation search. Works as a normal GET form; app.js adds instant results from the embedded index. --}}
@php $large = $large ?? false; @endphp
<form id="docs-search" method="GET" action="{{ route('docs.index') }}" role="search" class="dj-doc-search {{ $large ? 'dj-doc-search--lg' : '' }}" data-doc-search>
    <label class="sr-only" for="doc-search-{{ $large ? 'lg' : 'sm' }}">Search documentation</label>
    <x-icon name="search" :size="18" class="dj-doc-search__icon" />
    <input id="doc-search-{{ $large ? 'lg' : 'sm' }}" type="search" name="q" value="{{ $term }}" placeholder="Search the documentation…" autocomplete="off" maxlength="100"
           aria-autocomplete="list" aria-controls="doc-search-{{ $large ? 'lg' : 'sm' }}-list" aria-expanded="false">
    <button class="dj-btn dj-btn--primary" type="submit">Search</button>
    <ul class="dj-doc-search__list" id="doc-search-{{ $large ? 'lg' : 'sm' }}-list" role="listbox" hidden></ul>
    <script type="application/json" data-doc-index>@json($index)</script>
</form>
