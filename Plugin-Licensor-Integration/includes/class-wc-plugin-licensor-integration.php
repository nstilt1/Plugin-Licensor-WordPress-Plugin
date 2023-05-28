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
            $this->share_customer_info = $this->get_option( 'share_customer_info') == 'yes' 
                        || $this->get_option( 'share_customer_info') == true;
            $this->email_message = $this->get_option( 'email_message' );
            $this->include_software_names = $this->get_option('using_other_licensing');
            $this->debug            = $this->get_option( 'debug' );
            // Actions.
            add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );

            add_action('woocommerce_check_cart_items', 'plugin_licensor_validate_cart');
            add_action('woocommerce_thankyou', 'plugin_licensor_payment_complete');
            add_action('woocommerce_email_order_details', 'plugin_licensor_insert_license_code');
            add_action('show_user_profile', 'plugin_licensor_profile_licenses');
        }

        function plugin_licensor_human_timing($time) {
            $time = time() - $time;
            $time = ($time <1) ? 1 : $time;
            $minutes = intdiv($time, 60);
            $seconds = $time % 60;
            $result = "";
            if ( $minutes >= 1 ) {
                $result .= ($minutes+1) . " minutes";
            }else if ( $seconds > 0 ) {
                $result .= "about 1 minute";
            }
        }

        function plugin_licensor_display_license( $data, $time_since ) {
            $output_html = "<h1>Licenses</h1><div class='licenses'>";
            $code = $data['code'];
            $offline = $data['offline'];
            $output_html .= "<p>License code: " . implode("-", str_split($code, 4));
            $output_html .= "</p><p>If you want to use the plugin(s) offline, you should use this code: ";
            $output_html .= implode("-", str_split($code, 4)) . '-OFFLINE-' . $offline;
            $output_html .= '</p><p>If you register your plugin with that offline license code, you might not be able to remove it from the license.';
            $output_html .= "</p><p>The license code will work for the following plugins:</p><ul>";
            
            // get plugin data
            $plugins = $license_data['plugins'];
            foreach ( $plugins as $plugin ) {
                // get plugin name
                $args = array(
                    'status' => 'publish',
                    'limit' => 1,
                    'return' => 'ids',
                    'attribute' => 'plugin_licensor_id',
                    'attribute_term' => $plugin['id'],
                )
                $query = new WC_Product_Query( $args );
                $product_ids = $query->get_products();
                if ( count($product_ids) == 1 ) {
                    $output_html .= "<li>" . $product_ids[0]->get_name();
                    $output_html .= "<ul></li><li>License Type: <b>" . $plugin['license_type'];
                    $output_html .= "</b></li><li>Machine Limit: <b>" . $plugin['max_machines'];
                    $machines = $plugin['machines'];
                    $output_html .= '/' . count($machines);
                    $output_html .= "</b></li><li>Machines: <ul>";
                    if ( count( $machines ) == 0 ) {
                        $output_html .= "<li>No machines were registered last we checked. If this is incorrect, ";
                        $output_html .= "you can check again in ";
                        $output_html .= plugin_licensor_human_timing($time_since);
                        $output_html .= "</li>";
                    }else{
                        foreach ( $machines as $machine ) {
                            $output_html .= "<li>Computer name: " . $machine['computer_name'];
                            $output_html .= "<ul><li>Machine ID: " . $machine['id'];
                            $output_html .= "</li><li>Operating System: " . $machine['os'];
                            $output_html .= "</li></ul></li>"
                        }
                        $output_html .= "<p>The machine list can only be updated once every 30 minutes.<p>"
                    }
                    $output_html .= "</ul></li></ul></li></ul>";
                    return $output_html;
                }
                
            }
        }

        function plugin_licensor_profile_licenses( $user ) {
            $license_code = $this->plugin_licensor_get_license($user);

            // check if error. a tuple or object would work, but so should this
            if ($license_code[0] == "~") {
                // $license_code contains the error information
                $message = $license_code;
            }else{
                if ( strlen($license_code > 10) ) {
                    $data = get_user_meta( $user->ID, 'pluginlicensor_license', true);
                    $time_since_get = get_user_meta( $user->ID, 'pluginlicensor_getreq', true);
                    echo plugin_licensor_display_license($data, $time_since_get);
                }
            }
            
            
        }

        /**
         * Insert the license information into the email that is sent to the customer.
         * @param mixed $order the order object
         * @param mixed $admin
         * @param mixed $plain
         * @param mixed $email
         * @return void
         */
        function plugin_licensor_insert_license_code($order, $admin, $plain, $email) {
            $items = $order->get_items();
            $has_plugin = false;
            $names = array();
            foreach( $items as $item ) {
                if ( $item->get_attribute( 'plugin_licensor_id' ) ) {
                    $has_plugin = true;
                    array_push($names, $item->get_name());
                }
            }
            if ( $has_plugin ) {
                $license_code = $this->plugin_licensor_get_license($order->get_user());

                // check if error. a tuple or object would work, but so should this
                if ($license_code[0] == "~") {
                    // $license_code contains the error information
                    wc_add_notice($license_code, 'error');
                    
                }else{
                    $message = trim($this->email_message);
                    if (mb_substr($message, -1) != ':') {
                        $message .= ':';
                    }
                    if ($this->include_software_names) {
                        $name_list = $names . join(', ');

                        echo __("<strong>$message</strong><br><ul><li>$name_list<ul>$license_code</ul></li></ul>", 'plugin-licensor-integration');

                    }else{
                        echo __("<strong>$message</strong><br><ul><li>$license_code</li></ul>", 'plugin-licensor-integration');
                    }
                }
            }
        }

        /**
         * Ensure that there aren't duplicate items in the cart that could mess up the licensing
         * back end. If you want to interface with this API in another language, you must do the same.
         * @return void
         */
        function plugin_licensor_validate_cart() {
            $products_info = array();

            // check for duplicates, throw error if needed
            foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                $product = $cart_item['data'];
                $quantity = $cart_item['quantity'];
                $price = WC()->cart->get_product_price( $product );
                $subtotal = WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] );
                
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
         * Get the license codes for the order
         * @param mixed $user
         * @return string license code and update 
         */
        function plugin_licensor_get_license ( $user ) {
            //$order = wc_get_order( $order_id );
            //$user = $order->get_user();

            // don't do a get request if the user doesn't have a UUID
            $uuid = get_user_meta( $user->ID, 'pluginlicensor_uuid', true);
            if ( empty( $uuid ) ) {
                return "~Error: Empty UUID cwpli194";
            }
            
            // don't do a get request if one has been done in the last 30 minutes

            $last_get_request = get_user_meta( $user->ID, 'pluginlicensor_getreq', true);
            if( !empty( $last_get_request ) ) {
                if ( time() - (int)$last_get_request < 1800 ) {
                    $license_info = get_user_meta( $user->ID, 'pluginlicensor_license', true);
                    if ( empty ( $license_info ) ) {
                        return "~Error: Empty license cwpli201";
                    }
                    $license_info = json_decode( $license_info );
                    return $license_info['code'] . $license_info['offline'];
                }
            }
            update_user_meta( $user->ID, 'pluginlicensor_getreq', time());

            // do a get request

            $body = array(
                "company" => $this->company_id,
                "uuid" => $uuid,
                "timestamp" => time()
            );
            $is_success = openssl_sign(hash('sha256', $body['company'] . $body['uuid'] . $body['timestamp']), $signature, OPENSSL_ALGO_SHA256);
            $body['signature'] = $signature;
            $args = array(
                "body" => $body
            );
            if ( $is_success ) {
                $url = "https://4qlddpu7b6.execute-api.us-east-1.amazonaws.com/v1/get_license";
                $response = wp_remote_post($url, $args);
                if ( is_wp_error( $response ) ){
                    $error_message = $response->get_error_message();
                    wc_add_notice( "There was an error retrieving your license code: $error_message", 'error');
                }else{
                    $payload = $response['body'];

                    $outer_obj = json_decode($payload, true);
                    $data = $outer_obj['data'];
                    $nonce = base64_decode($outer_obj['nonce']);
                    $key_encrypted = base64_decode($outer_obj['key']);

                    // decrypt the AES key
                    $decrypt_success = openssl_private_decrypt($key_encrypted, $decrypted_key, $this->private_key);

                    if ( !$decrypt_success ) {
                        return "~Error decrypting response: $response";
                    }

                    // decrypt data
                    $ciphertext = substr($data, 0, -16);
                    $tag = substr($data,-16);
                    $associated_data = '';

                    $plaintext = openssl_decrypt($ciphertext, 'aes-128-gcm', $decrypted_key, OPENSSL_RAW_DATA, $nonce, $tag, $associated_data);

                    // parse the license data
                    
                    $license_data = json_decode($plaintext, true);
                    $license_code = $license_data['code'];
                    $offline_code = $license_data['offline'];

                    update_user_meta( $user->ID, 'pluginlicensor_license', $plaintext );

                    return $license_code . ',' . $offline_code;
                    
                }
            }else{
                wc_add_notice('There was an error signing the Plugin Licensor POST request.', 'error');
                return "~Error when signing Plugin Licensor POST request.";
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

            $uuid = get_user_meta( $user->ID, 'pluginlicensor_uuid', true);
            if ( empty ( $uuid ) ) {
                // generate a uuid with a much smaller chance for collisions
                $uuid = wp_generate_uuid4() . substr(microtime(false), -5);
                update_user_meta ( $user->ID, 'pluginlicensor_uuid', $uuid );
            }

            $has_physical_items = false;
            $has_plugins = false;

            // customer must have an account to own and manage licenses
            if( $user ){
                $total = 0;
                $products_info = array();
                $temp_products_info = array();
                $has_physical_items = false;
                
                foreach ( $order->get_items() as $item_id => $item ) {
                    $plugin_licensor_id = $item->get_meta("plugin_licensor_id");
                    
                    if ( !wc_get_product($item->get_product_id())->is_virtual() ) {
                        $has_physical_items = true;
                    }

                    if ( $plugin_licensor_id ) {
                        $has_plugins = true;
                        // check if license type in this order exists already
                        if ( !array_key_exists( $plugin_licensor_id, $products_info ) ) {
                            $license_type_t = $item->get_meta("license_type");

                            $product_obj = array(
                                "id" => $plugin_licensor_id,
                                "license_type" => $license_type_t,
                            );

                            // if "quantity_as_machine_limit" is set on the item, then the quantity will be the machine limit 

                            $machine_limit_selection = $item->meta_exists("quantity_as_machine_limit") ? "machine_limit" : "quantity";

                            array_push($product_obj, $machine_limit_selection => $item->get_quantity());

                            array_push($product_obj, "subtotal", $order->get_item_total($item, false, false));

                            //array_push($product_obj, "custom_success_message" => "Implement this if you want to");

                            $products_info[$plugin_licensor_id] = $product_obj;

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
                                }/* else
                                    If it got into an else statement here, something would be very wrong.
                                    The licensing service will not allow a purchase to be made with 
                                    different license types for the same plugin id. A work around is to 
                                    use multiple plugin ids for the same plugin, but you shouldn't have to.
                                    The cart validation (plugin_licensor_validate_cart()) will attempt to 
                                    prevent this from happening, but if it happens, the customer will not 
                                    receive a license code.  It looks difficult to automatically cancel the 
                                    transaction here, but if it is possible, that would be advisable
                                  */
                            }
                        }
                    }
                }
                // send post request, change order status
                if ($has_plugins){
                    $email = $order->get_billing_email();
                    echo "<h3>Your license code will be delivered to $email.</h3>";

                    $keys = array_keys($products_info);

                    $products_info = array_values($products_info);

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
                        'store_id' => $this->company_id,
                        'uuid' => $uuid,
                        'plugins' => $products_info,
                        'order_id' => $order_id,
                    );
                    if ($this->share_customer_info) {
                        $body['first_name'] = $order->get_billing_first_name();
                        $body['last_name'] = $order->get_billing_last_name();
                        $body['email'] = $order->get_billing_email();
                    }

                    $req = plugin_licensor_build_request( $body );

                    if $req == 0 {
                        wc_add_notice("There was a problem with the request.");
                    }else{

                        $response = wp_remote_post($url, $req);
                        if ( is_wp_error( $response) ) {
                            $error_message = $response->get_error_message();
                            wc_add_notice( "There was an error processing your purchase: $error_message", 'error');
                        }else{
                            if ( !$has_physical_items ) {
                                if ( $order->get_status() == 'processing' ) {
                                    $order->update_status( 'wc-completed' );
                                }
                            }
                        }
                    }

                }
            }
        }

        /**
         * Validate the API key
         * @see validate_settings_fields()
         */
        public function validate_api_key_field( $key ) {
            // get the posted value
            $value = $_POST[ $this->plugin_id . $this->id . '_' . $key ];

            // check if the API key is longer than 20 characters. Our imaginary API doesn't create keys that large so something must be wrong. Throw an error which will prevent the user from saving.
            if ( isset( $value ) &&
                20 > strlen( $value ) ) {
                $this->errors[] = $key;
            }else if(isset( $value )) {
                $passed = 0;
                $failed = false;
                $company = $this->company_id;
                $test_data = "test1" . "test2";
                $time = time();
                $test_sign = openssl_sign(hash('sha256', $company . $test_data . $time), $signature, $this->private_key, OPENSSL_ALGO_SHA256);
                if ($test_sign) {
                    $url = "https://4qlddpu7b6.execute-api.us-east-1.amazonaws.com/v1/server_test_a";
                    $args = array(
                        "body" => array(
                            "company" => $company,
                            "data" => $test_data,
                            "timestamp" => $time,
                            "signature" => $signature
                        )
                    );
                    $test_response = wp_remote_post($url, $args);
                    if ( is_wp_error($test_response) ){
                        $error_message = $test_response->get_error_message();
                        wc_add_notice("Error 274: $error_message");
                        $this->errors[] = "Error 274: $error_message";
                    }else{
                        $decrypt_success = openssl_private_decrypt($test_response['body'], $decrypted, $this->private_key);
                        if ($decrypt_success){
                            if ($decrypted == "Success?"){
                                wc_add_notice("Successfully tested Private Key.", 'notice');
                            }else{
                                $err = "Error 282: error while decrypting message. This was the response: $decrypted";
                                wc_add_notice($err, "error");
                                $this->errors[] = $err;
                            }
                        }else{
                            $err = "Error 287: error decrypting message with private key.";
                            wc_add_notice($err, "error");
                            $this->errors[] = $err;
                        }
                    }
                }else{
                    $this->errors[] = "There was a problem when validating your Private Key for signing";
                }
            }
            return $value;
        }

        /**
         * Initialize integration settings form fields.
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'private_key' => array(
                    'title'             => __( 'Private Key', 'plugin-licensor-integration' ),
                    'type'              => 'textarea',
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
                'share_customer_info' => array(
                    'title' => __( 'Share Customer Info', 'plugin-licensor-integration' ),
                    'type' => 'checkbox',
                    'description' => __( 'Optionally share customer info with Plugin Licensor. Sharing these will allow us to do some in-house analysis on purchase data, and the anonymized results will be visible in the Plugin Licensor Dashboard in the form of interactive charts and graphs. We do not sell or share personal customer information. If you check this box, you need to include a statement in your privacy policy. There is a template for you to use.' ),
                    'default' -> '',
                    'label' => 'Share customer info',
                ),

                'email_message' => array(
                    'title' => __( 'Preface of the license in the user emails and order history', 'plugin-licensor-integration' ),
                    'type' => 'textarea',
                    'description' => __( 'This will show right before their license codes. There will only be one license code for any software your users buy using Plugin Licensor. If you are using another licensing service, you might want to put " for" at the end, or an equivalent word in the language your site is in.', 'plugin-licensor-integration' ),
                    'desc_tip' => true,
                    'default' => 'Here is your license code for our software:'
                ),
                'include_software_names' => array(
                    'title' => __( 'Include software names in email?', 'plugin-licensor-integration' ),
                    'type' => 'checkbox',
                    'description' => __( "If you are using or planning on using other licensing services, then the user's license codes might not all be the same, and the email will now include the names of your software along with their license code IF you check this box..", 'plugin-licensor-integration'),
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

        function plugin_licensor_build_request($data) {
            $plugin_licensor_pubkey = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAoslfS6C+oL3/r+1Qc2IM\nn5WONSL1zvxEb6UsZxn1CQx1QH3Hcs4EjjbBANL7FqI8CU4od5hGUB6HSG9+9Ovw\nLecfuzefiQTdIlusG9KTLgLmUIEeOw8cgvmI/RxS1nlaEntH6tJUmQ7qQfw1vpX5\nxgJzBquovJhKLqlv55jiZcdowFvcBw51/IRPTiDMtl20R2M+ph5LhD4bg5A1Inj3\nSnovZqAyGNV8T+16b1e2nV6ISP//yAjCGviHW/SKzVVlBytNR/t8APLbtHTakG2O\nj6nnMm6USGM0lavkB6f7cObc4wY2EbKOxh6rY8gNVmoGbAIQ7H7nTH2Z53OTc8nT\nsQIDAQAB\n-----END PUBLIC KEY-----";
            $aesKey = openssl_random_pseudo_bytes(16);
            $nonce = openssl_random_pseudo_bytes(12);
            $plugin_licensor_key = openssl_pkey_get_public($plugin_licensor_pubkey);
            $ciphertext = openssl_encrypt(json_encode($data), 'aes-128-gcm', $aesKey, OPENSSL_RAW_DATA, $nonce, $tag);

            $encrypted_result = openssl_public_encrypt($aesKey, $encryptedAesKey, $plugin_licensor_key);
            if ($encrypted_result == false) {
                echo "Error building request";
                return 0;
            }

            $body = array(
                "data" => base64_encode($ciphertext . $tag),
                "nonce" => base64_encode($nonce),
                "key" => base64_encode($encryptedAesKey),
                "timestamp" => strval(time())
            );
            $contents = $body['data'] . $body['nonce'] . $body['key'] . $body['timestamp'];
            $private_key = openssl_pkey_get_private($this->private_key);
            $signed = openssl_sign($contents, $signature, $private_key, OPENSSL_ALGO_SHA256);
            if ($signed == false) {
                return 0;
            }
            $signature = base64_encode($signature);

            $body['signature'] = $signature;
            return $body;
        }

        function plugin_licensor_extract_response($response) {
            $plugin_licensor_pubkey = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAoslfS6C+oL3/r+1Qc2IM\nn5WONSL1zvxEb6UsZxn1CQx1QH3Hcs4EjjbBANL7FqI8CU4od5hGUB6HSG9+9Ovw\nLecfuzefiQTdIlusG9KTLgLmUIEeOw8cgvmI/RxS1nlaEntH6tJUmQ7qQfw1vpX5\nxgJzBquovJhKLqlv55jiZcdowFvcBw51/IRPTiDMtl20R2M+ph5LhD4bg5A1Inj3\nSnovZqAyGNV8T+16b1e2nV6ISP//yAjCGviHW/SKzVVlBytNR/t8APLbtHTakG2O\nj6nnMm6USGM0lavkB6f7cObc4wY2EbKOxh6rY8gNVmoGbAIQ7H7nTH2Z53OTc8nT\nsQIDAQAB\n-----END PUBLIC KEY-----";
            $outer = json_decode($response, true);
            $signed_stuff = $outer['data'] . $outer['nonce'] . $outer['key'] . $outer['timestamp'];
            $verified = openssl_verify($signed_stuff, base64_decode($outer['signature']), $plugin_licensor_pubkey, OPENSSL_ALGO_SHA256);
            if ($verified == 0){
                echo 'plugin licensor signature failed';
                return 0;
            }
            // decrypt request
            $private_key = openssl_pkey_get_private($this->private_key);
            if (!openssl_private_decrypt(base64_decode($outer['key']), $decrypted_aes_key, $private_key)) {
                echo 'Error with private decrypt';
                return 0;
            }
            $tag_length = 16;
            $encrypted_data = base64_decode($outer['data']);
            $decrypted = openssl_decrypt(substr($encrypted_data, 0, -$tag_length), 'aes-128-gcm', $decrypted_aes_key, OPENSSL_RAW_DATA, base64_decode($outer['nonce']), substr($encrypted_data, -$tag_length));
            if ($decrypted == false) {
                echo "Decrypted was false";
                return 0;
            }
            return json_decode($decrypted, true);
        }
    }
endif;
?>
