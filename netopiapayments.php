<?php

/*
Plugin Name: NETOPIA Payments Payment Gateway
Plugin URI: https://www.netopia-payments.ro
Description: accept payments through NETOPIA Payments
Author: Netopia
Version: 1.6
License: GPLv2
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'netopiapayments_init', 0 );
function netopiapayments_init() {
    // set Api Version to work with plugin
	

    // If the parent WC_Payment_Gateway class doesn't exist
    // it means WooCommerce is not installed on the site
    // so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	DEFINE ('NTP_PLUGIN_DIR', plugins_url(basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) . '/' );

    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'add_netopiapayments_gateway' );
    function add_netopiapayments_gateway( $methods ) {
        $methods[] = 'netopiapayments';
        return $methods;
        }

    // Add custom action links
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'netopia_action_links' );
    function netopia_action_links( $links ) {
        $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=netopiapayments' ) . '">' . __( 'Settings', 'netopiapayments' ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }


    if (getNtpApiVer() == 1) {
        
        // If we made it this far, then include our Gateway Class
        include_once( 'wc-netopiapayments-gateway.php' );
        include_once( 'wc-netopiapayments-update-key.php' );

        
        add_action( 'admin_enqueue_scripts', 'netopiapaymentsjs_init' );
        function netopiapaymentsjs_init($hook) {
            if ( 'woocommerce_page_wc-settings' != $hook ) {
                    return;
                }
                // Get ntp_notify_value if exist
				 $ntpNotify = '';
                 $ntpOptions = get_option( 'woocommerce_netopiapayments_settings' );
				 if($ntpOptions) {
					$ntpNotify = array_key_exists('ntp_notify_value', $ntpOptions) ? $ntpOptions['ntp_notify_value'] : '';
				 }
                 
 
                 wp_enqueue_script( 'netopiapaymentsjs', plugin_dir_url( __FILE__ ) . 'v2/js/netopiapayments.js',array('jquery'),'1.1' ,true);
                 wp_enqueue_script( 'netopiaUIjs', plugin_dir_url( __FILE__ ) . 'v2/js/netopiaCustom.js',array(),'1.2' ,true);
                 wp_localize_script( 'netopiaUIjs', 'netopiaUIPath_data', array(
                     'plugin_url' => getAbsoulutFilePath(),
                     'site_url' => get_site_url(),
                     'sKey' => base64_encode(md5(json_encode($ntpOptions).json_encode(get_home_url()))),
                     'ntp_notify' => $ntpNotify,
                     )
                 );
            }
    } else {
        // Api v2 Here 
        
        // If we made it this far, then include our Gateway Class
        include_once( 'v2/wc-netopiapayments-gateway.php' );
        include_once( 'wc-netopiapayments-update-key.php' );

        add_action( 'admin_enqueue_scripts', 'netopiapaymentsjs_init' );
        function netopiapaymentsjs_init($hook) {

            
            /**
             *  Return if is not WooCommerce seeting page
             */
                if ( 'woocommerce_page_wc-settings' != $hook ) {
                    return;
                    }

                // Get ntp_notify_value if exist
				$ntpNotify = '';
                $ntpOptions = get_option( 'woocommerce_netopiapayments_settings' );
				if($ntpOptions) {
					$ntpNotify = array_key_exists('ntp_notify_value', $ntpOptions) ? $ntpOptions['ntp_notify_value'] : '';
				}
                

                wp_enqueue_script( 'netopiapaymentsjs', plugin_dir_url( __FILE__ ) . 'v2/js/netopiapayments.js',array('jquery'),'1.0' ,true);
                wp_enqueue_script( 'netopiaUIjs', plugin_dir_url( __FILE__ ) . 'v2/js/netopiaCustom.js',array(),'1.1' ,true);
                wp_localize_script( 'netopiaUIjs', 'netopiaUIPath_data', array(
                    'plugin_url' => getAbsoulutFilePath(),
                    'site_url' => get_site_url(),
                    'sKey' => base64_encode(md5(json_encode($ntpOptions).json_encode(get_home_url()))),
                    'ntp_notify' => $ntpNotify,
                    )
                );

                /** Add ntpID & ntpTransactionID field in Admin Order title */ 
                add_filter( 'manage_edit-shop_order_columns', 'addNtpCustomFieldsView_order_admin_list_column' );
                function addNtpCustomFieldsView_order_admin_list_column( $columns ) {
                    error_log('Code is running'); // Add this line for debugging

                    $columns['_ntpID'] = 'Netopia ID';
                    $columns['_ntpTransactionID'] = 'Netopia Transaction ID';

                    error_log(print_r($columns, true)); // Add this line for debugging

                    return $columns;
                }
                
                /** Add ntpID & ntpTransactionID field in Admin Order content */ 
                add_action( 'manage_shop_order_posts_custom_column', 'addNtpCustomFieldsView_order_admin_list_column_content' );
                function addNtpCustomFieldsView_order_admin_list_column_content( $column ) {
                
                    global $post;
                
                    if ( '_ntpID' === $column ) {
                        $order = wc_get_order( $post->ID );
                        $ntpID = get_metadata( 'post', $order->ID, '_ntpID', false );
                        if(!empty($ntpID[0])) {
                            echo ($ntpID[0]);
                        }						
                    }

                    if ( '_ntpTransactionID' === $column ) {
                        $order = wc_get_order( $post->ID );
                        $ntpTransactionID = get_metadata( 'post', $order->ID, '_ntpTransactionID', false );
                        if(!empty($ntpTransactionID[0])) {
                            echo ($ntpTransactionID[0]);		
                        }						
                    }
                }
                

                /** Add ntpID as custom field in order (postmeta) */
                add_action( 'woocommerce_after_order_notes', 'ntpID_custom_checkout_field' );
                function ntpID_custom_checkout_field( $checkout ) {
                    woocommerce_form_field( 'ntpID', array(
                        'type'          => 'hidden',
                        'class'         => array(''),
                        'label'         => __(''),
                        'placeholder'   => __(''),
                        ), $checkout->get_value( '_ntpID' ));
                }

                /** Add ntpTransactionID as custom field in order (postmeta) */
                add_action( 'woocommerce_after_order_notes', 'ntpTransactionID_custom_checkout_field' );
                function ntpTransactionID_custom_checkout_field( $checkout ) {
                    woocommerce_form_field( 'ntpID', array(
                        'type'          => 'hidden',
                        'class'         => array(''),
                        'label'         => __(''),
                        'placeholder'   => __(''),
                        ), $checkout->get_value( '_ntpTransactionID' ));
                }


                /**
                 * Update the custom fields (ntpID & ntpTransactionID ) by defult value order meta with zero (string)
                 */
                add_action( 'woocommerce_checkout_update_order_meta', 'my_custom_checkout_field_update_order_meta' );
                function my_custom_checkout_field_update_order_meta( $order_id ) {
                        update_post_meta( $order_id, '_ntpID', sanitize_text_field( 0 ) );
                        update_post_meta( $order_id, '_ntpTransactionID', sanitize_text_field( 0 ) );
                }
                
                /** 
                 * To customize wooCommerce "Order received" title
                 * Add the buyer first name at title
                 * ex. Navid, Order received
                 * */
                
                add_filter( 'the_title', 'woo_personalize_order_received_title', 10, 2 );
                function woo_personalize_order_received_title( $title, $id ) {
                    if ( is_order_received_page() && get_the_ID() === $id ) {
                        global $wp;

                        // Get the order. Line 9 to 17 are present in order_received() in includes/shortcodes/class-wc-shortcode-checkout.php file
                        $order_id  = apply_filters( 'woocommerce_thankyou_order_id', absint( $wp->query_vars['order-received'] ) );
                        $order_key = apply_filters( 'woocommerce_thankyou_order_key', empty( $_GET['key'] ) ? '' : wc_clean( $_GET['key'] ) );

                        if ( $order_id > 0 ) {
                            $order = wc_get_order( $order_id );
                            if ( $order->get_order_key() != $order_key ) {
                                $order = false;
                            }
                        }

                        if ( isset ( $order ) ) {
                        $title = sprintf( "%s, ".$title, esc_html( $order->get_billing_first_name() ) );
                        }
                    }
                    return $title;
                }
        }
    }
}


function getAbsoulutFilePath() {
	// Get the absolute path to the plugin directory
	$plugin_dir_path = plugin_dir_path( __FILE__ );

	// Get the absolute path to the WordPress installation directory
	$wordpress_dir_path = realpath( ABSPATH . '..' );

	// Remove the WordPress installation directory from the plugin directory path
	$plugin_dir_path = str_replace( $wordpress_dir_path, '', $plugin_dir_path );

	// Remove the leading directory separator
	$plugin_dir_path = ltrim( $plugin_dir_path, '/' );

	// Remove the first directory name (which is the site directory name)
	$plugin_dir_path = preg_replace( '/^[^\/]+\//', '/', $plugin_dir_path );

	return $plugin_dir_path;
}

/**
 * Decided which API should be used 
 * Check if plugin is configured for API v2
 * NOTE : it MUST HAVE LIVE API KEY in order to switch to api v2
 * Note : If there is no any "woocommerce_netopiapayments_settings" as default , we will set API v2
 */
function getNtpApiVer() {
    $ntpOptions = get_option( 'woocommerce_netopiapayments_settings' );
	if($ntpOptions) {
		$hasLiveApiKey = array_key_exists('live_api_key', $ntpOptions) && !empty($ntpOptions['live_api_key']) ? true : false;
	} else {
		$hasLiveApiKey = false;
	}
    
    $apiV = $hasLiveApiKey ? 2 : 1;
    return $apiV;
}

/**
 * Activation hook  once after install / update will execute
 * By "verify-regenerat" key will verify if certifications not exist
 * Then try to regenerated the certifications
 * */ 
register_activation_hook( __FILE__, 'plugin_activated' );
function plugin_activated(){
	add_option( 'woocommerce_netopiapayments_certifications', 'verify-and-regenerate' );
}

/**
 * Once after upgrade the plugin will execute
 * By "verify-regenerat" key will verify if certifications not exist
 * Then try to regenerated the certifications, if is neccessary
 */
function setUpgradeStatus($upgrader_object, $options) {
    update_option( 'woocommerce_netopiapayments_certifications', 'verify-and-regenerate' );
}
