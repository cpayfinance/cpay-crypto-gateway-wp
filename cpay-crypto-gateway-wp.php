<?php

/*
 * Plugin Name: CPay Crypto Payment Gateway
 * Plugin URI: https://cpay.finance
 * Description: CPay Crypto payment gateway.
 * Author: CPay
 * Author URI: https://cpay.finance
 * Version: 1.0.0
 *
 */

defined('ABSPATH') || exit;

require_once ABSPATH . 'wp-admin/includes/plugin.php';
if (is_plugin_active('woocommerce/woocommerce.php') === true) {
    require_once ABSPATH . 'wp-content/plugins/woocommerce/woocommerce.php';
    // Add the Gateway to WooCommerce.
    add_filter('woocommerce_payment_gateways', 'wc_ljkjcpay_crypto_gateway');
    add_action('plugins_loaded', 'woocommerce_ljkjcpay_crypto_init', 0);

    /**
     * Add the gateways to WooCommerce
     *
     * @param array $methods $args {
     *                       Optional. An array of arguments.
     *
     * @type   type $key Description. Default 'value'. Accepts 'value', 'value'.
     *    (aligned with Description, if wraps to a new line)
     * @type   type $key Description.
     * }
     * @return array $methods
     * @since  1.0.0
     */
    function wc_ljkjcpay_crypto_gateway( $methods) {
        $methods[] = 'ljkjcpaycrypto';
        return $methods;
    }

    /**
     * Add the Gateway to WooCommerce init
     *
     * @return bool
     */
    function woocommerce_ljkjcpay_crypto_init() {
        if (class_exists('WC_Payment_Gateway') === false) {
            return;
        }
    }

    /**
     * Define Ljkjcpaycrypto Class
     *
     * @package  WooCommerce
     * @author   ljkjcpay <dev@cpay.finance>
     * @link     cpay.finance
     */
    class Ljkjcpaycrypto extends WC_Payment_Gateway {
        /**
         * Define Ljkjcpaycrypto Class constructor
         **/
        public function __construct() {
            $this->id   = 'ljkjcpaycrypto';
            $this->icon = plugins_url('cpay-icon.png', __FILE__); // plugins_url('images/crypto.png', __FILE__);

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->merchantid  = $this->get_option('merchantid');
            $this->cpayhost    = $this->get_option('cpayhost');

            $this->apikey         = '1';
            $this->secret         = $this->get_option('secret');
            $this->msg['message'] = '';
            $this->msg['class']   = '';

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            add_action('woocommerce_api_' . strtolower(get_class($this)).'_callback', array( &$this, 'cpaycrypto_callback_processor' ));

            // Valid for use.
            $this->enabled = 'no';
            if (empty($this->settings['enabled']) === false && empty($this->apikey) === false && empty($this->secret) === false) {
                $this->enabled = 'yes';
            }

            // Checking if apikey is not empty.
            if (empty($this->apikey) === true) {
                add_action('admin_notices', array( &$this, 'apikey_missingmessage' ));
            }

            // Checking if app_secret is not empty.
            if (empty($this->secret) === true) {
                add_action('admin_notices', array( &$this, 'secret_missingmessage' ));
            }
            
            // Checking if cpayhost is not empty.
            if (empty($this->cpayhost) === true) {
                add_action('admin_notices', array( &$this, 'secret_missingmessage' ));
            }
        }

        /**
         * Define initFormfields function
         *
         * @return mixed
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled'     => array(
                    'title'   => __('Enable/Disable', ''),
                    'type'    => 'checkbox',
                    'label'   => __('Enable CPay Crypto', ''),
                    'default' => 'yes',
                ),
                'title'       => array(
                    'title'       => __('Title', ''),
                    'type'        => 'text',
                    'description' => __('This controls the title the user can see during checkout.', ''),
                    'default'     => __('CPay Crypto', ''),
                ),
                'description' => array(
                    'title'       => __('Description', ''),
                    'type'        => 'textarea',
                    'description' => __('This controls the title the user can see during checkout.', ''),
                    'default'     => __('You will be redirected to cpay.finance to complete your purchasing.', ''),
                ),
                'cpayhost'  => array(
                    'title'       => __('API Host', ''),
                    'type'        => 'text',
                    'description' => __('Please enter the host, You can get this information from cpay.finance', ''),
                    'default'     => 'https://example.com',
                ),
                'merchantid'  => array(
                    'title'       => __('MerchantID', ''),
                    'type'        => 'text',
                    'description' => __('Please enter your MerchantID, You can get this information from cpay.finance', ''),
                    'default'     => 'N/A',
                ),
                'secret'      => array(
                    'title'       => __('SecurityKey', ''),
                    'type'        => 'password',
                    'description' => __('Please enter your SecurityKey, You can get this information from cpay.finance', ''),
                    'default'     => '*',
                ),
            );
        }//end init_form_fields()

        /**
         * Define adminOptions function
         *
         * @return mixed
         */
        public function admin_options() {
            ?>
            <h3><?php esc_html_e('CPay Crypto Checkout', 'CPay'); ?></h3>

            <div id="wc_get_started">
                <p><a href="https://cpay.finance" target="_blank" class="button"><?php esc_html_e('Learn more about CPay', 'CPay'); ?></a></p>
            </div>

            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
            <?php
        }//end admin_options()

        /**
         *  There are no payment fields for Cpay, but we want to show the description if set.
         *
         * @return string
         **/
        public function payment_fields() {
            if (strlen($this->description) > 0) {
                echo esc_html($this->description);
            }
        }//end payment_fields()

        /**
         * Process the payment and return the result
         *
         * @param int $orderid comment
         *
         * @return $array
         **/
        public function process_payment( $orderid )
        {
            global $woocommerce;
            $order = wc_get_order($orderid);
            if (strtoupper(get_woocommerce_currency()) != 'USD') {
                wc_add_notice('Checkout error: currency is not supported. [USD ONLY]', 'error');
                exit();
            }
            if ($order->get_total() < 1) {
                wc_add_notice('Checkout error: order amount cannot less than $1.00', 'error');
                exit();
            }
            if (empty($this->merchantid) === true) {
                wc_add_notice('Checkout error: please contact administrator [C1000]', 'error');
                exit();
            }
            if (empty($this->secret) === true) {
                wc_add_notice('Checkout error: please contact administrator [C1001]', 'error');
                exit();
            }
            if (empty($this->cpayhost) === true) {
                wc_add_notice('Checkout error: please contact administrator [C1002]', 'error');
                exit();
            }

            list($usec, $sec) = explode(" ", microtime());
            $params = [
                'merchantId' => $this->merchantid,
                'merchantTradeNo' => $this->merchantid . '_' . $orderid,
                'createTime' => round($sec*1000),
                'userId' => $sec,
                'cryptoCurrency' => 'USDT',
                'amount' => number_format($order->get_total(), 2, '.', ''),
                'callBackURL' => site_url('/?wc-api=ljkjcpaycrypto_callback'),
                'successURL' => '',
                'failURL' => '',
                'extInfo' => '',
            ];
            $params['sign'] = $this->gen_signature($params, $this->secret);

            $req = '';
            foreach ($params as $k => $v) {
                $req = $req . "{$k}={$v}&";
            }
            $url       = trim($this->cpayhost, '/') . '/openapi/v1/createOrder';
            $response  = wp_safe_remote_post($url, array('body' => trim($req, '&')));
            if ((false === is_wp_error($response)) && (200 === $response['response']['code']) && ('OK' === $response['response']['message'])) {
                $body = json_decode($response['body'], true);
                $code = isset($body['code']) ? $body['code'] : -1;
                $errmsg = isset($body['msg']) ? $body['msg'] : 'create order failed';
                if ($code == 0) {
                    // 更新订单状态为等待中 (等待第三方支付网关返回)
                    $order->update_status('pending', __( 'Awaiting payment', 'woocommerce' ));
                    $order->reduce_order_stock(); // 减少库存
                    $woocommerce->cart->empty_cart(); // 清空购物车
                    $rr = array(
                        'result'   => 'success',
                        'redirect' => isset($body['data']['cashierURL']) ? $body['data']['cashierURL'] : '',
                    );
                    return $rr;
                }
                wc_add_notice('Payment error: '.sprintf("%s [C%d]", $errmsg, $code), 'error');
                exit();
            }
            wc_add_notice('Payment error: system upgrade, please try it later.', 'error');
        }//end process_payment()

        /**
         * Check for valid Cpay server callback
         *
         *
         * @return string
         **/
        public function cpaycrypto_callback_processor() {
            global $woocommerce;
            $body = file_get_contents('php://input');
            $body_data = json_decode($body, true);
            $orderid = '';
            if (isset($body_data['merchantTradeNo']) && !empty($body_data['merchantTradeNo'])) {
                $oid = explode('_', $body_data['merchantTradeNo']);
                if (count($oid)>1) {
                    $orderid = $oid[1];
                }
            }
            if (empty($orderid) || !isset($body_data['orderStatus']) || !in_array($body_data['orderStatus'], [14, 15])) {
                echo 'invalid callback param';
                exit();
            }

            /**
            $order_statuses = array(
            'wc-pending'    => _x( 'Pending payment', 'Order status', 'woocommerce' ),
            'wc-processing' => _x( 'Processing', 'Order status', 'woocommerce' ),
            'wc-on-hold'    => _x( 'On hold', 'Order status', 'woocommerce' ),
            'wc-completed'  => _x( 'Completed', 'Order status', 'woocommerce' ),
            'wc-cancelled'  => _x( 'Cancelled', 'Order status', 'woocommerce' ),
            'wc-refunded'   => _x( 'Refunded', 'Order status', 'woocommerce' ),
            'wc-failed'     => _x( 'Failed', 'Order status', 'woocommerce' ),
            );
             */
            $order_status = $body_data['orderStatus'];
            $order = new WC_Order($orderid);
            if ($order_status == 14) {
                // Do your magic here, and return 200 OK to ljkjcpay.
                if ('pending' === $order->status) {
                    $order->update_status('processing', sprintf(__('IPN: Payment completed notification from CPay', 'woocommerce')));
                    $order->save();
                    $order->add_order_note( __( 'IPN: Update status event for CPay', 'woocommerce' ) . ' ' . $orderid);
                }
                echo 'ok';
                exit;
            } else {
                if ('failed' !== $order->status) {
                    $order->update_status('failed', sprintf(__('IPN: Payment failed notification from CPay', 'woocommerce')));
                }
                echo 'ok';
                exit;
            }
        }//end check_ljkjcpay_response()

        /**
         * Adds error message when not configured the api key.
         *
         * @return string Error Mensage.
         */
        public function apikey_missingmessage() {
            $message  = '<div class="notice notice-info is-dismissible">';
            $message .= '<p><strong>Gateway Disabled</strong> You should enter your SecurityKey in CPay configuration. <a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=ljkjcpay">Click here to configure</a></p>';
            $message .= '</div>';

            echo $message;
        }//end apikey_missingmessage()

        /**
         * Adds error message when not configured the secret.
         *
         * @return String Error Mensage.
         */
        public function secret_missingmessage() {
            $message  = '<div class="notice notice-info is-dismissible">';
            $message .= '<p><strong>Gateway Disabled</strong> Please check your MerchantID / SecurityKey / Host in CPay configuration. </p>';
            $message .= '</div>';

            echo $message;
        }//end secret_missingmessage()

        public function gen_signature($params, $security_key) {
            if (!is_array($params) || count($params) == 0) {
                return '';
            }

            ksort($params);
            $ps = '';
            foreach ($params as $k => $v) {
                if (!empty($v)) {
                    $ps = $ps . "{$k}={$v}&";
                }
            }
            $ps = $ps.'key='.$security_key;
            return hash_hmac("sha256", $ps, $security_key);
        }
    }//end class
}//end if
