@props(['title', 'text', 'action' => null, 'actionUrl' => null, 'icon' => 'rocket'])

<div class="empty-state">
    <span class="empty-state__icon" aria-hidden="true"><x-icon :name="$icon" :size="26" /></span>
    <h2>{{ $title }}</h2>
    <p>{{ $text }}</p>
    @if ($action && $actionUrl)
        <a class="button-primary" href="{{ $actionUrl }}">{{ $action }} <x-icon name="arrow-right" :size="16" /></a>
    @endif
</div>
