<svg class="dj-ill" viewBox="0 0 600 460" role="img" aria-labelledby="ill-oc-title" xmlns="http://www.w3.org/2000/svg" font-family="Inter Variable, Inter, system-ui, sans-serif">
    <title id="ill-oc-title">{{ $alt ?? 'OpenClaw on an isolated VPS behind an authenticated gateway.' }}</title>
    <defs>
        <marker id="oc-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0 0 10 5 0 10Z" fill="#2563EB"/></marker>
    </defs>
    <rect width="600" height="460" rx="20" fill="#F6F9FC"/>

    {{-- Your devices --}}
    <g transform="translate(24 128)">
        <rect width="124" height="168" rx="14" fill="#fff" stroke="#D5DEEA"/>
        <rect x="20" y="22" width="30" height="52" rx="6" fill="#EFF6FF" stroke="#0B1736" stroke-width="2"/>
        <path d="M31 66h8" stroke="#0B1736" stroke-width="2" stroke-linecap="round"/>
        <rect x="60" y="34" width="46" height="30" rx="3" fill="#EFF6FF" stroke="#0B1736" stroke-width="2"/>
        <path d="M54 70h58" stroke="#0B1736" stroke-width="3" stroke-linecap="round"/>
        <text x="62" y="112" font-size="13" font-weight="700" fill="#17243B" text-anchor="middle">Your devices</text>
        <text x="62" y="132" font-size="11" fill="#5B6B82" text-anchor="middle">Phone or computer</text>
    </g>

    <path d="M148 212H178" stroke="#2563EB" stroke-width="2" marker-end="url(#oc-arrow)"/>

    {{-- Gateway --}}
    <g transform="translate(184 150)">
        <rect width="128" height="124" rx="14" fill="#0B1736"/>
        <path d="M64 18l-26 10v18c0 16 11 28 26 33 15-5 26-17 26-33V28Z" fill="#13254F" stroke="#60A5FA" stroke-width="2" stroke-linejoin="round"/>
        <rect x="55" y="44" width="18" height="14" rx="3" fill="#60A5FA"/>
        <path d="M58 44v-5a6 6 0 0 1 12 0v5" fill="none" stroke="#60A5FA" stroke-width="2"/>
        <text x="64" y="98" font-size="12" font-weight="700" fill="#fff" text-anchor="middle">Authenticated</text>
        <text x="64" y="114" font-size="12" font-weight="700" fill="#fff" text-anchor="middle">gateway</text>
    </g>

    <path d="M312 212H346" stroke="#2563EB" stroke-width="2" marker-end="url(#oc-arrow)"/>

    {{-- Isolated VPS --}}
    <g transform="translate(352 40)">
        <rect width="224" height="376" rx="16" fill="#EFF6FF" stroke="#2563EB" stroke-width="2" stroke-dasharray="8 6"/>
        <text x="20" y="32" font-size="12" font-weight="700" fill="#1D4ED8">DEDICATED VPS · ISOLATED</text>

        <rect x="16" y="50" width="192" height="136" rx="12" fill="#fff" stroke="#D5DEEA"/>
        <rect x="32" y="66" width="30" height="30" rx="8" fill="#2563EB"/>
        <path d="M39 75h16v10h-9l-5 4Z" fill="none" stroke="#fff" stroke-width="1.8" stroke-linejoin="round"/>
        <text x="72" y="80" font-size="13" font-weight="700" fill="#17243B">OpenClaw</text>
        <text x="72" y="96" font-size="11" fill="#5B6B82">Your assistant</text>
        <rect x="32" y="114" width="150" height="8" rx="4" fill="#C5D0DF"/>
        <rect x="32" y="130" width="120" height="8" rx="4" fill="#C5D0DF"/>
        <rect x="32" y="146" width="158" height="8" rx="4" fill="#C5D0DF"/>
        <rect x="32" y="162" width="80" height="8" rx="4" fill="#93B4F5"/>

        <rect x="16" y="204" width="192" height="46" rx="10" fill="#fff" stroke="#D5DEEA"/>
        <circle cx="40" cy="222" r="6" fill="none" stroke="#0B1736" stroke-width="2"/>
        <path d="M30 240a10 10 0 0 1 20 0" fill="none" stroke="#0B1736" stroke-width="2"/>
        <text x="60" y="232" font-size="12" font-weight="600" fill="#17243B">Isolated service user</text>

        <rect x="16" y="262" width="192" height="46" rx="10" fill="#fff" stroke="#D5DEEA"/>
        <circle cx="38" cy="282" r="7" fill="none" stroke="#2563EB" stroke-width="2"/>
        <path d="M43 287l10 10M49 293l3-3" stroke="#2563EB" stroke-width="2" stroke-linecap="round"/>
        <text x="60" y="290" font-size="12" font-weight="600" fill="#17243B">Your AI provider key</text>

        <rect x="16" y="320" width="192" height="40" rx="10" fill="#0B1736"/>
        <text x="112" y="345" font-size="12" font-weight="600" fill="#fff" text-anchor="middle">Managed by DeveloperJet</text>
    </g>

    {{-- Note --}}
    <g transform="translate(24 336)">
        <rect width="288" height="80" rx="14" fill="#fff" stroke="#D5DEEA"/>
        <rect x="18" y="22" width="36" height="36" rx="9" fill="#DBEAFE"/>
        <path d="M30 40l5 5 9-10" fill="none" stroke="#1D4ED8" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
        <text x="68" y="38" font-size="13" font-weight="700" fill="#17243B">Not shared with others</text>
        <text x="68" y="56" font-size="11" fill="#5B6B82">Access details in your dashboard</text>
    </g>
</svg>
