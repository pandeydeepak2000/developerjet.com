<svg viewBox="0 0 760 190" role="img" aria-labelledby="dg-acc-t dg-acc-d" xmlns="http://www.w3.org/2000/svg" font-family="Inter, system-ui, sans-serif">
    <title id="dg-acc-t">Managed and unmanaged responsibilities</title>
    <desc id="dg-acc-d">Managed: DeveloperJet sets up and secures the server and root access is restricted; you use the application. Unmanaged: DeveloperJet provides the server and Docker; you get SSH root access and manage updates, firewall and containers.</desc>
    @foreach ([[10, 'Managed hosting', 'Hermes, n8n, OpenClaw, Claude Code, Bundle', ['We set up and secure the server', 'Root access restricted by policy', 'You use the application']], [390, 'Unmanaged VPS', 'Docker VPS', ['We hand over server + Docker', 'You get SSH / root access', 'You manage updates and firewall']]] as [$x, $title, $sub, $rows])
        <rect x="{{ $x }}" y="10" width="360" height="170" rx="14" fill="#F6F9FC" stroke="#C9D6E8"/>
        <text x="{{ $x + 22 }}" y="44" font-size="16" font-weight="700" fill="#0B1736">{{ $title }}</text>
        <text x="{{ $x + 22 }}" y="66" font-size="12" fill="#52627A">{{ $sub }}</text>
        @foreach ($rows as $i => $row)
            <path d="M{{ $x + 24 }} {{ 98 + $i * 26 }}l5 5 9-10" stroke="#15803D" stroke-width="2.4" fill="none"/>
            <text x="{{ $x + 46 }}" y="{{ 103 + $i * 26 }}" font-size="13" fill="#17243B">{{ $row }}</text>
        @endforeach
    @endforeach
</svg>
<figcaption>Checkout always shows which access model you are buying.</figcaption>
