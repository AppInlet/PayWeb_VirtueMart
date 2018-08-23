<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */
// No direct access
defined( '_JEXEC' ) or die;

if ( !class_exists( 'mod_virtuemart_order_pay_button' ) ) {
    require 'helper.php';
}

$cart = VirtueMartCart::getCart();

$orderNumber = JRequest::getVar( 'order_number' );

if ( $orderNumber ) {
    $cart->emptyCart();
    $helper = new mod_virtuemart_order_pay_button();
    $order  = $helper->getOrderProducts( $orderNumber );

    $products = $order['items'];
    require JModuleHelper::getLayoutPath( 'mod_virtuemart_order_pay_button' );
}
