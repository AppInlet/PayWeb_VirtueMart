<?php

namespace Paygate;

use Paygate\PaygateConstants;

class PaygatePaymentOptions
{
    private const PAYMENT_METHODS = [
        PaygateConstants::CREDIT_CARD_METHOD   => PaygateConstants::CREDIT_CARD_DESCRIPTION,
        PaygateConstants::BANK_TRANSFER_METHOD => PaygateConstants::BANK_TRANSFER_DESCRIPTION,
        PaygateConstants::ZAPPER_METHOD        => PaygateConstants::ZAPPER_DESCRIPTION,
        PaygateConstants::SNAPSCAN_METHOD      => PaygateConstants::SNAPSCAN_DESCRIPTION,
        PaygateConstants::PAYPAL_METHOD        => PaygateConstants::PAYPAL_DESCRIPTION,
        PaygateConstants::MOBICRED_METHOD      => PaygateConstants::MOBICRED_DESCRIPTION,
        PaygateConstants::MOMOPAY_METHOD       => PaygateConstants::MOMOPAY_DESCRIPTION,
        PaygateConstants::SCANTOPAY_METHOD     => PaygateConstants::SCANTOPAY_DESCRIPTION,
        PaygateConstants::RCS_METHOD           => PaygateConstants::RCS_DESCRIPTION,
    ];

    private const SPECIAL_CASES = [
        PaygateConstants::APPLE_PAY_METHOD   => 3,
        PaygateConstants::SAMSUNG_PAY_METHOD => 3,
    ];

    /**
     * Processes payment options data based on selected payment method
     *
     * @param array $initiateFields The initial fields for the payment request
     * @param string|null $paymentMethod The selected payment method (optional)
     * @return array The updated fields including payment method information
     */
    public function getPaymentOptions(array $initiateFields, ?string $paymentMethod = null): array
    {
        $paymentMethod = $paymentMethod ?? ($_REQUEST['payment_method'] ?? null);
        if (!empty($paymentMethod)) {
            if (isset(self::PAYMENT_METHODS[$paymentMethod])) {
                $initiateFields['PAY_METHOD']        = substr($paymentMethod, 0, 2);
                $initiateFields['PAY_METHOD_DETAIL'] = self::PAYMENT_METHODS[$paymentMethod];
            } elseif (isset(self::SPECIAL_CASES[$paymentMethod])) {
                $initiateFields['PAY_METHOD']        = substr($paymentMethod, 0, 2);
                $initiateFields['PAY_METHOD_DETAIL'] = substr($paymentMethod, self::SPECIAL_CASES[$paymentMethod]);
            }
        }
        return $initiateFields;
    }
}

