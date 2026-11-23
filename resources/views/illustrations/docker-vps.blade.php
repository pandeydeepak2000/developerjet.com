<svg class="dj-ill" viewBox="0 0 600 460" role="img" aria-labelledby="ill-docker-title" xmlns="http://www.w3.org/2000/svg" font-family="Inter Variable, Inter, system-ui, sans-serif">
    <title id="ill-docker-title">{{ $alt ?? 'Container images deployed with Docker Compose on a VPS you control over SSH.' }}</title>
    <defs>
        <marker id="dk-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0 0 10 5 0 10Z" fill="#2563EB"/></marker>
    </defs>
    <rect width="600" height="460" rx="20" fill="#F6F9FC"/>

    {{-- Images --}}
    <text x="28" y="44" font-size="12" font-weight="700" fill="#5B6B82">IMAGES</text>
    <g font-size="12" font-weight="600" fill="#17243B">
        <rect x="24" y="56" width="164" height="38" rx="9" fill="#fff" stroke="#D5DEEA"/>
        <path d="M38 70l8-4 8 4v10l-8 4-8-4Z" fill="none" stroke="#2563EB" stroke-width="1.6" stroke-linejoin="round"/>
        <text x="64" y="80">web:latest</text>
        <rect x="24" y="102" width="164" height="38" rx="9" fill="#fff" stroke="#D5DEEA"/>
        <path d="M38 116l8-4 8 4v10l-8 4-8-4Z" fill="none" stroke="#2563EB" stroke-width="1.6" stroke-linejoin="round"/>
        <text x="64" y="126">api:latest</text>
        <rect x="24" y="148" width="164" height="38" rx="9" fill="#fff" stroke="#D5DEEA"/>
        <path d="M38 162l8-4 8 4v10l-8 4-8-4Z" fill="none" stroke="#2563EB" stroke-width="1.6" stroke-linejoin="round"/>
        <text x="64" y="172">worker:latest</text>
    </g>

    {{-- compose.yaml --}}
    <g transform="translate(24 222)">
        <rect width="164" height="196" rx="12" fill="#0B1736"/>
        <text x="16" y="28" font-size="12" font-weight="700" fill="#fff">compose.yaml</text>
        <path d="M0 42H164" stroke="#1E3462"/>
        <g font-family="ui-monospace, SFMono-Regular, Menlo, Consolas, monospace" font-size="12">
            <text x="16" y="68" fill="#60A5FA">services:</text>
            <text x="28" y="92" fill="#E2E8F0">web:</text>
            <text x="28" y="116" fill="#E2E8F0">api:</text>
            <text x="28" y="140" fill="#E2E8F0">worker:</text>
            <text x="28" y="164" fill="#E2E8F0">db:</text>
        </g>
    </g>

    <path d="M188 120H216" stroke="#2563EB" stroke-width="2" marker-end="url(#dk-arrow)"/>
    <path d="M188 360H216" stroke="#2563EB" stroke-width="2" marker-end="url(#dk-arrow)"/>

    {{-- VPS --}}
    <g transform="translate(222 24)">
        <rect width="354" height="412" rx="16" fill="#fff" stroke="#D5DEEA"/>
        <text x="20" y="34" font-size="15" font-weight="700" fill="#0B1736">Your VPS</text>
        <rect x="174" y="14" width="164" height="30" rx="8" fill="#0B1736"/>
        <text x="256" y="34" font-size="11" fill="#E2E8F0" text-anchor="middle" font-family="ui-monospace, SFMono-Regular, Menlo, Consolas, monospace">$ ssh root@your-vps</text>

        <g font-size="13" font-weight="700" fill="#17243B">
            <rect x="20" y="62" width="150" height="110" rx="12" fill="#EFF6FF" stroke="#BFD3FB"/>
            <path d="M36 82l12-6 12 6v14l-12 6-12-6Z M36 82l12 6 12-6 M48 88v14" fill="none" stroke="#1D4ED8" stroke-width="1.8" stroke-linejoin="round"/>
            <text x="36" y="132">web</text>
            <circle cx="40" cy="152" r="4" fill="#16A34A"/><text x="50" y="156" font-size="11" font-weight="500" fill="#5B6B82">running</text>

            <rect x="184" y="62" width="150" height="110" rx="12" fill="#EFF6FF" stroke="#BFD3FB"/>
            <path d="M200 82l12-6 12 6v14l-12 6-12-6Z M200 82l12 6 12-6 M212 88v14" fill="none" stroke="#1D4ED8" stroke-width="1.8" stroke-linejoin="round"/>
            <text x="200" y="132">api</text>
            <circle cx="204" cy="152" r="4" fill="#16A34A"/><text x="214" y="156" font-size="11" font-weight="500" fill="#5B6B82">running</text>

            <rect x="20" y="186" width="150" height="110" rx="12" fill="#EFF6FF" stroke="#BFD3FB"/>
            <path d="M36 206l12-6 12 6v14l-12 6-12-6Z M36 206l12 6 12-6 M48 212v14" fill="none" stroke="#1D4ED8" stroke-width="1.8" stroke-linejoin="round"/>
            <text x="36" y="256">worker</text>
            <circle cx="40" cy="276" r="4" fill="#16A34A"/><text x="50" y="280" font-size="11" font-weight="500" fill="#5B6B82">running</text>

            <rect x="184" y="186" width="150" height="110" rx="12" fill="#EFF6FF" stroke="#BFD3FB"/>
            <ellipse cx="212" cy="206" rx="12" ry="5" fill="none" stroke="#1D4ED8" stroke-width="1.8"/>
            <path d="M200 206v16c0 3 5 5 12 5s12-2 12-5v-16" fill="none" stroke="#1D4ED8" stroke-width="1.8"/>
            <text x="200" y="256">db</text>
            <circle cx="204" cy="276" r="4" fill="#16A34A"/><text x="214" y="280" font-size="11" font-weight="500" fill="#5B6B82">running</text>
        </g>

        <rect x="20" y="312" width="314" height="38" rx="9" fill="#2563EB"/>
        <text x="177" y="336" font-size="13" font-weight="700" fill="#fff" text-anchor="middle">Docker Engine + Compose</text>
        <rect x="20" y="358" width="314" height="36" rx="9" fill="#0B1736"/>
        <text x="177" y="381" font-size="12" font-weight="600" fill="#E2E8F0" text-anchor="middle">Linux · you manage updates</text>
    </g>
</svg>
