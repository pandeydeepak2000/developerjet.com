<svg viewBox="0 0 760 150" role="img" aria-labelledby="dg-renew-t dg-renew-d" xmlns="http://www.w3.org/2000/svg" font-family="Inter, system-ui, sans-serif">
    <title id="dg-renew-t">Renewal timeline</title>
    <desc id="dg-renew-d">Before the due date we send a reminder and create a renewal order. You pay it yourself. If the due date passes unpaid the subscription becomes past due and an overdue notice is sent.</desc>
    <line x1="40" y1="70" x2="720" y2="70" stroke="#C9D6E8" stroke-width="4" stroke-linecap="round"/>
    <line x1="40" y1="70" x2="470" y2="70" stroke="#2563EB" stroke-width="4" stroke-linecap="round"/>
    @php $marks = [[70, 'Reminder sent', 'Before the due date', '#2563EB'], [270, 'Renewal order', 'Pay from your dashboard', '#2563EB'], [470, 'Due date', 'Paid = service continues', '#15803D'], [670, 'Past due', 'Overdue notice', '#B45309']]; @endphp
    @foreach ($marks as [$x, $label, $sub, $colour])
        <circle cx="{{ $x }}" cy="70" r="11" fill="#fff" stroke="{{ $colour }}" stroke-width="4"/>
        <text x="{{ $x }}" y="38" text-anchor="middle" font-size="14" font-weight="600" fill="#0B1736">{{ $label }}</text>
        <text x="{{ $x }}" y="106" text-anchor="middle" font-size="12" fill="#52627A">{{ $sub }}</text>
    @endforeach
</svg>
<figcaption>Nothing is charged automatically. The reminder window is set by DeveloperJet and may change.</figcaption>
