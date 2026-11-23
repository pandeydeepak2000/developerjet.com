@php
    $snap = $invoice->billing_snapshot ?? [];
    $company = $snap['company'] ?? [];
    $customer = $snap['customer'] ?? [];
    $isCredit = $invoice->type === 'credit_note';
    $m = fn ($v) => \App\Support\Money::inr($v);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $invoice->number }}</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #0f172a; }
    h1 { font-size: 22px; margin: 0; }
    .muted { color: #64748b; }
    table { width: 100%; border-collapse: collapse; }
    .items th { text-align: left; background: #f1f5f9; padding: 8px; font-size: 10px; text-transform: uppercase; }
    .items td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
    .right { text-align: right; }
    .totals td { padding: 4px 8px; }
    .badge { display: inline-block; padding: 3px 10px; border: 1px solid #0f172a; border-radius: 10px; font-size: 10px; }
</style>
</head>
<body>
<table>
    <tr>
        <td>
            <h1>{{ $isCredit ? 'Credit note' : 'Tax invoice' }}</h1>
            <p class="muted">No. {{ $invoice->number }}<br>
            Issued {{ $invoice->issued_at?->format('d M Y') }}
            @if ($invoice->status === 'unpaid' && $invoice->due_at) · <strong>Due {{ $invoice->due_at->format('d M Y') }}</strong> @endif
            @if ($invoice->period_start)<br>Service period {{ $invoice->period_start->format('d M Y') }} – {{ $invoice->period_end?->format('d M Y') }}@endif
            @if ($invoice->order) · Order {{ $invoice->order->number }} @endif
            @if ($isCredit && $invoice->related_invoice_id) · Against invoice {{ \App\Models\Invoice::find($invoice->related_invoice_id)?->number }} @endif</p>
        </td>
        <td class="right">
            <strong>{{ $company['trade_name'] ?? 'DeveloperJet' }}</strong><br>
            {{ $company['name'] ?? '' }}<br>
            @if (!empty($company['address'])){!! nl2br(e($company['address'])) !!}<br>@endif
            @if (!empty($company['gstin']))GSTIN: {{ $company['gstin'] }}<br>@endif
            @if (!empty($company['email'])){{ $company['email'] }}@endif
        </td>
    </tr>
</table>
<br>
<table>
    <tr>
        <td>
            <strong>Bill to</strong><br>
            {{ $customer['billing_name'] ?? $customer['name'] ?? $invoice->user->name }}<br>
            {{ $customer['email'] ?? $invoice->user->email }}<br>
            @if (!empty($customer['billing_address'])){!! nl2br(e($customer['billing_address'])) !!}<br>@endif
            @if (!empty($customer['gstin']))GSTIN: {{ $customer['gstin'] }}@endif
        </td>
        <td class="right"><span class="badge">{{ $isCredit ? 'CREDITED' : strtoupper($invoice->status) }}</span></td>
    </tr>
</table>
<br>
<table class="items">
    <thead><tr><th>Description</th><th class="right">Amount</th><th class="right">Discount</th><th class="right">Tax</th><th class="right">Total</th></tr></thead>
    <tbody>
    @foreach ($invoice->items as $item)
        <tr>
            <td>{{ $item->description }}</td>
            <td class="right">{{ $m($item->unit_minor * $item->quantity) }}</td>
            <td class="right">{{ $m($item->discount_minor) }}</td>
            <td class="right">{{ $m($item->tax_minor) }}</td>
            <td class="right">{{ $m($item->total_minor) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<br>
<table class="totals" style="width: 55%; margin-left: 45%;">
    <tr><td>Subtotal</td><td class="right">{{ $m($invoice->subtotal_minor) }}</td></tr>
    <tr><td>Discount</td><td class="right">− {{ $m($invoice->discount_minor) }}</td></tr>
    <tr><td>Tax</td><td class="right">{{ $m($invoice->tax_minor) }}</td></tr>
    <tr><td><strong>Total</strong></td><td class="right"><strong>{{ $m($invoice->total_minor) }}</strong></td></tr>
</table>
@if (!$isCredit && $invoice->status === 'unpaid')
    <p><strong>Payment status: UNPAID.</strong> {{ $invoice->payment_info['instructions'] ?? '' }}@if (!empty($invoice->payment_info['pay_url']))<br>Renew online: {{ $invoice->payment_info['pay_url'] }}@endif</p>
@elseif (!$isCredit && $invoice->payment_info)
    <p class="muted">Payment: {{ ucfirst($invoice->payment_info['method'] ?? 'n/a') }}
        @if (!empty($invoice->payment_info['wallet_applied_minor'])) · Wallet applied {{ $m($invoice->payment_info['wallet_applied_minor']) }} @endif
        @if ($invoice->paid_at) · Paid {{ $invoice->paid_at->format('d M Y') }} @endif</p>
@endif
@if (($invoice->tax_minor ?? 0) === 0)
    <p class="muted">Tax shown is as configured by the issuer at the time of invoicing.</p>
@endif
</body>
</html>
