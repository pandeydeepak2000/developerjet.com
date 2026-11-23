<svg class="dj-ill" viewBox="0 0 600 480" role="img" aria-labelledby="ill-aa-title" xmlns="http://www.w3.org/2000/svg" font-family="Inter Variable, Inter, system-ui, sans-serif">
    <title id="ill-aa-title">{{ $alt ?? 'Your own API keys for ChatGPT (OpenAI), Claude (Anthropic), Gemini (Google) and Grok (xAI) connect to one DeveloperJet workspace. Planned — not live yet.' }}</title>
    <defs>
        <marker id="aa-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0 0 10 5 0 10Z" fill="#2563EB"/></marker>
    </defs>
    <rect width="600" height="480" rx="20" fill="#F6F9FC"/>

    {{-- Your provider accounts: names only (original monogram tiles, no third-party logos). --}}
    <text x="100" y="50" font-size="14" font-weight="700" fill="#17243B" text-anchor="middle">Your API keys</text>
    @foreach ([[66, 'ChatGPT', 'OpenAI', 'OA', '#0F766E'], [156, 'Claude', 'Anthropic', 'An', '#B45309'], [246, 'Gemini', 'Google', 'G', '#1D4ED8'], [336, 'Grok', 'xAI', 'xA', '#0B1736']] as [$y, $product, $company, $mono, $colour])
        <g transform="translate(20 {{ $y }})">
            <rect width="160" height="74" rx="12" fill="#fff" stroke="#D5DEEA"/>
            <rect x="12" y="17" width="40" height="40" rx="10" fill="{{ $colour }}"/>
            <text x="32" y="42" font-size="14" font-weight="800" fill="#fff" text-anchor="middle">{{ $mono }}</text>
            <text x="62" y="34" font-size="15" font-weight="700" fill="#17243B">{{ $product }}</text>
            <text x="62" y="54" font-size="13" fill="#52627A">{{ $company }} · key</text>
        </g>
        <path d="M180 {{ $y + 37 }} C 210 {{ $y + 37 }}, 214 232, 240 232" fill="none" stroke="#2563EB" stroke-width="2" stroke-dasharray="5 5" marker-end="url(#aa-arrow)"/>
    @endforeach
    <text x="100" y="440" font-size="13" fill="#52627A" text-anchor="middle">Usage billed by each provider</text>

    {{-- Workspace window --}}
    <g transform="translate(248 56)">
        <rect width="332" height="340" rx="16" fill="#fff" stroke="#CFD8E4"/>
        <path d="M0 16a16 16 0 0 1 16-16h300a16 16 0 0 1 16 16v26H0Z" fill="#0B1736"/>
        <circle cx="20" cy="21" r="4" fill="#5B6B82"/><circle cx="34" cy="21" r="4" fill="#5B6B82"/><circle cx="48" cy="21" r="4" fill="#5B6B82"/>
        <text x="172" y="26" font-size="13" font-weight="700" fill="#fff" text-anchor="middle">DeveloperJet AI workspace</text>

        {{-- Sidebar: conversations --}}
        <rect x="12" y="54" width="92" height="274" rx="10" fill="#F6F9FC"/>
        <text x="24" y="76" font-size="12" font-weight="700" fill="#52627A">CHATS</text>
        <rect x="22" y="86" width="72" height="22" rx="6" fill="#DBEAFE"/>
        <rect x="22" y="114" width="72" height="22" rx="6" fill="#fff" stroke="#E3E9F1"/>
        <rect x="22" y="142" width="72" height="22" rx="6" fill="#fff" stroke="#E3E9F1"/>
        <path d="M30 97h44M30 125h36M30 153h40" stroke="#52627A" stroke-width="3" stroke-linecap="round" opacity=".6"/>

        {{-- Model picker (open) --}}
        <rect x="116" y="54" width="204" height="32" rx="8" fill="#EFF6FF" stroke="#BFD3FB"/>
        <text x="128" y="75" font-size="13" font-weight="600" fill="#1E40AF">Model: Claude</text>
        <path d="m302 67 4 4 4-4" fill="none" stroke="#1E40AF" stroke-width="2" stroke-linecap="round"/>
        <rect x="116" y="90" width="204" height="104" rx="8" fill="#fff" stroke="#CFD8E4"/>
        @foreach ([['ChatGPT', '#0F766E', false], ['Claude', '#B45309', true], ['Gemini', '#1D4ED8', false], ['Grok', '#0B1736', false]] as $i => [$m, $c, $sel])
            @if ($sel)<rect x="120" y="{{ 94 + $i * 24 }}" width="196" height="22" rx="5" fill="#EFF6FF"/>@endif
            <circle cx="132" cy="{{ 105 + $i * 24 }}" r="5" fill="{{ $c }}"/>
            <text x="144" y="{{ 110 + $i * 24 }}" font-size="13" font-weight="{{ $sel ? 700 : 500 }}" fill="#17243B">{{ $m }}</text>
        @endforeach

        {{-- Messages --}}
        <rect x="176" y="206" width="144" height="34" rx="10" fill="#2563EB"/>
        <path d="M190 219h112M190 229h72" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".85"/>
        <rect x="116" y="248" width="170" height="30" rx="10" fill="#F6F9FC" stroke="#E3E9F1"/>
        <path d="M130 263h130" stroke="#3B4A63" stroke-width="3" stroke-linecap="round" opacity=".55"/>

        {{-- Composer --}}
        <rect x="116" y="288" width="204" height="40" rx="10" fill="#fff" stroke="#CFD8E4"/>
        <path d="M130 308h96" stroke="#52627A" stroke-width="3" stroke-linecap="round" opacity=".45"/>
        <rect x="282" y="296" width="30" height="24" rx="6" fill="#0B1736"/>
        <path d="m292 308h11m-4-4 4 4-4 4" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </g>

    {{-- Status tag --}}
    <g transform="translate(348 420)">
        <rect width="232" height="34" rx="17" fill="#FEF3C7" stroke="#FDE68A"/>
        <text x="116" y="22" font-size="13" font-weight="700" fill="#92400E" text-anchor="middle">Planned · integrations not live yet</text>
    </g>
</svg>
