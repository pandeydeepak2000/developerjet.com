<svg class="dj-ill" viewBox="0 0 600 460" role="img" aria-labelledby="ill-hcli-title" xmlns="http://www.w3.org/2000/svg" font-family="Inter Variable, Inter, system-ui, sans-serif">
    <title id="ill-hcli-title">{{ $alt ?? 'Hermes CLI on a dedicated VPS, reached over SSH or PuTTY.' }}</title>
    <defs>
        <pattern id="hcli-grid" width="24" height="24" patternUnits="userSpaceOnUse"><path d="M24 0H0V24" fill="none" stroke="#E4EAF3"/></pattern>
        <marker id="hcli-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0 0 10 5 0 10Z" fill="#2563EB"/></marker>
    </defs>
    <rect width="600" height="460" rx="20" fill="#F6F9FC"/>
    <rect width="600" height="460" rx="20" fill="url(#hcli-grid)"/>

    {{-- Your computer --}}
    <g transform="translate(28 32)">
        <rect width="236" height="92" rx="12" fill="#fff" stroke="#D5DEEA"/>
        <rect x="18" y="20" width="52" height="34" rx="4" fill="#EFF6FF" stroke="#2563EB" stroke-width="2"/>
        <path d="M12 62h64l-6 8H18Z" fill="#2563EB"/>
        <text x="88" y="42" font-size="15" font-weight="700" fill="#17243B">Your computer</text>
        <text x="88" y="62" font-size="12" fill="#5B6B82">Windows, macOS, Linux</text>
    </g>

    {{-- Dedicated VPS --}}
    <g transform="translate(336 32)">
        <rect width="236" height="92" rx="12" fill="#0B1736"/>
        <rect x="18" y="20" width="44" height="20" rx="4" fill="none" stroke="#60A5FA" stroke-width="2"/>
        <rect x="18" y="46" width="44" height="20" rx="4" fill="none" stroke="#60A5FA" stroke-width="2"/>
        <circle cx="27" cy="30" r="2.5" fill="#60A5FA"/><circle cx="27" cy="56" r="2.5" fill="#60A5FA"/>
        <text x="80" y="42" font-size="15" font-weight="700" fill="#fff">Dedicated VPS</text>
        <text x="80" y="62" font-size="12" fill="#B6C3D6">Managed by DeveloperJet</text>
    </g>

    {{-- Connection --}}
    <path d="M90 124V250H160" fill="none" stroke="#2563EB" stroke-width="2" stroke-dasharray="6 6" marker-end="url(#hcli-arrow)"/>
    <rect x="40" y="168" width="100" height="28" rx="14" fill="#2563EB"/>
    <text x="90" y="187" font-size="12" font-weight="700" fill="#fff" text-anchor="middle">SSH / PuTTY</text>
    <path d="M454 124V150" stroke="#0B1736" stroke-width="2"/>

    {{-- Terminal on the VPS --}}
    <g transform="translate(170 150)">
        <rect width="402" height="270" rx="14" fill="#0B1736"/>
        <path d="M0 38H402" stroke="#1E3462"/>
        <circle cx="22" cy="19" r="5" fill="#33507F"/><circle cx="40" cy="19" r="5" fill="#33507F"/><circle cx="58" cy="19" r="5" fill="#33507F"/>
        <text x="201" y="24" font-size="12" fill="#94A3B8" text-anchor="middle" font-family="ui-monospace, SFMono-Regular, Menlo, Consolas, monospace">you@your-vps: ~</text>
        <g font-family="ui-monospace, SFMono-Regular, Menlo, Consolas, monospace" font-size="14">
            <text x="22" y="72"><tspan fill="#60A5FA">$</tspan><tspan fill="#E2E8F0" dx="8">ssh you@your-vps</tspan></text>
            <text x="22" y="98"><tspan fill="#4ADE80">✓</tspan><tspan fill="#94A3B8" dx="8">Connected · Hermes CLI ready</tspan></text>
            <text x="22" y="132"><tspan fill="#60A5FA">$</tspan><tspan fill="#E2E8F0" dx="8">hermes</tspan></text>
            <text x="22" y="158"><tspan fill="#60A5FA">›</tspan><tspan fill="#E2E8F0" dx="8">Draft a plan for today's tasks</tspan></text>
        </g>
        <rect x="22" y="176" width="358" height="72" rx="8" fill="#13254F"/>
        <rect x="38" y="192" width="220" height="8" rx="4" fill="#3B5B8F"/>
        <rect x="38" y="208" width="292" height="8" rx="4" fill="#3B5B8F"/>
        <rect x="38" y="224" width="168" height="8" rx="4" fill="#2563EB"/>
    </g>

    {{-- Your AI key --}}
    <g transform="translate(28 300)">
        <rect width="130" height="96" rx="12" fill="#fff" stroke="#D5DEEA"/>
        <circle cx="30" cy="30" r="9" fill="none" stroke="#2563EB" stroke-width="2"/>
        <path d="M37 36l14 14M45 44l4-4" stroke="#2563EB" stroke-width="2" stroke-linecap="round"/>
        <text x="16" y="70" font-size="13" font-weight="700" fill="#17243B">Your AI key</text>
        <text x="16" y="86" font-size="11" fill="#5B6B82">Added in dashboard</text>
    </g>
</svg>
