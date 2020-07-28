<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
// No direct access
defined( '_JEXEC' ) or die;

// Load the language file of com_virtuemart.
JFactory::getLanguage()->load( 'com_virtuemart' );

if ( !class_exists( 'VirtueMartModelOrders' ) ) {
    require JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'models' . DS . 'orders.php';
}

class mod_virtuemart_order_pay_button
{
    private $orderNumber;

    public function __construct( $orderNumber = 0 )
    {
        $this->orderNumber = $orderNumber;
    }

    public function getOrderProducts( $orderNumber = 0 )
    {
        if ( !$orderNumber ) {
            $orderNumber = $this->orderNumber;
        }

        $orderModel = new VirtueMartModelOrders();
        $orderid    = $orderModel->getOrderIdByOrderNumber( $orderNumber );

        return $orderModel->getOrder( $orderid );
    }

    public function getCustomIdByTitle( $title )
    {
        if ( !$title ) {
            return null;
        }

        $db    = JFactory::getDbo();
        $query = $db->getQuery( true );
        $query->select( 'virtuemart_custom_id' )
            ->from( '#__virtuemart_customs' )
            ->where( 'custom_title = ' . $db->quote( $title ) );
        $db->setQuery( $query );
        $result = $db->loadResult();

        if ( $errorMsg = $db->getErrorMsg() ) {
            JFactory::getApplication()->enqueueMessage( $errorMsg, 'error' );
        }

        return $result;
    }

    public function isSsl()
    {
        $url = parse_url( JURI::base() );

        return $url['scheme'] == 'https';
    }
}
