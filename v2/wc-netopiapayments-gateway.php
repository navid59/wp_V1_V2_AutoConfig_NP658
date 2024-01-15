<?php
// For Version Api V2
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include_once __DIR__ . '/vendor/autoload.php';
include_once('setting/static.php');
include_once('lib/request.php');


$request = new Request();
class netopiapayments extends WC_Payment_Gateway {
    /** 
     * We just keeo the Dynamic property used in API v1 as well
     * Becuse this values still exist in DB as well
     * */ 
    public $environment;
    public $default_status;
    public $key_setting;
    public $account_id;
    public $live_cer;
    public $live_key;
    public $sandbox_cer;
    public $sandbox_key;
    public $payment_methods;
    public $sms_setting;
    public $service_id;
    //End Dynamic property used in API v1

    // Dynamic property
    public $live_api_key;
    public $sandbox_api_key;
    public $ntp_notify_value;
    public $seckey;
    public $hash;

    // Dynamic property
    public $favicon;
    public $netopiLogo;
    public $has_fields;
    public $notify_url;
    public $envMod;

    /**
     * Setup our Gateway's id, description and other values
     */ 
    function __construct() 
        {
        $this->id                     = "netopiapayments";
        $this->method_title           = __( "NETOPIA Payments", 'netopiapayments' );
        $this->method_description     = __( "NETOPIA Payments V2 Plugin for WooCommerce", 'netopiapayments' );
        $this->title                  = __( "NETOPIA", 'netopiapayments' );
        $this->favicon                = NTP_PLUGIN_DIR . 'img/favicon.png';
        $this->netopiLogo             = NTP_PLUGIN_DIR . 'img/NETOPIA_Payments.svg';
        $this->has_fields             = true;
        $this->notify_url             = WC()->api_request_url( 'netopiapayments' );	// IPN URL - WC REST API
        $this->envMod                 = MODE_STARTUP;
        // $this->envMod                 = MODE_NORMAL;
        

        /**
         * Defination the plugin setting fiels in payment configuration
         */
        $this->init_form_fields();
        $this->init_settings();
        
        // Turn these settings into variables we can use
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }
                  
        /**
         * Define the checkNetopiapaymentsResponse methos as NETOPIA Payments IPN
         */
        add_action('init', array(&$this, 'checkNetopiapaymentsResponse'));
        add_action('woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'checkNetopiapaymentsResponse' ) );

        // Save settings
        if ( is_admin() ) {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        /**
         * In Receipt page give short info to Buyer and then will start redirecting to payment page
         */
        add_action('woocommerce_receipt_netopiapayments', array(&$this, 'receipt_page'));
    }


    /**
     * Build the administration fields for this specific Gateway
     */
	public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
            'title'        => __( 'Enable / Disable', 'netopiapayments' ),
            'label'        => __( 'Enable this payment gateway', 'netopiapayments' ),
            'type'         => 'checkbox',
            'description' => __( 'Disable / Enable of NETOPIA Payment method.', 'netopiapayments' ),
            'desc_tip'    => true,
            'default'      => 'no',
            ),
            'environment'  => array(
            'title'       => __( 'NETOPIA Payments Test Mode', 'netopiapayments' ),
            'label'       => __( 'Enable Test Mode', 'netopiapayments' ),
            'type'        => 'checkbox',
            'description' => __( 'Place the payment gateway in test mode.', 'netopiapayments' ),
            'desc_tip'    => true,
            'default'     => 'no',
            ),
            'title' => array(
            'title'       => __( 'Title', 'netopiapayments' ),
            'type'        => 'text',
            'description' => __( 'Payment title the customer will see during the checkout process.', 'netopiapayments' ),
            'desc_tip'    => true,
            'default'     => __( 'NETOPIA Payments', 'netopiapayments' ),
            ),
            'description'  => array(
            'title'       => __( 'Description', 'netopiapayments' ),
            'type'        => 'textarea',
            'description' => __( 'Payment description the customer will see during the checkout process.', 'netopiapayments' ),
            'desc_tip'    => true,
            'css'         => 'max-width:350px;',
            ),
            'default_status' => array(
            'title'        => __( 'Default status', 'netopiapayments' ),
            'type'         => 'select',
            'description'  => __( 'Default status of transaction.', 'netopiapayments' ),
            'desc_tip'     => true,
            'default'      => 'processing',
            'options'      => array(
            'completed'    => __('Completed'),
            'processing'   => __('Processing'),
            ),
            'css'       => 'max-width:350px;',
            )
        );

        if ($this->envMod == MODE_STARTUP ) {
            $this->form_fields['wizard_setting'] =  array(
                                                    'title'       => '',
                                                    'type'        => 'title',
                                                    'description' => __("To ensure the smooth and optimal functioning of our NETOPIA Payments wodpress plugin, it is imperative to have <br>
                                                    an active `<b>Signature</b>` and at least one `<b>API Key</b>` These fundamental components are the backbone of our plugin's capabilities.</br></br>
                                                    To get started, simply click on <b>Configuration</b> button, where you'll be prompted to enter your <b>Username</b> and <b>password</b> form NETOPIA paltform.<br>
                                                    Once authenticated, the wizard will automatically return and configure your `<b>Signature</b>`, `<b>Livw API Key</b>` & `<b>Sandbox API Key</b>`<br><br>
                                                    The `<b>Sandbox API Key</b>` is not obligatory but highly recommended. <br>
                                                    It serves as a virtual playground, allowing you to thoroughly test your plugin implementation before moving into a production/live environment.", 'netopiapayments' ),
                                                );
                        
            $this->form_fields['wizard_button'] = array(
                                                    'title'             => __( 'Configuration!', 'netopiapayments' ),
                                                    'type'              => 'button',
                                                    'custom_attributes' => array(),
                                                    'description'       => __( 'Configure your plugin for NETOPIA Payments Method automatically!', 'netopiapayments' ),
                                                    'desc_tip'          => true,
                                                );
            // Add the feilds in hidden type
            $this->form_fields['account_id'] = array(
                                                    'title'        => __( 'Seller Account ID', 'netopiapayments' ),
                                                    'type'        => 'text',
                                                    'desc_tip'    => __( 'Seller Account ID / Merchant POS identifier, is available in your NETOPIA account.', 'netopiapayments' ),
                                                    'description'	=> __( 'Find it from NETOPIA Payments admin -> Seller Accounts -> Technical settings.', 'netopiapayments' ),
                                                    'custom_attributes' => array('readonly' => 'readonly')
                                                );
            $this->form_fields['live_api_key'] = array(
                                                    'title'        => __( 'Live API Key: ', 'netopiapayments' ),
                                                    'type'        => 'text',
                                                    'desc_tip'    => __( 'In order to communicate with the payment API, you need a specific API KEY.', 'netopiapayments' ),
                                                    'description' => __( 'Generate / Find it from NETOPIA Payments admin -> Profile -> Security', 'netopiapayments' ),
                                                    'custom_attributes' => array('readonly' => 'readonly')
                                                );
            $this->form_fields['sandbox_api_key'] = array(
                                                    'title'        => __( 'Sandbox API Key: ', 'netopiapayments' ),
                                                    'type'        => 'text',
                                                    'desc_tip'    => __( 'In order to communicate with the payment API, you need a specific API KEY.', 'netopiapayments' ),
                                                    'description' => __( 'Generate / Find it from NETOPIA Payments admin -> Profile -> Security', 'netopiapayments' ),
                                                    'custom_attributes' => array('readonly' => 'readonly')
                                                );

            // To display Notify to Merchant
            $this->form_fields['ntp_notify'] =  array(
                                                    'title'       => '',
                                                    'type'        => 'title',
                                                    'description' => __("", 'netopiapayments' ),
                                                );
            $this->form_fields['ntp_notify_value'] = array(
                                                    'title'             => __( '', 'netopiapayments' ),
                                                    'type'              => 'hidden',
                                                    'custom_attributes' => array(),
                                                );
        } else {
            $this->form_fields['key_setting'] = array(
                                                    'title'       => __( 'Seller Account', 'netopiapayments' ),
                                                    'type'        => 'title',
                                                    'description' => '',
                                                );
            $this->form_fields['account_id'] = array(
                                                    'title'        => __( 'Seller Account ID', 'netopiapayments' ),
                                                    'type'        => 'text',
                                                    'desc_tip'    => __( 'Seller Account ID / Merchant POS identifier, is available in your NETOPIA account.', 'netopiapayments' ),
                                                    'description'	=> __( 'Find it from NETOPIA Payments admin -> Seller Accounts -> Technical settings.', 'netopiapayments' ),
                                                );
            $this->form_fields['live_api_key'] = array(
                                                    'title'        => __( 'Live API Key: ', 'netopiapayments' ),
                                                    'type'        => 'text',
                                                    'desc_tip'    => __( 'In order to communicate with the payment API, you need a specific API KEY.', 'netopiapayments' ),
                                                    'description' => __( 'Generate / Find it from NETOPIA Payments admin -> Profile -> Security', 'netopiapayments' ),
                                                );
            $this->form_fields['sandbox_api_key'] = array(
                                                    'title'        => __( 'Sandbox API Key: ', 'netopiapayments' ),
                                                    'type'        => 'text',
                                                    'desc_tip'    => __( 'In order to communicate with the payment API, you need a specific API KEY.', 'netopiapayments' ),
                                                    'description' => __( 'Generate / Find it from NETOPIA Payments admin -> Profile -> Security', 'netopiapayments' ),
                                                    );
        }
    }

    /**
     * Generate custom Button HTML in ADMIN.
     */
    public function generate_button_html( $key, $data ) {
        $field    = $this->plugin_id . $this->id . '_' . $key;
        $defaults = array(
            'class'             => 'button-secondary',
            'css'               => '',
            'custom_attributes' => array(),
            'desc_tip'          => false,
            'description'       => '',
            'title'             => '',
        );

        $data = wp_parse_args( $data, $defaults );

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
                <?php echo $this->get_tooltip_html( $data ); ?>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
                    <button class="<?php echo esc_attr( $data['class'] ); ?>" type="button" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php echo $this->get_custom_attribute_html( $data ); ?>><?php echo wp_kses_post( $data['title'] ); ?></button>
                    <?php echo $this->get_description_html( $data ); ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
    * Display Method of payment in checkout page
    */
    function payment_fields() {
        // Description of payment method from settings
          if ( $this->description ) { ?>
             <p><?php echo $this->description; ?></p>
        <?php }
        
          $payment_methods = array('credit_card');
          $name_methods = array(
              'credit_card'	      => __( 'Credit / Debit Card', 'netopiapayments' )
          );
        ?>
        <div id="netopia-methods">
            <ul>
            <?php  foreach ($payment_methods as $method) { ?>
                  <?php 
                  $checked ='';
                  if($method == 'credit_card') $checked = 'checked="checked"';
            ?>
                  <li>
                    <input type="radio" name="netopia_method_pay" class="netopia-method-pay" id="netopia-method-<?=$method?>" value="<?=$method?>" <?php echo $checked; ?> /><label for="inspire-use-stored-payment-info-yes" style="display: inline;"><?php echo $name_methods[$method] ?></label>
                  </li>             
            <?php } ?>
            </ul>
        </div>

        <style type="text/css">
              #netopia-methods{display: inline-block;}
              #netopia-methods ul{margin: 0;}
              #netopia-methods ul li{list-style-type: none;}
        </style>
        <script type="text/javascript">
            jQuery(document).ready(function($){                
                var method_ = $('input[name=netopia_method_pay]:checked').val();
                $('.billing-shipping').show('slow');
            });
        </script>
        <?php
      }

    /**
    * Submit checkout for payment
    */
	public function process_payment( $order_id ) {
		$order = new WC_Order( $order_id );			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) {
				/* 2.1.0 */
				$checkout_payment_url = $order->get_checkout_payment_url( true );
			} else {
				/* 2.0.0 */
				$checkout_payment_url = get_permalink( get_option ( 'woocommerce_pay_page_id' ) );
			}

			/** To defination chosen type of payment
			 * Like : credit card, Bitcoin, GPay,...
			 */
			$netopiaPaymentTypeModel = $this->get_post( 'netopia_method_pay' );
						
            return array(
                'result' => 'success', 
				'redirect' => add_query_arg(
					'method', 
					$netopiaPaymentTypeModel, 
					add_query_arg(
						'key', 
						$order->get_order_key(), 
						$checkout_payment_url
					)
				)
        	);
    }

    /**
     * Validate fields
     */
    public function validate_fields() {
        $method_pay            = $this->get_post( 'netopia_method_pay' );
        // Check card number
        if ( empty($method_pay ) ) {
            wc_add_notice( __( 'Alege metoda de plata.', 'netopiapayments' ), $notice_type = 'error' );
            return false;
            }
        return true;
    }

    /**
     * Receipt Page
    **/
    function receipt_page($order){
        $customer_order = new WC_Order( $order );
        echo '<p>'.__('Multumim pentru comanda, te redirectionam in pagina de plata NETOPIA payments.', 'netopiapayments').'</p>';
        echo '<p><strong>'.__('Total', 'netopiapayments').": ".$customer_order->get_total().' '.$customer_order->get_currency().'</strong></p>';
        echo $this->generateNetopiaPaymentLink($order);
    }

    /**
    * Generate payment Link / Payment button And redirect
    **/
    function generateNetopiaPaymentLink($order_id){
        global $woocommerce;

        // Get this Order's information so that we know
        // who to charge and how much
        $customer_order = new WC_Order( $order_id );
        $user = new WP_User( $customer_order->get_user_id());
        
        $request = new Request();
        $request->posSignature  = $this->account_id;                                                    // Your signiture ID hear
        $request->isLive        = $this->isLive($this->environment);
        if($request->isLive ) {
            $request->apiKey = $this->live_api_key;                                                     // Live API key
            } else {
            $request->apiKey = $this->sandbox_api_key;                                                  // Sandbox API key
            }
        $request->notifyUrl     = $this->notify_url;                                                    // Your IPN URL
        $request->redirectUrl   = htmlentities(WC_Payment_Gateway::get_return_url( $customer_order ));  // Your backURL

        /**
         * Prepare json for start action
         */

        /** - Config section  */
        $configData = [
         'emailTemplate' => "",
         'notifyUrl'     => $request->notifyUrl,
         'redirectUrl'   => $request->redirectUrl,
         'language'      => "RO"
         ];
		
        // /** - 3DS section  */
         // $threeDSecusreData =  array(); 

         /** - Order section  */
        $orderData = new \StdClass();
		
        /**
         * Set a custom Order description
         */
        $customPaymentDescription = 'Plata pentru comanda cu ID: '.$customer_order->get_order_number().' | '.$customer_order->payment_method_title.' | '.$customer_order->get_billing_first_name() .' '.$customer_order->get_billing_last_name();

        $orderData->description             = $customPaymentDescription;
        $orderData->orderID                 = $customer_order->get_order_number().'_'.$this->randomUniqueIdentifier();
        $orderData->amount                  = $customer_order->get_total();
        $orderData->currency                = $customer_order->get_currency();

        $orderData->billing                 = new \StdClass();
        $orderData->billing->email          = $customer_order->get_billing_email();
        $orderData->billing->phone          = $customer_order->get_billing_phone();
        $orderData->billing->firstName      = $customer_order->get_billing_first_name();
        $orderData->billing->lastName       = $customer_order->get_billing_last_name();
        $orderData->billing->city           = $customer_order->get_billing_city();
        $orderData->billing->country        = 642;
        $orderData->billing->state          = $customer_order->get_billing_state();
        $orderData->billing->postalCode     = $customer_order->get_billing_postcode();

        $billingFullStr = $customer_order->get_billing_country() 
         .' , '.$orderData->billing->city
         .' , '.$orderData->billing->state
         .' , '.$customer_order->get_billing_address_1() . $customer_order->get_billing_address_2()
         .' , '.$orderData->billing->postalCode;
        $orderData->billing->details        = !empty($customer_order->get_customer_note()) ?  $customer_order->get_customer_note() . " | ". $billingFullStr : $billingFullStr;

        $orderData->shipping                = new \StdClass();
        $orderData->shipping->email         = $customer_order->get_billing_email();			// As default there is no shiping email, so use billing email
        $orderData->shipping->phone         = $customer_order->get_billing_phone();			// As default there is no shiping phone, so use billing phone
        $orderData->shipping->firstName     = $customer_order->get_shipping_first_name();
        $orderData->shipping->lastName      = $customer_order->get_shipping_last_name();
        $orderData->shipping->city          = $customer_order->get_shipping_city();
        $orderData->shipping->country       = 642 ;
        $orderData->shipping->state         = $customer_order->get_shipping_state();
        $orderData->shipping->postalCode    = $customer_order->get_shipping_postcode();

        $shippingFullStr = $customer_order->get_shipping_country() 
         .' , '.$orderData->shipping->city
         .' , '.$orderData->shipping->state
         .' , '.$customer_order->get_shipping_address_1() . $customer_order->get_shipping_address_2()
         .' , '.$orderData->shipping->postalCode;
        $orderData->shipping->details       = !empty($customer_order->get_customer_note()) ?  $customer_order->get_customer_note() . " | ". $shippingFullStr : $shippingFullStr;
		
        $orderData->products                = $this->getCartSummary(); // It's JSON

        /**	Add Woocomerce & Wordpress version to request*/
        $orderData->data				 	= new \StdClass();
        $orderData->data->vesion 		    = "2.0.0";
        $orderData->data->api 		        = "2.0";
        $orderData->data->platform 		    = "Wordpress";
        $orderData->data->wordpress 		= $this->getWpInfo();
        $orderData->data->wooCommerce 		= $this->getWooInfo();

        /**
         * Assign values and generate Json
         */
        $request->jsonRequest = $request->setRequest($configData, $orderData);

        /**
         * Send Json to Start action 
         */
        $startResult = $request->startPayment();


        /**
         * Result of start action is in jason format
         * get PaymentURL & do redirect
         */
        
        $resultObj = json_decode($startResult);
        
        switch($resultObj->status) {
            case 1:
            // Uncomented for debugging
            // echo "<pre>";
            // print_r($resultObj);
            // echo "</pre>";
            if ($resultObj->code == 200 &&  !is_null($resultObj->data->payment->paymentURL)) {
                $parsUrl = parse_url($resultObj->data->payment->paymentURL);
                $actionStr = $parsUrl['scheme'].'://'.$parsUrl['host'].$parsUrl['path'];
                parse_str($parsUrl['query'], $queryParams);
                $formAttributes = '';
                foreach($queryParams as $key => $val) {
                        $formAttributes .= '<input type="hidden" name ="'.$key.'" value="'.$val.'">';
                    }
                
                try {                        
                    return '<form action="'.$actionStr.'" method="get" id="frmPaymentRedirect">
                                    '.$formAttributes.'
                                    <input type="submit" class="button-alt" id="submit_netopia_payment_form" value="'.__('Plateste prin NETOPIA payments', 'netopiapayments').'" />
                                    <a class="button cancel" href="'.$customer_order->get_cancel_order_url().'">'.__('Anuleaza comanda &amp; goleste cosul', 'netopiapayments').'</a>
                                    <script type="text/javascript">
                                    jQuery(function(){
                                    jQuery("body").block({
                                        message: "'.__('Iti multumim pentru comanda. Te redirectionam catre NETOPIA payments pentru plata.', 'netopiapayments').'",
                                        overlayCSS: {
                                            background		: "#fff",
                                            opacity			: 0.6
                                        },
                                        css: {
                                            padding			: 20,
                                            textAlign		: "center",
                                            color			: "#555",
                                            border			: "3px solid #aaa",
                                            backgroundColor	: "#fff",
                                            cursor			: "wait",
                                            lineHeight		: "32px"
                                        }
                                    });
                                    jQuery("#submit_netopia_payment_form").click();});
                                    </script>
                                </form>';
                    } catch (\Exception $e) {
                    echo '<p><i style="color:red">Asigura-te ca ai completat configurari in setarii,pentru mediul sandbox si live!. Citeste cu atentie instructiunile din manual!</i></p>
                                        <p style="font-size:small">Ai in continuare probleme? Trimite-ne doua screenshot-uri la <a href="mailto:implementare@netopia.ro">implementare@netopia.ro</a>, unul cu setarile metodei de plata din adminul wordpress.</p>';
                            }
                } else {
                echo $resultObj->message;
                }
            break;
            default:
            // Un comented for debuging
            // echo "<pre>";
            // print_r($resultObj);
            // echo "</pre>";
            echo "There is a problem, the server is not response to request or Payment URL is not generated";
            break;
        }
	}	

    /**
    * Check for valid NETOPIA server callback
    * This is the IPN for new plugin
    **/
    function checkNetopiapaymentsResponse() {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        include_once('lib/ipn.php');
        require_once 'vendor/autoload.php';


        
        // /**
        //  * get defined keys
        //  */
        $ntpIpn = new IPN();

        $ntpIpn->activeKey         = $this->account_id; // activeKey or posSignature
        $ntpIpn->posSignatureSet[] = $this->account_id; // The active key should be in posSignatureSet as well
        $ntpIpn->posSignatureSet[] = 'AAAA-BBBB-CCCC-DDDD-EEEE'; 
        $ntpIpn->posSignatureSet[] = 'DDDD-AAAA-BBBB-CCCC-EEEE'; 
        $ntpIpn->posSignatureSet[] = 'EEEE-DDDD-AAAA-BBBB-CCCC';
        $ntpIpn->hashMethod        = 'SHA512';
        $ntpIpn->alg               = 'RS512';
        
        $ntpIpn->publicKeyStr = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAy6pUDAFLVul4y499gz1P\ngGSvTSc82U3/ih3e5FDUs/F0Jvfzc4cew8TrBDrw7Y+AYZS37D2i+Xi5nYpzQpu7\nryS4W+qvgAA1SEjiU1Sk2a4+A1HeH+vfZo0gDrIYTh2NSAQnDSDxk5T475ukSSwX\nL9tYwO6CpdAv3BtpMT5YhyS3ipgPEnGIQKXjh8GMgLSmRFbgoCTRWlCvu7XOg94N\nfS8l4it2qrEldU8VEdfPDfFLlxl3lUoLEmCncCjmF1wRVtk4cNu+WtWQ4mBgxpt0\ntX2aJkqp4PV3o5kI4bqHq/MS7HVJ7yxtj/p8kawlVYipGsQj3ypgltQ3bnYV/LRq\n8QIDAQAB\n-----END PUBLIC KEY-----\n";
        $ipnResponse = $ntpIpn->verifyIPN();

        /**
         * IPN Output
         */
        echo json_encode($ipnResponse);
        die();
    }


    // Check if we are forcing SSL on checkout pages
    // Custom function not required by the Gateway
    public function do_ssl_check() {
        if ( $this->enabled == "yes" ) {
            if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
                echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
            }
        }
    }

    /**
     * Get post data if set
     */
    private function get_post( $name ) {
        if ( isset($_REQUEST[ $name ] ) ) {
            return $_REQUEST[ $name ];
            }
        return null;
    }


    /**
     * Save fields (Payment configuration) in DB
     */
    public function process_admin_options() {
        $this->init_settings();
        $post_data = $this->get_post_data();
        // $cerValidation = $this->cerValidation();

        foreach ( $this->get_form_fields() as $key => $field ) {
            if ( ('title' !== $this->get_field_type( $field )) && ('file' !== $this->get_field_type( $field ))) {
                try {
                    $this->settings[ $key ] = $this->get_field_value( $key, $field, $post_data );
                } catch ( Exception $e ) {
                    $this->add_error( $e->getMessage() );
                }
            }
        }
        return update_option($this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes' );
    }

    /**
     * 
     */
    private function _canManageWcSettings() {
        return current_user_can('manage_woocommerce');
    }

    /**
     * 
     */
    public function getCartSummary() {
        $cartArr = WC()->cart->get_cart();
        $i = 0;	
        $cartSummary = array();	
        foreach ($cartArr as $key => $value ) {
            $cartSummary[$i]['name']                 =  $value['data']->get_name();
            $cartSummary[$i]['code']                 =  $value['data']->get_sku();
            $cartSummary[$i]['price']                =  floatval($value['data']->get_price());
            $cartSummary[$i]['quantity']             =  $value['quantity'];	
            $cartSummary[$i]['short_description']    =  !is_null($value['data']->get_short_description()) || !empty($value['data']->get_short_description()) ? substr($value['data']->get_short_description(), 0, 100) : 'no description';
            $i++;
           }
        return $cartSummary;
    }

    /**
     * 
     */
    public function getWpInfo() {
        global $wp_version;	
        return 'Version '.$wp_version;
    }

    /**
     * 
     */
    public function getWooInfo() {
        $wooCommerce_ver = WC()->version;
        return 'Version '.$wooCommerce_ver;
    }

    /**
     * 
     */
    public function isLive($environment) {
        if ( $environment == 'no' ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 
     */
    public function randomUniqueIdentifier() {
        $microtime = microtime();
        list($usec, $sec) = explode(" ", $microtime);
        $seed = (int)($sec * 1000000 + $usec);
        srand($seed);
        $randomUniqueIdentifier = md5(uniqid(rand(), true));
        return $randomUniqueIdentifier;
    }
}