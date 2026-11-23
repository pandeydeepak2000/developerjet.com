<div class="admin-table-wrap"><table class="admin-table admin-matrix">
    <thead><tr><th>Module</th>@foreach (config('permissions.actions') as $a)<th>{{ ucfirst($a) }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach ($modules as $key => $mod)
        <tr><th scope="row">{{ $mod['label'] }}</th>
            @foreach (config('permissions.actions') as $a)
                <td>@if (in_array($a, $mod['actions']))<input type="checkbox" name="permissions[]" value="{{ $key }}.{{ $a }}" @checked(in_array("$key.$a", $granted)) aria-label="{{ $mod['label'] }} {{ $a }}">@else<span class="muted">—</span>@endif</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table></div>
