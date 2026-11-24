<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MailDeliveryService;
use App\Services\TaxService;
use App\Support\Audit;
use App\Support\Setting;
use App\Support\StaffSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /** @return array<string, array{title: string, note?: string, fields: array<int, array>}> */
    public const SUPER_ONLY_SECTIONS = ['security', 'smtp', 'telegram'];

    public static function schema(): array
    {
        $events = [];
        foreach (config('notifications.events') as $key => $label) {
            $events[] = ['notify.telegram.'.$key, $label.' (Telegram)', 'checkbox', 'nullable|boolean', null, true];
        }

        $templates = [];
        foreach (config('notifications.templates') as $key => [$subject, $body]) {
            $templates[] = ["mail.template.$key.subject", Str($key)->headline().' — subject', 'text', 'nullable|string|max:200', null, $subject];
            $templates[] = ["mail.template.$key.body", Str($key)->headline().' — body', 'textarea', 'nullable|string|max:4000', null, $body];
        }

        return [
            'company' => ['title' => 'Company & invoicing', 'fields' => [
                ['company.name', 'Legal company name', 'text', 'nullable|string|max:150', null, 'AmbitionHost Pvt. Ltd.'],
                ['company.address', 'Billing address', 'textarea', 'nullable|string|max:500'],
                ['company.gstin', 'Company GSTIN (shown on invoices only when set)', 'text', 'nullable|string|max:20'],
                ['company.email', 'Billing email', 'text', 'nullable|email|max:150'],
                ['invoice.prefix', 'Invoice prefix (e.g. AUN)', 'text', 'nullable|alpha_num|max:10', null, 'INV'],
                ['invoice.start_number', 'First invoice number (applies to a new prefix)', 'number', 'nullable|integer|min:1', null, 1],
                ['invoice.pad', 'Number padding (digits)', 'number', 'nullable|integer|min:1|max:10', null, 5],
                ['invoice.credit_prefix', 'Credit note prefix', 'text', 'nullable|alpha_num|max:10', null, 'CN'],
                ['sales.phone', 'Sales team contact number (shown on Add Funds)', 'text', 'nullable|string|max:40'],
            ]],
            'sales' => ['title' => 'Sales & lead notifications', 'note' => 'Business Automation requests are emailed to this address (queued) and to the Telegram admin chat. Suggested: support@developerjet.com — but enter it only after confirming the mailbox exists and receives mail, then use “Send test” → “Sales notification email”. “Sent” in the notification log means the mail transport accepted the message, not that it reached the inbox.', 'fields' => [
                ['sales.notify_email', 'Sales/Support notification email', 'text', 'nullable|email|max:150'],
                ['sales.notify_email_verified', 'An administrator has confirmed this mailbox receives mail', 'checkbox', 'nullable|boolean', null, false],
                ['automation.customer_ack', 'Email customers an acknowledgement with their request reference', 'checkbox', 'nullable|boolean', null, true],
            ]],
            'tax' => ['title' => 'Tax (pending professional review)', 'note' => 'Rates are percentages, e.g. 18. Leave blank when not yet decided — customers will see “tax not configured” rather than an assumed zero.', 'fields' => [
                ['tax.rate.standard', 'Standard rate (%)', 'text', 'nullable|numeric|between:0,100'],
                ['tax.rate.reduced', 'Reduced rate (%)', 'text', 'nullable|numeric|between:0,100'],
                ['tax.rate.wallet_topup', 'Wallet deposit rate (%)', 'text', 'nullable|numeric|between:0,100'],
                ['wallet.tax_class', 'Tax class applied to wallet deposits', 'select', 'nullable|in:'.implode(',', array_keys(TaxService::CLASSES)), TaxService::CLASSES, 'wallet_topup'],
            ]],
            'billing' => ['title' => 'Billing & renewals', 'note' => 'Applies to recurring hosting subscriptions. The daily run (billing:run, 08:00) is idempotent. Customers always pay renewal invoices themselves — nothing is auto-charged. Live suspension needs a verified provider adapter; none exists yet, so suspensions and reactivations become staff tasks in Admin → Billing.', 'fields' => [
                ['billing.invoice_days_before', 'Generate the renewal invoice this many days before the due date', 'number', 'nullable|integer|between:0,60', null, 7],
                ['billing.reminders_enabled', 'Send renewal reminder e-mails', 'checkbox', 'nullable|boolean', null, true],
                ['billing.reminder_offsets', 'Reminder days before the due date (comma separated, e.g. 7,3)', 'text', 'nullable|string|max:40|regex:/^[0-9 ,]*$/', null, '7,3'],
                ['billing.reminder_on_due_date', 'Send a reminder on the due date if unpaid', 'checkbox', 'nullable|boolean', null, true],
                ['billing.overdue_offsets', 'Overdue reminder days after the due date (comma separated; blank = none)', 'text', 'nullable|string|max:40|regex:/^[0-9 ,]*$/', null, '1'],
                ['billing.grace_days', 'Grace period after the due date (days)', 'number', 'nullable|integer|between:0,60', null, 3],
                ['billing.auto_suspend', 'Queue suspension automatically when the grace period ends (per service)', 'checkbox', 'nullable|boolean', null, false],
                ['billing.suspension_requires_approval', 'Suspensions need staff approval', 'checkbox', 'nullable|boolean', null, true],
                ['billing.live_suspension_enabled', 'Allow a verified provider adapter to suspend/reactivate (keep OFF until an adapter is verified and approved)', 'checkbox', 'nullable|boolean', null, false],
                ['billing.reactivation_policy', 'Reactivation after payment', 'select', 'nullable|in:auto,manual', ['auto' => 'Use a verified adapter when available, otherwise staff task', 'manual' => 'Always a staff task'], 'auto'],
                ['billing.payment_instructions', 'Payment instructions printed on renewal invoices', 'textarea', 'nullable|string|max:600'],
            ]],
            'lifecycle' => ['title' => 'Cancellation, expiry & add-on policy', 'note' => 'Policy switches only — no refund or proration amounts are calculated automatically. Expired services are never deleted: staff receive a retention-review task and data is handled manually under your retention policy.', 'fields' => [
                ['billing.cancellation_enabled', 'Customers can request cancellation from their dashboard', 'checkbox', 'nullable|boolean', null, true],
                ['billing.cancellation_requires_approval', 'Cancellation requests need staff approval', 'checkbox', 'nullable|boolean', null, true],
                ['billing.cancellation_allow_immediate', 'Allow customers to request immediate cancellation (otherwise end of the paid period only)', 'checkbox', 'nullable|boolean', null, false],
                ['billing.expire_after_days', 'Expire an unpaid overdue/suspended subscription this many days after its due date (blank = never automatically)', 'number', 'nullable|integer|between:1,365'],
                ['billing.addon_midcycle_policy', 'Add-ons added in the middle of a billing period', 'select', 'nullable|in:next_renewal,manual_invoice', ['next_renewal' => 'Start billing from the next renewal (no charge for the current period)', 'manual_invoice' => 'Start now; staff raise any part-period charge manually (no automatic proration)'], 'next_renewal'],
                ['billing.renewal_failure_alert_after', 'Alert staff after this many consecutive renewal processing failures', 'number', 'nullable|integer|between:1,20', null, 3],
            ]],
            'razorpay' => ['title' => 'Razorpay', 'note' => 'Webhook URL: '.url('/webhooks/razorpay').' — subscribe to payment.captured, order.paid and payment.failed. Not verified against a live account yet.', 'fields' => [
                ['gateway.razorpay.enabled', 'Enable Razorpay', 'checkbox', 'nullable|boolean'],
                ['gateway.razorpay.mode', 'Mode', 'select', 'nullable|in:test,live', ['test' => 'Test', 'live' => 'Live'], 'test'],
                ['gateway.razorpay.key_id', 'Key ID', 'text', 'nullable|string|max:100'],
                ['gateway.razorpay.key_secret', 'Key secret', 'secret', 'nullable|string|max:200'],
                ['gateway.razorpay.webhook_secret', 'Webhook secret', 'secret', 'nullable|string|max:200'],
            ]],
            'telegram' => ['title' => 'Telegram alerts', 'fields' => array_merge([
                ['telegram.bot_token', 'Bot token', 'secret', 'nullable|string|max:200'],
                ['telegram.chat_id', 'Admin chat ID', 'text', 'nullable|string|max:60'],
            ], $events)],
            'smtp' => ['title' => 'Email (SMTP)', 'note' => 'Until a host is saved, mail uses the configured local mailer (log by default).', 'fields' => [
                ['smtp.host', 'SMTP host', 'text', 'nullable|string|max:150'],
                ['smtp.port', 'Port', 'number', 'nullable|integer|between:1,65535', null, 587],
                ['smtp.encryption', 'Encryption', 'select', 'nullable|in:tls,ssl,none', ['tls' => 'TLS (STARTTLS)', 'ssl' => 'SSL', 'none' => 'None'], 'tls'],
                ['smtp.username', 'Username', 'text', 'nullable|string|max:150'],
                ['smtp.password', 'Password', 'secret', 'nullable|string|max:200'],
                ['smtp.from_address', 'Sender email', 'text', 'nullable|email|max:150'],
                ['smtp.from_name', 'Sender name', 'text', 'nullable|string|max:100', null, 'DeveloperJet'],
            ]],
            'templates' => ['title' => 'Email templates', 'note' => 'Placeholders: {name} {order} {invoice} {ticket} {url} {service} {date} {project}; renewal templates also {amount} {cycle} {support}; Business Automation templates {reference} {customer} {business_type} {summary} {email} {phone} {status} {message}.', 'fields' => $templates],
            'support' => ['title' => 'Support & SLA', 'note' => 'SLA targets are optional and blank by default (no SLA is promised until you set one). Department targets override these. Hours are calendar hours. The SLA check runs every 15 minutes (support:sla-check).', 'fields' => [
                ['support.sla.urgent.first_response_hours', 'Urgent — first response target (hours)', 'number', 'nullable|integer|min:1|max:720'],
                ['support.sla.urgent.resolution_hours', 'Urgent — resolution target (hours)', 'number', 'nullable|integer|min:1|max:2160'],
                ['support.sla.high.first_response_hours', 'High — first response target (hours)', 'number', 'nullable|integer|min:1|max:720'],
                ['support.sla.high.resolution_hours', 'High — resolution target (hours)', 'number', 'nullable|integer|min:1|max:2160'],
                ['support.sla.normal.first_response_hours', 'Normal — first response target (hours)', 'number', 'nullable|integer|min:1|max:720'],
                ['support.sla.normal.resolution_hours', 'Normal — resolution target (hours)', 'number', 'nullable|integer|min:1|max:2160'],
                ['support.sla.low.first_response_hours', 'Low — first response target (hours)', 'number', 'nullable|integer|min:1|max:720'],
                ['support.sla.low.resolution_hours', 'Low — resolution target (hours)', 'number', 'nullable|integer|min:1|max:2160'],
                ['support.auto_escalate_on_breach', 'Escalate tickets automatically when an SLA target is missed', 'checkbox', 'nullable|boolean'],
                ['support.email_assignee', 'Email staff when a ticket is assigned to them', 'checkbox', 'nullable|boolean'],
            ]],
            'security' => ['title' => 'Staff security', 'note' => 'Staff can enrol an authenticator app at Admin → My security. When enforcement is on, staff without it are redirected there until they enrol.', 'fields' => [
                ['security.require_staff_mfa', 'Require two-factor authentication for all staff', 'checkbox', 'nullable|boolean'],
            ]],
            'provisioning' => ['title' => 'Provisioning & gating', 'fields' => [
                ['provisioning.mode', 'Provisioning mode', 'select', 'nullable|in:manual,automatic', ['manual' => 'Manual assignment (only deployable mode)', 'automatic' => 'Automatic (blocked: no verified provider)'], 'manual'],
                ['n8n.resale_verified', 'n8n commercial resale licensing verified (unlocks n8n sales/deploys)', 'checkbox', 'nullable|boolean'],
                ['openclaw.spec_approved', 'OpenClaw managed-hosting specification approved', 'checkbox', 'nullable|boolean'],
                ['infrastructure.max_monthly_cost_minor', 'Server cost limit per month (minor units / paise; blank = no limit)', 'number', 'nullable|integer|min:0'],
                ['infrastructure.min_margin_percent', 'Minimum margin on assignment (%; blank = not enforced)', 'text', 'nullable|numeric|between:0,100'],
            ]],
        ];
    }

    /** Settings tabs: key => [label, short description, section keys]. One tab is shown at a time. */
    public const GROUPS = [
        'business' => ['Business & invoices', 'Company details, invoice numbering, tax and sales contact', ['company', 'tax', 'sales']],
        'payments' => ['Payments', 'Razorpay online payments and the customer wallet', ['razorpay']],
        'billing' => ['Billing & renewals', 'Renewal invoices, reminders, grace period, cancellation', ['billing', 'lifecycle']],
        'email' => ['Email & alerts', 'SMTP email, Telegram alerts and email templates', ['smtp', 'telegram', 'templates']],
        'support' => ['Support', 'Ticket SLA targets and assignment emails', ['support']],
        'system' => ['Security & system', 'Staff two-factor, provisioning and product gates', ['security', 'provisioning']],
    ];

    public static function groupOf(string $section): string
    {
        foreach (self::GROUPS as $key => [, , $sections]) {
            if (in_array($section, $sections, true)) {
                return $key;
            }
        }

        return 'business';
    }

    public static function sectionUrl(string $section): string
    {
        return route('admin.settings.edit', ['tab' => self::groupOf($section)]).'#'.$section;
    }

    public function edit(Request $request): View
    {
        abort_unless($request->user()->hasPermission('settings.view'), 403);
        $tab = array_key_exists((string) $request->query('tab'), self::GROUPS) ? (string) $request->query('tab') : 'business';
        $schema = self::schema();

        return view('admin.settings', [
            'schema' => $schema,
            'groups' => self::GROUPS,
            'tab' => $tab,
            'sections' => array_intersect_key($schema, array_flip(self::GROUPS[$tab][2])),
            'canEdit' => $request->user()->hasPermission('settings.edit'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.edit'), 403);
        $section = $request->validate(['section' => ['required', 'string']])['section'];
        $def = self::schema()[$section] ?? abort(404);

        // Mail transport and staff-security switches can be used to take over accounts (e.g. route password-reset
        // mail through an attacker's SMTP): Super Admin only, with a recent password re-confirmation.
        if (in_array($section, self::SUPER_ONLY_SECTIONS, true)) {
            abort_unless($request->user()->isSuper(), 403, 'Only a Super Admin can change this section.');
            if (! StaffSecurity::recentlyConfirmed($request)) {
                $request->session()->put('staff_reauth_return', self::sectionUrl($section));

                return redirect()->route('admin.confirm-access')->with('error', 'Confirm your password, then save the section again.');
            }
        }

        $rules = [];
        foreach ($def['fields'] as $f) {
            $rules[str_replace('.', '__', $f[0])] = explode('|', $f[3]);
        }
        $validator = Validator::make($this->flatten($request, $def), $rules);
        $data = $validator->validate();

        $changed = [];
        foreach ($def['fields'] as $f) {
            [$key, , $type] = $f;
            $input = $data[str_replace('.', '__', $key)] ?? null;

            if ($type === 'secret') {
                if ($input !== null && $input !== '') {
                    Setting::set($key, $input, true);
                    $changed[] = $key; // names only — never values
                }
            } elseif ($type === 'checkbox') {
                Setting::set($key, $request->boolean(str_replace('.', '__', $key)) ? '1' : '0');
                $changed[] = $key;
            } else {
                Setting::set($key, $input);
                $changed[] = $key;
            }
        }

        Audit::log('settings.updated', null, ['section' => $section, 'keys' => $changed]);

        return redirect()->to(self::sectionUrl($section))->with('success', $def['title'].' saved.');
    }

    /** SMTP connection test: Super Admin only, recent re-authentication, sends NO email. */
    public function testSmtp(Request $request, MailDeliveryService $mail): RedirectResponse
    {
        abort_unless($request->user()->isSuper(), 403, 'Only a Super Admin can test email settings.');
        if (! StaffSecurity::recentlyConfirmed($request)) {
            $request->session()->put('staff_reauth_return', self::sectionUrl('smtp'));

            return redirect()->route('admin.confirm-access')->with('error', 'Confirm your password, then run the SMTP test again.');
        }
        $result = $mail->testConnection();
        Audit::log('settings.smtp_test', null, ['ok' => $result['ok'], 'missing' => $result['missing']]);

        return redirect()->to(self::sectionUrl('smtp'))->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /** Dotted setting keys cannot be form field names reliably, so the form posts them with "__". */
    private function flatten(Request $request, array $def): array
    {
        $out = [];
        foreach ($def['fields'] as $f) {
            $name = str_replace('.', '__', $f[0]);
            $out[$name] = $request->input($name);
        }

        return $out;
    }
}
