<?php

// MERGE the 'payments' key below into your existing config/services.php
// return array — do not replace that file.

return [
    'payments' => [
        // Shared secret for the generic webhook endpoint. With this unset,
        // every webhook request is rejected (fails closed).
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
    ],
];
