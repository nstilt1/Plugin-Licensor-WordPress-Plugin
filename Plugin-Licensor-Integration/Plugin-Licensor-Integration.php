<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

 // Test to see if WooCommerce is active (including network activated).
$plugin_path = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce/woocommerce.php';

if (
    in_array( $plugin_path, wp_get_active_and_valid_plugins() )
    || in_array( $plugin_path, wp_get_active_network_plugins() )
) {
/**
 * Plugin Licensor Integration.
 *
 * @package  WC_Plugin_Licensor_Integration
 * @category Integration
 * @author   Noah Stiltner
 */
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
        }

        function pluginlicensor_validate_cart() {
            $products_info = array();
            foreach (WC()->cart->cart_contents as $cart_content_product) {
                // validate cart
                $plugin_id = $cart_content_product['data']
                if ( array_key_exists())
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

                // customer must have an account to own and manage licenses
                if( $user ){
                    $total = 0;
                    $products_info = array();
                    $plugins_to_get = array();
                    
                    foreach ( $order->get_items() as $item_id => $item ) {
                        $plugin_licensor_id = $item->get_meta("plugin_licensor_id");
                        if ( $plugin_licensor_id ) {
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
