<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Order;
use App\Models\Subscription;
use App\Support\BillingCycle;
use App\Support\BillingSettings;
use App\Support\OrderStatus;
use App\Support\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Invoices are issued only for orders with verified payment. Numbers come from a row-locked sequence so they are
 * gap-aware, unique and atomic. Prefix, starting number and padding are configurable (owner decision pending:
 * defaults are INV / 1 / 5 digits, e.g. INV00001; set "AUN" and a start number in Admin → Settings → Billing).
 */
class InvoiceService
{
    public function __construct(private Notifier $notifier) {}

    public function issueForOrder(Order $order): Invoice
    {
        $order->refresh();

        if (! $order->paid_at || ! OrderStatus::isPaidState($order->status)) {
            throw new LogicException('A paid tax invoice cannot be generated for an unpaid order.');
        }

        // One invoice per order (unique order_id + type): repeat calls return the existing invoice. A renewal invoice
        // issued earlier as "unpaid" is settled in place (same number), exactly once, under a row lock.
        if ($existing = Invoice::where('order_id', $order->id)->where('type', 'invoice')->first()) {
            return $existing->status === 'unpaid' ? $this->settle($existing, $order) : $existing;
        }

        $invoice = DB::transaction(function () use ($order) {
            $invoice = Invoice::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'number' => $this->nextNumber((string) Setting::get('invoice.prefix', 'INV')),
                'type' => 'invoice',
                'status' => 'paid',
                'currency' => $order->currency,
                'subtotal_minor' => $order->subtotal_minor,
                'discount_minor' => $order->discount_minor,
                'tax_minor' => $order->tax_minor,
                'total_minor' => $order->total_minor,
                'issued_at' => now(),
                'due_at' => now(),
                'paid_at' => $order->paid_at,
                'billing_snapshot' => $this->billingSnapshot($order),
                'payment_info' => [
                    'method' => $order->payment_method,
                    'wallet_applied_minor' => $order->wallet_applied_minor,
                    'gateway_payment_ids' => $order->attempts()->where('status', 'verified')->pluck('gateway_payment_id')->all(),
                ],
            ]);

            foreach ($order->items as $item) {
                $invoice->items()->create([
                    'description' => $item->name.' ('.$item->billing_interval.')',
                    'unit_minor' => $item->unit_minor + $item->setup_minor,
                    'quantity' => 1,
                    'discount_minor' => $item->discount_minor,
                    'tax_minor' => $item->tax_minor,
                    'total_minor' => $item->total_minor,
                ]);
            }

            return $invoice;
        });

        $this->notifier->admin('invoice_generated', "invoice:{$invoice->id}", "Invoice {$invoice->number} generated for order {$order->number}.");
        $this->email($invoice);

        return $invoice;
    }

    /**
     * Unpaid renewal invoice for one subscription period. Not a receipt: status "unpaid" until the linked renewal
     * order is paid through the normal verified-payment path, which then calls settle().
     */
    public function issueRenewal(Order $order, Subscription $sub, CarbonInterface $periodStart, CarbonInterface $periodEnd): Invoice
    {
        if ($existing = Invoice::where('order_id', $order->id)->where('type', 'invoice')->first()) {
            return $existing;
        }

        $invoice = DB::transaction(function () use ($order, $sub, $periodStart, $periodEnd) {
            $invoice = Invoice::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'subscription_id' => $sub->id,
                'number' => $this->nextNumber((string) Setting::get('invoice.prefix', 'INV')),
                'type' => 'invoice',
                'status' => 'unpaid',
                'currency' => $order->currency,
                'subtotal_minor' => $order->subtotal_minor,
                'discount_minor' => $order->discount_minor,
                'tax_minor' => $order->tax_minor,
                'total_minor' => $order->total_minor,
                'billing_interval' => $sub->billing_interval,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'issued_at' => now(),
                'due_at' => $periodStart,
                'billing_snapshot' => $this->billingSnapshot($order),
                'payment_info' => [
                    'instructions' => BillingSettings::paymentInstructions(),
                    'pay_url' => route('dashboard.orders.show', $order),
                ],
            ]);

            foreach ($order->items as $item) {
                $invoice->items()->create([
                    'description' => $item->name.' — '.BillingCycle::label($item->billing_interval).', '.$periodStart->format('d M Y').' to '.$periodEnd->format('d M Y'),
                    'unit_minor' => $item->unit_minor + $item->setup_minor,
                    'quantity' => 1,
                    'discount_minor' => $item->discount_minor,
                    'tax_minor' => $item->tax_minor,
                    'total_minor' => $item->total_minor,
                ]);
            }

            return $invoice;
        });

        $this->notifier->admin('invoice_generated', "invoice:{$invoice->id}", "Renewal invoice {$invoice->number} issued (unpaid, due {$periodStart->format('d M Y')}) for order {$order->number}.");

        return $invoice;
    }

    /** Mark an unpaid invoice paid after verified payment of its order (idempotent). */
    private function settle(Invoice $invoice, Order $order): Invoice
    {
        $settled = DB::transaction(function () use ($invoice, $order) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->first();
            if ($locked->status !== 'unpaid') {
                return null;
            }
            $locked->update([
                'status' => 'paid',
                'paid_at' => $order->paid_at,
                'payment_info' => array_merge($locked->payment_info ?? [], [
                    'method' => $order->payment_method,
                    'wallet_applied_minor' => $order->wallet_applied_minor,
                    'gateway_payment_ids' => $order->attempts()->where('status', 'verified')->pluck('gateway_payment_id')->all(),
                ]),
            ]);

            return $locked;
        });

        if (! $settled) {
            return $invoice->fresh();
        }

        $this->notifier->admin('invoice_generated', "invoice:{$settled->id}:paid", "Invoice {$settled->number} paid (order {$order->number}).");
        $this->email($settled);

        return $settled;
    }

    /** Credit note for a refunded order, referencing the original invoice. */
    public function creditNoteFor(Invoice $invoice): Invoice
    {
        if ($existing = Invoice::where('related_invoice_id', $invoice->id)->where('type', 'credit_note')->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($invoice) {
            $note = Invoice::create([
                'user_id' => $invoice->user_id,
                'order_id' => null, // (order_id, type) uniqueness applies to invoices; credit notes link via related_invoice_id
                'related_invoice_id' => $invoice->id,
                'number' => $this->nextNumber((string) Setting::get('invoice.credit_prefix', 'CN')),
                'type' => 'credit_note',
                'status' => 'issued',
                'currency' => $invoice->currency,
                'subtotal_minor' => $invoice->subtotal_minor,
                'discount_minor' => $invoice->discount_minor,
                'tax_minor' => $invoice->tax_minor,
                'total_minor' => $invoice->total_minor,
                'issued_at' => now(),
                'billing_snapshot' => $invoice->billing_snapshot,
            ]);

            foreach ($invoice->items as $item) {
                $note->items()->create($item->only(['description', 'unit_minor', 'quantity', 'discount_minor', 'tax_minor', 'total_minor']));
            }

            return $note;
        });
    }

    public function nextNumber(string $prefix): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9-]/', '', $prefix) ?: 'INV';
        $pad = max(1, (int) Setting::get('invoice.pad', 5));
        $start = max(1, (int) Setting::get('invoice.start_number', 1));

        InvoiceSequence::firstOrCreate(['prefix' => $prefix], ['last_number' => $start - 1]);

        // Caller is inside a transaction; the row lock serialises concurrent issuers.
        $sequence = InvoiceSequence::where('prefix', $prefix)->lockForUpdate()->firstOrFail();
        $sequence->update(['last_number' => $sequence->last_number + 1]);

        return $prefix.str_pad((string) $sequence->last_number, $pad, '0', STR_PAD_LEFT);
    }

    public function pdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->load('items', 'user', 'order');

        // Font subsetting keeps the emailed PDF small (only the glyphs used are embedded).
        return Pdf::loadView('invoices.pdf', ['invoice' => $invoice])->setPaper('a4')->setOption('isFontSubsettingEnabled', true);
    }

    public function email(Invoice $invoice): void
    {
        $dir = 'invoices';
        $path = "{$dir}/{$invoice->number}.pdf";
        Storage::disk('local')->put($path, $this->pdf($invoice)->output());

        $this->notifier->customer('invoice', "invoice:{$invoice->id}", $invoice->user, 'invoice',
            ['invoice' => $invoice->number, 'url' => route('dashboard.invoices')],
            Storage::disk('local')->path($path), $invoice->number.'.pdf');

        $invoice->update(['emailed_at' => now()]);
    }

    private function billingSnapshot(Order $order): array
    {
        return [
            'company' => [
                'name' => Setting::get('company.name', 'AmbitionHost Pvt. Ltd.'),
                'trade_name' => 'DeveloperJet',
                'address' => Setting::get('company.address'),
                'gstin' => Setting::get('company.gstin'), // shown only when configured
                'email' => Setting::get('company.email'),
            ],
            'customer' => $order->customer_snapshot ?? ['name' => $order->user->name, 'email' => $order->user->email],
        ];
    }
}
