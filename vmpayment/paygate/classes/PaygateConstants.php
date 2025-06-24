<?php

namespace Paygate;

class PaygateConstants
{
    // Payment Methods
    const CREDIT_CARD_METHOD   = 'CC';
    const BANK_TRANSFER_METHOD = 'BT';
    const ZAPPER_METHOD        = 'EW-ZAPPER';
    const SNAPSCAN_METHOD      = 'EW-SNAPSCAN';
    const PAYPAL_METHOD        = 'EW-PAYPAL';
    const MOBICRED_METHOD      = 'EW-MOBICRED';
    const MOMOPAY_METHOD       = 'EW-MOMOPAY';
    const SCANTOPAY_METHOD     = 'EW-MASTERPASS';
    const SAMSUNG_PAY_METHOD   = 'EW-Samsungpay';
    const APPLE_PAY_METHOD     = 'CC-Applepay';
    const RCS_METHOD           = 'CC-RCS';
    // Payment method descriptions
    const CREDIT_CARD_DESCRIPTION   = 'Card';
    const BANK_TRANSFER_DESCRIPTION = 'SID';
    const ZAPPER_DESCRIPTION        = 'Zapper';
    const SNAPSCAN_DESCRIPTION      = 'SnapScan';
    const PAYPAL_DESCRIPTION        = 'PayPal';
    const MOBICRED_DESCRIPTION      = 'Mobicred';
    const MOMOPAY_DESCRIPTION       = 'Momopay';
    const SCANTOPAY_DESCRIPTION     = 'MasterPass';
    const SAMSUNG_DESCRIPTION       = 'Samsung Pay';
    const APPLE_DESCRIPTION         = 'ApplePay';
    const RCS_DESCRIPTION           = 'RCS';

    // Orders
    const ORDER_NUMBER = 'Order #';
}
