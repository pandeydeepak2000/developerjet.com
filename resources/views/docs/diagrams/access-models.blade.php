<svg viewBox="0 0 760 190" role="img" aria-labelledby="dg-acc-t dg-acc-d" xmlns="http://www.w3.org/2000/svg" font-family="Inter, system-ui, sans-serif">
    <title id="dg-acc-t">Self-hosted: our part and your part</title>
    <desc id="dg-acc-d">DeveloperJet provides the VPS and installs your software, then hands it over. You get SSH root access and look after updates, firewall, your data and backups.</desc>
    @foreach ([[10, 'DeveloperJet does', 'Every plan', ['Provides a VPS dedicated to you', 'Installs your software', 'Hands over SSH / root access']], [390, 'You do', 'After handover', ['Use and configure the server', 'Keep updates and firewall current', 'Keep your own backups']]] as [$x, $title, $sub, $rows])
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
