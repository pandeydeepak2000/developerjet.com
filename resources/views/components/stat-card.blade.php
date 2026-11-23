@props(['label', 'value', 'href' => null, 'note' => null])

@if ($href)
    <a href="{{ $href }}" class="stat-card">
        <span>{{ $label }}</span>
        <strong>{{ $value }}</strong>
        @if ($note)<small>{{ $note }}</small>@endif
    </a>
@else
    <div class="stat-card">
        <span>{{ $label }}</span>
        <strong>{{ $value }}</strong>
        @if ($note)<small>{{ $note }}</small>@endif
    </div>
@endif
