@props(['title', 'text', 'action' => null, 'actionUrl' => null])

<div class="empty-state">
    <h2>{{ $title }}</h2>
    <p>{{ $text }}</p>
    @if ($action && $actionUrl)
        <a class="button-primary" href="{{ $actionUrl }}">{{ $action }} <span aria-hidden="true">↗</span></a>
    @endif
</div>
