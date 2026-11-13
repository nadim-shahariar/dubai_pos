<?php

return [
    'name' => env('POS_NAME', 'Git Gadgets POS'),
    'company' => env('POS_COMPANY', 'GIT GADGETS VEHICLE PARTS'),
    'trn' => env('POS_TRN', '100000000000003'),
    'address' => env('POS_ADDRESS', 'Al Quoz Industrial Area 3, Dubai, UAE'),
    'phone' => env('POS_PHONE', '+971 4 555 0180'),
    'email' => env('POS_EMAIL', 'counter@gitgadgets.ae'),
    'currency' => 'AED',
    'vat_rate' => 5.00,
    'invoice_prefix' => env('POS_INVOICE_PREFIX', 'GG'),
    'cashier_discount_limit' => 5.00,
    'manager_pin' => env('POS_MANAGER_PIN', '1234'),
];
