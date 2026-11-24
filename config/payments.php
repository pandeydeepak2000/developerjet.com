<?php

return [
    // Razorpay REST base (documented public API). Credentials live encrypted in the settings table.
    'razorpay_api' => 'https://api.razorpay.com/v1',

    // Supported payment methods: Razorpay (online) and the DeveloperJet wallet. PTSure was removed on owner request.

    // Gateway order amount may not drift from the stored attempt amount.
    'currency' => 'INR',
];
