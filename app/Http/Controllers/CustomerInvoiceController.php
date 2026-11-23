<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Response;

class CustomerInvoiceController extends Controller
{
    public function download(Invoice $invoice, InvoiceService $invoices): Response
    {
        $this->authorize('view', $invoice);

        return $invoices->pdf($invoice)->download($invoice->number.'.pdf');
    }
}
