<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
    exit; 
}

/**
 * Sends API requests to Tapsys.
 */
class Tapsys_API_Handler {

    /** @var string Tapsys sandbox API url. */
    public static $sandbox_api_url = 'https://staginggateway.tapsys.net/plugin/';

    /** @var string Tapsys production API url. */
    public static $production_api_url = 'https://gateway.tapsys.net/plugin/';

    /** @var string Tapsys init transaction endpoint. */
    public static $init_transaction_endpoint = "wordpress/order/v1/init";

    /** @var string Tapsys sandbox API key. */
    public static $sandbox_api_key;

    /** @var string Tapsys production API key. */
    public static $production_api_key;

    public static $SANDBOX = "sandbox";

    public static $PRODUCTION = "production";

    /**
     * Get the response from an API request.
     * @param  string $endpoint
     * @param  array  $params
     * @param  string $method
     * @return array
     */
    public static function send_request($environment = "sandbox", $endpoint = "", $params = array(), $method = 'GET')
    {
        $args = array(
            'method'  => $method,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        );

        $baseURL = $environment === self::$SANDBOX ? self::$sandbox_api_url : self::$production_api_url;
        $url = $baseURL . $endpoint;
        print_r($url);
        if (in_array( $method, array('POST'))) {
            $args['body'] = json_encode($params);
        }

        $response = wp_remote_request(esc_url_raw($url), $args);

        if (is_wp_error($response)) {
            return array( false, $response->get_error_message() );
        } else {
            $result = json_decode( $response['body'], true);
            $code = $response['response']['code'];
            if ( in_array( $code, array(200), true)) {
                return array(true, $result);
            } else {
                return array( false, $code );
            }
        }
    }

    /**
     * Create a new charge request.
     * @param  int    $amount
     * @param  string $currency
     * @param  array  $metadata
     * @param  string $redirect
     * @param  string $name
     * @param  string $desc
     * @param  string $cancel
     * @return array
     */
    public static function create_charge($order = null,$merchant_id = null,$merchant_client_id = null,$amount = null, $currency = null, $environment = "sandbox")
    {
        $args = array(
            "environment" => $environment
        );

        if (is_null($amount)) {
            return array(false, "Missing amount");
        }
        $args["amount"] = floatval($amount);

        if (is_null($currency)) {
            return array(false, "Missing currency");
        }
        if($currency === "PKR"){
            $args["currency"] = "586";
        }
        $args["firstName"] = $order->get_billing_first_name();
        $args["lastName"] = $order->get_billing_last_name();
        $args["billingEmail"] = $order->get_billing_email();
        $args["billingAddress"] = $order->get_billing_address_1();
        $args["billingPhone"] = substr($order->get_billing_phone(),1);
        $args["clientId"] = $merchant_client_id;
        $args["merchantId"] = $merchant_id;

        $client = "";
        if ($environment === self::$SANDBOX) {
            $client = self::$sandbox_api_key;
        } else if ($environment === self::$PRODUCTION) {
            $client = self::$production_api_key;
        } else {
            return array(false, "Invalid environment");
        }

        if ($client === "") {
            return array(false, "Missing client");
        }
        $args["clientWordPressKey"] = $client;

        $result = self::send_request($environment, self::$init_transaction_endpoint, $args, 'POST');
        
        return $result;
    }
}
