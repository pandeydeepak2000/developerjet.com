@extends('layouts.admin')

@section('title', $cfg['title'])
@section('heading', $cfg['title'])

@section('actions')
    @if (in_array('create', $cfg['actions'] ?? []) && auth()->user()->hasPermission($cfg['module'].'.'.($cfg['perms']['create'] ?? 'create')))
        <a class="admin-btn" href="{{ route('admin.resource.create', $resource) }}">+ New</a>
    @endif
@endsection

@section('content')
    <form class="admin-filters" method="GET" action="{{ route('admin.resource.index', $resource) }}">
        @if (! empty($cfg['search']))
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search…" aria-label="Search">
        @endif
        @foreach ($cfg['filters'] ?? [] as [$col, $label, $options])
            <select name="{{ $col }}" aria-label="{{ $label }}"><option value="">{{ $label }}: all</option>
                @foreach ($options as $value => $text)<option value="{{ $value }}" @selected((string) request($col) === (string) $value)>{{ $text }}</option>@endforeach
            </select>
        @endforeach
        <button class="admin-btn admin-btn-quiet" type="submit">Filter</button>
        @if (request()->query())<a class="admin-link-btn" href="{{ route('admin.resource.index', $resource) }}">Reset</a>@endif
    </form>

    @if ($rows->isEmpty())
        <div class="admin-empty"><h2>Nothing here yet</h2><p>{{ request()->query() ? 'No records match your filters.' : 'Records will appear here as they are created.' }}</p></div>
    @else
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr>@foreach ($cfg['columns'] as $col)<th scope="col">{{ $col[1] }}</th>@endforeach<th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($cfg['columns'] as $col)
                            @php $val = data_get($row, $col[0]); $type = $col[2] ?? 'text'; @endphp
                            <td data-label="{{ $col[1] }}">
                                @switch($type)
                                    @case('bool') {{ $val ? 'Yes' : 'No' }} @break
                                    @case('money') {{ \App\Support\Money::inr($val) }} @break
                                    @case('badge') @if ($val)<x-status-badge :status="$val" />@endif @break
                                    @case('date') {{ $val ? \Illuminate\Support\Carbon::parse($val)->format('d M Y H:i') : '—' }} @break
                                    @default {{ \Illuminate\Support\Str::limit((string) ($val ?? '—'), 60) }}
                                @endswitch
                            </td>
                        @endforeach
                        <td class="admin-row-actions">
                            @if (! empty($cfg['row_link']))<a href="{{ route($cfg['row_link'], $row) }}">{{ $cfg['row_link'] === 'admin.invoices.download' ? 'PDF' : 'Open' }}</a>@endif
                            @if (($cfg['row_action'] ?? null) === 'notification_retry' && ($row->channel === 'telegram' || str_starts_with((string) $row->event, 'automation_')) && $row->status === 'failed' && auth()->user()->hasPermission('notifications.edit'))
                                <form method="POST" action="{{ route('admin.notifications.retry', $row) }}">@csrf<button class="admin-btn admin-btn-quiet" type="submit">Retry</button></form>
                            @endif
                            @if (in_array('edit', $cfg['actions'] ?? []) && auth()->user()->hasPermission($cfg['module'].'.'.($cfg['perms']['edit'] ?? 'edit')))<a href="{{ route('admin.resource.edit', [$resource, $row->id]) }}">Edit</a>@endif
                            @if (in_array('delete', $cfg['actions'] ?? []) && auth()->user()->hasPermission($cfg['module'].'.'.($cfg['perms']['delete'] ?? 'delete')))
                                <form method="POST" action="{{ route('admin.resource.destroy', [$resource, $row->id]) }}" onsubmit="return confirm('Delete this record?')">@csrf @method('DELETE')<button class="admin-link-danger" type="submit">Delete</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $rows->links() }}
    @endif
@endsection
