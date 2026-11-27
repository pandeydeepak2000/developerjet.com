<svg class="dj-ill" viewBox="0 0 600 460" role="img" aria-labelledby="ill-node-title" xmlns="http://www.w3.org/2000/svg" font-family="Inter Variable, Inter, system-ui, sans-serif">
    <title id="ill-node-title">{{ $alt ?? 'Your code goes from Git to your own VPS, where Caddy serves it over HTTPS and PM2 keeps your Node.js app running.' }}</title>
    <defs>
        <marker id="nd-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0 0 10 5 0 10Z" fill="#16A34A"/></marker>
    </defs>
    <rect width="600" height="460" rx="20" fill="#F6F9FC"/>

    {{-- Your code --}}
    <text x="28" y="44" font-size="12" font-weight="700" fill="#5B6B82">YOUR CODE</text>
    <g transform="translate(24 56)">
        <rect width="170" height="150" rx="12" fill="#0B1736"/>
        <text x="16" y="28" font-size="12" font-weight="700" fill="#fff">package.json</text>
        <path d="M0 42H170" stroke="#1E3462"/>
        <g font-family="ui-monospace, Consolas, monospace" font-size="11">
            <text x="16" y="66" fill="#86EFAC">"start":</text>
            <text x="28" y="84" fill="#E2E8F0">"node server.js"</text>
            <text x="16" y="108" fill="#86EFAC">"build":</text>
            <text x="28" y="126" fill="#E2E8F0">"next build"</text>
        </g>
    </g>
    <g transform="translate(24 230)">
        <rect width="170" height="46" rx="10" fill="#fff" stroke="#D5DEEA"/>
        <circle cx="26" cy="23" r="9" fill="none" stroke="#16A34A" stroke-width="2"/>
        <circle cx="26" cy="23" r="3" fill="#16A34A"/>
        <text x="46" y="28" font-size="13" font-weight="600" fill="#17243B">git push / clone</text>
    </g>
    <g transform="translate(24 296)" font-size="11" font-weight="600" fill="#17243B">
        <rect width="82" height="30" rx="8" fill="#fff" stroke="#D5DEEA"/><text x="14" y="20">Next.js</text>
        <rect x="88" width="82" height="30" rx="8" fill="#fff" stroke="#D5DEEA"/><text x="102" y="20">Express</text>
        <rect y="38" width="82" height="30" rx="8" fill="#fff" stroke="#D5DEEA"/><text x="14" y="58">NestJS</text>
        <rect x="88" y="38" width="82" height="30" rx="8" fill="#fff" stroke="#D5DEEA"/><text x="102" y="58">Nuxt</text>
    </g>

    <path d="M200 253H262" stroke="#16A34A" stroke-width="2.4" marker-end="url(#nd-arrow)"/>

    {{-- Your VPS --}}
    <g transform="translate(270 40)">
        <rect width="306" height="380" rx="16" fill="#fff" stroke="#D5DEEA" stroke-width="1.5"/>
        <text x="20" y="32" font-size="12" font-weight="700" fill="#5B6B82">YOUR VPS · FULL ROOT</text>

        <rect x="20" y="50" width="266" height="62" rx="12" fill="#ECFDF5" stroke="#A7F3D0"/>
        <path d="M44 72a8 8 0 0 1 16 0v6h-16Z" fill="none" stroke="#16A34A" stroke-width="2"/>
        <rect x="40" y="78" width="24" height="18" rx="3" fill="#16A34A"/>
        <text x="80" y="76" font-size="14" font-weight="700" fill="#14532D">Caddy</text>
        <text x="80" y="96" font-size="12" fill="#166534">https://your-app.com · auto SSL</text>

        <path d="M153 116v26" stroke="#16A34A" stroke-width="2" marker-end="url(#nd-arrow)"/>

        <rect x="20" y="148" width="266" height="62" rx="12" fill="#F0F9FF" stroke="#BAE6FD"/>
        <circle cx="52" cy="179" r="14" fill="none" stroke="#0284C7" stroke-width="2.4" stroke-dasharray="66 22"/>
        <text x="80" y="174" font-size="14" font-weight="700" fill="#0C4A6E">PM2</text>
        <text x="80" y="194" font-size="12" fill="#075985">restarts on crash · starts on boot</text>

        <path d="M153 214v26" stroke="#16A34A" stroke-width="2" marker-end="url(#nd-arrow)"/>

        <rect x="20" y="246" width="266" height="62" rx="12" fill="#0B1736"/>
        <path d="M52 262l14 8v16l-14 8-14-8v-16Z" fill="#16A34A"/>
        <text x="80" y="272" font-size="14" font-weight="700" fill="#fff">Node.js 22 LTS</text>
        <text x="80" y="292" font-size="12" fill="#A7F3D0">your app · npm · git</text>

        <g font-size="11" fill="#5B6B82">
            <text x="20" y="338">SSH: ssh root@your-server</text>
            <text x="20" y="358">App folder: /var/www/app</text>
        </g>
    </g>
</svg>
