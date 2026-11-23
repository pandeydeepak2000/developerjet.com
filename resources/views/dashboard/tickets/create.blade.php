@extends('layouts.dashboard')

@section('title', 'Open a ticket')
@section('heading', 'Open a ticket')
@section('subheading', 'Tell us what you need. Never include passwords or API keys in a ticket.')

@section('dash')
    <form class="panel form-grid" method="POST" action="{{ route('dashboard.tickets.store') }}" enctype="multipart/form-data" data-loading-form>
        @csrf
        <label>Department
            <select name="department_id" required>@foreach ($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id') == $d->id)>{{ $d->name }}</option>@endforeach</select>
        </label>
        <label>Priority
            <select name="priority">@foreach (['low', 'normal', 'high', 'urgent'] as $p)<option value="{{ $p }}" @selected(old('priority', 'normal') === $p)>{{ ucfirst($p) }}</option>@endforeach</select>
        </label>
        <label>Related service (optional)
            <select name="service_id"><option value="">None</option>@foreach ($services as $s)<option value="{{ $s->id }}" @selected(old('service_id', $preselect ?? null) == $s->id)>{{ $s->label }}</option>@endforeach</select>
        </label>
        <label>Subject<input name="subject" maxlength="160" required value="{{ old('subject') }}"></label>
        <label class="span-2">Message<textarea name="body" rows="7" maxlength="8000" required>{{ old('body') }}</textarea></label>
        <label class="span-2">Attachments (up to 3 · jpg, png, pdf, txt, zip · 5 MB each)<input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.txt,.zip"></label>
        <div class="span-2"><button class="button-primary" type="submit">Submit ticket</button></div>
    </form>
@endsection
