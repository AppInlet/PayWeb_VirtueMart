<?php

namespace Paygate;

class PaygateSettings
{
    public const SETTINGS = [
        'test'           => [0, 'int'],
        'id'             => ['', 'char'],
        'key'            => ['', 'char'],
        'successful'     => ['', 'char'],
        'failed'         => ['', 'char'],
        'payment_logos'  => ['', 'char'],
        'card'           => [0, 'int'],
        'sid_secure_eft' => [0, 'int'],
        'zapper'         => [0, 'int'],
        'snapscan'       => [0, 'int'],
        'paypal'         => [0, 'int'],
        'mobicred'       => [0, 'int'],
        'momopay'        => [0, 'int'],
        'masterpass'     => [0, 'int'],
        'apple_pay'      => [0, 'int'],
        'samsung_pay'    => [0, 'int'],
        'rcs'            => [0, 'int'],
    ];
}
