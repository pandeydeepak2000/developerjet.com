@extends('layouts.dashboard')

@section('title', $form['submit'] ?? 'Request a quote')
@section('heading', $plan->name.' — '.$form['title'])
@section('subheading', $form['intro'] ?? 'No payment is taken. We review your requirements and send a quote you can accept or decline.')

@section('dash')
    <form class="panel form-grid" method="POST" action="{{ route('services.request.store', $plan) }}" enctype="multipart/form-data" data-loading-form>
        @csrf
        @foreach ($form['fields'] as $f)
            @php [$name, $label, $type, , $options] = array_pad($f, 5, null); @endphp
            <label class="{{ $type === 'textarea' ? 'span-2' : '' }}">{{ $label }}
                @switch($type)
                    @case('textarea')<textarea name="req[{{ $name }}]" rows="3">{{ old('req.'.$name) }}</textarea>@break
                    @case('select')<select name="req[{{ $name }}]">@foreach ($options as $v => $l)<option value="{{ $v }}" @selected(old('req.'.$name) === (string) $v)>{{ $l }}</option>@endforeach</select>@break
                    @case('date')<input type="date" name="req[{{ $name }}]" value="{{ old('req.'.$name) }}" min="{{ now()->addDay()->toDateString() }}">@break
                    @default<input type="text" name="req[{{ $name }}]" value="{{ old('req.'.$name) }}">
                @endswitch
                @error('req.'.$name)<small class="field-error">{{ $message }}</small>@enderror
            </label>
        @endforeach
        @if ($form['files'] ?? true)
        <label class="span-2">Files (design references, content — up to 5 · jpg, png, pdf, txt, zip, docx, xlsx · 10 MB each)<input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.txt,.zip,.docx,.xlsx"></label>
        @endif
        <div class="span-2"><button class="button-primary" type="submit">{{ $form['submit'] ?? 'Submit requirements' }}</button></div>
    </form>
@endsection
