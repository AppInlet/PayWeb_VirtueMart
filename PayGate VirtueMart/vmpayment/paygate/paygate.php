<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
defined( '_JEXEC' ) || die();

# Get plugin class
if ( !class_exists( 'vmPSPlugin' ) ) {
    require JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';
}

/**
 * Class plgVmPaymentPayGate
 *
 * @author  App Inlet (Pty) Ltd
 * @version 1.0.3
 * @link    https://developer.paygate.co.za/products/shoppingcarts
 */
class plgVmPaymentPayGate extends vmPSPlugin
{

    public static $_this = false;

    public $paygateId;
    public $paygateKey;

    const ORDER_NUMBER = 'Order #';

    /**
     * Save settings
     *
     * @param $subject
     * @param $config
     */
    public function __construct( &$subject, $config )
    {

        parent::__construct( $subject, $config );

        $this->_loggable = true;

        $this->tableFields  = array_keys( $this->getTableSQLFields() );
        $this->_tablepkey   = 'id';
        $this->_tableId     = 'id';
        $this->_initiateurl = 'https://secure.paygate.co.za/payweb3/initiate.trans';
        $this->_processurl  = 'https://secure.paygate.co.za/payweb3/process.trans';

        $settings = array(
            'test'          => array( 0, 'int' ),
            'id'            => array( '', 'char' ),
            'key'           => array( '', 'char' ),
            'pw3iframe'     => array( '', 'char' ),
            'successful'    => array( '', 'char' ),
            'failed'        => array( '', 'char' ),
            'payment_logos' => array( '', 'char' ),
        );

        $this->setConfigParameterable( $this->_configTableFieldName, $settings );
    }

    /**
     * @ If curl is working on the server
     */
    public function _is_curl_installed()
    {
        return in_array( 'curl', get_loaded_extensions() );
    }

    /**
     * @ Generate the checksum string
     */

    public function generateChecksum( $postData, $paygetkey )
    {
        $checksum = '';
        foreach ( $postData as $value ) {
            if ( $value != '' ) {
                $checksum .= $value;
            }
        }
        $checksum .= $paygetkey;
        return md5( $checksum );
    }

    /**
     * Define name for PayGate table
     *
     * @return mixed
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL( 'PayGate Table' );
    }

    /**
     * Define fields for PayGate table
     *
     * @return array
     */
    public function getTableSQLFields()
    {
        return array(
            'id'                  => 'INT(11) NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'INT(11)',
            'paygate_post'        => 'varchar(2500)',
            'payment_name'        => 'VARCHAR(75) NOT NULL DEFAULT "PayGate"',
        );
    }

    /**
     * Redirect Customer
     *
     * @param $cart
     * @param $order
     * @return bool
     */
    public function plgVmConfirmedOrder( $cart, $order )
    {
        $details = $order['details']['BT'];
        $method  = $this->getVmPluginMethod( $details->virtuemart_paymentmethod_id );

        if ( !$method ) {
            return false;
        }

        $iframe = $method->get( 'pw3iframe' );

        if ( !$this->selectedThisElement( $method->payment_element ) ) {
            return false;
        }

        $responseFields = $this->initiateTransaction( $cart, $order );

        unset( $responseFields['CHECKSUM'] );

        $checksum   = $this->generateChecksum( $responseFields, $this->paygateKey );
        $processurl = $this->_processurl;

        // Add CSS for the IFrame
        vmJsApi::css( 'paygate', 'plugins/vmpayment/paygate/' );
        if ( $iframe !== '0' ) {
            $html = <<<HTML
<p>Kindly wait while you're redirected to PayGate ...</p>
<div id="payPopup">
    <div id="payPopupContent">
        <form action="{$processurl}" method="post" name="redirect" target="pw3Iframe">
            <input name="PAY_REQUEST_ID" type="hidden" value="{$responseFields['PAY_REQUEST_ID']}" />
            <input name="CHECKSUM" type="hidden" value="{$checksum}" />
        </form>
        <iframe name="pw3Iframe" id="payPopupFrame"></iframe>
        <script type="text/javascript">document.forms['redirect'].submit();</script>
    </div>
</div>
HTML;
        } else {

            $html = <<<HTML
<p>Kindly wait while you're redirected to PayGate ...</p>
        <form action="{$processurl}" method="post" name="redirect" >
            <input name="PAY_REQUEST_ID" type="hidden" value="{$responseFields['PAY_REQUEST_ID']}" />
            <input name="CHECKSUM" type="hidden" value="{$checksum}" />
        </form>
        <script type="text/javascript">document.forms['redirect'].submit();</script>
HTML;
        }
        ( new JInput() )->set( 'html', $html );
        return false;
    }

    /**
     * Notify Handler
     *
     * @param $html
     * @return bool
     */
    public function plgVmOnPaymentResponseReceived( &$html )
    {
        $method             = filter_var( $_GET['pm'], FILTER_SANITIZE_STRING );
        $order              = filter_var( $_GET['on'], FILTER_SANITIZE_STRING );
        $pm                 = filter_var( $_GET['pm'], FILTER_SANITIZE_STRING );
        $itemid             = filter_var( $_GET['Itemid'], FILTER_SANITIZE_STRING );
        $PAY_REQUEST_ID     = filter_var( $_POST['PAY_REQUEST_ID'], FILTER_SANITIZE_STRING );
        $TRANSACTION_STATUS = filter_var( $_POST['TRANSACTION_STATUS'], FILTER_SANITIZE_STRING );
        $CHECKSUM           = filter_var( $_POST['CHECKSUM'], FILTER_SANITIZE_STRING );
        if ( !$order ) {
            return false;
        }

        if ( isset( $_GET['ifr'] ) ) {
            $ifr = filter_var( $_GET['ifr'], FILTER_SANITIZE_STRING );
        }

        $method = $this->getVmPluginMethod( $method );
        if ( !$method ) {
            return false;
        }

        // Check if iFrame is enabled
        $iframe = $method->get( 'pw3iframe' );
        if ( $iframe && $ifr !== 'true' ) {
            $root = JROUTE::_( JURI::root() );
            $url  = <<<URL
{$root}index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on={$order}&pm={$pm}&Itemid={$itemid}&ifr=true&PAY_REQUEST_ID={$PAY_REQUEST_ID}&TRANSACTION_STATUS={$TRANSACTION_STATUS}&CHECKSUM={$CHECKSUM}
URL;
            echo '<script>window.top.location.href="' . $url . '";</script>';
            exit;
        } elseif ( $iframe && $ifr === 'true' ) {
            $_POST                       = [];
            $_POST['PAY_REQUEST_ID']     = filter_var( $_GET['PAY_REQUEST_ID'], FILTER_SANITIZE_STRING );
            $_POST['TRANSACTION_STATUS'] = filter_var( $_GET['TRANSACTION_STATUS'], FILTER_SANITIZE_STRING );
            $_POST['CHECKSUM']           = filter_var( $_GET['CHECKSUM'], FILTER_SANITIZE_STRING );
        }

        if ( !$this->selectedThisElement( $method->payment_element ) ) {
            return false;
        }

        $status    = $_POST['TRANSACTION_STATUS'];
        $model     = VmModel::getModel( 'orders' );
        $id        = $model->getOrderIdByOrderNumber( $order );
        $or        = $model->getOrder( $id );
        $detail    = $or['details']['BT'];
        $reference = self::ORDER_NUMBER . $detail->order_number;

        // Check validity of checksum before going any further
        $sent_sum  = array_pop( $_POST );
        $paygateid = $method->get( 'id' );
        $paygatekey = $method->get( 'key' );
        if ($method->get( 'test' )) {
            $paygateid = '10011072130';
            $paygatekey = 'secret';
        }
        $our_sum   = md5( $paygateid . implode( '', $_POST ) . $reference . $paygatekey );
        $sumok     = hash_equals( $sent_sum, $our_sum );
        if ( $sumok ) {
            if ( $detail->order_status == 'P' ) {
                if ( $status == '1' ) {
                    $displayComment                = vmText::_( 'VMPAYMENT_PAYGATE_SUCCESSFUL_PAYMENT' );
                    $details['customer_notified']  = 1;
                    $details['order_status']       = $method->successful;
                    $details['comments']           = vmText::sprintf( 'VMPAYMENT_PAYGATE_SUCCESSFUL_PAYMENT_COMMENT', $_POST['PAY_REQUEST_ID'] );
                    $fields['virtuemart_order_id'] = $id;
                } else {
                    if ( $status == '2' ) {
                        $details['comments'] = vmText::_( 'VMPAYMENT_PAYGATE_DECLINED_PAYMENT' );
                        $displayComment      = vmText::_( 'VMPAYMENT_PAYGATE_DECLINED_PAYMENT_COMMENT' );
                    } else {
                        $details['comments'] = vmText::_( 'VMPAYMENT_PAYGATE_FAILURE_PAYMENT' );
                        $displayComment      = vmText::_( 'VMPAYMENT_PAYGATE_FAILURE_PAYMENT_COMMENT' );
                    }
                    $details['customer_notified']  = 1;
                    $details['order_status']       = $method->failed;
                    $fields['virtuemart_order_id'] = $id;
                }

                $post = '';
                foreach ( $_POST as $k => $v ) {
                    $post .= $k . '="' . $v . '"|';
                }

                $fields['paygate_post'] = substr( $post, 0, -1 );
                $fields['payment_name'] = 'PayGate';

                $this->storePSPluginInternalData( $fields, 'virtuemart_order_id', true );
                $model->updateStatusForOneOrder( $id, $details, true );
            }

            if ( !class_exists( 'VirtueMartCart' ) ) {
                require JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php';
            }

            $cart = VirtueMartCart::getCart();
            if ( $status == 1 ) {
                $cart->emptyCart();
            }
        } else {
            $displayComment = 'The transaction could not be verified. Please contact the store';
        }

        $html = <<<HTML
<p>{$displayComment}</p>
HTML;
        return true;
    }

    /*
     * notify handler
     */
    public function plgVmOnPaymentNotification()
    {

        if ( !class_exists( 'VirtueMartModelOrders' ) ) {
            require JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php';
        }

        // Notify PayGate
        echo 'OK';
        $errors       = false;
        $paygate_data = array();

        $notify_data = array();
        // Get notify data
        if ( !$errors ) {
            $paygate_data = $this->getPostData();
            if ( $paygate_data === false ) {
                $errors = true;
            }
        }

        $order_number = $paygate_data['USER1'];

        $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber( $paygate_data['USER1'] );

        if ( !$virtuemart_order_id ) {
            exit;
        }

        $method = $_GET['pm'];
        $method = $this->getVmPluginMethod( $method );
        if ( !$method ) {
            return false;
        }
        if ( !$this->selectedThisElement( $method->payment_element ) ) {
            return false;
        }
        $order = $_GET['on'];
        if ( !$order ) {
            return false;
        }
        $model   = new VirtueMartModelOrders();
        $payment = $model->getOrderIdByOrderNumber( $order_number );

        if ( !$payment ) {
            return null;
        }

        $checkSumParams = '';
        if ( !$errors ) {

            foreach ( $paygate_data as $key => $val ) {
                $notify_data[$key] = stripslashes( $val );

                if ( $key == 'PAYGATE_ID' ) {
                    $checkSumParams .= $val;
                }
                if ( $key != 'CHECKSUM' && $key != 'PAYGATE_ID' ) {
                    $checkSumParams .= $val;
                }

                if ( empty( $notify_data ) ) {
                    $errors = true;
                }
            }
            $checkSumParams .= $method->$key;
        }

        // Verify security signature
        if ( !$errors ) {
            $checkSumParams = md5( $checkSumParams );
            if ( $checkSumParams != $paygate_data['CHECKSUM'] ) {
                $errors = true;
            }
        }

        if ( !$errors ) {
            $status  = 'P';
            $comment = 'You unfortunately did not complete payment with PayGate';
            switch ( $paygate_data['TRANSACTION_STATUS'] ) {
                case '1':
                    $comment = 'Notify response: Payment was successfully completed with PayGate';
                    $status  = 'C';
                    break;
                case '2':
                    $comment = 'Notify response: Payment was declined with PayGate';
                    $status  = 'X';
                    break;
                case '0':
                case '4':
                    $comment = 'Notify response: You unfortunately did not complete payment with PayGate';
                    $status  = 'X';
                    break;

                default:
                    // If unknown status, do nothing (safest course of action)
                    break;
            }

            $id                            = $model->getOrderIdByOrderNumber( $order_number );
            $details['customer_notified']  = 0;
            $details['order_status']       = $status;
            $details['comments']           = $comment;
            $fields['virtuemart_order_id'] = $id;

            $post = '';
            foreach ( $paygate_data as $k => $v ) {
                $post .= $k . '="' . $v . '"|';
            }

            $fields['paygate_post'] = substr( $post, 0, -1 );
            $fields['payment_name'] = 'PayGate';

            $this->storePSPluginInternalData( $fields, 'virtuemart_order_id', true );

            $model->updateStatusForOneOrder( $id, $details, true );
            if ( !class_exists( 'VirtueMartCart' ) ) {
                require JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php';
            }

            $cart = VirtueMartCart::getCart();

            $cart->emptyCart();
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
    protected function checkConditions( $cart, $method, $cart_prices )
    {

        $this->convert( $method );

        $address = (  ( $cart->ST == 0 ) ? $cart->BT : $cart->ST );

        $amount      = $cart_prices['salesPrice'];
        $amount_cond = ( $amount >= $method->min_amount && $amount <= $method->max_amount
            ||
            ( $method->min_amount <= $amount && ( $method->max_amount == 0 ) ) );

        $countries = array();
        if ( !empty( $method->countries ) ) {
            if ( !is_array( $method->countries ) ) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }
        // Probably did not gave his BT:ST address
        if ( !is_array( $address ) ) {
            $address                          = array();
            $address['virtuemart_country_id'] = 0;
        }

        if ( !isset( $address['virtuemart_country_id'] ) ) {
            $address['virtuemart_country_id'] = 0;
        }
        if (  ( in_array( $address['virtuemart_country_id'], $countries ) || empty( $countries ) ) && $amount_cond ) {
            return true;
        }

        return false;
    }

    /**
     * @param $method
     */
    public function convert( $method )
    {

        $method->cost_percent_total   = null;
        $method->cost_per_transaction = null;

        if ( !isset( $method->min_amount ) && !isset( $method->max_amount ) ) {
            $method->min_amount = 0;
            $method->max_amount = 0;
        }

        $method->min_amount = (float) $method->min_amount;
        $method->max_amount = (float) $method->max_amount;
    }

    // Updated curlPost function
    public function curlPost( $url, $fields )
    {
        if ( $this->_is_curl_installed() ) {
            $fields_string = '';
            foreach ( $fields as $key => $value ) {
                $fields_string .= $key . '=' . urlencode( $value ) . '&';
            }
            $fields_string = rtrim( $fields_string, '&' );
            // Get host safely
            $possibleHostSources   = array( 'HTTP_X_FORWARDED_HOST', 'HTTP_HOST', 'SERVER_NAME', 'SERVER_ADDR' );
            $sourceTransformations = array(
                "HTTP_X_FORWARDED_HOST" => function ( $value ) {
                    $elements = explode( ',', $value );
                    return trim( end( $elements ) );
                },
            );
            $host = '';
            foreach ( $possibleHostSources as $source ) {
                if ( !empty( $host ) ) {
                    break;
                }

                if ( empty( $_SERVER[$source] ) ) {
                    continue;
                }

                $host = $_SERVER[$source];
                if ( array_key_exists( $source, $sourceTransformations ) ) {
                    $host = $sourceTransformations[$source]( $host );
                }
            }

            // Remove port number from host
            $host = trim( preg_replace( '/:\d+$/', '', $host ) );

            $ch = curl_init();
            if ( !$this->isSsl() ) {
                curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 1 );
                curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
            }
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_NOBODY, false );
            curl_setopt( $ch, CURLOPT_REFERER, $host );
            curl_setopt( $ch, CURLOPT_POST, 1 );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields_string );
            $result = curl_exec( $ch );
            curl_close( $ch );
            return $result;
        } else {
            echo 'cURL is NOT installed on this server. http://php.net/manual/en/curl.setup.php';
            exit;
        }
    }

    public function isSsl()
    {
        $url = parse_url( JURI::base() );

        return ($url['scheme'] == 'https');
    }

    public function getPostData()
    {
        // Posted variables from ITN
        $nData = $_POST;

        // Strip any slashes in data
        foreach ( $nData as $key => $val ) {
            $nData[$key] = stripslashes( $val );
        }

        // Return "false" if no data was received
        if ( empty( $nData ) ) {
            return ( false );
        } else {
            return ( $nData );
        }

    }

    /**
     * Log function for logging output.
     *
     * @param $msg String Message to log
     * @param $close Boolean Whether to close the log file or not
     */
    public function logData( $msg = '', $close = false )
    {
        static $fh = 0;
        global $module;

        // Only log if debugging is enabled
        if ( PF_DEBUG ) {
            if ( $close ) {
                fclose( $fh );
            } else {
                // If file doesn't exist, create it
                if ( !$fh ) {
                    $pathinfo = pathinfo( __FILE__ );
                    $fh       = fopen( $pathinfo['dirname'] . '/paygate.log', 'a+' );
                }

                // If file was successfully created
                if ( $fh ) {
                    $line = date( 'Y-m-d H:i:s' ) . ' : ' . $msg . "\n";

                    fwrite( $fh, $line );
                }
            }
        }
    }

    /**
     * Function that does server to server call for Initiate to PayGate
     *
     * @param $cart
     * @param $order
     * @return array
     */
    public function initiateTransaction( $cart, $order )
    {

        $details = $order['details']['BT'];
        $method  = $this->getVmPluginMethod( $details->virtuemart_paymentmethod_id );
        if ( !$method ) {
            return false;
        }

        if ( !$this->selectedThisElement( $method->payment_element ) ) {
            return false;
        }

        $this->logInfo( self::ORDER_NUMBER . $details->order_number . ' confirmed', 'message' );

        if ( $method->test ) {
            $this->paygateId  = '10011072130';
            $this->paygateKey = 'secret';
        } else {
            $this->paygateId  = $method->id;
            $this->paygateKey = $method->key;
        }

        $db = JFactory::getDBO();
        // Get country code 3
        $query = $db->getQuery( true );
        $query->select( 'country_3_code' );
        $query->from( '#__virtuemart_countries' );
        $query->where( 'virtuemart_country_id = ' . $details->virtuemart_country_id );
        $db->setQuery( $query );
        $country_code3 = $db->loadResult();

        // Get currency code 3
        $query = $db->getQuery( true );
        $query->select( 'currency_code_3' );
        $query->from( '#__virtuemart_currencies' );
        $query->where( 'virtuemart_currency_id = ' . $details->order_currency );
        $db->setQuery( $query );
        $currency_code3 = $db->loadResult();

        $reference = self::ORDER_NUMBER . $details->order_number;
        $amount    = number_format( $details->order_total * 100, 0, '', '' );

        $url        = JROUTE::_( JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $details->order_number . '&pm=' . $details->virtuemart_paymentmethod_id . '&Itemid=' . JRequest::getInt( 'Itemid' ) );
        $date       = date( 'Y-m-d H:i:s' );
        $email      = $details->email;
        $notify_url = JROUTE::_( JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . "&XDEBUG_SESSION_START=session_name" . "&o_id={$order['details']['BT']->order_number}" );

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
            'NOTIFY_URL'       => $notify_url,
            'USER1'            => $details->order_number,
            'USER3'            => 'virtuemart-v1.0.3',
        );

        $initiateFields['CHECKSUM'] = $this->generateChecksum( $initiateFields, $this->paygateKey );
        $response                   = $this->curlPost( $this->_initiateurl, $initiateFields );
        parse_str( $response, $responseFields );
        return $responseFields;
    }

# Default functions

    public function plgVmDisplayListFEPayment( VirtueMartCart $cart, $selected = 0, &$html )
    {
        $this->displayListFE( $cart, $selected, $html );
        return true;
    }

    public function plgVmDeclarePluginParamsPaymentVM3( &$data )
    {
        return $this->declarePluginParams( 'payment', $data );
    }

    public function plgVmSetOnTablePluginParamsPayment( $name, $id, &$table )
    {
        return $this->setOnTablePluginParams( $name, $id, $table );
    }

    public function plgVmonShowOrderPrintPayment( $order, $method )
    {
        return $this->onShowOrderPrint( $order, $method );
    }

    public function plgVmOnShowOrderFEPayment( $order, $method, &$payment )
    {
        $this->onShowOrderFE( $order, $method, $payment );
    }

    public function plgVmOnCheckAutomaticSelectedPayment( VirtueMartCart $cart, $prices = array(), &$counter )
    {
        return $this->onCheckAutomaticSelected( $cart, $prices, $counter );
    }

    public function plgVmonSelectedCalculatePricePayment( VirtueMartCart $cart, &$prices, &$names )
    {
        return $this->onSelectedCalculatePrice( $cart, $prices, $names );
    }

    public function plgVmOnSelectCheckPayment( VirtueMartCart $cart )
    {
        return $this->OnSelectCheck( $cart );
    }

    public function plgVmOnStoreInstallPaymentPluginTable( $id )
    {
        return $this->onStoreInstallPluginTable( $id );
    }

    public function plgVmDeclarePluginParamsCustomVM3( &$data )
    {
        return $this->declarePluginParams( 'custom', $data );
    }

    public function plgVmGetTablePluginParams( $psType, $name, $id, &$xParams, &$varsToPush )
    {
        return $this->getTablePluginParams( $psType, $name, $id, $xParams, $varsToPush );
    }
}
