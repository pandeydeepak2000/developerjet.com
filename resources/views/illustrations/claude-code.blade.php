<svg class="dj-ill" viewBox="0 0 600 460" role="img" aria-labelledby="ill-cc-title" xmlns="http://www.w3.org/2000/svg" font-family="Inter Variable, Inter, system-ui, sans-serif">
    <title id="ill-cc-title">{{ $alt ?? 'A development cycle on a managed server: plan, edit, run and test.' }}</title>
    <defs>
        <marker id="cc-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto"><path d="M0 0 10 5 0 10Z" fill="#2563EB"/></marker>
    </defs>
    <rect width="600" height="460" rx="20" fill="#F6F9FC"/>

    {{-- Code editor --}}
    <g transform="translate(24 24)">
        <rect width="372" height="256" rx="14" fill="#fff" stroke="#D5DEEA"/>
        <path d="M0 14a14 14 0 0 1 14-14h344a14 14 0 0 1 14 14v24H0Z" fill="#EEF3F9"/>
        <rect x="14" y="8" width="128" height="30" rx="6" fill="#fff"/>
        <text x="28" y="28" font-size="12" font-weight="600" fill="#17243B">src/report.ts</text>
        <g font-family="ui-monospace, SFMono-Regular, Menlo, Consolas, monospace" font-size="12">
            <g fill="#94A3B8" text-anchor="end">
                <text x="34" y="66">12</text><text x="34" y="88">13</text><text x="34" y="110">14</text><text x="34" y="132">15</text>
                <text x="34" y="154">16</text><text x="34" y="176">17</text><text x="34" y="198">18</text><text x="34" y="220">19</text>
            </g>
            <text x="48" y="66" fill="#1D4ED8">export function <tspan fill="#17243B">saveReport(input) {</tspan></text>
            <rect x="42" y="74" width="318" height="20" fill="#FEE2E2"/>
            <text x="48" y="88" fill="#B91C1C">-  return db.insert(input);</text>
            <rect x="42" y="96" width="318" height="20" fill="#DCFCE7"/>
            <text x="48" y="110" fill="#15803D">+  const data = validate(input);</text>
            <rect x="42" y="118" width="318" height="20" fill="#DCFCE7"/>
            <text x="48" y="132" fill="#15803D">+  if (!data.ok) return data.errors;</text>
            <rect x="42" y="140" width="318" height="20" fill="#DCFCE7"/>
            <text x="48" y="154" fill="#15803D">+  return db.insert(data.value);</text>
            <text x="48" y="176" fill="#17243B">}</text>
        </g>
        <rect x="48" y="210" width="120" height="8" rx="4" fill="#E3E9F1"/>
        <rect x="48" y="226" width="190" height="8" rx="4" fill="#E3E9F1"/>
    </g>

    {{-- Terminal --}}
    <g transform="translate(224 196)">
        <rect width="352" height="208" rx="14" fill="#0B1736"/>
        <path d="M0 36H352" stroke="#1E3462"/>
        <circle cx="20" cy="18" r="5" fill="#33507F"/><circle cx="38" cy="18" r="5" fill="#33507F"/><circle cx="56" cy="18" r="5" fill="#33507F"/>
        <text x="176" y="23" font-size="11" fill="#94A3B8" text-anchor="middle" font-family="ui-monospace, SFMono-Regular, Menlo, Consolas, monospace">ssh · your managed server</text>
        <g font-family="ui-monospace, SFMono-Regular, Menlo, Consolas, monospace" font-size="13">
            <text x="20" y="66"><tspan fill="#60A5FA">$</tspan><tspan fill="#E2E8F0" dx="8">claude</tspan></text>
            <text x="20" y="92"><tspan fill="#60A5FA">›</tspan><tspan fill="#E2E8F0" dx="8">Add validation to saveReport</tspan></text>
            <text x="20" y="118"><tspan fill="#FBBF24">●</tspan><tspan fill="#94A3B8" dx="8">Editing src/report.ts</tspan></text>
            <text x="20" y="152"><tspan fill="#60A5FA">$</tspan><tspan fill="#E2E8F0" dx="8">npm test</tspan></text>
            <text x="20" y="178"><tspan fill="#4ADE80">✓</tspan><tspan fill="#94A3B8" dx="8">All tests passed</tspan></text>
        </g>
    </g>

    {{-- Managed server --}}
    <g transform="translate(24 300)">
        <rect width="184" height="104" rx="14" fill="#fff" stroke="#D5DEEA"/>
        <rect x="18" y="20" width="36" height="16" rx="4" fill="none" stroke="#2563EB" stroke-width="2"/>
        <rect x="18" y="42" width="36" height="16" rx="4" fill="none" stroke="#2563EB" stroke-width="2"/>
        <text x="66" y="36" font-size="13" font-weight="700" fill="#17243B">Managed server</text>
        <text x="66" y="54" font-size="11" fill="#5B6B82">Always on</text>
        <text x="18" y="84" font-size="11" fill="#5B6B82">Sign in with your own Claude</text>
    </g>

    {{-- Cycle --}}
    <g transform="translate(24 422)" font-size="12" font-weight="700">
        <rect width="74" height="28" rx="14" fill="#DBEAFE"/><text x="37" y="19" fill="#1D4ED8" text-anchor="middle">Plan</text>
        <path d="M80 14H98" stroke="#2563EB" stroke-width="2" marker-end="url(#cc-arrow)"/>
        <rect x="104" width="74" height="28" rx="14" fill="#DBEAFE"/><text x="141" y="19" fill="#1D4ED8" text-anchor="middle">Edit</text>
        <path d="M184 14H202" stroke="#2563EB" stroke-width="2" marker-end="url(#cc-arrow)"/>
        <rect x="208" width="74" height="28" rx="14" fill="#DBEAFE"/><text x="245" y="19" fill="#1D4ED8" text-anchor="middle">Run</text>
        <path d="M288 14H306" stroke="#2563EB" stroke-width="2" marker-end="url(#cc-arrow)"/>
        <rect x="312" width="74" height="28" rx="14" fill="#2563EB"/><text x="349" y="19" fill="#fff" text-anchor="middle">Test</text>
    </g>
</svg>
