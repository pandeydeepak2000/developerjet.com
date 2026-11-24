<?php

return [
    // Razorpay REST base (documented public API). Credentials live encrypted in the settings table.
    'razorpay_api' => 'https://api.razorpay.com/v1',

    // Supported payment methods: Razorpay (online) and the DeveloperJet wallet. PTSure was removed on owner request.

    // GST on online (Razorpay) payments; an order paid fully from the wallet carries no GST (owner decision).
    // Admin → Settings → Tax "Standard rate" overrides this. Set GST_PERCENT= (empty) to disable the default.
    'gst_percent' => env('GST_PERCENT', 18),

    // Text shown under the Razorpay option at checkout.
    'razorpay_powered_by' => env('RAZORPAY_POWERED_BY', 'JixPay'),

    // Gateway order amount may not drift from the stored attempt amount.
    'currency' => 'INR',
];
