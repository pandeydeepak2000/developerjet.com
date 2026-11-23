<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function download(Request $request, Invoice $invoice, InvoiceService $invoices): Response
    {
        abort_unless($request->user()->hasPermission('invoices.view'), 403);

        return $invoices->pdf($invoice)->download($invoice->number.'.pdf');
    }
}
