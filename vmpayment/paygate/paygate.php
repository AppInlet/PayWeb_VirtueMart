<?php

/*
 * Copyright (c) 2024 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Router\Route;

defined('_JEXEC') || die();

# Get plugin class
if (!class_exists('vmPSPlugin')) {
    require JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';
}

/**
 * Class plgVmPaymentPaygate
 *
 * @author  App Inlet (Pty) Ltd
 * @version 1.1.0
 * @link    https://developer.paygate.co.za/products/shoppingcarts
 */
class plgVmPaymentPaygate extends vmPSPlugin
{

    public static $_this = false;

    public $paygateId;
    public $paygateKey;

    const ORDER_NUMBER = 'Order #';

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

    /**
     * Save settings
     *
     * @param $subject
     * @param $config
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->_loggable = true;

        $this->tableFields  = array_keys($this->getTableSQLFields());
        $this->_tablepkey   = 'id';
        $this->_tableId     = 'id';
        $this->_initiateurl = 'https://secure.paygate.co.za/payweb3/initiate.trans';
        $this->_processurl  = 'https://secure.paygate.co.za/payweb3/process.trans';

        $settings = array(
            'test'           => array(0, 'int'),
            'id'             => array('', 'char'),
            'key'            => array('', 'char'),
            'successful'     => array('', 'char'),
            'failed'         => array('', 'char'),
            'payment_logos'  => array('', 'char'),
            'card'           => array(0, 'int'),
            'sid_secure_eft' => array(0, 'int'),
            'zapper'         => array(0, 'int'),
            'snapscan'       => array(0, 'int'),
            'paypal'         => array(0, 'int'),
            'mobicred'       => array(0, 'int'),
            'momopay'        => array(0, 'int'),
            'masterpass'     => array(0, 'int'),
            'apple_pay'      => array(0, 'int'),
            'samsung_pay'    => array(0, 'int'),
            'rcs'            => array(0, 'int'),
        );

        $this->addVarsToPushCore($settings, 1);
        $this->setConfigParameterable($this->_configTableFieldName, $settings);
    }

    /**
     * @ If curl is working on the server
     */
    public function _is_curl_installed()
    {
        return in_array('curl', get_loaded_extensions());
    }

    /**
     * @ Generate the checksum string
     */

    public function generateChecksum($postData, $paygetkey)
    {
        $checksum = '';
        foreach ($postData as $value) {
            if ($value != '') {
                $checksum .= $value;
            }
        }
        $checksum .= $paygetkey;

        return md5($checksum);
    }

    /**
     * Define name for Paygate table
     *
     * @return mixed
     */
    public function getVmPluginCreateTableSQL()
    {
        // Drop the table if it already exists
        $dropQuery   = "DROP TABLE IF EXISTS `" . $this->_tablename . "`;";
        $createQuery = $this->createTableSQL('Paygate Table');

        // Combine the drop and create queries
        return $dropQuery . " " . $createQuery;
    }

    /**
     * Define fields for Paygate table
     *
     * @return array
     */
    public function getTableSQLFields()
    {
        return array(
            'id'                          => 'INT(11) NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id'         => 'INT(11)',
            'paygate_post'                => 'varchar(2500)',
            'payment_name'                => 'VARCHAR(75) NOT NULL DEFAULT "Paygate"',
            'virtuemart_paymentmethod_id' => ' mediumint(1) UNSIGNED',
            // Add new fields for persisting the cart data
            'order_number'                => 'VARCHAR(255)', // To store the session ID
            'cart_data'                   => 'MEDIUMTEXT',        // To store the serialized cart data
            'created_at'                  => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP' // Track when the data was stored
        );
    }

    /**
     * Redirect Customer
     *
     * @param $cart
     * @param $order
     *
     * @return bool
     */
    public function plgVmConfirmedOrder($cart, $order)
    {
        $details = $order['details']['BT'];
        $method  = $this->getVmPluginMethod($details->virtuemart_paymentmethod_id);

        if (!$method) {
            return false;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $responseFields = $this->initiateTransaction($cart, $order);

        unset($responseFields['CHECKSUM']);

        $checksum   = $this->generateChecksum($responseFields, $this->paygateKey);
        $processurl = $this->_processurl;

        $this->saveCartBeforeCheckout($order);

        $html = <<<HTML
<p>Kindly wait while you're redirected to Paygate ...</p>
        <form action="{$processurl}" method="post" name="redirect" >
            <input name="PAY_REQUEST_ID" type="hidden" value="{$responseFields['PAY_REQUEST_ID']}" />
            <input name="CHECKSUM" type="hidden" value="{$checksum}" />
        </form>
        <script type="text/javascript">document.forms['redirect'].submit();</script>
HTML;

        vRequest::setVar('html', $html);

        return false;
    }

    /**
     * Notify Handler
     *
     * @param $html
     *
     * @return bool
     * @throws Exception
     */
    public function plgVmOnPaymentResponseReceived(&$html)
    {
        $method             = filter_var($_GET['pm'], FILTER_SANITIZE_STRING);
        $order              = filter_var($_GET['on'], FILTER_SANITIZE_STRING);
        $pm                 = filter_var($_GET['pm'], FILTER_SANITIZE_STRING);
        $itemid             = filter_var($_GET['Itemid'], FILTER_SANITIZE_STRING);
        $PAY_REQUEST_ID     = filter_var($_POST['PAY_REQUEST_ID'], FILTER_SANITIZE_STRING);
        $TRANSACTION_STATUS = filter_var($_POST['TRANSACTION_STATUS'], FILTER_SANITIZE_STRING);
        $CHECKSUM           = filter_var($_POST['CHECKSUM'], FILTER_SANITIZE_STRING);
        if (!$order) {
            return false;
        }

        $method = $this->getVmPluginMethod($method);
        if (!$method) {
            return false;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $status    = $_POST['TRANSACTION_STATUS'];
        $model     = VmModel::getModel('orders');
        $id        = $model->getOrderIdByOrderNumber($order);
        $or        = $model->getOrder($id);
        $detail    = $or['details']['BT'];
        $reference = self::ORDER_NUMBER . $detail->order_number;
        $db        = Factory::getDbo();

        // Check validity of checksum before going any further
        $sent_sum   = array_pop($_POST);
        $paygateid  = $method->get('id');
        $paygatekey = $method->get('key');

        if ($method->get('test')) {
            $paygateid  = '10011072130';
            $paygatekey = 'secret';
        }
        $our_sum = md5($paygateid . implode('', $_POST) . $reference . $paygatekey);
        $sumok   = hash_equals($sent_sum, $our_sum);
        if ($sumok) {
            if ($detail->order_status === 'P') {
                if ($status === '1') {
                    $displayComment                = vmText::_('VMPAYMENT_PAYGATE_SUCCESSFUL_PAYMENT');
                    $details['customer_notified']  = 1;
                    $details['order_status']       = $method->successful;
                    $details['comments']           = vmText::sprintf(
                        'VMPAYMENT_PAYGATE_SUCCESSFUL_PAYMENT_COMMENT',
                        $_POST['PAY_REQUEST_ID']
                    );
                    $fields['virtuemart_order_id'] = $id;
                } else {
                    if ($status === '2') {
                        $details['comments'] = vmText::_('VMPAYMENT_PAYGATE_DECLINED_PAYMENT');
                        $displayComment      = vmText::_('VMPAYMENT_PAYGATE_DECLINED_PAYMENT_COMMENT');
                    } else {
                        $details['comments'] = vmText::_('VMPAYMENT_PAYGATE_FAILURE_PAYMENT');
                        $displayComment      = vmText::_('VMPAYMENT_PAYGATE_FAILURE_PAYMENT_COMMENT');
                    }
                    $details['customer_notified']  = 1;
                    $details['order_status']       = $method->failed;
                    $fields['virtuemart_order_id'] = $id;
                }

                $post = '';
                foreach ($_POST as $k => $v) {
                    $post .= $k . '="' . $v . '"|';
                }

                $fields['paygate_post'] = substr($post, 0, -1);
                $fields['payment_name'] = 'Paygate';

                $query = $db->getQuery(true)
                            ->update(
                                $db->quoteName('#__virtuemart_payment_plg_paygate')
                            )  // Replace with your actual table name
                            ->set(
                        $db->quoteName('virtuemart_order_id') . ' = ' . $db->quote($fields['virtuemart_order_id'])
                    )
                            ->set($db->quoteName('paygate_post') . ' = ' . $db->quote($fields['paygate_post']))
                            ->set($db->quoteName('payment_name') . ' = ' . $db->quote($fields['payment_name']))
                            ->set(
                                $db->quoteName('virtuemart_paymentmethod_id') . ' = ' . $db->quote(
                                    $detail->virtuemart_paymentmethod_id
                                )
                            )
                            ->where($db->quoteName('order_number') . ' = ' . $db->quote($detail->order_number));

                $db->setQuery($query);
                $db->execute();

                $model->updateStatusForOneOrder($id, $details, true);
            }

            if (!class_exists('VirtueMartCart')) {
                require JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php';
            }

            $cart = VirtueMartCart::getCart();

            // Retrieve saved cart data from the table
            $query = $db->getQuery(true)
                        ->select($db->quoteName('cart_data'))
                        ->from(
                            $db->quoteName('#__virtuemart_payment_plg_paygate')
                        )  // Replace with your actual table name
                        ->where($db->quoteName('order_number') . ' = ' . $db->quote($detail->order_number));

            $db->setQuery($query);
            $savedCartData = $db->loadResult();
            $restoredCart  = (object)json_decode($savedCartData, true);

            if ($status === '1') {
                $cart->emptyCart();
            } else {
                $app              = Factory::getApplication();
                $virtuemartUserId = !empty($restoredCart->ST['virtuemart_user_id']) ?
                    $restoredCart->ST['virtuemart_user_id'] : $restoredCart->BT['virtuemart_user_id'];

                if (empty($virtuemartUserId) || $virtuemartUserId == 0) {
                    // The user is a guest
                    $isGuest = true;
                } else {
                    // The user is logged in
                    $isGuest = false;
                }

                // Payment declined or failed: restore the cart for guest users
                if ($isGuest) {
                    $session = Factory::getSession();

                    if ($savedCartData) {
                        // Decode the JSON cart data to a PHP object
                        $session->set('vmcart', json_encode($restoredCart), 'vm');

                        // Restore the cart properties
                        $this->getCartDataToStore($restoredCart);
                    }
                }

                // Set the failure message
                $checkoutUrl = Route::_('index.php?option=com_virtuemart&view=cart', false);
                if ($status === '2') {
                    $app->enqueueMessage('Your payment was declined. Please try again.', 'error');
                } else {
                    $app->enqueueMessage('Your payment has failed. Please try again.', 'error');
                }

                // Redirect back to the cart without clearing it
                $app->redirect($checkoutUrl);
            }
        } else {
            $displayComment = 'The transaction could not be verified. Please contact the store';
        }

        $html = <<<HTML
<p>{$displayComment}</p>
HTML;

        return true;
    }

    public function saveCartBeforeCheckout($order)
    {
        $details   = $order['details']['BT'];
        $cart      = VirtueMartCart::getCart();
        $guestUser = empty($cart->BT['virtuemart_user_id']) || $cart->BT['virtuemart_user_id'] == 0;  // Check if the user is a guest

        if ($guestUser) {
            $db = Factory::getDbo();

            // Convert the cart's products and cartData to JSON
            $jsonCartData = json_encode($cart);  // Encode the cartData object as JSON

            // Insert the JSON-encoded cart data into the database
            $query = $db->getQuery(true)
                        ->insert(
                            $db->quoteName('#__virtuemart_payment_plg_paygate')
                        )  // Replace with your actual table name
                        ->columns(array($db->quoteName('order_number'), $db->quoteName('cart_data')))
                        ->values($db->quote($details->order_number) . ', ' . $db->quote($jsonCartData));

            $db->setQuery($query);
            $db->execute();
        }
    }

    public function getCartDataToStore($restoredCart)
    {
        $cart = VirtueMartCart::getCart();

        $cart->cartProductsData             = $restoredCart->cartProductsData;
        $cart->vendorId                     = $restoredCart->vendorId;
        $cart->lastVisitedCategoryId        = $restoredCart->lastVisitedCategoryId;
        $cart->virtuemart_shipmentmethod_id = $restoredCart->virtuemart_shipmentmethod_id;
        $cart->virtuemart_paymentmethod_id  = $restoredCart->virtuemart_paymentmethod_id;
        $cart->automaticSelectedShipment    = $restoredCart->automaticSelectedShipment;
        $cart->automaticSelectedPayment     = $restoredCart->automaticSelectedPayment;
        $cart->order_number                 = $restoredCart->order_number;

        $cart->BT         = $restoredCart->BT;
        $cart->ST         = $restoredCart->ST;
        $cart->cartfields = $restoredCart->cartfields;

        $cart->couponCode           = $restoredCart->couponCode;
        $cart->_triesValidateCoupon = $restoredCart->_triesValidateCoupon;
        $cart->order_language       = $restoredCart->order_language;

        $cart->pricesCurrency  = $restoredCart->pricesCurrency;
        $cart->paymentCurrency = $restoredCart->paymentCurrency;

        //private variables
        //We nee to store this, so that we now if a user logged in before
        $cart->_guest                    = Factory::getApplication()->getIdentity()->guest;
        $cart->_inCheckOut               = $restoredCart->_inCheckOut;
        $cart->_inConfirm                = $restoredCart->_inConfirm;
        $cart->_redirected               = $restoredCart->_redirected;
        $cart->_dataValidated            = $restoredCart->_dataValidated;
        $cart->_confirmDone              = $restoredCart->_confirmDone;
        $cart->STsameAsBT                = $restoredCart->STsameAsBT;
        $cart->selected_shipto           = $restoredCart->selected_shipto;
        $cart->_fromCart                 = $restoredCart->_fromCart;
        $cart->layout                    = $restoredCart->layout;
        $cart->layoutPath                = $restoredCart->layoutPath;
        $cart->virtuemart_cart_id        = $restoredCart->virtuemart_cart_id;
        $cart->OrderIdOrderDone          = $restoredCart->OrderIdOrderDone;
        $cart->orderdoneHtml             = $restoredCart->orderdoneHtml;
        $cart->virtuemart_order_id       = $restoredCart->virtuemart_order_id;
        $cart->byDefaultBT               = $restoredCart->byDefaultBT;
        $cart->byDefaultST               = $restoredCart->byDefaultST;
        $cart->productCartLoaded         = $restoredCart->productCartLoaded;
        $cart->loadedCart                = $restoredCart->loadedCart;
        $cart->lastAddedProduct          = $restoredCart->lastAddedProduct;
        $cart->products                  = $restoredCart->products;
        $cart->cartData                  = $restoredCart->cartData;
        $cart->couponCode                = $restoredCart->couponCode;
        $cart->automaticSelectedShipment = $restoredCart->automaticSelectedShipment;
        $cart->automaticSelectedPayment  = $restoredCart->automaticSelectedPayment;
        $cart->pricesCurrency            = $restoredCart->pricesCurrency;
        $cart->cartPrices                = $restoredCart->cartPrices;
        $cart->productsQuantity          = $restoredCart->productsQuantity;
        $cart->pricesUnformatted         = $restoredCart->pricesUnformatted;
        $cart->productCartLoaded         = $restoredCart->productCartLoaded;
        $cart->byDefaultBT               = $restoredCart->byDefaultBT;
        $cart->setCartIntoSession();
    }


    /*
     * notify handler
     */
    public function plgVmOnPaymentNotification()
    {
        if (!class_exists('VirtueMartModelOrders')) {
            require JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php';
        }

        // Notify Paygate
        echo 'OK';
        $errors       = false;
        $paygate_data = array();

        $notify_data = array();
        // Get notify data
        if (!$errors) {
            $paygate_data = $this->getPostData();
            if ($paygate_data === false) {
                $errors = true;
            }
        }

        $order_number = $paygate_data['USER1'];

        $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($paygate_data['USER1']);

        if (!$virtuemart_order_id) {
            exit;
        }

        $method = $_GET['pm'];
        $method = $this->getVmPluginMethod($method);
        if (!$method) {
            return false;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $order = $_GET['on'];
        if (!$order) {
            return false;
        }
        $model   = new VirtueMartModelOrders();
        $payment = $model->getOrderIdByOrderNumber($order_number);

        if (!$payment) {
            return null;
        }

        $checkSumParams = '';
        if (!$errors) {
            foreach ($paygate_data as $key => $val) {
                $notify_data[$key] = stripslashes($val);

                if ($key == 'PAYGATE_ID') {
                    $checkSumParams .= $val;
                }
                if ($key != 'CHECKSUM' && $key != 'PAYGATE_ID') {
                    $checkSumParams .= $val;
                }

                if (empty($notify_data)) {
                    $errors = true;
                }
            }
            $checkSumParams .= $method->$key;
        }

        // Verify security signature
        if (!$errors) {
            $checkSumParams = md5($checkSumParams);
            if ($checkSumParams != $paygate_data['CHECKSUM']) {
                $errors = true;
            }
        }

        if (!$errors) {
            $status  = 'P';
            $comment = 'You unfortunately did not complete payment with Paygate';
            switch ($paygate_data['TRANSACTION_STATUS']) {
                case '1':
                    $comment = 'Notify response: Payment was successfully completed with Paygate';
                    $status  = 'C';
                    break;
                case '2':
                    $comment = 'Notify response: Payment was declined with Paygate';
                    $status  = 'X';
                    break;
                case '0':
                case '4':
                    $comment = 'Notify response: You unfortunately did not complete payment with Paygate';
                    $status  = 'X';
                    break;

                default:
                    // If unknown status, do nothing (safest course of action)
                    break;
            }

            $id                            = $model->getOrderIdByOrderNumber($order_number);
            $details['customer_notified']  = 0;
            $details['order_status']       = $status;
            $details['comments']           = $comment;
            $fields['virtuemart_order_id'] = $id;

            $post = '';
            foreach ($paygate_data as $k => $v) {
                $post .= $k . '="' . $v . '"|';
            }

            $fields['paygate_post'] = substr($post, 0, -1);
            $fields['payment_name'] = 'Paygate';

            $this->storePSPluginInternalData($fields, 'virtuemart_order_id', true);

            try {
                $model->updateStatusForOneOrder($id, $details, true);
            } catch (Exception $e) {
                $this->logInfo('Email sending error: ' . $e->getMessage(), 'ERROR');
            }

            return true;
        }
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     *
     * @param $cart
     * @param $method
     * @param $cart_prices
     *
     * @return true: if the conditions are fulfilled, false otherwise
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        $this->convert($method);

        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amount      = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount && $amount <= $method->max_amount
                        ||
                        ($method->min_amount <= $amount && ($method->max_amount == 0)));

        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }
        // Probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address                          = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if ((in_array($address['virtuemart_country_id'], $countries) || empty($countries)) && $amount_cond) {
            return true;
        }

        return false;
    }

    /**
     * @param $method
     */
    public function convert($method)
    {
        $method->cost_percent_total   = null;
        $method->cost_per_transaction = null;

        if (!isset($method->min_amount) && !isset($method->max_amount)) {
            $method->min_amount = 0;
            $method->max_amount = 0;
        }

        $method->min_amount = (float)$method->min_amount;
        $method->max_amount = (float)$method->max_amount;
    }

    // Updated curlPost function
    public function curlPost($url, $fields)
    {
        if ($this->_is_curl_installed()) {
            $fields_string = '';
            foreach ($fields as $key => $value) {
                $fields_string .= $key . '=' . urlencode($value) . '&';
            }
            $fields_string = rtrim($fields_string, '&');
            // Get host safely
            $possibleHostSources   = array('HTTP_X_FORWARDED_HOST', 'HTTP_HOST', 'SERVER_NAME', 'SERVER_ADDR');
            $sourceTransformations = array(
                "HTTP_X_FORWARDED_HOST" => function ($value) {
                    $elements = explode(',', $value);

                    return trim(end($elements));
                },
            );
            $host                  = '';
            foreach ($possibleHostSources as $source) {
                if (!empty($host)) {
                    break;
                }

                if (empty($_SERVER[$source])) {
                    continue;
                }

                $host = $_SERVER[$source];
                if (array_key_exists($source, $sourceTransformations)) {
                    $host = $sourceTransformations[$source]($host);
                }
            }

            // Remove port number from host
            $host = trim(preg_replace('/:\d+$/', '', $host));

            $ch = curl_init();
            if (!$this->isSsl()) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            }
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_NOBODY, false);
            curl_setopt($ch, CURLOPT_REFERER, $host);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
            $result = curl_exec($ch);
            curl_close($ch);

            return $result;
        } else {
            echo 'cURL is NOT installed on this server. http://php.net/manual/en/curl.setup.php';
            exit;
        }
    }

    public function isSsl()
    {
        $url = parse_url(JURI::base());

        return ($url['scheme'] == 'https');
    }

    public function getPostData()
    {
        // Posted variables from ITN
        $nData = $_POST;

        // Strip any slashes in data
        foreach ($nData as $key => $val) {
            $nData[$key] = stripslashes($val);
        }

        // Return "false" if no data was received
        if (empty($nData)) {
            return (false);
        } else {
            return ($nData);
        }
    }

    /**
     * Log function for logging output.
     *
     * @param $msg String Message to log
     * @param $close Boolean Whether to close the log file or not
     */
    public function logData($msg = '', $close = false)
    {
        static $fh = 0;
        global $module;

        // Only log if debugging is enabled
        if (PF_DEBUG) {
            if ($close) {
                fclose($fh);
            } else {
                // If file doesn't exist, create it
                if (!$fh) {
                    $pathinfo = pathinfo(__FILE__);
                    $fh       = fopen($pathinfo['dirname'] . '/paygate.log', 'a+');
                }

                // If file was successfully created
                if ($fh) {
                    $line = date('Y-m-d H:i:s') . ' : ' . $msg . "\n";

                    fwrite($fh, $line);
                }
            }
        }
    }

    /**
     * Function that does server to server call for Initiate to Paygate
     *
     * @param $cart
     * @param $order
     *
     * @return array
     */
    public function initiateTransaction($cart, $order)
    {
        $details = $order['details']['BT'];
        $method  = $this->getVmPluginMethod($details->virtuemart_paymentmethod_id);
        if (!$method) {
            return false;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $this->logInfo(self::ORDER_NUMBER . $details->order_number . ' confirmed', 'message');

        if ($method->test) {
            $this->paygateId  = '10011072130';
            $this->paygateKey = 'secret';
        } else {
            $this->paygateId  = $method->id;
            $this->paygateKey = $method->key;
        }

        $db = JFactory::getDBO();
        // Get country code 3
        $query = $db->getQuery(true);
        $query->select('country_3_code');
        $query->from('#__virtuemart_countries');
        $query->where('virtuemart_country_id = ' . $details->virtuemart_country_id);
        $db->setQuery($query);
        $country_code3 = $db->loadResult();

        // Get currency code 3
        $query = $db->getQuery(true);
        $query->select('currency_code_3');
        $query->from('#__virtuemart_currencies');
        $query->where('virtuemart_currency_id = ' . $details->order_currency);
        $db->setQuery($query);
        $currency_code3 = $db->loadResult();

        $reference = self::ORDER_NUMBER . $details->order_number;
        $amount    = number_format($details->order_total * 100, 0, '', '');

        $url        = JROUTE::_(
            JURI::root(
            ) . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $details->order_number . '&pm=' . $details->virtuemart_paymentmethod_id . '&Itemid=' . vRequest::getInt(
                'Itemid'
            )
        );
        $date       = date('Y-m-d H:i:s');
        $email      = $details->email;
        $notify_url = JROUTE::_(
            JURI::root(
            ) . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . "&XDEBUG_SESSION_START=session_name" . "&o_id={$order['details']['BT']->order_number}"
        );

        $initiateFields = array(
            'PAYGATE_ID'       => $this->paygateId,
            'REFERENCE'        => $reference,
            'AMOUNT'           => $amount,
            'CURRENCY'         => $currency_code3,
            'RETURN_URL'       => $url,
            'TRANSACTION_DATE' => $date,
            'LOCALE'           => $details->order_language,
            'COUNTRY'          => $country_code3,
            'EMAIL'            => $email,
        );

        $initiateFields = $this->paymentOptionsDataToSend($initiateFields);

        $initiateFields['NOTIFY_URL'] = $notify_url;
        $initiateFields['USER1']      = $details->order_number;
        $initiateFields['USER3']      = 'virtuemart-v1.1.0';

        $initiateFields['CHECKSUM'] = $this->generateChecksum($initiateFields, $this->paygateKey);
        $response                   = $this->curlPost($this->_initiateurl, $initiateFields);
        parse_str($response, $responseFields);

        return $responseFields;
    }

# Default functions
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, int $selected, &$html): bool
    {
        if (isset($_POST['virtuemart_paymentmethod_id'])) {
            $selected = (int)htmlspecialchars($_POST['virtuemart_paymentmethod_id']);
        }
        if (isset($this->methods) && (int)$this->methods[0]->virtuemart_paymentmethod_id === $selected) {
            $cart->cartData['paymentName'] = "Paygate";
        }

        return $this->displayListFE($cart, $selected, $html);
    }

    public function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    public function plgVmOnShowOrderPrintPayment($order, $method)
    {
        return $this->onShowOrderPrint($order, $method);
    }

    public function plgVmOnShowOrderFEPayment($order, $method, &$payment)
    {
        $this->onShowOrderFE($order, $method, $payment);
    }

    /**
     *
     *
     * @param VirtueMartCart $cart
     * @param array $prices
     * @param $counter
     *
     * @return array|null
     */
    public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, $prices = array(), &$counter)
    {
        return $this->onCheckAutomaticSelected($cart, $prices, $counter);
    }

    public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, &$prices, &$names)
    {
        return $this->onSelectedCalculatePrice($cart, $prices, $names);
    }

    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart)
    {
        return $this->OnSelectCheck($cart);
    }

    public function plgVmOnStoreInstallPaymentPluginTable($id): bool
    {
        return $this->onStoreInstallPluginTable($id);
    }

    public function plgVmDeclarePluginParamsCustomVM3(&$data)
    {
        return $this->declarePluginParams('custom', $data);
    }

    public function plgVmGetTablePluginParams($psType, $name, $id, &$xParams, &$varsToPush)
    {
        return $this->getTablePluginParams($psType, $name, $id, $xParams, $varsToPush);
    }

    protected function renderPluginName($activeMethod)
    {
        $returnString = '';

        $plugin_name    = $this->_psType . '_name';
        $plugin_desc    = $this->_psType . '_desc';
        $logosFieldName = $this->_psType . '_logos';
        $logos          = $activeMethod->$logosFieldName;

        if (!empty($logos)) {
            $returnString = $this->displayLogos($logos) . ' ';
        }

        $pluginName = $returnString . '<span class="' . $this->_type . '_name">' . $activeMethod->$plugin_name . '</span>';

        if (isset($_SESSION['paygate_selected']) && !$_SESSION['paygate_selected']) {
            return $pluginName;
        }

        $getPaymentOptions = $this->setPaymentMethods($activeMethod);
        $pluginName        .= $this->renderHtml($getPaymentOptions);

        if (!empty($activeMethod->$plugin_desc)) {
            $pluginName .= '<span class="' . $this->_type . '_description">' . $activeMethod->$plugin_desc . '</span>';
        }

        return $pluginName;
    }

    public function renderHtml($paymentOptions)
    {
        $html                 = <<<HTML
<table>
<tbody>
HTML;
        $sessionPaymentmethod = $_REQUEST['payment_method'] ?? '';
        foreach ($paymentOptions as $paymentMethod => $value) {
            $checkedValue = $paymentMethod == $sessionPaymentmethod ? ' checked' : '';
            $html         .= '<tr class="payment-options">';
            $html         .= '<td><input type="radio" data-dynamic-update="1" name="payment_method" id="' . $paymentMethod . '" value="' . $paymentMethod . '"' . $checkedValue . '>' . ' ' . $value['description'] . ' ' . $value['image'] . '</td>';
            $html         .= '</tr>';
        }
        $html .= '</tbody></table>';

        $html .= <<<HTML
                <script>
                jQuery( document ).ready(function() {
                    if (window.ApplePaySession === undefined) {
                      // Apple Pay is not available, so let's hide the specific input element
                      var applePayElement = jQuery('#CC-APPLEPAY')
                      var applePayLogo = jQuery('img[alt="apple-pay"]')
                
                      applePayElement.parent().parent().remove()
                      applePayLogo.parent().parent().remove()
                    }
                  
                    jQuery('.vm-payment-plugin-single [name=virtuemart_paymentmethod_id]').on('click', function(){ jQuery('.payment-options').remove(); });
                    jQuery('.payment-options td img').css({'width': '100px', 'height':'auto', 'border': 'none',  'max-height': '56px', 'padding': '10px 5px', 'float': 'right'});
                    jQuery('.payment-options td').css('border', 'none');
                    // Set the styles
                    jQuery('.vmpayment .vmCartPaymentLogo').css('order', '0');
                });
                </script>
            HTML;

        return $html;
    }

    public function setPaymentMethods($method)
    {
        $paymentOptionsArray = array();

        if ($method->card == '1') {
            $paymentOptionsArray[self::CREDIT_CARD_METHOD] = array(
                'image'       => $this->displayLogos('mastercard-visa.svg') . ' ',
                'description' => self::CREDIT_CARD_DESCRIPTION
            );
        }

        if ($method->sid_secure_eft == '1') {
            $paymentOptionsArray[self::BANK_TRANSFER_METHOD] = array(
                'image'       => $this->displayLogos('sid.svg') . ' ',
                'description' => self::BANK_TRANSFER_DESCRIPTION
            );
        }

        if ($method->zapper == '1') {
            $paymentOptionsArray[self::ZAPPER_METHOD] = array(
                'image'       => $this->displayLogos('zapper.svg') . ' ',
                'description' => self::ZAPPER_DESCRIPTION
            );
        }

        if ($method->snapscan == '1') {
            $paymentOptionsArray[self::SNAPSCAN_METHOD] = array(
                'image'       => $this->displayLogos('snapscan.svg') . ' ',
                'description' => self::SNAPSCAN_DESCRIPTION
            );
        }

        if ($method->paypal == '1') {
            $paymentOptionsArray[self::PAYPAL_METHOD] = array(
                'image'       => $this->displayLogos('paypal.svg') . ' ',
                'description' => self::PAYPAL_DESCRIPTION
            );
        }

        if ($method->mobicred == '1') {
            $paymentOptionsArray[self::MOBICRED_METHOD] = array(
                'image'       => $this->displayLogos('mobicred.svg') . ' ',
                'description' => self::MOBICRED_DESCRIPTION
            );
        }

        if ($method->momopay == '1') {
            $paymentOptionsArray[self::MOMOPAY_METHOD] = array(
                'image'       => $this->displayLogos('momopay.svg') . ' ',
                'description' => self::MOMOPAY_DESCRIPTION
            );
        }

        if ($method->masterpass == '1') {
            $paymentOptionsArray[self::SCANTOPAY_METHOD] = array(
                'image'       => $this->displayLogos('scan-to-pay.svg') . ' ',
                'description' => self::SCANTOPAY_DESCRIPTION
            );
        }

        if ($method->apple_pay == '1') {
            $paymentOptionsArray[self::APPLE_PAY_METHOD] = array(
                'image'       => $this->displayLogos('apple-pay.svg') . ' ',
                'description' => self::APPLE_DESCRIPTION
            );
        }

        if ($method->samsung_pay == '1') {
            $paymentOptionsArray[self::SAMSUNG_PAY_METHOD] = array(
                'image'       => $this->displayLogos('samsung-pay.svg') . ' ',
                'description' => self::SAMSUNG_DESCRIPTION
            );
        }

        if ($method->rcs == '1') {
            $paymentOptionsArray[self::RCS_METHOD] = array(
                'image'       => $this->displayLogos('rcs.svg') . ' ',
                'description' => self::RCS_DESCRIPTION
            );
        }

        return $paymentOptionsArray;
    }

    public function paymentOptionsDataToSend(array $initiateFields)
    {
        $dataToSend = $_REQUEST['payment_method'];
        if (!empty($_REQUEST['payment_method'])) {
            switch ($_REQUEST['payment_method']) {
                case self::CREDIT_CARD_METHOD:
                    $initiateFields['PAY_METHOD']        = substr(self::CREDIT_CARD_METHOD, 0, 2);
                    $initiateFields['PAY_METHOD_DETAIL'] = self::CREDIT_CARD_DESCRIPTION;
                    break;
                case self::BANK_TRANSFER_METHOD:
                    $initiateFields['PAY_METHOD']        = substr(self::BANK_TRANSFER_METHOD, 0, 2);
                    $initiateFields['PAY_METHOD_DETAIL'] = self::BANK_TRANSFER_DESCRIPTION;
                    break;
                case self::ZAPPER_METHOD:
                    $initiateFields['PAY_METHOD']        = substr(self::ZAPPER_METHOD, 0, 2);
                    $initiateFields['PAY_METHOD_DETAIL'] = self::ZAPPER_DESCRIPTION;
                    break;
                case self::SNAPSCAN_METHOD:
                    $initiateFields['PAY_METHOD']        = substr(self::SNAPSCAN_METHOD, 0, 2);
                    $initiateFields['PAY_METHOD_DETAIL'] = self::SNAPSCAN_DESCRIPTION;
                    break;
                case self::PAYPAL_METHOD:
                    $initiateFields['PAY_METHOD']        = substr(self::PAYPAL_METHOD, 0, 2);
                    $initiateFields['PAY_METHOD_DETAIL'] = self::PAYPAL_DESCRIPTION;
                    break;
                case self::MOBICRED_METHOD:
                    $initiateFields['PAY_METHOD']        = substr(self::MOBICRED_METHOD, 0, 2);
                    $initiateFields['PAY_METHOD_DETAIL'] = self::MOBICRED_DESCRIPTION;
                    break;
                case self::MOMOPAY_METHOD:
                    $initiateFields['PAY_METHOD']        = substr(self::MOMOPAY_METHOD, 0, 2);
                    $initiateFields['PAY_METHOD_DETAIL'] = self::MOMOPAY_DESCRIPTION;
                    break;
                case self::SCANTOPAY_METHOD:
                    $initiateFields['PAY_METHOD']        = substr(self::SCANTOPAY_METHOD, 0, 2);
                    $initiateFields['PAY_METHOD_DETAIL'] = self::SCANTOPAY_DESCRIPTION;
                    break;
                case self::APPLE_PAY_METHOD:
                    $initiateFields['PAY_METHOD']        = substr(self::APPLE_PAY_METHOD, 0, 2);
                    $initiateFields['PAY_METHOD_DETAIL'] = substr(self::APPLE_PAY_METHOD, 3);
                    break;
                case self::SAMSUNG_PAY_METHOD:
                    $initiateFields['PAY_METHOD']        = substr(self::SAMSUNG_PAY_METHOD, 0, 2);
                    $initiateFields['PAY_METHOD_DETAIL'] = substr(self::SAMSUNG_PAY_METHOD, 3);
                    break;
                case self::RCS_METHOD:
                    $initiateFields['PAY_METHOD']        = substr(self::RCS_METHOD, 0, 2);
                    $initiateFields['PAY_METHOD_DETAIL'] = self::RCS_DESCRIPTION;
                    break;
                default:
                    break;
            }
        }

        return $initiateFields;
    }

    public function onSelectCheck(VirtueMartCart $cart)
    {
        $idName = $this->_idName;
        if (!$this->selectedThisByMethodId($cart->{$idName})) {
            $_SESSION['paygate_selected'] = false;

            return null; // Another method was selected, do nothing
        }
        $_SESSION['paygate_selected'] = true;

        return true; // this method was selected , and the data is valid by default
    }

    /**
     * @param $virtuemart_order_id
     * @param $payment_method_id
     *
     * @return string|null
     */
    public function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id): ?string
    {
        if (!$this->selectedThisByMethodId($payment_method_id)) {
            return null;
            // Another method was selected, do nothing
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $q  = 'SELECT * FROM `' . $this->_tablename . '` '
              . 'WHERE `virtuemart_order_id` = ' . $db->quote($virtuemart_order_id);
        $db->setQuery($q);
        if (!($paymentTable = $db->loadObject())) {
            return '';
        }

        $this->getPaymentCurrency($paymentTable);
        $q  = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="'
              . $db->quote($paymentTable->payment_currency) . '" ';
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery($q);
        $html = '<table class="adminlist">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
        $code = "paygate_response_";
        foreach ($paymentTable as $key => $value) {
            if (substr($key, 0, strlen($code)) == $code) {
                $html .= $this->getHtmlRowBE($key, $value);
            }
        }
        $html .= '</table>' . "\n";

        return $html;
    }
}
