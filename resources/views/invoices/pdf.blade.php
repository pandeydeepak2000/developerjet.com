@php
    $snap = $invoice->billing_snapshot ?? [];
    $company = $snap['company'] ?? [];
    $customer = $snap['customer'] ?? [];
    $isCredit = $invoice->type === 'credit_note';
    $isUnpaid = ! $isCredit && $invoice->status === 'unpaid';
    $m = fn ($v) => \App\Support\Money::inr($v);
    $pay = $invoice->payment_info ?? [];

    $logoPath = public_path('images/logo-icon.png');
    $logo = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : null;

    $statusLabel = $isCredit ? 'CREDIT NOTE' : strtoupper($invoice->status);
    $ribbonColor = match (true) {
        $isCredit => '#2563eb',
        $invoice->status === 'paid' => '#16a34a',
        $isUnpaid => '#dc2626',
        default => '#64748b',
    };

    $methodLabel = fn (?string $method) => match ($method) {
        'wallet' => 'DeveloperJet Wallet',
        'razorpay' => 'Razorpay (UPI / Card / Netbanking)',
        null, '' => 'Online payment',
        default => ucfirst($method),
    };

    // Transaction rows come only from what was recorded on the invoice: wallet part, then the online part.
    $transactions = [];
    if (! $isCredit && $invoice->status === 'paid') {
        $walletPart = (int) ($pay['wallet_applied_minor'] ?? 0);
        $online = max(0, (int) $invoice->total_minor - $walletPart);
        if ($walletPart > 0) {
            $transactions[] = ['method' => $methodLabel('wallet'), 'ref' => null, 'amount' => min($walletPart, (int) $invoice->total_minor)];
        }
        if ($online > 0 || $transactions === []) {
            $method = ($pay['method'] ?? null) === 'wallet' && $walletPart > 0 ? null : ($pay['method'] ?? null);
            $transactions[] = ['method' => $methodLabel($method), 'ref' => ($pay['gateway_payment_ids'] ?? [])[0] ?? null, 'amount' => $online > 0 ? $online : (int) $invoice->total_minor];
        }
    }

    $related = $isCredit && $invoice->related_invoice_id ? \App\Models\Invoice::find($invoice->related_invoice_id)?->number : null;
    $taxable = (int) $invoice->subtotal_minor - (int) $invoice->discount_minor;
    $gstRate = $taxable > 0 && $invoice->tax_minor > 0 ? round($invoice->tax_minor * 100 / $taxable) : 0;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $invoice->number }}</title>
<style>
    @page { size: A4; margin: 0; }
    * { margin: 0; padding: 0; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #0f172a; line-height: 1.45; }
    .page { padding: 34px 40px 0; position: relative; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .right { text-align: right; }
    .muted { color: #64748b; }
    .mono { font-family: 'DejaVu Sans Mono', monospace; }
    .caps { text-transform: uppercase; letter-spacing: 1px; font-size: 8px; font-weight: bold; color: #94a3b8; }

    .ribbon { position: absolute; top: 30px; right: -52px; width: 200px; padding: 6px 0; text-align: center;
        color: #fff; font-weight: bold; font-size: 10px; letter-spacing: 2px; transform: rotate(45deg); }

    .logo { width: 54px; height: 54px; border-radius: 12px; }
    .brand { font-size: 24px; font-weight: bold; letter-spacing: -0.5px; color: #0f172a; }
    .legal { font-size: 9.5px; color: #334155; font-weight: bold; }
    .addr { color: #64748b; font-size: 9px; }
    .pill { display: inline-block; margin-top: 6px; padding: 3px 9px; border: 1px solid #e2e8f0; border-radius: 6px;
        background: #f8fafc; font-size: 8.5px; font-weight: bold; }

    .doc-title { font-size: 12px; font-weight: bold; color: #0f172a; letter-spacing: 1px; text-transform: uppercase; margin: 20px 0 8px; }
    .box { border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; }
    .box td { padding: 4px 16px; }
    .box tr:first-child td { padding-top: 12px; }
    .box tr:last-child td { padding-bottom: 12px; }
    .box .label { color: #64748b; }
    .box .value { text-align: right; font-weight: bold; }

    .billto { margin-top: 18px; }
    .billto .name { font-size: 14px; font-weight: bold; text-transform: uppercase; margin: 3px 0 2px; }

    .card { border: 1px solid #e2e8f0; border-radius: 10px; margin-top: 16px; }
    .items th { background: #f1f5f9; text-align: left; padding: 9px 14px; font-size: 8px; text-transform: uppercase;
        letter-spacing: .8px; color: #334155; border-bottom: 1px solid #e2e8f0; }
    .items th:first-child { border-top-left-radius: 10px; }
    .items th:last-child { border-top-right-radius: 10px; }
    .items td { padding: 9px 14px; border-bottom: 1px solid #f1f5f9; }
    .items tr:last-child td { border-bottom: none; }
    .items .desc { font-weight: bold; }
    .items .sub { font-size: 8px; color: #64748b; font-weight: normal; }

    .totals { width: 52%; margin: 12px 0 0 48%; }
    .totals td { padding: 3px 14px; color: #64748b; }
    .totals td.right { color: #0f172a; font-weight: bold; }
    .totals .grand td { border-top: 1.5px solid #cbd5e1; padding-top: 8px; font-size: 15px; font-weight: bold; color: #0f172a; }

    .section { font-size: 10.5px; font-weight: bold; margin: 20px 0 0; }
    .tx th { text-align: left; padding: 8px 14px; font-size: 8px; text-transform: uppercase; letter-spacing: .8px; color: #64748b;
        border-bottom: 1px solid #e2e8f0; }
    .tx td { padding: 8px 14px; border-bottom: 1px solid #f1f5f9; }
    .method { display: inline-block; padding: 3px 9px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 8.5px; font-weight: bold; }
    .ok { color: #16a34a; font-weight: bold; }
    .items th.right, .tx th.right { text-align: right; }

    .due { margin-top: 18px; padding: 12px 16px; border: 1px solid #fecaca; background: #fef2f2; border-radius: 10px; }
    .note { margin-top: 14px; padding: 10px 14px; border-radius: 10px; background: #f8fafc; color: #64748b; font-size: 8.5px; }

    /* Many lines: tighter spacing so the invoice stays on one A4 page. */
    .compact .page { padding-top: 26px; }
    .compact .doc-title { margin: 12px 0 6px; }
    .compact .box td { padding: 2px 16px; }
    .compact .billto { margin-top: 12px; }
    .compact .items td { padding: 5px 14px; }
    .compact .items th, .compact .tx th { padding: 6px 14px; }
    .compact .tx td { padding: 5px 14px; }
    .compact .section { margin-top: 12px; }
    .compact .footer { margin-top: 14px; }

    .footer { margin-top: 26px; padding-top: 12px; border-top: 1px solid #e2e8f0; text-align: center; color: #64748b; font-size: 8.5px; }
</style>
</head>
<body class="{{ $invoice->items->count() > 4 ? 'compact' : '' }}">
<div class="ribbon" style="background: {{ $ribbonColor }};">{{ $isCredit ? 'CREDIT' : $statusLabel }}</div>
<div class="page">

    {{-- Issuer --}}
    <table>
        <tr>
            @if ($logo)
                <td style="width: 66px;"><img class="logo" src="{{ $logo }}" alt=""></td>
            @endif
            <td style="padding-right: 110px;">
                <div class="brand">{{ strtoupper($company['trade_name'] ?? 'DeveloperJet') }}</div>
                @if (! empty($company['name']))<div class="legal">{{ $company['name'] }}</div>@endif
                <div class="addr">
                    @if (! empty($company['address'])){!! nl2br(e($company['address'])) !!}<br>@endif
                    @if (! empty($company['email'])){{ $company['email'] }}@endif
                </div>
                @if (! empty($company['gstin']))<span class="pill">GSTIN: {{ $company['gstin'] }}</span>@endif
            </td>
        </tr>
    </table>

    {{-- Document details --}}
    <div class="doc-title">{{ $isCredit ? 'Credit Note' : 'Tax Invoice' }}</div>
    <table class="box">
        <tr><td class="label">{{ $isCredit ? 'Credit note no:' : 'Invoice no:' }}</td><td class="value mono">#{{ $invoice->number }}</td></tr>
        <tr><td class="label">Date:</td><td class="value">{{ $invoice->issued_at?->format('d-m-Y') }}</td></tr>
        @if ($invoice->order)
            <tr><td class="label">Order no:</td><td class="value mono">{{ $invoice->order->number }}</td></tr>
        @endif
        @if ($related)
            <tr><td class="label">Against invoice:</td><td class="value mono">#{{ $related }}</td></tr>
        @endif
        @if ($invoice->period_start)
            <tr><td class="label">Service period:</td><td class="value">{{ $invoice->period_start->format('d-m-Y') }} to {{ $invoice->period_end?->format('d-m-Y') }}</td></tr>
        @endif
        @if ($isUnpaid && $invoice->due_at)
            <tr><td class="label">Due date:</td><td class="value">{{ $invoice->due_at->format('d-m-Y') }}</td></tr>
        @endif
        <tr><td class="label">Status:</td><td class="value" style="color: {{ $ribbonColor }};">{{ $statusLabel }}</td></tr>
    </table>

    {{-- Customer --}}
    <div class="billto">
        <div class="caps">Invoiced to</div>
        <div class="name">{{ $customer['billing_name'] ?? $customer['name'] ?? $invoice->user?->name }}</div>
        <div class="addr">
            @if (! empty($customer['billing_contact']))Attn: {{ $customer['billing_contact'] }}<br>@endif
            @if (! empty($customer['billing_address'])){!! nl2br(e($customer['billing_address'])) !!}<br>@endif
            {{ $customer['email'] ?? $invoice->user?->email }}@if (! empty($customer['phone'])) · {{ $customer['phone'] }}@endif
        </div>
        @if (! empty($customer['gstin']))<div style="margin-top: 3px;"><strong>GSTIN:</strong> {{ $customer['gstin'] }}</div>@endif
    </div>

    {{-- Items --}}
    <div class="card">
        <table class="items">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right" style="width: 18%;">Rate</th>
                    <th class="right" style="width: 12%;">GST %</th>
                    <th class="right" style="width: 18%;">Amount</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($invoice->items as $item)
                @php
                    $gross = $item->unit_minor * $item->quantity;
                    $base = $gross - $item->discount_minor;
                    $rate = $base > 0 && $item->tax_minor > 0 ? round($item->tax_minor * 100 / $base) : 0;
                @endphp
                <tr>
                    <td class="desc">{{ $item->description }}
                        @if ($item->quantity > 1)<div class="sub">Qty {{ $item->quantity }} × {{ $m($item->unit_minor) }}</div>@endif
                        @if ($item->discount_minor > 0)<div class="sub">Discount − {{ $m($item->discount_minor) }}</div>@endif
                    </td>
                    <td class="right mono">{{ $m($gross) }}</td>
                    <td class="right">{{ $rate }}%</td>
                    <td class="right mono" style="font-weight: bold;">{{ $m($item->total_minor) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totals --}}
    <table class="totals">
        @if ($invoice->discount_minor > 0)
            <tr><td>Subtotal</td><td class="right mono">{{ $m($invoice->subtotal_minor) }}</td></tr>
            <tr><td>Discount</td><td class="right mono">− {{ $m($invoice->discount_minor) }}</td></tr>
        @endif
        <tr><td>Taxable amount</td><td class="right mono">{{ $m($taxable) }}</td></tr>
        <tr><td>GST amount{{ $gstRate ? ' ('.$gstRate.'%)' : '' }}</td><td class="right mono">{{ $m($invoice->tax_minor) }}</td></tr>
        <tr class="grand"><td>{{ $isCredit ? 'Total credited' : 'Grand total' }}</td><td class="right mono">{{ $m($invoice->total_minor) }}</td></tr>
    </table>

    {{-- Payment --}}
    @if ($transactions)
        <div class="section">Transaction history</div>
        <table class="tx">
            <thead><tr><th style="width: 20%;">Date</th><th>Payment method</th><th style="width: 24%;">Status</th><th class="right" style="width: 18%;">Amount</th></tr></thead>
            <tbody>
            @foreach ($transactions as $tx)
                <tr>
                    <td class="mono">{{ ($invoice->paid_at ?? $invoice->issued_at)?->format('d-m-Y') }}</td>
                    <td><span class="method">{{ $tx['method'] }}</span>@if ($tx['ref'])<div class="muted mono" style="font-size: 7.5px; margin-top: 2px;">Ref: {{ $tx['ref'] }}</div>@endif</td>
                    <td class="ok">✓ Paid &amp; Verified</td>
                    <td class="right mono" style="font-weight: bold;">{{ $m($tx['amount']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @elseif ($isUnpaid)
        <div class="due">
            <strong style="color: #dc2626;">Payment due: {{ $m($invoice->total_minor) }}</strong>@if ($invoice->due_at) by {{ $invoice->due_at->format('d-m-Y') }}@endif
            @if (! empty($pay['instructions']))<div style="margin-top: 4px;">{{ $pay['instructions'] }}</div>@endif
            @if (! empty($pay['pay_url']))<div style="margin-top: 4px;">Pay online: <span class="mono">{{ $pay['pay_url'] }}</span></div>@endif
        </div>
    @elseif ($isCredit)
        <div class="note">This credit note reverses the amount above{{ $related ? ' against invoice #'.$related : '' }}. The refund is sent to the original payment method or your DeveloperJet wallet.</div>
    @endif

    @if ((int) $invoice->tax_minor === 0 && ! $isCredit)
        <div class="note">No GST is charged on this invoice (order paid from the DeveloperJet wallet or tax not applicable).</div>
    @endif

    <div class="footer">
        This is a computer generated {{ $isCredit ? 'credit note' : 'invoice' }}, no signature required.<br>
        Thank you for choosing {{ $company['trade_name'] ?? 'DeveloperJet' }}@if (! empty($company['email'])) · Questions? {{ $company['email'] }}@endif
    </div>
</div>
</body>
</html>
