<svg class="dj-ill" viewBox="0 0 600 480" role="img" aria-labelledby="ill-home-title" xmlns="http://www.w3.org/2000/svg" font-family="Inter Variable, Inter, system-ui, sans-serif">
    <title id="ill-home-title">How DeveloperJet works: you order from your dashboard, our team assigns a dedicated VPS, and the platform deploys your AI and developer tools onto it.</title>
    <defs>
        <pattern id="home-grid" width="24" height="24" patternUnits="userSpaceOnUse"><path d="M24 0H0V24" fill="none" stroke="#E4EAF3"/></pattern>
        <marker id="home-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0 0 10 5 0 10Z" fill="#2563EB"/></marker>
    </defs>
    <rect width="600" height="480" rx="22" fill="#F6F9FC"/>
    <rect width="600" height="480" rx="22" fill="url(#home-grid)"/>

    {{-- Customer dashboard --}}
    <g transform="translate(24 32)">
        <rect width="216" height="264" rx="14" fill="#fff" stroke="#D5DEEA"/>
        <path d="M0 14a14 14 0 0 1 14-14h188a14 14 0 0 1 14 14v26H0Z" fill="#EEF3F9"/>
        <circle cx="18" cy="20" r="4" fill="#C5D0DF"/><circle cx="32" cy="20" r="4" fill="#C5D0DF"/><circle cx="46" cy="20" r="4" fill="#C5D0DF"/>
        <text x="18" y="68" font-size="14" font-weight="700" fill="#0B1736">Your dashboard</text>
        <g font-size="12" fill="#17243B">
            <rect x="14" y="84" width="188" height="36" rx="8" fill="#F6F9FC"/>
            <text x="28" y="107" font-weight="600">Services</text>
            <rect x="128" y="92" width="62" height="20" rx="10" fill="#DCFCE7"/><text x="159" y="106" font-size="11" font-weight="700" fill="#15803D" text-anchor="middle">Active</text>
            <rect x="14" y="128" width="188" height="36" rx="8" fill="#F6F9FC"/>
            <text x="28" y="151" font-weight="600">Orders</text>
            <rect x="110" y="136" width="80" height="20" rx="10" fill="#DBEAFE"/><text x="150" y="150" font-size="11" font-weight="700" fill="#1D4ED8" text-anchor="middle">In setup</text>
            <rect x="14" y="172" width="188" height="36" rx="8" fill="#F6F9FC"/>
            <text x="28" y="195" font-weight="600">Invoices</text>
            <rect x="14" y="216" width="188" height="36" rx="8" fill="#F6F9FC"/>
            <text x="28" y="239" font-weight="600">Support</text>
        </g>
    </g>

    {{-- Platform core --}}
    <path d="M240 164H270" stroke="#2563EB" stroke-width="2" marker-end="url(#home-arrow)"/>
    <g transform="translate(276 92)">
        <rect width="132" height="148" rx="16" fill="#0B1736"/>
        <path d="M40 30h28a22 22 0 0 1 0 44H40Z" fill="none" stroke="#fff" stroke-width="6" stroke-linejoin="round"/>
        <path d="M30 42h18M24 52h20M30 62h14" stroke="#60A5FA" stroke-width="4" stroke-linecap="round"/>
        <text x="66" y="100" font-size="13" font-weight="700" fill="#fff" text-anchor="middle">DeveloperJet</text>
        <text x="66" y="118" font-size="11" fill="#B6C3D6" text-anchor="middle">Billing · Assignment</text>
        <text x="66" y="134" font-size="11" fill="#B6C3D6" text-anchor="middle">Deployment</text>
    </g>

    {{-- Connections to servers --}}
    <g fill="none" stroke="#2563EB" stroke-width="2">
        <path d="M408 140C430 140 426 74 444 74" marker-end="url(#home-arrow)"/>
        <path d="M408 172C426 172 428 178 444 178" marker-end="url(#home-arrow)"/>
        <path d="M408 192C430 192 426 282 444 282" marker-end="url(#home-arrow)"/>
    </g>

    {{-- Dedicated servers --}}
    <g font-size="12">
        <g transform="translate(450 28)">
            <rect width="128" height="92" rx="12" fill="#fff" stroke="#D5DEEA"/>
            <text x="14" y="24" font-size="10" font-weight="700" fill="#5B6B82" letter-spacing=".5">DEDICATED VPS</text>
            <rect x="14" y="36" width="24" height="24" rx="6" fill="#2563EB"/>
            <path d="M20 43l4 4-4 4M27 51h5" stroke="#fff" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            <text x="46" y="52" font-weight="700" fill="#17243B">Hermes</text>
            <circle cx="20" cy="76" r="4" fill="#16A34A"/><text x="30" y="80" font-size="11" fill="#5B6B82">Deployed</text>
        </g>
        <g transform="translate(450 132)">
            <rect width="128" height="92" rx="12" fill="#fff" stroke="#D5DEEA"/>
            <text x="14" y="24" font-size="10" font-weight="700" fill="#5B6B82" letter-spacing=".5">DEDICATED VPS</text>
            <rect x="14" y="36" width="24" height="24" rx="6" fill="#0B1736"/>
            <path d="M19 45h5M30 45h5M24 45c3 0 3 8 6 8" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round"/>
            <text x="46" y="52" font-weight="700" fill="#17243B">n8n</text>
            <circle cx="20" cy="76" r="4" fill="#16A34A"/><text x="30" y="80" font-size="11" fill="#5B6B82">Deployed</text>
        </g>
        <g transform="translate(450 236)">
            <rect width="128" height="92" rx="12" fill="#fff" stroke="#D5DEEA"/>
            <text x="14" y="24" font-size="10" font-weight="700" fill="#5B6B82" letter-spacing=".5">DEDICATED VPS</text>
            <rect x="14" y="36" width="24" height="24" rx="6" fill="#1D4ED8"/>
            <path d="M22 43l-4 5 4 5M30 43l4 5-4 5" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            <text x="46" y="52" font-weight="700" fill="#17243B">Claude Code</text>
            <circle cx="20" cy="76" r="4" fill="#F59E0B"/><text x="30" y="80" font-size="11" fill="#5B6B82">Installing</text>
        </g>
    </g>

    {{-- Honest delivery note --}}
    <g transform="translate(24 336)">
        <rect width="554" height="128" rx="16" fill="#0B1736"/>
        <text x="24" y="36" font-size="11" font-weight="700" fill="#93B4F5" letter-spacing=".6">HOW DELIVERY WORKS</text>
        <g font-size="13" font-weight="600" fill="#fff">
            <circle cx="36" cy="68" r="12" fill="#2563EB"/><text x="36" y="72" text-anchor="middle" font-size="11">1</text>
            <text x="56" y="72">Order &amp; pay</text>
            <circle cx="200" cy="68" r="12" fill="#2563EB"/><text x="200" y="72" text-anchor="middle" font-size="11">2</text>
            <text x="220" y="72">Environment prepared</text>
            <circle cx="36" cy="106" r="12" fill="#2563EB"/><text x="36" y="110" text-anchor="middle" font-size="11">3</text>
            <text x="56" y="110">Automated setup</text>
            <circle cx="200" cy="106" r="12" fill="#2563EB"/><text x="200" y="110" text-anchor="middle" font-size="11">4</text>
            <text x="220" y="110">Access in dashboard</text>
        </g>
        <path d="M380 50v66" stroke="#1E3462"/>
        <text x="400" y="72" font-size="12" font-weight="700" fill="#fff">Not instant</text>
        <text x="400" y="92" font-size="12" fill="#B6C3D6">A person assigns</text>
        <text x="400" y="110" font-size="12" fill="#B6C3D6">every server.</text>
    </g>
</svg>
