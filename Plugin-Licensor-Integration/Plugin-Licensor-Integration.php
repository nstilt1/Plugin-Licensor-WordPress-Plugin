<?php
/**
 * Plugin Licensor Integration.
 *
 * @package  WC_Plugin_Licensor_Integration
 * @category Integration
 * @author   Noah Stiltner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

 // Test to see if WooCommerce is active (including network activated).
$plugin_path = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce/woocommerce.php';

if (
    in_array( $plugin_path, wp_get_active_and_valid_plugins() )
    || in_array( $plugin_path, wp_get_active_network_plugins() )
) {

    if ( ! class_exists( 'WC_Plugin_Licensor_Integration' ) ) :
        class WC_Plugin_Licensor_Integration extends WC_Integration {
            /**
             * Init and hook in the integration.
             */
            public function __construct() {
                global $woocommerce;
                $this->id                 = 'plugin-licensor-integration';
                $this->method_title       = __( 'Plugin Licensor', 'plugin-licensor-integration' );
                $this->method_description = __( 'Integrate your store with Plugin Licensor', 'plugin-licensor-integration' );
                // Load the settings.
                $this->init_form_fields();
                $this->init_settings();
                // Define user set variables.
                $this->private_key          = $this->get_option( 'private_key' );
                $this->company_id = $this->get_option( 'company_id' );
                $this->debug            = $this->get_option( 'debug' );
                // Actions.
                add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );

                add_action('woocommerce_check_cart_items', 'pluginlicensor_validate_cart');
                add_action('woocommerce_thankyou', 'plugin_licensor_payment_complete');
            }

            function pluginlicensor_validate_cart() {
                $products_info = array();

                foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                    $product = $cart_item['data'];
                    $quantity = $cart_item['quantity'];
                    $price = WC()->cart->get_product_price( $product );
                    $subtotal = WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] );
                    // Anything related to $product, check $product tutorial
                    
                    $plugin_id = $product->get_attribute( 'plugin_licensor_id' );
                    if ($plugin_id) {
                        $license_type = $product->get_attribute('license_type');
                        if (array_key_exists($plugin_id, $products_info)){
                            if ($subtotal > 0 || $products_info[$plugin_id]['subtotal'] > 0 || $license_type != $products_info[$plugin_id]['license_type']) {
                                wc_add_notice(sprintf('<strong>You must not purchase different license types for the same plugin</strong>'), 'error');
                            }
                            // nothing else needs to be done if the array key exists
                            // this is just to show the error if needed
                        }else{
                            $products_info[$plugin_id] = array(
                                "subtotal" => $subtotal,
                                "license_type" => $license_type
                            );
                        }
                    }
                }
            }

            /**
             * Communicate with the server to create licenses if needed.
             * @param mixed $order_id
             * @return void
             */
            function plugin_licensor_payment_complete( $order_id ){
                global $wpdb;
                $order = wc_get_order($order_id);
                $user = $order->get_user();

                $has_physical_items = false;
                $has_plugins = false;

                // customer must have an account to own and manage licenses
                if( $user ){
                    $total = 0;
                    $products_info = array();
                    $plugins_to_get = array();
                    $has_physical_items = false;
                    
                    foreach ( $order->get_items() as $item_id => $item ) {
                        $plugin_licensor_id = $item->get_meta("plugin_licensor_id");
                        
                        if ( !wc_get_product($item->get_product_id())->is_virtual() ) {
                            $has_physical_items = true;
                        }

                        if ( $plugin_licensor_id ) {
                            $has_plugins = true;
                            if ( !array_key_exists($plugin_licensor_id, $products_info ) ) {
                                $products_info[$plugin_licensor_id] = array(
                                    "subtotal" => $order->get_item_total($item, false, false),
                                    "licenseType" => $item->get_meta("license_type"),
                                    "quantity" => $item->get_quantity()
                                );
                            }else{
                                if ( $products_info[$plugin_licensor_id]["licenseType"] == $item->get_meta("license_type") ){
                                    $products_info[$plugin_licensor_id]["subtotal"] += $order->get_item_total($item, false, false);
                                    $products_info[$plugin_licensor_id]["quantity"] += $item->get_quantity();
                                }else{
                                    if ( $products_info[$plugin_licensor_id]["subtotal"] == 0 || $item->get_item_total($item, false, false) == 0 ) {
                                        $current = $products_info[$plugin_licensor_id]["subtotal"];
                                        $new = $item->get_item_total($item, false, false, false);
                                        $products_info[$plugin_licensor_id]["subtotal"] = ($current > $new) ? $current : $new;
                                        $current_2 = $products_info[$plugin_licensor_id]["quantity"];
                                        $new_2 = $item->get_quantity();
                                        $products_info[$plugin_licensor_id]["quantity"] = ($current > $new) ? $current_2 : $new_2;
                                        $current_2 = $products_info[$plugin_licensor_id]["licenseType"];
                                        $new_2 = $item->get_meta("license_type");
                                        $products_info[$plugin_licensor_id]["licenseType"] = ($current > $new) ? $current_2 : $new_2;
                                    }//else
                                    // If it got into an else statement here, something would be very wrong.
                                    // The licensing service will not allow a purchase to be made with different license types for
                                    // the same plugin id. A work around is to use multiple plugin ids for the same plugin, but
                                    // you shouldn't have to.
                                    // The cart validation here will attempt to prevent this from happening, but if it happens,
                                    // the customer will not receive a license code
                                    // It looks difficult to automatically cancel the transaction here, but if it is possible,
                                    // that would be advisable
                                }
                            }
                        }
                    }
                    // send post request, change order status
                    if ($has_plugins){
                        $email = $order->get_billing_email();
                        echo "<h3>Your license code will be delivered to $email.</h3>";

                        $keys = array_keys($products_info);

                        $products_info_str = "";
                        for ($i = 0; $i < count($keys); $i++){
                            if ($i > 0) {
                                $products_info_str .= ";";
                            }
                            $key = $keys[$i];
                            $products_info_str .= $key
                                . ","
                                . $products_info[$key]["licenseType"]
                                . ","
                                . $products_info[$key]["quantity"]
                                . ","
                                . $products_info[$key]["subtotal"];
                        }

                        $url = "https://4qlddpu7b6.execute-api.us-east-1.amazonaws.com/v1/create_license";
                        // body:
                        /**
                         * "company": company id string
                         * "products_info": products info string
                         * "order_number": order number
                         * "first_name": first name string
                         * "last_name": last name string
                         * "email": email - this gets hashed and salted before it goes in the database
                         * "timestamp": timestamp string, as seconds
                         * "signature": all fields concatenated, signed
                         */

                        $body = array(
                            'company' => $this->company_id,
                            'products_info' => $products_info_str,
                            'order_number' => $order_id,
                            'first_name' => $order->get_billing_first_name(),
                            'last_name' => $order->get_billing_last_name(),
                            'email' => $order->get_billing_email(),
                            'timestamp' => time()
                        );
                        $to_sign = $body['company'] . $body['products_info']
                            . $body['order_number'] . $body['first_name']
                            . $body['last_name'] . $body['email']
                            . $body['timestamp'];
                        $is_success = openssl_sign($to_sign, $signature, $this->private_key, OPENSSL_ALGO_SHA256);
                        if (!$is_success){
                            echo "Error signing";
                        }else{
                            $body['signature'] = $signature;
                            $args = array(
                                'body' => $body,
                            );

                            $response = wp_remote_post($url, $args);
                            if ( is_wp_error( $response ) ){
                                $error_message = $response->get_error_message();
                                echo "There was an error processing your purchase: $error_message";
                            }
                        }


                        if ( !$has_physical_items ) {
                            if ( $order->get_status() == 'processing' ) {
                                $order->update_status('wc-completed');
                            }
                        }
                    }
                }
            }

            /**
             * Initialize integration settings form fields.
             */
            public function init_form_fields() {
                $this->form_fields = array(
                    'private_key' => array(
                        'title'             => __( 'Private Key', 'plugin-licensor-integration' ),
                        'type'              => 'text',
                        'description'       => __( 'Enter your Private Key found in the Plugin Licensor console.', 'plugin-licensor-integration' ),
                        'desc_tip'          => true,
                        'default'           => ''
                    ),
                    'company_id' => array(
                        'title' => __( 'Company ID', 'plugin-licensor-integration' ),
                        'type' => 'text',
                        'description' => __( 'Enter your company ID found in the Plugin Licensor console.', 'plugin-licensor-integration' ),
                        'desc_tip' => true,
                        'default' => ''
                    ),
                    'debug' => array(
                        'title'             => __( 'Debug Log', 'woocommerce-integration-demo' ),
                        'type'              => 'checkbox',
                        'label'             => __( 'Enable logging', 'plugin-licensor-integration' ),
                        'default'           => 'no',
                        'description'       => __( 'Log events such as API requests', 'plugin-licensor-integration' ),
                    ),
                );
            }
        }
    endif;
}




?>
