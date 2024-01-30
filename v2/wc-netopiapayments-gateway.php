<?php
// For Version Api V2
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include_once __DIR__ . '/vendor/autoload.php';
include_once('setting/static.php');
include_once('lib/request.php');
include_once('lib/status.php');
include_once('lib/refund.php');
include_once('lib/staticPro.php');


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
    public $wizard_button;

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
        
        /** Definition the Netopia support refund */
		$this->supports = array(
			'products',
			'refunds'
		  );

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

        /**
		 * Get transaction status & Customeze the thank you text 
		 */
		add_filter('woocommerce_thankyou_order_received_text', array($this,'getNetopiaPaymentStatus_change_order_received_text'), 10, 2 );	
    }

    	/**
	 * Get transaction status & return string 
	 */
	public function getNetopiaPaymentStatus_change_order_received_text( $str , $order) {
        
		/** Return defulte woo text - do nothing */
		if ( !$order->ID )
        	return esc_html__($str);

		$status = new Status();
		$ntpID = get_metadata( 'post', $order->ID, '_ntpID', false );
		$ntpTransactionID = get_metadata( 'post', $order->ID, '_ntpTransactionID', false );

        
		/**	Set status request*/
		$status->isLive        = $this->isLive($this->environment);
		if($status->isLive ) {
			$status->apiKey = $this->live_api_key;						// Live API key
		} else {
			$status->apiKey = $this->sandbox_api_key;					// Sandbox API key
		}

		$status->posSignature	= $this->account_id;
		$status->ntpID 			= $ntpID[0];	
		$status->orderID 		= $ntpTransactionID[0];	

		$orderStatusJson = $status->setStatus();


		/** Get Order Status */
		$statusRespunse = $status->getStatus($orderStatusJson);
		$statusRespunseObj = json_decode($statusRespunse);


		if($statusRespunseObj->code == 200) {
			$orderStatusMsg = $this->getOrderStatusMessage($statusRespunseObj->data);
            if($statusRespunseObj->data->error->code == "00") {
                $new_str = $str ." ". $orderStatusMsg['errorMessage'];
            } else {
                $new_str = $orderStatusMsg['errorMessage'].". ". $statusRespunseObj->data->error->message;
            }
		} else {
			$new_str = $str ." ". $statusRespunseObj->message;
		}

		return esc_html__($new_str);
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
                                                    Once authenticated, the wizard will automatically return and configure your `<b>Signature</b>`, `<b>Live API Key</b>` & `<b>Sandbox API Key</b>`<br><br>
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
        
        /**
         * Redirect URL,
         * Redirect the user after payment
         * !! 3DS cases !! ??
         */
        $request->redirectUrl   = htmlentities(WC_Payment_Gateway::get_return_url( $customer_order )); 

        /**
         * backURL 
         * Back to Merchant page in case of Cancellation of payment
         */
        $request->cancelUrl     = htmlentities(WC_Payment_Gateway::get_return_url( $customer_order )); 

        /**
         * Prepare json for start action
         */

        /** - Config section  */
        $configData = [
         'emailTemplate' => "",
         'notifyUrl'     => $request->notifyUrl,
         'redirectUrl'   => $request->redirectUrl,
         'cancelUrl'     => $request->cancelUrl,
         'language'      => "RO"
         ];
		
        // /** - 3DS section  */
         // $threeDSecusreData =  array(); 

         /** - Order section  */
        $orderData = new \StdClass();
		
        /**
         * Set a custom Order description
         */
        $customPaymentDescription = 'Plata pentru comanda cu ID: '.$customer_order->get_order_number().' | '.$customer_order->get_payment_method_title().' | '.$customer_order->get_billing_first_name() .' '.$customer_order->get_billing_last_name();

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
        $orderData->data->plugin 		    = $this->getPluginInfo();
        $orderData->data->api 		        = $this->getApiInfo();
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
            case 0:
                if(($resultObj->code == 401) && ($resultObj->data->code == 401)) {
                    echo '<p><i style="color:red">Sa pare ca datele de authentificare introduse nu sunt corecte sau lipsesc.</i></p>';
                } elseif (($resultObj->code == 400) && ($resultObj->data->code == 99)) {
                    echo '<p><i style="color:red">Sa pare ca datele de POS introduse ( POS ) nu sunt corecte sau lipsesc.</i></p>';
                }
                echo '<script> document.getElementById("ntpRedirectMsg").innerHTML = "<i style=\'color:red\'>Imi pare rau, nu putem sa redirectionam in pagina de plata NETOPIA payments</i>";</script>';
                echo '<p><i style="color:red">Asigura-te ca ai completat configurari in setarii,pentru mediul sandbox si live!. Citeste cu atentie instructiunile din manual!</i></p>';
                echo '<p style="font-size:small">Ai in continuare probleme? Trimite-ne doua screenshot-uri la <a href="mailto:implementare@netopia.ro">implementare@netopia.ro</a>, unul cu setarile metodei de plata din adminul wordpress.</p>';
            break;
            case 1:
            if ($resultObj->code == 200 &&  !is_null($resultObj->data->payment->paymentURL)) {
                // Update ntpID & TransactionID
				update_post_meta( $customer_order->get_order_number(), '_ntpID', sanitize_text_field( $resultObj->data->payment->ntpID ) );
				update_post_meta( $customer_order->get_order_number(), '_ntpTransactionID', sanitize_text_field( $orderData->orderID  ) );

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
                        echo '<script> document.getElementById("ntpRedirectMsg").innerHTML = "<i style=\'color:red\'>Imi pare rau, nu putem sa redirectionam in pagina de plata NETOPIA payments</i>";</script>';
                        echo '<p><i style="color:red">Asigura-te ca ai completat configurari in setarii,pentru mediul sandbox si live!. Citeste cu atentie instructiunile din manual!</i></p>';
                        echo '<p style="font-size:small">Ai in continuare probleme? Trimite-ne doua screenshot-uri la <a href="mailto:implementare@netopia.ro">implementare@netopia.ro</a>, unul cu setarile metodei de plata din adminul wordpress.</p>';
                    }
                } else {
                echo $resultObj->message;
                }
            break;
            default:
            echo '<script> document.getElementById("ntpRedirectMsg").innerHTML = "<i style=\'color:red\'>Imi pare rau, nu putem sa redirectionam in pagina de plata NETOPIA payments</i>";</script>';
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
		 * Status change and payment tracking
		 * Base on IPN response
		 */
        
		if($ipnResponse['data']['orderID']) {
			/** Get Order info */
			$orderArr = explode("_",$ipnResponse['data']['orderID']);
			$realOrderID = $orderArr[0];
			$customer_order = new WC_Order( $realOrderID );

			switch($ipnResponse['data']['status']) {
				case $ntpIpn::STATUS_PAID:
					/**
					 * status code : 3 | it mean "PAID" ,
					 * Certainty that the money has left from card holder's account and we update the status 
					 *  */
					
						/** Base on "default_status" in configuration, the order status will be change */
						if($this->default_status == 'processing') {
						/** Update order status -> to processing */
						$customer_order->update_status( 'processing', $ipnResponse['errorMessage'] );
						$customer_order->add_order_note('Payment was received. Order status changed to "processing"', 1);
						}else {
						/** Update order status -> to completed */
						$customer_order->update_status( 'completed', $ipnResponse['errorMessage'] );
						$customer_order->add_order_note('Payment was received. Order status changed to "completed"', 1);
						}

						/** Manage downloadeable products */
						if($customer_order->has_downloadable_item()) {
						/** Update order status -> to completed for orders which content a downloadable product */
						$customer_order->update_status( 'completed', $ipnResponse['errorMessage'] );
						$customer_order->add_order_note('Order content downloadable product and status is "completed"', 1);
						}

					/** Add Note in order for tracking by wp Admin */ 
					$customer_order->add_order_note($ipnResponse['errorMessage'].'<br />ntpID: '.$ipnResponse['data']['ntpID'], 1);

						/** Verify total amount with total pay */ 
						if (strcasecmp($ipnResponse['data']['currency'], $customer_order->get_currency()) == 0) {
						if($ipnResponse['data']['amount'] != (float)$customer_order->get_total()) {
							/** Update order status -> to on-hold for orders which total payment is diffrent from total order amount */
							$customer_order->update_status( 'on-hold', "Status is changed to 'on-hold', because amount has conflict with total payment." );
							$customer_order->add_order_note('Note: Payment is '.$ipnResponse['data']['amount'].$ipnResponse['data']['currency'].' and total order  is '.(float)$customer_order->get_total().$customer_order->get_currency(), 1);
						    }
						} else {
						/** Update order status -> to on-hold for orders which payment currency is diffrent from order currency */
						$customer_order->update_status( 'on-hold', "Status is changed to 'on-hold', because currency has conflict." );
						$customer_order->add_order_note('Note: Payment currency is '.$ipnResponse['data']['currency'].' and order currency is '.$customer_order->get_currency(), 1);
						}
							

					/** Change errorCode, becuse as output response for Confirmed must be null */
					$ipnResponse['errorCode'] = null;
					break;
				case $ntpIpn::STATUS_CANCELED:
					/**
					 * status code : 4 |  it mean "CANCELED"
					 *  */
						/** Update the order status */
						$customer_order->update_status('cancelled', $ipnResponse['errorMessage']);
						$customer_order->add_order_note('Order status changed to "cancelled"', 1);

					/** Add Note in order for tracking by wp Admin */ 
					$customer_order->add_order_note($ipnResponse['errorMessage'].'<br />ntpID: '.$ipnResponse['data']['ntpID'], 1);
					break;
				case $ntpIpn::STATUS_CONFIRMED:
					/**
					 * status code : 5 | it mean "CONFIRMED" ,
					 * Certainty that the money has left from card holder's account and we update the status 
					 *  */
					
					if( $customer_order->get_status($customer_order) != 'completed' ) {
							/** Update order status -> to completed */
						$customer_order->update_status( 'completed', $ipnResponse['errorMessage'] );
						$customer_order->add_order_note('Order status changed to "completed"', 1);
					}

					/** Add Note in order for tracking by wp Admin */ 
					$customer_order->add_order_note($ipnResponse['errorMessage'].'<br />ntpID: '.$ipnResponse['data']['ntpID'], 1);

					/** Change errorCode, becuse as output response for Confirmed must be null */
					$ipnResponse['errorCode'] = null;
					break;
				case $ntpIpn::STATUS_PENDING :
					/**
					 * status code : 6 | it mean "pending status"
					 *  */
						/** Update the order status */
						$customer_order->update_status('on-hold', $ipnResponse['errorMessage']);
						$customer_order->add_order_note('Order status changed to "on-hold". payment is currently being processed.', 1);

						/** Add Note in order for tracking by wp Admin */ 
					$customer_order->add_order_note($ipnResponse['errorMessage'].'<br />ntpID: '.$ipnResponse['data']['ntpID'], 1);
					break;
				case $ntpIpn::STATUS_CREDIT:
					/**
					 * status code : 8 |  it mean "CREDIT"
					 * To previous refunds the remaining balance put in status processing / completed
					 * If it's full refund set it as refund status in process_refund method
					 *  */
					
					if( !in_array($customer_order->get_status($customer_order), array('processing', 'completed', 'refunded')) ) {
							/** Update the order status */
							$customer_order->update_status('processing', $ipnResponse['errorMessage']);
							$customer_order->add_order_note('The order has REFUNDED. SO the status changed to "processing". First check the amount  and then change the status, if is necessary', 1);
					}
					
					/** Add Note in order for tracking by wp Admin */ 
					$customer_order->add_order_note($ipnResponse['errorMessage'].'<br />ntpID: '.$ipnResponse['data']['ntpID'], 1);
					break;
				case $ntpIpn::STATUS_ERROR:
					/**
					 * status code : 11 | it mean "Payment has an error"
					 *  */ 
					if( $customer_order->get_status($customer_order) != 'failed' ) {
							/** Update order status -> to Failed */
							$customer_order->update_status( 'failed', $ipnResponse['errorMessage'] );
							$customer_order->add_order_note('Order status changed to "failed"', 1);
					}

					/** Add Note in order for tracking by wp Admin */ 
					$customer_order->add_order_note($ipnResponse['errorMessage'].'<br />ntpID: '.$ipnResponse['data']['ntpID'], 1);
					break;
				case $ntpIpn::STATUS_DECLINED:
					/**
					 * status code : 12 | it mean "Payment REJECTED"
					 *  */ 
					if( $customer_order->get_status($customer_order) != 'failed' ) {
							/** Update order status -> to Failed */
							$customer_order->update_status( 'failed', $ipnResponse['errorMessage'] );
							$customer_order->add_order_note('Order status changed to "failed"', 1);
					}

					/** Add Note in order for tracking by wp Admin */ 
					$customer_order->add_order_note($ipnResponse['errorMessage'].'<br />ntpID: '.$ipnResponse['data']['ntpID'], 1);
					break;
				case $ntpIpn::STATUS_FRAUD:
					// status code : 13 | it mean "Payment in review from anti fraud"
					/** Update order status -> to on-hold for review it*/
					$customer_order->update_status( 'on-hold', $ipnResponse['errorMessage'] );
					$customer_order->add_order_note('Order status changed to "on-hold", because payment need to be reviewd by anti fraud', 1);
					
					/** Add Note in order for tracking by wp Admin */ 
					$customer_order->add_order_note($ipnResponse['errorMessage'].'<br />ntpID: '.$ipnResponse['data']['ntpID'], 1);
					break;
				case $ntpIpn::STATUS_3D_AUTH:
					/**
					 * status code : 13 | it mean "3DS authentication required"
					 *  */
					
					/** Add Note in order for tracking by wp Admin */ 
					$customer_order->add_order_note($ipnResponse['errorMessage'].'<br />ntpID: '.$ipnResponse['data']['ntpID'], 1);
					break;
				default:
				/** Add Note in order for tracking by wp Admin */ 
				$strTracking = 'Error Code : '.$ipnResponse['errorCode'].'<br>';
				$strTracking .= $ipnResponse['errorMessage'].'<br />ntpID: '.$ipnResponse['data']['ntpID'];
				$customer_order->add_order_note($strTracking, 1);
			}
		} else {
			// Order ID Can not be zero / null / Empty
		}

        /**
         * IPN Output
         */
        // echo json_encode($ipnResponse);
        echo json_encode([
            "errorType" => $ipnResponse['errorType'],
            "errorCode" => $ipnResponse['errorCode'],
            "errorMessage" => $ipnResponse['errorMessage']
        ]);
        die();
    }

    function getOrderStatusMessage($statusResObj) {
            $static  = new StaticPro(); 
            switch($statusResObj->payment->status)
                {
                /**
                 * +----------------------------+
                 * | Most usable payment status |
                 * +----------------------------+
                 */
                
                case $static::STATUS_PAID: // capturate (card)
                    /**
                     * payment was confirmed; deliver goods
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_NONE;
                    $outputData['errorCode'] 	= $static::STATUS_PAID;
                    $outputData['errorMessage']	= __('The transaction was processed successfully','netopiapayments');
                break;
                case $static::STATUS_CANCELED: // void
                    /**
                     * payment was cancelled; do not deliver goods
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_CANCELED;
                    $outputData['errorMessage']	= __('payment was cancelled; do not deliver goods','netopiapayments');
                break;
                case $static::STATUS_DECLINED: // declined
                    /**
                     * payment is declined
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_DECLINED;
                    $outputData['errorMessage']	= __('Payment is DECLINED','netopiapayments');
                break;
                case $static::STATUS_FRAUD: // fraud
                    /**
                     * payment status is in fraud, reviw the payment
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_FRAUD;
                    $outputData['errorMessage']	= __('Payment fraud','netopiapayments');
                break;
                case $static::STATUS_PENDING_AUTH: // in review
                    /**
                     * payment status is in reviwing
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_PENDING_AUTH;
                    $outputData['errorMessage']	= __('Payment in reviwing','netopiapayments');
                break;
                case $static::STATUS_3D_AUTH:
                    /**
                     * In STATUS_3D_AUTH the paid purchase need to be authenticate by bank
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_3D_AUTH;
                    $outputData['errorMessage']	= __('3D AUTH required','netopiapayments');
                break;

                /**
                 * +-----------------------+
                 * | Other patments status |
                 * +-----------------------+
                 */

                case $static::STATUS_NEW:
                    /**
                     * STATUS_NEW
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_NONE;
                    $outputData['errorCode']	= $static::STATUS_NEW;
                    $outputData['errorMessage']	= __('STATUS_NEW','netopiapayments');
                break;
                case $static::STATUS_OPENED: // preauthorizate (card)
                    /**
                    * preauthorizate (card)
                    */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_OPENED;
                    $outputData['errorMessage']	= __('preauthorizate (card)','netopiapayments');
                break;
                case $static::STATUS_CONFIRMED:
                    /**
                     * payment was confirmed; deliver goods
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_NONE;
                    $outputData['errorCode']	= $static::STATUS_CONFIRMED;
                    $outputData['errorMessage']	= __('The transaction was confirmed successfully','netopiapayments');
                break;
                case $static::STATUS_PENDING:
                    /**
                    * payment in pending
                    */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_PENDING;
                    $outputData['errorMessage']	= __('Payment pending','netopiapayments');
                break;
                case $static::STATUS_SCHEDULED:
                    /**
                     * *!*!*!*
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_SCHEDULED;
                    $outputData['errorMessage']	= "";
                break;
                case $static::STATUS_CREDIT: // capturate si apoi refund
                    /**
                     * a previously confirmed payment eas refinded; cancel goods delivery
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_CREDIT;
                    $outputData['errorMessage']	= __('a previously confirmed payment was refinded; cancel goods delivery','netopiapayments');
                break;
                case $static::STATUS_CHARGEBACK_INIT: // chargeback initiat
                        /**
                     * chargeback initiat
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_CHARGEBACK_INIT;
                    $outputData['errorMessage']	= __('chargeback initiat','netopiapayments');
                break;
                case $static::STATUS_CHARGEBACK_ACCEPT: // chargeback acceptat
                    /**
                     * *!*!*!*
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_CHARGEBACK_ACCEPT;
                    $outputData['errorMessage']	= "";
                break;
                case $static::STATUS_ERROR: // error
                    /**
                     * payment has an error
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_ERROR;
                    $outputData['errorMessage']	= __('Payment has an error','netopiapayments');
                break;
                case $static::STATUS_PENDING_AUTH: // in asteptare de verificare pentru tranzactii autorizate
                    /**
                     * update payment status, last modified date&time in your system
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_PENDING_AUTH;
                    $outputData['errorMessage']	= __('specific status to authorization pending, awaiting acceptance (verify)','netopiapayments');
                break;
                case $static::STATUS_CHARGEBACK_REPRESENTMENT:
                    /**
                     * *!*!*!*
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_CHARGEBACK_REPRESENTMENT;
                    $outputData['errorMessage']	= "";
                break;
                case $static::STATUS_REVERSED:
                    /**
                     * *!*!*!*
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_REVERSED;
                    $outputData['errorMessage']	= "";
                break;
                case $static::STATUS_PENDING_ANY:
                    /**
                     * *!*!*!*
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_PENDING_ANY;
                    $outputData['errorMessage']	= "";
                break;
                case $static::STATUS_PROGRAMMED_RECURRENT_PAYMENT:
                    /**
                     * *!*!*!*
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_PROGRAMMED_RECURRENT_PAYMENT;
                    $outputData['errorMessage']	= "";
                break;
                case $static::STATUS_CANCELED_PROGRAMMED_RECURRENT_PAYMENT:
                        /**
                     * *!*!*!*
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_CANCELED_PROGRAMMED_RECURRENT_PAYMENT;
                    $outputData['errorMessage']	= "";
                break;
                case $static::STATUS_TRIAL_PENDING: //specific to Model_Purchase_Sms_Online; wait for ACTON_TRIAL IPN to start trial period
                        /**
                     * specific to Model_Purchase_Sms_Online; wait for ACTON_TRIAL IPN to start trial period
                     */
                    $outputData['errorecho $statusRespunseArr->message;Type']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_TRIAL;
                    $outputData['errorMessage']	= __('specific to Model_Purchase_Sms_Online; trial period has started','netopiapayments');
                break;
                case $static::STATUS_EXPIRED: //cancel a not paid purchase
                        /**
                     * cancel a not paid purchase
                     */
                    $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= $static::STATUS_EXPIRED;
                    $outputData['errorMessage']	= __('cancel a not payed purchase.','netopiapayments');
                break;
                default:
                $outputData['errorType']	= $static::ERROR_TYPE_TEMPORARY;
                $outputData['errorCode']	= $statusResObj->payment->status;
                $outputData['errorMessage']	= __('Unknown','netopiapayments');
            }
        return $outputData;
    }

    public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		// Check payment method
		$payment_method = $order->get_payment_method();


		// Check if the order is paied by other method except NetopiaPayments
		if ( $payment_method !== 'netopiapayments' ) {
			return false;
		}

		// check if status is 'processing', 'completed', 'refunded' do the refund otherwise should stop the refound proccess
		if( !in_array($order->get_status(), array('processing', 'completed', 'refunded')) ) {
			$order->add_order_note(sprintf(__( 'Refunded failed because order status is %1$s', 'netopiapayments' ), $order->get_status()));
			$order->save();
			return false;
		}

		// Call the refund API here
		$refund_successful = $this->netopia_create_refund_action($order_id,$amount);
		if ( $refund_successful ) {
			
			$order->add_order_note(sprintf(__( 'Refunded %1$s - %2$s', 'netopiapayments' ), wc_price( $amount ),$reason));
			$order->save();
			return true;
		} else {
			return new WP_Error( 'refund_error', __( 'Refund failed.', 'netopiapayments' ) );
		}
	}
	
	


	/**
	 * Function for `netopia_create_refund_action` action-hook.
	 * This Hook will call Netopia API for refund 
	 * @param  $ntpID 
	 * @param  $amount  
	 *
	 * @return void
	 */
	function netopia_create_refund_action($order_id,$amount){
		$refund = new refund();
		$refund->posSignature 	= $this->account_id;
		$refund->isLive       	= $this->isLive($this->environment);
		if($refund->isLive ) {
			$refund->apiKey 	= $this->live_api_key;						// Live API key
		} else {
			$refund->apiKey 	= $this->sandbox_api_key;					// Sandbox API key
		}
		
		$ntpID = get_metadata( 'post', $order_id, '_ntpID', false );
		$refund->ntpID  = $ntpID[0];
		$refund->amount = $amount;
		

		$jsonStr = $refund->setRefundRequest();
		$refundResult = json_decode($refund->sendRefundRequest($jsonStr));

		if($refundResult->status) {
			return true;
		} else {
			return false;
		}
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
	public function getApiInfo() {
		return '2.0';	
	}

	/**
	 * 
	 */
	public function getPluginInfo() {
		return '2.0.0';	
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

    /**
     * Find the real order ID on Wordpress
     */
    public function getRealOrderID($ntpOrderID) {
        $expStr = explode("_", $ntpOrderID);
        $ocOrderID = $expStr[0]; 
        return $ocOrderID;
    }
}