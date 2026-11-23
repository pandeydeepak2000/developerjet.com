@extends('layouts.admin')

@section('title', ($row->exists ? 'Edit ' : 'New ').$cfg['title'])
@section('heading', ($row->exists ? 'Edit' : 'New').' — '.$cfg['title'])

@section('content')
    <form class="admin-card admin-form" method="POST" action="{{ $row->exists ? route('admin.resource.update', [$resource, $row->id]) : route('admin.resource.store', $resource) }}" data-loading-form>
        @csrf
        @if ($row->exists) @method('PUT') @endif
        <div class="admin-form-grid">
            @foreach ($cfg['fields'] as $field)
                @php
                    [$name, $label, $type] = $field;
                    $choices = $field[4] ?? ($options[$name] ?? []);
                    $current = old($name, $type === 'money' ? ($row->{$name} !== null ? $row->{$name} / 100 : '') : ($type === 'lines' ? implode("\n", (array) $row->{$name}) : ($type === 'secret' ? '' : $row->{$name})));
                @endphp
                <label class="{{ in_array($type, ['textarea', 'lines']) ? 'span-2' : '' }}">
                    {{ $label }}
                    @switch($type)
                        @case('textarea') @case('lines')
                            <textarea name="{{ $name }}" rows="{{ $name === 'body' ? 16 : 4 }}">{{ $current }}</textarea> @break
                        @case('checkbox')
                            <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $row->exists ? $row->{$name} : true))> @break
                        @case('select')
                            <select name="{{ $name }}">@foreach ($choices as $value => $text)<option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $text }}</option>@endforeach</select> @break
                        @case('secret')
                            <input type="password" name="{{ $name }}" autocomplete="new-password" placeholder="{{ $row->exists && $row->getRawOriginal($name) ? '•••• stored — leave blank to keep' : 'Not set' }}"> @break
                        @case('money') <input type="number" step="0.01" min="0" name="{{ $name }}" value="{{ $current }}"> @break
                        @case('number') <input type="number" name="{{ $name }}" value="{{ $current }}"> @break
                        @default <input type="text" name="{{ $name }}" value="{{ $current }}">
                    @endswitch
                </label>
            @endforeach
        </div>
        <div class="admin-form-actions"><button class="admin-btn" type="submit">Save</button><a class="admin-link-btn" href="{{ route('admin.resource.index', $resource) }}">Cancel</a></div>
    </form>
@endsection
