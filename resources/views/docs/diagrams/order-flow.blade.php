<svg viewBox="0 0 760 150" role="img" aria-labelledby="dg-order-t dg-order-d" xmlns="http://www.w3.org/2000/svg" font-family="Inter, system-ui, sans-serif">
    <title id="dg-order-t">From order to access</title>
    <desc id="dg-order-d">Five steps: you pay, the payment is verified on our servers, our team prepares your environment, the software is installed and checked, and access details appear in your dashboard.</desc>
    @php $steps = [['1', 'You pay', 'Checkout'], ['2', 'Payment verified', 'On our servers'], ['3', 'Environment', 'Set up for you'], ['4', 'Install & check', 'By our team'], ['5', 'Access ready', 'In your dashboard']]; @endphp
    @foreach ($steps as $i => [$n, $label, $sub])
        @php $x = 10 + $i * 150; @endphp
        <rect x="{{ $x }}" y="30" width="130" height="90" rx="12" fill="{{ $i === 2 ? '#FFF7ED' : '#F6F9FC' }}" stroke="{{ $i === 2 ? '#B45309' : '#C9D6E8' }}"/>
        <circle cx="{{ $x + 24 }}" cy="56" r="13" fill="#2563EB"/>
        <text x="{{ $x + 24 }}" y="61" text-anchor="middle" font-size="13" font-weight="700" fill="#fff">{{ $n }}</text>
        <text x="{{ $x + 14 }}" y="92" font-size="14" font-weight="600" fill="#0B1736">{{ $label }}</text>
        <text x="{{ $x + 14 }}" y="110" font-size="12" fill="#52627A">{{ $sub }}</text>
        @if ($i < 4)<path d="M{{ $x + 133 }} 75h12m-5-5 5 5-5 5" stroke="#2563EB" stroke-width="2" fill="none"/>@endif
    @endforeach
</svg>
<figcaption>Delivery is not instant: step 3 is done by our team, so allow time for your environment to be prepared.</figcaption>
