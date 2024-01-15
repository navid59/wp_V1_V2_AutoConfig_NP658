<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

CONST KYC_STATUS_NEW            = 0;
CONST KYC_STATUS_SEND           = 1;
CONST KYC_STATUS_REJECT         = 2;
CONST KYC_STATUS_STEP_BACK      = 3;
CONST KYC_STATUS_NEED_REVIEW    = 4;
CONST KYC_STATUS_APROVED        = 5;

CONST POS_TYPE_CRM              = 0;

// Register the custom endpoint
add_action('rest_api_init', 'netopiaCustomEndpoint');

function netopiaCustomEndpoint()
{
    // Register the credential endpoint
    register_rest_route('netopiapayments/v1', '/updatecredential', array(
        'methods' => 'POST',
        'callback' => 'updateCredentialCallback',
        'permission_callback' => '__return_true'
    ));

}


// Callback function to update credentials
function updateCredentialCallback($request)
{
    // Predefine response data
    $data = array();

    // Create a new response object
    $response = new WP_REST_Response();
    
    // Get the request parameters
    $params = $request->get_params();

    if (!is_array($params) || empty($params)) {
        wp_send_json([
            'code' => 400,
            'message' => "Credential are not updated!. The Request is empty!",
            'timestamp' => time(),
        ]);        
    }
    
    // Retrieve and process data
    $data = array(
        'params' => $params,
        'timestamp' => time(),
    );

    

    // Get the existing settings option
    $settings_serialized = get_option('woocommerce_netopiapayments_settings');

    // If the option exists and is not empty, unserialize the data
    if ($settings_serialized !== false && !empty($settings_serialized)) {
        $settings = maybe_unserialize($settings_serialized);
    } else {
        // If the option doesn't exist or is empty, start with an empty array
        $settings = array();
    }

    /* 
    Verify the Hash , if data is Safe then Update the Data
    */
    // Get ntp_notify_value if exist
    $ntpOptions = get_option( 'woocommerce_netopiapayments_settings' );

    $receivedHash = $params['hash'];
    $seckey = base64_encode(md5(json_encode($ntpOptions).json_encode(get_home_url())));
    
    // Generate Hash from recived Data
    $calculatedHash = hashCredentialData($seckey, $params['signature'], $params['apiKeyLive'], $params['apiKeySandbox']);
    
    if ($calculatedHash === $receivedHash) {
         // Update the settings with the provided values
        $settings['account_id'] = $params['signature'];
        $settings['live_api_key'] = $params['apiKeyLive'];
        $settings['sandbox_api_key'] = $params['apiKeySandbox'];
        $settings['ntp_notify_value'] = $params['notifyMerchant'];
        

        // Save the updated settings options
        update_option('woocommerce_netopiapayments_settings', $settings);

        // Set Message
        $data['isupdated'] = true;
        $data['msg'] = "Data Updated  Successfully";
        

    } else {
        // Data is potentially tampered with; reject the request.
        $data['isupdated'] = false;
        $data['msg'] = "Data Not Updated. Security issue";
    }

    $response = $data;
    wp_send_json($response);
}

function hashCredentialData($seckey, $signature, $apiKeyLive, $apiKeySandbox) {
    $data = [
        $signature,
        $apiKeyLive,
        $apiKeySandbox
    ];
    
    $dataJson = json_encode($data);
    $hash = hash('sha256', $dataJson . $seckey);
    
    return $hash;
}

?>