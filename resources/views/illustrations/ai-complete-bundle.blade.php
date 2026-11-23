<svg class="dj-ill" viewBox="0 0 600 460" role="img" aria-labelledby="ill-bundle-title" xmlns="http://www.w3.org/2000/svg" font-family="Inter Variable, Inter, system-ui, sans-serif">
    <title id="ill-bundle-title">{{ $alt ?? 'Hermes CLI, Hermes Workspace and n8n on one managed VPS.' }}</title>
    <rect width="600" height="460" rx="20" fill="#0B1736"/>
    <text x="36" y="52" font-size="16" font-weight="700" fill="#fff">One managed VPS</text>
    <text x="36" y="74" font-size="12" fill="#94A3B8">Dedicated to you · applications kept separate</text>
    <rect x="474" y="34" width="90" height="28" rx="14" fill="#2563EB"/>
    <text x="519" y="53" font-size="12" font-weight="700" fill="#fff" text-anchor="middle">Managed</text>

    {{-- Hermes CLI --}}
    <g transform="translate(36 100)">
        <rect width="164" height="246" rx="14" fill="#13254F" stroke="#2C4A80"/>
        <rect x="14" y="14" width="136" height="120" rx="8" fill="#0B1736" stroke="#2C4A80"/>
        <g font-family="ui-monospace, SFMono-Regular, Menlo, Consolas, monospace" font-size="11">
            <text x="24" y="40"><tspan fill="#60A5FA">$</tspan><tspan fill="#E2E8F0" dx="6">hermes</tspan></text>
            <text x="24" y="60"><tspan fill="#60A5FA">›</tspan><tspan fill="#E2E8F0" dx="6">plan the week</tspan></text>
        </g>
        <rect x="24" y="76" width="96" height="6" rx="3" fill="#3B5B8F"/>
        <rect x="24" y="90" width="116" height="6" rx="3" fill="#3B5B8F"/>
        <rect x="24" y="104" width="70" height="6" rx="3" fill="#2563EB"/>
        <text x="14" y="172" font-size="14" font-weight="700" fill="#fff">Hermes CLI</text>
        <text x="14" y="192" font-size="12" fill="#94A3B8">Terminal over SSH</text>
        <rect x="14" y="208" width="58" height="22" rx="11" fill="#1E3462"/>
        <text x="43" y="223" font-size="11" fill="#BFD3FB" text-anchor="middle">Agent</text>
    </g>

    {{-- Hermes Workspace --}}
    <g transform="translate(218 100)">
        <rect width="164" height="246" rx="14" fill="#13254F" stroke="#2C4A80"/>
        <rect x="14" y="14" width="136" height="120" rx="8" fill="#fff"/>
        <path d="M14 22a8 8 0 0 1 8-8h120a8 8 0 0 1 8 8v8H14Z" fill="#EEF3F9"/>
        <circle cx="24" cy="22" r="3" fill="#C5D0DF"/><circle cx="34" cy="22" r="3" fill="#C5D0DF"/>
        <rect x="70" y="42" width="70" height="18" rx="6" fill="#2563EB"/>
        <rect x="24" y="68" width="100" height="34" rx="6" fill="#F6F9FC" stroke="#E3E9F1"/>
        <rect x="32" y="78" width="70" height="5" rx="2" fill="#C5D0DF"/>
        <rect x="32" y="89" width="50" height="5" rx="2" fill="#C5D0DF"/>
        <rect x="24" y="110" width="116" height="16" rx="5" fill="#fff" stroke="#D5DEEA"/>
        <text x="14" y="172" font-size="14" font-weight="700" fill="#fff">Hermes Workspace</text>
        <text x="14" y="192" font-size="12" fill="#94A3B8">In your browser</text>
        <rect x="14" y="208" width="68" height="22" rx="11" fill="#1E3462"/>
        <text x="48" y="223" font-size="11" fill="#BFD3FB" text-anchor="middle">Docker</text>
    </g>

    {{-- n8n --}}
    <g transform="translate(400 100)">
        <rect width="164" height="246" rx="14" fill="#13254F" stroke="#2C4A80"/>
        <rect x="14" y="14" width="136" height="120" rx="8" fill="#0B1736" stroke="#2C4A80"/>
        <g fill="none" stroke="#5B7BB0" stroke-width="1.6">
            <path d="M50 74H66"/><path d="M98 66C110 66 106 46 118 46"/><path d="M98 82C110 82 106 102 118 102"/>
        </g>
        <rect x="22" y="60" width="28" height="28" rx="7" fill="#2563EB"/>
        <rect x="66" y="60" width="32" height="28" rx="7" fill="#1E3462" stroke="#60A5FA"/>
        <rect x="118" y="34" width="24" height="24" rx="6" fill="#1E3462" stroke="#60A5FA"/>
        <rect x="118" y="90" width="24" height="24" rx="6" fill="#1E3462" stroke="#60A5FA"/>
        <text x="14" y="172" font-size="14" font-weight="700" fill="#fff">n8n</text>
        <text x="14" y="192" font-size="12" fill="#94A3B8">Workflow automation</text>
        <rect x="14" y="208" width="76" height="22" rx="11" fill="#1E3462"/>
        <text x="52" y="223" font-size="11" fill="#BFD3FB" text-anchor="middle">Browser</text>
    </g>

    {{-- Footer facts --}}
    <g transform="translate(36 366)">
        <rect width="528" height="58" rx="12" fill="#13254F"/>
        <g font-size="13" font-weight="600" fill="#E2E8F0">
            <path d="M22 29l5 5 9-10" fill="none" stroke="#60A5FA" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
            <text x="46" y="34">One server</text>
            <path d="M190 29l5 5 9-10" fill="none" stroke="#60A5FA" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
            <text x="214" y="34">One invoice</text>
            <path d="M360 29l5 5 9-10" fill="none" stroke="#60A5FA" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
            <text x="384" y="34">One renewal</text>
        </g>
    </g>
</svg>
