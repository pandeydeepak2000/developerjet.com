@extends('layouts.admin')

@section('title', ($row->exists ? 'Edit ' : 'New ').$cfg['title'])
@section('heading', ($row->exists ? 'Edit' : 'New').' — '.$cfg['title'])

@section('content')
    @php
        // 'advanced' => [field names] collapses rarely-needed fields under "More details"; 'defaults' pre-fills new rows.
        $advancedNames = $cfg['advanced'] ?? [];
        $groups = [
            'main' => array_values(array_filter($cfg['fields'], fn ($f) => ! in_array($f[0], $advancedNames, true))),
            'advanced' => array_values(array_filter($cfg['fields'], fn ($f) => in_array($f[0], $advancedNames, true))),
        ];
        $hasAdvancedError = collect($groups['advanced'])->contains(fn ($f) => $errors->has($f[0]));
    @endphp
    <form class="admin-card admin-form" method="POST" action="{{ $row->exists ? route('admin.resource.update', [$resource, $row->id]) : route('admin.resource.store', $resource) }}" data-loading-form>
        @csrf
        @if ($row->exists) @method('PUT') @endif
        @foreach ($groups as $group => $fields)
            @continue (! $fields)
            @if ($group === 'advanced')<details class="admin-advanced" @if ($hasAdvancedError) open @endif><summary>More details (optional)</summary>@endif
            <div class="admin-form-grid">
                @foreach ($fields as $field)
                    @php
                        [$name, $label, $type] = $field;
                        $choices = $field[4] ?? ($options[$name] ?? []);
                        $stored = $row->exists ? $row->{$name} : ($cfg['defaults'][$name] ?? $row->{$name});
                        $current = old($name, $type === 'money' ? ($stored !== null ? $stored / 100 : '') : ($type === 'lines' ? implode("\n", (array) $stored) : ($type === 'secret' ? '' : $stored)));
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
            @if ($group === 'advanced')</details>@endif
        @endforeach
        <div class="admin-form-actions"><button class="admin-btn" type="submit">Save</button><a class="admin-link-btn" href="{{ route('admin.resource.index', $resource) }}">Cancel</a></div>
    </form>
@endsection
