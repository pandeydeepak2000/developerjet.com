<?php

return [
    // Razorpay REST base (documented public API). Credentials live encrypted in the settings table.
    'razorpay_api' => 'https://api.razorpay.com/v1',

    /*
     | PTSure: no verified merchant API documentation or credentials are available. Until the owner supplies
     | them and this flag is flipped after a verified sandbox integration, the adapter refuses to transact
     | even if an admin enables it.
     */
    'ptsure_integration_verified' => false,

    // Gateway order amount may not drift from the stored attempt amount.
    'currency' => 'INR',
];
