<?php
/**
 * @package SC_SS_ShoppingCart_Fix
 */
/*
Plugin Name: Sharpspring Shopping Cart Abandonment Tracking
Plugin URI: https://akismet.com/
Description: Enables Shopping Cart Abandonment in Sharpspring CRM, it does require a Sharpspring CRM account and to have the tracking code already installed.
Version: 1.0.0
Author: shawncrigger
Contributors: shawncrigger
Author URI: https://github.com/shawncrigger/
License: GPLv2 or later
Text Domain: sc-sharpspring
*/

/**
 *
 */
class SC_SS_ShoppingCart_Fix
{

	public function __construct()
	{
		add_action( 'woocommerce_thankyou', array( $this, 'complete_tracking' ),10,1 );
		add_action( 'wp_head', array( $this, 'start_cart_tracking' ), 999 );
	}

	// ------------------------------------------------------------------------

	public function delete_order_id()
	{
		$user_id = (int) get_current_user_id();
		setcookie("_custom_cart_key", null, time()-3600);
		$_COOKIE['_custom_cart_key'] = '';
		if ( 0 >= $user_id ) return;
		delete_usermeta( $user_id, '_custom_cart_key' );
	}

	public function get_custom_order_id()
	{
		$user_id = (int) get_current_user_id();

		if ( isset( $_COOKIE['_custom_cart_key'] ) && '' != $_COOKIE['_custom_cart_key'] )
		{
		    $uniqid = $_COOKIE['_custom_cart_key'];
		} elseif ( $uniqid = get_user_meta( $user_id, '_custom_cart_key', true ) )
		{
			$uniqid = get_user_meta( $user_id, '_custom_cart_key', true );
		} else {
		    $uniqid = uniqid();
		}

		if ( $user_id > 0 ) {
		    update_user_meta( $user_id, '_custom_cart_key', $uniqid );
		}

		setcookie("_custom_cart_key", $uniqid, time()+3600);
		if ( ! isset( $_COOKIE['_custom_cart_sent'] ) OR 'true' != $_COOKIE['_custom_cart_sent'] ) :
		    setcookie("_custom_cart_sent", 'true', time()+3600);
		endif;
		return $uniqid;
	}

	// ------------------------------------------------------------------------

	public function start_cart_tracking() {
	    global $woocommerce;

	    if ( 0 >= sizeof( WC()->cart->get_cart() ) ) return;

	    $user_id = (int) get_current_user_id();
	    $uniqid  = stm_get_custom_order_id();

	    $first_name = $last_name = $email_address = '';
	    $total = $tax = $shipping = $zipcode = $country = 0;

	    $country = 'USA';
	    $current_user = wp_get_current_user();
	    if ( $current_user instanceof WP_User ) {
	    	$user_id       = (int) $current_user->ID;
	    	$name = $current_user->display_name;
	    	$name = explode( ' ', $name );
	    	$first_name    = isset( $name[0] ) ? $name[0] : $name;
	    	$last_name     = isset( $name[1] ) ? $name[1] : $name;
	    	$email_address = $current_user->user_email;

	    	$name       = get_user_meta( $current_user->ID, 'billing_first_name', true );
	    	$first_name = ( '' != $name ) ? $name : $first_name;
	    	$name       = get_user_meta( $current_user->ID, 'billing_last_name', true );
	    	$last_name  = ( '' != $name ) ? $name : $last_name;
	    	$city       = get_user_meta( $current_user->ID, 'billing_city', true );
	    	$state      = get_user_meta( $current_user->ID, 'billing_state', true );
	    	$zipcode    = get_user_meta( $current_user->ID, 'billing_postcode', true );
	    }

	    $cart_url = wc_get_cart_url();
	    echo '<!--' . PHP_EOL;
	    echo 'CARTKEY: ' . $uniqid . PHP_EOL;
	    echo PHP_EOL . '-->' . PHP_EOL;

	    foreach ( WC()->cart->get_cart() as $cart_item_key => $product ) :

	        $product_sku_array = new WC_Product($product['product_id']);
	        $product_sku = $product_sku_array->get_sku();
	        $product_name = $product_sku_array->get_name();
	        $total = $total + $product['line_total'];
	        $tax   = $tax + $product['line_tax'];
	        $thumb = get_the_post_thumbnail_url($product['product_id']);
	        $url   = get_permalink( $product['product_id'] );
	        $items .= <<<EOL

	        _ss.push(['_addTransactionItem', {
	            'transactionID': '{$uniqid}',
	            'itemCode':         '{$product_sku}',
	            'productName':  '{$product_name}',
	            'category':         'General',
	            'price':        '{$product['line_total']}',
	            'quantity':         '{$product['quantity']}',
	            'imagePath':        '{$thumb}',
	            'productURL':        '{$url}'
	        }]);
EOL;

	    endforeach;

	    echo <<<EOL

		<!-- SharpSpring Shopping Cart Start -->

	    <script type='text/javascript'>
	        _ss.push(['_setTransaction', {
	            'transactionID': '{$uniqid}',
	            'storeName': 'innovativedehumidifiers.com Online Store',
	            'total':    '{$total}',
	            'tax':      '{$tax}',
	            'shipping': '{$shipping}',
	            'city':     '{$city}',
	            'state':    '{$state}',
	            'zipcode':  '{$zipcode}',
	            'country':  '{$country}',
	            'firstName' : '{$first_name}',
	            'lastName' : '{$last_name}',
	            'emailAddress' : '{$email_address}',
	            'cartUrl': '{$cart_url}'

	        }, function() {
	        	{$items}
	        }]);

	    </script>
	    <!-- SharpSpring Shopping Cart End -->
EOL;

	}

	// ------------------------------------------------------------------------

	public function complete_tracking( $order_id ) {

	    // Lets grab the order
	    $order = wc_get_order( $order_id );
	    $billing = $order->get_address('billing');
	    update_purchased_products( $order_id );

	    $uniqid  = stm_get_custom_order_id();
	    if ( '' == $uniqid OR ! is_string( $uniqid ) )
	    {
	    	$uniqid = $order->get_order_number();
	    }
	?>
	    <script type='text/javascript'>
	        _ss.push(['_setTransaction', {
	            'transactionID': '<?php echo $uniqid; ?>',
	            'storeName': 'innovativedehumidifiers.com Online Store',
	            'total':    '<?php echo $order->get_total(); ?>',
	            'tax':      '<?php echo $order->get_total_tax(); ?>',
	            'shipping': '<?php echo $order->get_total_shipping(); ?>',
	            'city':     '<?php echo $billing['city']; ?>',
	            'state':    '<?php echo $billing['state']; ?>',
	            'zipcode':  '<?php echo $billing['postcode']; ?>',
	            'country':  '<?php echo $billing['country']; ?>',

	            'firstName' : '<?php echo $billing['first_name']; ?>', // optional parameter
	            'lastName' : '<?php echo $billing['last_name']; ?>', // optional parameter
	            'emailAddress' : '<?php echo $billing['email']; ?>', // optional parameter
	        }]);
	<?php
	    $order_item = $order->get_items();

	    foreach( $order_item as $product ) :
	        $product_sku_array = new WC_Product($product['product_id']);
	        $product_sku = $product_sku_array->get_sku();
	        $thumb = get_the_post_thumbnail_url($product['product_id']);
	        echo <<<EOL

	        _ss.push(['_addTransactionItem', {
	            'transactionID': '{$uniqid}',
	            'itemCode':      '{$product_sku}',
	            'productName':   '{$product['name']}',
	            'category':      'General',
	            'price':         '{$product['line_total']}',
	            'imagePath':     '{$thumb}',
	            'quantity':      '{$product['qty']}'
	        }]);
EOL;
		endforeach;

		echo <<<EOL
	    </script>
	    <script type='text/javascript'>
	        _ss.push(['_completeTransaction', {
	            'transactionID': '<?php echo $uniqid; ?>'
	        }]);
	    </script>
EOL;

	}

}


$GLOBALS['SC_SS_ShoppingCart_Fix'] = new SC_SS_ShoppingCart_Fix();