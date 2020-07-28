<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

// order_status_code
$details = $order['details']['BT'];

$db = JFactory::getDBO();
// Get method config data
$query = $db->getQuery( true );
$query->select( 'payment_params' );
$query->from( '#__virtuemart_paymentmethods' );
$query->where( 'virtuemart_paymentmethod_id = ' . $details->virtuemart_paymentmethod_id );
$db->setQuery( $query );
$method_params = $db->loadResult();
$method_params = str_replace( array( '|', '"' ), array( '&', '' ), $method_params );
$method_data   = array();
parse_str( $method_params, $method_data );
$id  = '';
$key = '';
if ( $method_data['test'] == '1' ) {
    $id  = '10011072130';
    $key = 'secret';
} else {
    $id  = $method_data['id'];
    $key = $method_data['key'];
}

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

$reference  = 'Order #' . $details->order_number;
$amount     = number_format( $details->order_total * 100, 0, '', '' );
$url        = JROUTE::_( JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $details->order_number . '&pm=' . $details->virtuemart_paymentmethod_id . '&Itemid=' . JRequest::getInt( 'Itemid' ) );
$date       = date( 'Y-m-d H:i:s' );
$email      = $details->email;
$checksum   = $id . '|' . $reference . '|' . $amount . '|' . $currency_code3 . '|' . $url . '|' . $date . '|' . $email . '|' . $key;
$checksum   = md5( $checksum );
$notify_url = JROUTE::_( JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . "&XDEBUG_SESSION_START=session_name" . "&o_id={$order['details']['BT']->order_number}" );

$fields = array(
    'PAYGATE_ID'       => $id,
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
$fields['CHECKSUM'] = md5( implode( '', $fields ) . $key );
$url                = 'https://secure.paygate.co.za/payweb3/initiate.trans';
$fields_string      = '';
foreach ( $fields as $k => $value ) {
    $fields_string .= $k . '=' . urlencode( $value ) . '&';
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
if ( !$helper->isSsl() ) {
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 1 );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
}
curl_setopt( $ch, CURLOPT_URL, $url );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
curl_setopt( $ch, CURLOPT_NOBODY, false );
curl_setopt( $ch, CURLOPT_REFERER, $host );
curl_setopt( $ch, CURLOPT_POST, 1 );
curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields_string );
$response = curl_exec( $ch );
curl_close( $ch );

parse_str( $response, $responsefields );
unset( $responsefields['CHECKSUM'] );
$checksum       = md5( implode( '', $responsefields ) . $key );
$pay_request_id = $responsefields['PAY_REQUEST_ID'];

if ( isset( $products ) && is_array( $products ) && count( $products ) > 0 && $details->order_status == 'P' ) {
    ?>
<div>
  <form method="post" action="https://secure.paygate.co.za/payweb3/process.trans">
    </br></br>
    <div class="pay-bar">
      <span class="addtocart-button">
        <input type="submit" name="pay" class="addtocart-button" value="Pay using PayGate" title="Pay using PayGate"/>
      </span>
    </div>
    <input name="PAY_REQUEST_ID" type="hidden" value="<?php echo $pay_request_id; ?>" />
    <input name="CHECKSUM" type="hidden" value="<?php echo $checksum; ?>" />
  </form>
</div>
<?php }
