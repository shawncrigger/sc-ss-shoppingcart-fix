<?php
/**
 * @package SC_SS_ShoppingCart_Fix
 */
/*
Plugin Name: Sharpspring Shopping Cart Abandonment Tracking
Plugin URI: https://github.com/shawncrigger/sc-ss-shoppingcart-fix/
Description: Enables Shopping Cart Abandonment in Sharpspring CRM, it does require a Sharpspring CRM account and to have the tracking code already installed.
Version: 1.5.0
Author: shawncrigger <ithippyshawn@gmail.com>
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
   /**
    * Holds current logged in users ID or Zero if not logged in
    * @var        int
    */
   protected $_user_id = 0;
   /**
    * Holds Store Name
    * @var        string
    */
   public $storeName = 'Whatever Store';

   // ------------------------------------------------------------------------

   /**
    * Add the Start Tracking Functionality to the WP HEAD and complete tracking at thank you page.
    */
   public function __construct()
   {
      add_action( 'woocommerce_thankyou', array( $this, 'complete_tracking' ),10,1 );
      add_action( 'wp_head', array( $this, 'start_cart_tracking' ), 999 );
      add_action( 'init',    array( $this, 'set_current_user_id' ), 10, 1 );
   }

   // ------------------------------------------------------------------------

   public function set_current_user_id() {
      $this->_user_id = (int) get_current_user_id();
      return $this->_user_id;
   }

   // ------------------------------------------------------------------------

   public function delete_order_id()
   {
      setcookie("_custom_cart_key", null, time()-3600);
      $_COOKIE['_custom_cart_key'] = '';
      if ( 0 >= $this->_user_id ) return;
      delete_usermeta( $this->_user_id, '_custom_cart_key' );
   }

   public function get_custom_order_id()
   {
      $user_id = $this->_user_id;
      $default = uniqid();

      if ( isset( $_COOKIE['_custom_cart_key'] ) && '' != $_COOKIE['_custom_cart_key'] )
      {
        $uniqid = $_COOKIE['_custom_cart_key'];
      } elseif ( $this->_user_id > 0 ) {
         $uniqid = get_user_meta( $user_id, '_custom_cart_key', true );
      }

      // Okay they were all blank so set a unique id
      if (!$uniqid OR is_null($uniqid) OR empty($uniqid)) {
         $uniqid = $default;
      }

      // if there is a logged in user update there meta value
      if ( $this->_user_id > 0 ) {
          update_user_meta( $user_id, '_custom_cart_key', $uniqid );
      }

      setcookie("_custom_cart_key", $uniqid, time()+3600);
      if ( ! isset( $_COOKIE['_custom_cart_sent'] ) OR 'true' != $_COOKIE['_custom_cart_sent'] ) {
          setcookie("_custom_cart_sent", 'true', time()+3600);
      }
      return $uniqid;
   }

   // ------------------------------------------------------------------------

   public function start_cart_tracking() {
       global $woocommerce;

       if ( 0 >= sizeof( WC()->cart->get_cart() ) ) return;

       $user_id = $this->_user_id;
       $uniqid  = $this->get_custom_order_id();

       $first_name = $last_name = $email_address = '';
       $total = $tax = $shipping = $zipcode = $country = 0;

       $country = 'USA';
       $items   = '';
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

       // Loop over cart items and create JSON array of items to push to SS
       foreach ( WC()->cart->get_cart() as $cart_item_key => $product ) :

        $product_sku_array = new WC_Product($product['product_id']);
           $product_sku  = $product_sku_array->get_sku();
           $product_name = $product_sku_array->get_name();
           $total = $total + $product['line_total'];
           $tax   = $tax + $product['line_tax'];
           $thumb = get_the_post_thumbnail_url($product['product_id']);
           $url   = get_permalink( $product['product_id'] );

           $json = array(
            'transactionID' => $uniqid,
            'itemCode'      => $product_sku,
            'productName'   => $product_name,
            'category'      => 'General',
            'price'         => $product['line_total'],
            'quantity'      => $product['quantity'],
            'imagePath'     => $thumb,
            'productURL'    => $url,
           );
           $json = json_encode($json);
           $items .= "\t_ss.push(['_addTransactionItem', {$json}]);\n";
       endforeach;

       //Build the _setTransaction javascript and add to page
       $json = array(
         'transactionID' => $uniqid,
         'storeName'     => $this->storeName,
         'total'         => $total,
         'tax'           => $tax,
         'shipping'      => $shipping,
         'city'          => $city,
         'state'         => $state,
         'zipcode'       => $zipcode,
         'country'       => $country,
         'firstName'     => $first_name,
         'lastName'      => $last_name,
         'emailAddress'  => $email_address,
         'cartUrl'       => $cart_url,
       );
       $json = json_encode($json);

       $script = "<!-- SharpSpring Shopping Cart Start -->\n
       <script type='text/javascript'>
           _ss.push(['_setTransaction', {$json}
           }, function() {
            {$items}
           }]);

       </script>
       <!-- SharpSpring Shopping Cart End -->";

   }

   // ------------------------------------------------------------------------


   public function complete_tracking( $order_id ) {

      // Lets grab the order
      $order = wc_get_order( $order_id );
      $billing = $order->get_address('billing');
      update_purchased_products( $order_id );

      $uniqid  = $this->get_custom_order_id();
      if ( '' == $uniqid OR ! is_string( $uniqid ) ) {
        $uniqid = $order->get_order_number();
      }

      $json = array(
       'transactionID' => "{$uniqid}",
       'storeName'     => "{$this->storeName}",
       'total'         => $order->get_total(),
       'tax'           => $order->get_total_tax(),
       'shipping'      => $order->get_total_shipping(),
       'city'          => "{$billing['city']}",
       'state'         => "{$billing['state']}",
       'zipcode'       => "{$billing['postcode']}",
       'country'       => "{$billing['country']}",
       'firstName'     => "{$billing['first_name']}",
       'lastName'      => "{$billing['last_name']}",
       'emailAddress'  => "{$billing['email']}",
      );
      $json = json_encode($json);

      $script = "<!-- SharpSpring Shopping Cart Start -->\n
      <script type='text/javascript'>
          _ss.push(['_setTransaction', $json]);\n\n";

      $order_item = $order->get_items();
      foreach( $order_item as $product ) :
         $product_sku_array = new WC_Product($product['product_id']);
         $product_sku = $product_sku_array->get_sku();
         $thumb = get_the_post_thumbnail_url($product['product_id']);
         $json = json_encode(array(
            'transactionID' => "{$uniqid}",
            'itemCode'      => "{$product_sku}",
            'productName'   => "{$product['name']}",
            'category'      => 'General',
            'price'         => "{$product['line_total']}",
            'imagePath'     => "{$thumb}",
            'quantity'      => "{$product['qty']}",
         ));
         $script .= "  _ss.push(['_addTransactionItem', $json]);\n";
      endforeach;

      $script .= "
         _ss.push(['_completeTransaction', {
            'transactionID': '{$uniqid}'
         }]);
       </script>\n
       \n<!-- SharpSpring Shopping Cart End -->\n";


   }// complete_tracking()

}// SC_SS_ShoppingCart_Fix


$GLOBALS['SC_SS_ShoppingCart_Fix'] = new SC_SS_ShoppingCart_Fix();
