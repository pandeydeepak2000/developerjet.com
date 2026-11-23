@extends('layouts.admin')

@section('title', 'Exports')
@section('heading', 'Exports')

@section('content')
    <section class="admin-card">
        <p class="muted small">CSV files (UTF-8, opens in Excel, Google Sheets and LibreOffice). Exports never include passwords, tokens, API keys, server credentials, payment payloads, ticket message bodies or private notes, phone numbers or addresses. Each download is recorded in the audit log. Native XLSX is not available yet.</p>
    </section>
    <div class="admin-export-grid">
        @foreach ($types as $key => $t)
            <form class="admin-card admin-form" method="GET" action="{{ route('admin.exports.download', $key) }}">
                <h2>{{ $t['label'] }}</h2>
                <div class="admin-form-grid">
                    <label>From<input type="date" name="from"></label>
                    <label>To<input type="date" name="to"></label>
                    @if (in_array($key, ['orders', 'invoices', 'renewals', 'subscriptions', 'tickets', 'deployments'], true))
                        <label class="span-2">Status (optional)<input name="status" pattern="[a-z_]+" maxlength="30" placeholder="e.g. paid, unpaid, active, open"></label>
                    @endif
                </div>
                <button class="admin-btn admin-btn-quiet" type="submit">Download CSV</button>
            </form>
        @endforeach
    </div>
@endsection
