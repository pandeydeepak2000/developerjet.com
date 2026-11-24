<svg class="dj-ill" viewBox="0 0 600 460" role="img" aria-labelledby="ill-n8n-title" xmlns="http://www.w3.org/2000/svg" font-family="Inter Variable, Inter, system-ui, sans-serif">
    <title id="ill-n8n-title">{{ $alt ?? 'An automation workflow of connected nodes on your own VPS.' }}</title>
    <defs>
        <pattern id="n8n-dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.2" fill="#D5DEEA"/></pattern>
    </defs>
    <rect width="600" height="460" rx="20" fill="#F6F9FC"/>

    {{-- Editor canvas --}}
    <g transform="translate(24 24)">
        <rect width="552" height="326" rx="14" fill="#fff" stroke="#D5DEEA"/>
        <rect x="1" y="52" width="550" height="273" fill="url(#n8n-dots)"/>
        <path d="M0 52H552" stroke="#E3E9F1"/>
        <text x="20" y="32" font-size="14" font-weight="700" fill="#0B1736">Workflow · New lead follow-up</text>
        <rect x="456" y="14" width="78" height="26" rx="13" fill="#DCFCE7"/>
        <circle cx="472" cy="27" r="4" fill="#16A34A"/>
        <text x="482" y="31" font-size="12" font-weight="700" fill="#15803D">Active</text>

        {{-- Connections --}}
        <g fill="none" stroke="#94A3B8" stroke-width="2">
            <path d="M108 180H150"/>
            <path d="M234 180H276"/>
            <path d="M360 170C392 170 380 122 414 122"/>
            <path d="M360 190C392 190 380 242 414 242"/>
        </g>
        <g fill="#94A3B8"><circle cx="150" cy="180" r="4"/><circle cx="276" cy="180" r="4"/><circle cx="414" cy="122" r="4"/><circle cx="414" cy="242" r="4"/></g>
        <text x="380" y="140" font-size="11" fill="#5B6B82">true</text>
        <text x="380" y="230" font-size="11" fill="#5B6B82">false</text>

        {{-- Nodes --}}
        <g>
            <rect x="34" y="144" width="74" height="72" rx="16" fill="#fff" stroke="#2563EB" stroke-width="2"/>
            <path d="M75 160l-12 22h11l-4 18 14-25H73Z" fill="#2563EB"/>
            <text x="71" y="238" font-size="12" font-weight="600" fill="#17243B" text-anchor="middle">Webhook</text>
        </g>
        <g>
            <rect x="160" y="144" width="74" height="72" rx="12" fill="#fff" stroke="#C5D0DF" stroke-width="1.6"/>
            <circle cx="197" cy="180" r="16" fill="none" stroke="#0B1736" stroke-width="2"/>
            <path d="M181 180h32M197 164c6 6 6 26 0 32M197 164c-6 6-6 26 0 32" fill="none" stroke="#0B1736" stroke-width="1.6"/>
            <text x="197" y="238" font-size="12" font-weight="600" fill="#17243B" text-anchor="middle">HTTP request</text>
        </g>
        <g>
            <rect x="286" y="144" width="74" height="72" rx="12" fill="#fff" stroke="#C5D0DF" stroke-width="1.6"/>
            <path d="M323 162l18 18-18 18-18-18Z" fill="#EFF6FF" stroke="#2563EB" stroke-width="2" stroke-linejoin="round"/>
            <text x="323" y="238" font-size="12" font-weight="600" fill="#17243B" text-anchor="middle">IF</text>
        </g>
        <g>
            <rect x="424" y="86" width="74" height="72" rx="12" fill="#fff" stroke="#C5D0DF" stroke-width="1.6"/>
            <rect x="443" y="108" width="36" height="26" rx="4" fill="none" stroke="#0B1736" stroke-width="2"/>
            <path d="M444 110l17 13 17-13" fill="none" stroke="#0B1736" stroke-width="2" stroke-linejoin="round"/>
            <text x="461" y="180" font-size="12" font-weight="600" fill="#17243B" text-anchor="middle">Send email</text>
        </g>
        <g>
            <rect x="424" y="206" width="74" height="72" rx="12" fill="#fff" stroke="#C5D0DF" stroke-width="1.6"/>
            <rect x="443" y="226" width="36" height="32" rx="4" fill="none" stroke="#0B1736" stroke-width="2"/>
            <path d="M443 237h36M443 248h36M455 226v32" stroke="#0B1736" stroke-width="1.6"/>
            <text x="461" y="300" font-size="12" font-weight="600" fill="#17243B" text-anchor="middle">Update sheet</text>
        </g>
    </g>

    {{-- Server strip --}}
    <g transform="translate(24 368)">
        <rect width="552" height="68" rx="14" fill="#0B1736"/>
        <rect x="18" y="18" width="32" height="32" rx="8" fill="#2563EB"/>
        <path d="M26 28h16M26 34h16M26 40h10" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
        <text x="64" y="32" font-size="14" font-weight="700" fill="#fff">Self-hosted n8n</text>
        <text x="64" y="50" font-size="12" fill="#B6C3D6">Installed and checked by DeveloperJet</text>
        <text x="534" y="40" font-size="12" fill="#B6C3D6" text-anchor="end">Server set up for your plan</text>
    </g>
</svg>
