{{-- Product chooser table. $keys = product keys from config/product_matrix.php, $current = highlighted key (optional). --}}
@php($current = $current ?? null)
<div class="dj-table-wrap" role="region" aria-label="Product comparison" tabindex="0">
    <table class="dj-table">
        <caption class="sr-only">Comparison of DeveloperJet products</caption>
        <thead>
            <tr>
                <th scope="col"><span class="sr-only">Feature</span></th>
                @foreach ($keys as $key)
                    @php($p = $matrix['products'][$key])
                    <th scope="col" class="{{ $key === $current ? 'is-current' : '' }}">
                        <a href="{{ route($p['route'][0], $p['route'][1] ?? []) }}">
                            <span class="dj-table__icon"><x-icon :name="$p['icon']" :size="18" /></span>
                            {{ $p['name'] }}
                        </a>
                        @if (! empty($p['status']))<span class="dj-table__soon">{{ $p['status'] }}</span>@endif
                        @if ($key === $current)<span class="dj-table__here">This page</span>@endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($matrix['rows'] as $field => $label)
                <tr>
                    <th scope="row">{{ $label }}</th>
                    @foreach ($keys as $key)
                        <td class="{{ $key === $current ? 'is-current' : '' }}">{{ $matrix['products'][$key][$field] }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
