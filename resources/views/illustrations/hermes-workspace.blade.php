<svg class="dj-ill" viewBox="0 0 600 460" role="img" aria-labelledby="ill-hws-title" xmlns="http://www.w3.org/2000/svg" font-family="Inter Variable, Inter, system-ui, sans-serif">
    <title id="ill-hws-title">{{ $alt ?? 'Hermes Workspace in a browser, running with Docker on a dedicated VPS.' }}</title>
    <rect width="600" height="460" rx="20" fill="#F6F9FC"/>

    {{-- Browser window --}}
    <g transform="translate(32 28)">
        <rect width="536" height="300" rx="14" fill="#fff" stroke="#D5DEEA"/>
        <path d="M0 14a14 14 0 0 1 14-14h508a14 14 0 0 1 14 14v26H0Z" fill="#EEF3F9"/>
        <circle cx="20" cy="20" r="5" fill="#C5D0DF"/><circle cx="38" cy="20" r="5" fill="#C5D0DF"/><circle cx="56" cy="20" r="5" fill="#C5D0DF"/>
        <rect x="118" y="9" width="300" height="22" rx="11" fill="#fff" stroke="#D5DEEA"/>
        <rect x="132" y="15" width="8" height="8" rx="2" fill="none" stroke="#16A34A" stroke-width="1.6"/>
        <text x="148" y="24" font-size="11" fill="#5B6B82">workspace.your-domain.com</text>

        {{-- Sidebar --}}
        <rect x="0" y="40" width="132" height="260" fill="#F6F9FC"/>
        <path d="M132 40V300" stroke="#E3E9F1"/>
        <text x="18" y="70" font-size="13" font-weight="700" fill="#0B1736">Hermes</text>
        <rect x="12" y="84" width="108" height="30" rx="8" fill="#DBEAFE"/>
        <text x="26" y="104" font-size="12" font-weight="600" fill="#1D4ED8">Chats</text>
        <text x="26" y="138" font-size="12" fill="#5B6B82">Files</text>
        <text x="26" y="166" font-size="12" fill="#5B6B82">Settings</text>

        {{-- Conversation --}}
        <rect x="298" y="60" width="218" height="34" rx="10" fill="#2563EB"/>
        <text x="314" y="82" font-size="12" fill="#fff">Summarise this week's notes</text>
        <rect x="152" y="110" width="300" height="78" rx="10" fill="#F6F9FC" stroke="#E3E9F1"/>
        <rect x="168" y="126" width="200" height="8" rx="4" fill="#C5D0DF"/>
        <rect x="168" y="142" width="262" height="8" rx="4" fill="#C5D0DF"/>
        <rect x="168" y="158" width="150" height="8" rx="4" fill="#C5D0DF"/>
        <rect x="168" y="172" width="90" height="6" rx="3" fill="#93B4F5"/>
        <rect x="380" y="204" width="136" height="30" rx="10" fill="#2563EB"/>
        <text x="396" y="224" font-size="12" fill="#fff">Make it a list</text>
        <rect x="152" y="252" width="364" height="34" rx="9" fill="#fff" stroke="#D5DEEA"/>
        <text x="168" y="274" font-size="12" fill="#8A98AD">Message Hermes…</text>
        <circle cx="498" cy="269" r="11" fill="#2563EB"/>
        <path d="M493 269h10M499 264l5 5-5 5" stroke="#fff" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
    </g>

    <path d="M300 328V352" stroke="#2563EB" stroke-width="2"/>
    <circle cx="300" cy="352" r="4" fill="#2563EB"/>

    {{-- Server strip --}}
    <g transform="translate(32 356)">
        <rect width="536" height="76" rx="14" fill="#0B1736"/>
        <rect x="18" y="18" width="208" height="40" rx="9" fill="#13254F" stroke="#2C4A80"/>
        <path d="M34 30l10-5 10 5v12l-10 5-10-5Z M34 30l10 5 10-5 M44 35v12" fill="none" stroke="#60A5FA" stroke-width="1.6" stroke-linejoin="round"/>
        <text x="66" y="43" font-size="12" font-weight="600" fill="#fff">Hermes · Docker container</text>
        <rect x="240" y="18" width="78" height="40" rx="9" fill="#13254F" stroke="#2C4A80"/>
        <text x="279" y="43" font-size="12" font-weight="600" fill="#fff" text-anchor="middle">HTTPS</text>
        <text x="518" y="35" font-size="13" font-weight="700" fill="#fff" text-anchor="end">Dedicated VPS</text>
        <text x="518" y="53" font-size="12" fill="#B6C3D6" text-anchor="end">Set up by DeveloperJet</text>
    </g>
</svg>
