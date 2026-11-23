<?php

// Dialling codes offered on registration/profile. India first (default). Add rows as needed; the national-number
// rule falls back to the generic E.164 limits (4–14 digits, 15 total) for any code without a specific pattern.
return [
    'default' => '+91',
    'countries' => [
        '+91' => ['India', '/^[6-9]\d{9}$/'],
        '+1' => ['United States / Canada', '/^[2-9]\d{9}$/'],
        '+44' => ['United Kingdom', '/^\d{9,10}$/'],
        '+971' => ['United Arab Emirates', '/^\d{8,9}$/'],
        '+65' => ['Singapore', '/^[3689]\d{7}$/'],
        '+61' => ['Australia', '/^\d{9}$/'],
        '+49' => ['Germany', null],
        '+33' => ['France', '/^\d{9}$/'],
        '+966' => ['Saudi Arabia', '/^\d{9}$/'],
        '+974' => ['Qatar', '/^\d{8}$/'],
        '+977' => ['Nepal', '/^\d{8,10}$/'],
        '+880' => ['Bangladesh', '/^\d{10}$/'],
        '+94' => ['Sri Lanka', '/^\d{9}$/'],
        '+60' => ['Malaysia', '/^\d{9,10}$/'],
        '+81' => ['Japan', '/^\d{9,10}$/'],
        '+31' => ['Netherlands', '/^\d{9}$/'],
        '+353' => ['Ireland', '/^\d{7,9}$/'],
        '+64' => ['New Zealand', '/^\d{8,10}$/'],
        '+27' => ['South Africa', '/^\d{9}$/'],
        '+234' => ['Nigeria', '/^\d{10}$/'],
        '+254' => ['Kenya', '/^\d{9}$/'],
    ],
];
