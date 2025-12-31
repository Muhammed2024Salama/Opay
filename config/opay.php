<?php

return [
    'base_url'      => env('OPAY_BASE_URL'),
    'merchant_id'   => env('OPAY_MERCHANT_ID'),
    'public_key'    => env('OPAY_PUBLIC_KEY'),
    'secret_key'    => env('OPAY_SECRET_KEY'),
    'return_url'    => env('OPAY_RETURN_URL'),

    'skip_signature_verification' => env('OPAY_SKIP_SIGNATURE_VERIFICATION', false),
];
