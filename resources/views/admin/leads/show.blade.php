@extends('layouts.admin')

@section('title', $lead->reference)
@section('heading', $lead->reference.' · '.$lead->name)
@section('actions')<span class="badge">{{ $lead->statusLabel() }}</span>@endsection

@section('content')
    @php
        $u = auth()->user();
        $canEdit = $u->hasPermission('leads.edit');
        $canAssign = $u->hasPermission('leads.assign');
        $closed = in_array($lead->status, ['converted', 'closed'], true);
    @endphp

    @if ($lead->redacted)
        <div class="flash flash-info" role="status">Something that looked like a password or API key was removed from this request before it was saved. Never ask the customer to send credentials by form, email or Telegram.</div>
    @endif

    <div class="admin-grid-2">
        <section class="admin-card" aria-labelledby="lead-contact">
            <h2 id="lead-contact">Customer</h2>
            <dl class="kv">
                <div><dt>Name</dt><dd>{{ $lead->name }}</dd></div>
                <div><dt>Email</dt><dd><a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></dd></div>
                <div><dt>Phone</dt><dd><a href="tel:{{ $lead->phone }}">{{ $lead->phoneDisplay() }}</a></dd></div>
                <div><dt>Company</dt><dd>{{ $lead->company ?: '—' }}</dd></div>
                <div><dt>Website</dt><dd>@if ($lead->website)<a href="{{ $lead->website }}" target="_blank" rel="noopener noreferrer nofollow">{{ $lead->website }}</a>@else — @endif</dd></div>
                <div><dt>Account</dt><dd>
                    @if ($lead->user)
                        Customer account #{{ $lead->user->id }}
                    @else
                        Guest enquiry
                        @if ($matchingAccount && $canEdit)
                            <form method="POST" action="{{ route('admin.leads.link-account', $lead) }}" class="admin-inline-form">@csrf<button class="admin-btn admin-btn-quiet" type="submit">Link to verified account with this email</button></form>
                        @endif
                    @endif
                </dd></div>
                <div><dt>Received</dt><dd>{{ $lead->created_at->format('d M Y H:i') }}</dd></div>
            </dl>
        </section>

        <section class="admin-card" aria-labelledby="lead-owner">
            <h2 id="lead-owner">Ownership &amp; follow-up</h2>
            <dl class="kv">
                <div><dt>Status</dt><dd>{{ $lead->statusLabel() }}</dd></div>
                <div><dt>Sales owner</dt><dd>{{ $lead->assignee?->name ?? 'Unassigned' }}</dd></div>
                <div><dt>Technical owner</dt><dd>{{ $lead->technicalOwner?->name ?? '—' }}</dd></div>
                <div><dt>Follow-up</dt><dd>{{ $lead->followUpOwner?->name ?? '—' }}{{ $lead->follow_up_at ? ' · '.$lead->follow_up_at->format('d M Y H:i') : '' }}</dd></div>
                @if ($lead->project)<div><dt>Project</dt><dd>@if ($u->hasPermission('projects.view'))<a href="{{ route('admin.projects.show', $lead->project) }}">{{ $lead->project->number }}</a>@else {{ $lead->project->number }} @endif</dd></div>@endif
            </dl>
            @if ($canAssign && ! $closed)
                <form method="POST" action="{{ route('admin.leads.assign', $lead) }}" class="admin-form">@csrf
                    <div class="admin-form-grid">
                        <label>Sales owner<select name="assigned_to"><option value="">Unassigned</option>@foreach ($staffList as $s)<option value="{{ $s->id }}" @selected($lead->assigned_to === $s->id)>{{ $s->name }} · {{ $s->staffRole?->name }}</option>@endforeach</select></label>
                        <label>Technical owner<select name="technical_owner_id"><option value="">None</option>@foreach ($staffList as $s)<option value="{{ $s->id }}" @selected($lead->technical_owner_id === $s->id)>{{ $s->name }} · {{ $s->staffRole?->name }}</option>@endforeach</select></label>
                    </div>
                    <button class="admin-btn" type="submit">Save assignment</button>
                </form>
            @endif
            @if ($canEdit && ! $closed)
                <details><summary>Set follow-up</summary>
                    <form method="POST" action="{{ route('admin.leads.follow-up', $lead) }}" class="admin-form">@csrf
                        <div class="admin-form-grid">
                            <label>Owner<select name="owner_id"><option value="">None</option>@foreach ($staffList as $s)@if ($canAssign || $s->id === $u->id || $s->id === $lead->assigned_to)<option value="{{ $s->id }}" @selected($lead->follow_up_owner_id === $s->id)>{{ $s->name }}</option>@endif @endforeach</select></label>
                            <label>Date &amp; time<input type="datetime-local" name="follow_up_at" value="{{ $lead->follow_up_at?->format('Y-m-d\TH:i') }}"></label>
                        </div>
                        <label>Note (internal)<input type="text" name="note" maxlength="1000"></label>
                        <button class="admin-btn admin-btn-quiet" type="submit">Save follow-up</button>
                    </form>
                </details>
            @endif
        </section>
    </div>

    <section class="admin-card" aria-labelledby="lead-req">
        <h2 id="lead-req">Requirements</h2>
        <dl class="kv">
            <div><dt>Business type</dt><dd>{{ $lead->business_type }}</dd></div>
            <div><dt>Business size</dt><dd>{{ \App\Models\AutomationRequest::BUSINESS_SIZES[$lead->business_size] ?? '—' }}</dd></div>
            <div><dt>Plan of interest</dt><dd>{{ $lead->plan?->name ?? '—' }}</dd></div>
            <div><dt>Requirements</dt><dd>{!! nl2br(e($lead->requirements)) !!}</dd></div>
            <div><dt>Current tools</dt><dd>{!! $lead->current_tools ? nl2br(e($lead->current_tools)) : '—' !!}</dd></div>
            <div><dt>Services to integrate</dt><dd>{!! $lead->integrations ? nl2br(e($lead->integrations)) : '—' !!}</dd></div>
            <div><dt>Timeline</dt><dd>{{ \App\Models\AutomationRequest::TIMELINES[$lead->timeline] ?? '—' }}</dd></div>
            <div><dt>Budget</dt><dd>{{ \App\Models\AutomationRequest::BUDGETS[$lead->budget] ?? '—' }}</dd></div>
            <div><dt>Notes</dt><dd>{!! $lead->notes ? nl2br(e($lead->notes)) : '—' !!}</dd></div>
        </dl>
    </section>

    @if ($canEdit)
        <div class="admin-grid-2">
            <section class="admin-card" aria-labelledby="lead-status">
                <h2 id="lead-status">Status</h2>
                @if ($transitions)
                    <form method="POST" action="{{ route('admin.leads.status', $lead) }}" class="admin-form">@csrf
                        <label>Move to<select name="status">@foreach ($transitions as $t)<option value="{{ $t }}">{{ \App\Support\LeadStatus::label($t) }}</option>@endforeach</select></label>
                        <label>Internal note (optional)<textarea name="note" rows="2" maxlength="2000"></textarea></label>
                        <button class="admin-btn" type="submit">Change status</button>
                        <p class="muted small">“Quotation sent”, “Customer accepted/declined”, “Converted” and “Closed” email the customer a short update. Record acceptance only when the customer has actually confirmed it. Acceptance never creates an order or payment.</p>
                    </form>
                @else
                    <p class="muted">No further status changes are available.</p>
                @endif
                @if ($lead->status === 'accepted')
                    @if ($u->hasPermission('projects.create'))
                        <form method="POST" action="{{ route('admin.leads.convert', $lead) }}" class="admin-form">@csrf
                            <button class="admin-btn" type="submit" @disabled(! $lead->user_id)>Convert to project</button>
                            @unless ($lead->user_id)<p class="muted small">The customer needs a verified DeveloperJet account with this email before the project can be opened.</p>@endunless
                        </form>
                    @else
                        <p class="muted small">Converting to a project needs the projects.create permission.</p>
                    @endif
                @endif
            </section>

            <section class="admin-card" aria-labelledby="lead-quote">
                <h2 id="lead-quote">Quotation</h2>
                @if ($lead->quote_summary)
                    <p>Amount: <strong>{{ $lead->quote_minor !== null ? \App\Support\Money::inr($lead->quote_minor) : 'Not set' }}</strong> · prepared {{ $lead->quoted_at?->format('d M Y') }}@if ($lead->quote_sent_at) · sent {{ $lead->quote_sent_at->format('d M Y') }}@endif</p>
                    <p class="muted">{!! nl2br(e($lead->quote_summary)) !!}</p>
                @endif
                @if (in_array($lead->status, ['reviewing', 'assigned', 'requirements_discussion', 'quotation_prepared', 'quotation_sent'], true))
                    <form method="POST" action="{{ route('admin.leads.quote', $lead) }}" class="admin-form">@csrf
                        <label>Amount in ₹ (optional, excl. tax)<input type="number" name="amount" min="0" step="0.01" value="{{ $lead->quote_minor !== null ? $lead->quote_minor / 100 : '' }}"></label>
                        <label>Scope summary (shown to the customer once sent)<textarea name="summary" rows="4" maxlength="5000" required>{{ $lead->quote_summary }}</textarea></label>
                        <button class="admin-btn admin-btn-quiet" type="submit">{{ $lead->quote_summary ? 'Revise quotation' : 'Prepare quotation' }}</button>
                    </form>
                @endif
            </section>
        </div>

        @unless ($closed)
            <div class="admin-grid-2">
                <section class="admin-card" aria-labelledby="lead-note">
                    <h2 id="lead-note">Internal note or contact log</h2>
                    <form method="POST" action="{{ route('admin.leads.note', $lead) }}" class="admin-form">@csrf
                        <div class="admin-form-grid">
                            <label>Type<select name="type"><option value="note">Internal note</option><option value="contact">Contacted the customer</option></select></label>
                            <label>Channel (contact only)<select name="channel"><option value="">—</option><option value="phone">Phone</option><option value="email">Email</option><option value="whatsapp">WhatsApp</option><option value="meeting">Meeting</option><option value="other">Other</option></select></label>
                        </div>
                        <label>Details<textarea name="body" rows="3" maxlength="4000" required></textarea></label>
                        <button class="admin-btn admin-btn-quiet" type="submit">Save (never shown to the customer)</button>
                    </form>
                </section>
                <section class="admin-card" aria-labelledby="lead-info">
                    <h2 id="lead-info">Request more information</h2>
                    <form method="POST" action="{{ route('admin.leads.request-info', $lead) }}" class="admin-form">@csrf
                        <label>Message to the customer<textarea name="message" rows="3" maxlength="3000" required></textarea></label>
                        <p class="muted small">Emailed to {{ $lead->email }} and shown in their dashboard. Never ask for passwords or API keys.</p>
                        <button class="admin-btn admin-btn-quiet" type="submit">Send request</button>
                    </form>
                </section>
            </div>
        @endunless
    @endif

    <section class="admin-card" aria-labelledby="lead-history">
        <h2 id="lead-history">History</h2>
        <ol class="admin-timeline">
            @foreach ($lead->events as $e)
                <li>
                    <span class="muted small">{{ $e->created_at->format('d M Y H:i') }} · {{ $e->user?->name ?? 'System' }} · {{ \Illuminate\Support\Str::headline($e->type) }}</span>
                    <span class="badge {{ $e->visibility === 'customer' ? 'badge-good' : '' }}">{{ $e->visibility === 'customer' ? 'Visible to customer' : 'Internal' }}</span>
                    @if ($e->to_status)<span class="small">{{ $e->from_status ? \App\Support\LeadStatus::label($e->from_status).' → ' : '' }}{{ \App\Support\LeadStatus::label($e->to_status) }}</span>@endif
                    @if ($e->body)<p>{!! nl2br(e($e->body)) !!}</p>@endif
                </li>
            @endforeach
        </ol>
    </section>
@endsection
