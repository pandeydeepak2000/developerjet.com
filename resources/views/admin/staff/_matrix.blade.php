{{-- Permission matrix. The "Sidebar menu" column shows which menu the module's View permission unlocks. --}}
@php $menuByModule = collect($sidebarItems ?? [])->groupBy(fn ($i) => explode('.', $i[1])[0])->map(fn ($g) => $g->pluck(0)->unique()->implode(', ')); @endphp
<div class="admin-table-wrap"><table class="admin-table admin-matrix">
    <thead><tr><th>Module</th><th>Sidebar menu</th>@foreach (config('permissions.actions') as $a)<th>{{ ucfirst($a) }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach ($modules as $key => $mod)
        <tr><th scope="row">{{ $mod['label'] }}</th>
            <td class="muted small">{{ $menuByModule[$key] ?? '—' }}</td>
            @foreach (config('permissions.actions') as $a)
                <td>@if (in_array($a, $mod['actions']))<input type="checkbox" name="permissions[]" value="{{ $key }}.{{ $a }}" @checked(in_array("$key.$a", $granted)) aria-label="{{ $mod['label'] }} {{ $a }}">@else<span class="muted" aria-hidden="true">·</span>@endif</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table></div>
