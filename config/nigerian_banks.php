<?php

/**
 * The commercial banks licensed by the Central Bank of Nigeria, with their
 * CBN sort codes. Used to populate the "bank" dropdown when a pastor records
 * the account their subvention should be paid into.
 *
 * Kept as a config array rather than a DB table on purpose: this list is a
 * fixed national register, not tenant data. It changes rarely (a licence is
 * granted or revoked) and shipping it here means no church has to seed it,
 * and an update is a one-line deploy.
 *
 * Format: 'CBN bank code' => 'Bank name'. The codes match the CBN's published
 * NIBSS codes so a future direct-debit / transfer integration can use them
 * as-is.
 */
return [
    'banks' => [
        '000014' => 'Access Bank',
        '000005' => 'Access Bank (Diamond)',
        '000063' => 'Access Bank (Heritage)',
        '000045' => 'Alpha Morgan Bank',
        '000035' => 'Bank of Industry (BOI)',
        '000016' => 'First Bank of Nigeria',
        '000003' => 'FCMB (First City Monument Bank)',
        '000007' => 'Fidelity Bank',
        '000011' => 'Unity Bank',
        '000012' => 'Stanbic IBTC Bank',
        '000013' => 'Guaranty Trust Bank (GTBank)',
        '000015' => 'Zenith Bank',
        '000017' => 'Wema Bank',
        '000018' => 'Union Bank of Nigeria',
        '000019' => 'Enterprise Bank',
        '000020' => 'Heritage Bank',
        '000021' => 'Standard Chartered Bank',
        '000022' => 'SunTrust Bank',
        '000023' => 'Providus Bank',
        '000024' => 'Rand Merchant Bank',
        '000025' => 'Titan Trust Bank',
        '000026' => 'Taj Bank',
        '000027' => 'Globus Bank',
        '000028' => 'Central Bank of Nigeria',
        '000029' => 'Lotus Bank',
        '000030' => 'Parallex Bank',
        '000031' => 'Premium Trust Bank',
        '000032' => 'Signature Bank',
        '000033' => 'Optimus Bank',
        '000034' => 'Wema Bank (ALAT)',
        '000036' => 'Keystone Bank',
        '000037' => 'Polaris Bank',
        '000038' => 'Sterling Bank',
        '000039' => 'United Bank for Africa (UBA)',
        '000040' => 'Ecobank Nigeria',
        '000041' => 'Jaiz Bank',
        '000042' => 'Kuda Microfinance Bank',
        '000044' => 'Sparkle Microfinance Bank',
        '100004' => 'Opay (Paycom)',
        '100005' => 'PalmPay',
        '100006' => 'Moniepoint MFB',
        '100007' => 'Carbon',
        '100008' => 'FairMoney',
        '100009' => 'VBank',
        '100010' => 'Rubies MFB',
        '100011' => 'Mint MFB',
        '100012' => 'Paga',
        '100013' => 'Fortis Mobile',
        '100014' => 'Page MFB',
        '100015' => 'Tagpay',
        '100016' => 'FBNQuest Merchant Bank',
        '100017' => 'Safe Haven MFB',
        '100018' => 'Eyowo',
        '100019' => 'Dot Microfinance Bank',
        '100020' => 'Kegow',
        '100021' => '9 Payment Service Bank (9PSB)',
        '100022' => 'Airtel Smartcash PSB',
        '100023' => 'Mo Payment Service Bank (MTN)',
        '100025' => 'Hope Payment Service Bank',
    ],
];
