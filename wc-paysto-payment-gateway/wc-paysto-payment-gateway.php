<?php
/*
Plugin Name: Paysto Payment Gateway
Plugin URI: https://github.com/alexsaab/woocommerce-paysto
Description: Allows you to use Paysto payment gateway with the WooCommerce plugin.
Version: 1.3
Author: Alex Agafonov
Author URI: https://github.com/alexsaab/
 */

if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly

/**
 * Add roubles in currencies
 *
 * @since 0.3
 */
function paysto_rub_currency_symbol($currency_symbol, $currency)
{
    if ($currency == "RUB") {
        $currency_symbol = 'р.';
    }

    if ($currency == "USD") {
        $currency_symbol = '$';
    }

    return $currency_symbol;
}

function paysto_rub_currency($currencies)
{
    $currencies["RUB"] = 'Russian Roubles';
    $currencies["USD"] = 'USA Dollars';

    return $currencies;
}

add_filter('woocommerce_currency_symbol', 'paysto_rub_currency_symbol', 10, 2);
add_filter('woocommerce_currencies', 'paysto_rub_currency', 10, 1);


/**
 * Add a custom payment class to WC
 */
add_action('plugins_loaded', 'woocommerce_paysto', 0);
function woocommerce_paysto()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    // if the WC payment gateway class is not available, do nothing
    if (class_exists('WC_PAYSTO')) {
        return;
    }

    /**
     * Class WC_PAYSTO extends WC_Payment_Gateway
     *
     */
    class WC_PAYSTO extends WC_Payment_Gateway
    {
        /** In this var store servers ip lists for Paysto system @var array */
        public $PaystoServers = [];

        public function __construct()
        {

            $plugin_dir = plugin_dir_url(__FILE__);

            global $woocommerce;

            $this->id = 'paysto';
            $this->icon = apply_filters('woocommerce_paysto_icon', '' . $plugin_dir . 'paysto.png');
            $this->has_fields = false;
            $this->liveurl = 'https://paysto.com/ru/pay/AuthorizeNet';

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');

            $this->paysto_x_description = $this->get_option('paysto_x_description');
            $this->paysto_x_login = $this->get_option('paysto_x_login');

            $this->paysto_secret = $this->get_option('paysto_secret');
            $this->paysto_order_status = $this->get_option('paysto_order_status');

            $this->paysto_ips_servers = $this->get_option('paysto_ips_servers');
            $this->PaystoServers = preg_split('/\r\n|[\r\n]/', $this->paysto_ips_servers);
            $this->paysto_only_from_ips = $this->get_option('paysto_only_from_ips');

            //  vat settings
            $this->paysto_vat_products = $this->get_option('paysto_vat_products');
            $this->paysto_vat_delivery = $this->get_option('paysto_vat_delivery');

            $this->debug = $this->get_option('debug');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions');

            // Logs
            if (($this->debug == 'yes') && (method_exists($woocommerce, 'logger'))) {
                $this->log = $woocommerce->logger();
            }

            // Actions
            add_action('valid-paysto-standard-request', array($this, 'successful_request'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // Save options
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Payment listener/API hook
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_response'));

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }


        /**
         * Check if this gateway is enabled and available in the user's country
         *
         * @return bool
         */
        function is_valid_for_use()
        {
            if (!in_array(get_option('woocommerce_currency'), array('RUB', 'USD'))) {
                return false;
            }

            return true;
        }


        /**
         * Admin Panel Options - Options for bits like 'title' and availability on a country-by-country basis
         *
         * @since 0.1
         */
        public function admin_options()
        {
            ?>
            <h3><?php _e('PAYSTO', 'woocommerce'); ?></h3>
            <p><?php _e('Settings payment recieve from PAYSTO system.', 'woocommerce'); ?></p>

            <?php if ($this->is_valid_for_use()): ?>

            <table class="form-table">

                <?php
                // Generate the HTML For the settings form.
                $this->generate_settings_html();
                ?>
            </table><!--/.form-table-->

        <?php else: ?>
            <div class="inline error"><p>
                    <strong><?php _e('Gate swich off', 'woocommerce'); ?></strong>:
                    <?php _e('PAYSTO not support currency used in your store.', 'woocommerce'); ?>
                </p></div>
        <?php
        endif;

        } // End admin_options()

        /**
         * Initialise Gateway Settings form
         *
         * @access public
         * @return void
         */
        public function init_form_fields()
        {
            $this->form_fields = array(

                'enabled' => array(
                    'title' => __('Of/Off', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('On', 'woocommerce'),
                    'default' => 'yes',
                ),

                'title' => array(
                    'title' => __('Name of payment method', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('User see this title in order checkout.', 'woocommerce'),
                    'default' => __('Paysto', 'woocommerce'),
                ),

                'paysto_x_description' => array(
                    'title' => __('Comment for payment', 'woocommerce'),
                    'type' => 'text',
                    'required' => true,
                    'description' => __('Fill this field obligatory', 'woocommerce'),
                    'default' => __('Payment in my store throw Paysto payment system', 'woocommerce'),
                ),

                'paysto_x_login' => array(
                    'title' => __('Code of your store', 'woocommerce'),
                    'type' => 'text',
                    'required' => true,
                    'description' => __('Please find your merchant id in merchant in Paysto merchant backoffice',
                        'woocommerce'),
                    'default' => '',
                ),

                'paysto_secret' => array(
                    'title' => __('Secret', 'woocommerce'),
                    'type' => 'password',
                    'description' => __('Paysto secret word, please set it also in Paysto merchant backoffice',
                        'woocommerce'),
                    'default' => '',
                ),

                'paysto_order_status' => array(
                    'title' => __('Order status', 'woocommerce'),
                    'type' => 'select',
                    'options' => wc_get_order_statuses(),
                    'description' => __('Setup order status after successfull payment', 'woocommerce'),
                ),

                'paysto_vat_products' => array(
                    'title' => __('VAT for products', 'woocommerce'),
                    'type' => 'select',
                    'options' => array(
                        1 => __('With VAT', 'woocommerce'),
                        0 => __('Without VAT', 'woocommerce')
                    ),
                    'description' => __('Set VAT for products in checkout', 'woocommerce'),
                    'default' => '0',
                ),

                'paysto_vat_delivery' => array(
                    'title' => __('VAT for delivery', 'woocommerce'),
                    'type' => 'select',
                    'options' => array(
                        1 => __('With VAT', 'woocommerce'),
                        0 => __('Without VAT', 'woocommerce')
                    ),
                    'description' => __('Set VAT for delivery in checkout', 'woocommerce'),
                    'default' => '0',
                ),

                'paysto_ips_servers' => array(
                    'title' => __('IPs Addresses of Paysto callback servers', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('This options need for security reason. Each server IP must begin in new line.',
                        'woocommerce'),
                    'default' => '95.213.209.218
95.213.209.219
95.213.209.220
95.213.209.221
95.213.209.222',
                ),

                'paysto_only_from_ips' => array(
                    'title' => __('Only approved server', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Use only callbacks from approved Paysto servers', 'woocommerce'),
                    'default' => 'yes',
                ),

                'debug' => array(
                    'title' => __('Debug', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Switch on logging in file (<code>woocommerce/logs/paysto.txt</code>)',
                        'woocommerce'),
                    'default' => 'no',
                ),

                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Description of payment method which user can see in you site.',
                        'woocommerce'),
                    'default' => __('Payment with Paysto service.', 'woocommerce'),
                ),

                'instructions' => array(
                    'title' => __('Instructions', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Instructions which can added in page of thank-you-payment page.',
                        'woocommerce'),
                    'default' => __('Payment with Paysto service. Thank you very much for you payment.',
                        'woocommerce'),
                ),
            );
        }


        /**
         * There are no payment fields for paysto, but we want to show the description if set.
         */
        function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }
        }


        /**
         * Generate the dibs button link
         */
        public function generate_form($order_id)
        {
            global $woocommerce;

            $order = new WC_Order($order_id);

            $action_adr = $this->liveurl; // Url
            $orderAmount = $this->getOrderTotal($order); // Get order amount in 123.21
            $now = time();

            $x_relay_url = get_site_url() . '/' . '?wc-api=wc_paysto&paysto=result';

            $args = array(
                // Merchant
                'x_description' => $this->paysto_x_description,
                'x_login' => $this->paysto_x_login,
                'x_amount' => $orderAmount,
                'x_email' => $order->get_billing_email(),
                'x_currency_code' => $order->get_currency(),
                'x_fp_sequence' => $order_id,
                'x_fp_timestamp' => $now,
                'x_fp_hash' => $this->get_x_fp_hash($this->paysto_x_login, $order_id, $now,
                    $orderAmount, $order->get_currency()),
                'x_invoice_num' => $order_id,
                'x_relay_response' => "TRUE",
                'x_relay_url' => $x_relay_url,
            );

            //add products
            $pos = 1;
            $x_line_item = '';
            foreach ($order->get_items() as $product) {
                $lineArr = array();
                $productObject = wc_get_product($product->get_product_id());
                $lineArr[] = '№' . $pos . "  ";
                $lineArr[] = substr($productObject->get_sku(), 0, 30);
                $lineArr[] = substr($product['name'], 0, 254);
                $lineArr[] = substr($product['quantity'], 0, 254);
                $lineArr[] = number_format($product['total'] / $product['quantity'], 2, '.',
                    '');
                $lineArr[] = $this->paysto_vat_products;
                $x_line_item .= implode('<|>', $lineArr) . "0<|>\n";
                $pos++;
            }

            // add delivery
            $deliveryPrice = number_format($order->get_shipping_total(), 2, '.', '');
            if ($deliveryPrice > 0.00) {
                $lineArr = array();

                $lineArr[] = '№' . $pos . "  ";
                $lineArr[] = __('Delivery ', 'woocommerce') . $order_id;
                $lineArr[] = __('Delivery of order #', 'woocommerce') . $order_id;
                $lineArr[] = 1;
                $lineArr[] = number_format($order->get_shipping_total(), 2, '.', '');
                $lineArr[] = $this->paysto_vat_delivery;

                $x_line_item .= implode('<|>', $lineArr) . "0<|>\n";
                $pos++;
            }

            $args['x_line_item'] = $x_line_item;

            $args_array = array();

            foreach ($args as $key => $value) {
                $args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) .
                    '" />';
            }

            return
                '<form action="' . esc_url($action_adr) . '" method="POST" id="paysto_payment_form">' . "\n" .
                implode("\n", $args_array) .
                '<input type="submit" class="button alt" id="submit_paysto_payment_form" value="' .
                __('Pay now', 'woocommerce') . '" /> <a class="button cancel" href="' .
                $order->get_cancel_order_url() . '">' .
                __('Refuse payment and return to buyer cart.', 'woocommerce') . '</a>' . "\n" .
                '</form>';
        }


        /**
         * Return hash md5 HMAC
         *
         * @param $x_login
         * @param $x_fp_sequence
         * @param $x_fp_timestamp
         * @param $x_amount
         * @param $x_currency_code
         * @return false|string
         */
        private function get_x_fp_hash($x_login, $x_fp_sequence, $x_fp_timestamp, $x_amount, $x_currency_code)
        {
            $arr = array($x_login, $x_fp_sequence, $x_fp_timestamp, $x_amount, $x_currency_code);
            $str = implode('^', $arr);
            return hash_hmac('md5', $str, $this->paysto_secret);
        }


        /**
         * Return sign with MD5 algoritm
         *
         * @param $x_login
         * @param $x_trans_id
         * @param $x_amount
         * @return string
         */
        private function get_x_MD5_Hash($x_login, $x_trans_id, $x_amount)
        {
            return md5($this->paysto_secret . $x_login . $x_trans_id . $x_amount);
        }


        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order_id, add_query_arg('key', $order->get_order_key(),
                    get_permalink(wc_get_page_id('pay')))),
            );
        }


        /**
         * Thank you form
         *
         * @param $order
         */
        function receipt_page($order)
        {
            echo '<p>' . __('Thank you very much for your order! Please press button below for begin payment',
                    'woocommerce') . '</p>';
            echo $this->generate_form($order);
        }

        /**
         * Return the order amount in format 0.00
         *
         * @param $order
         * @return string
         */
        public function getOrderTotal($order)
        {
            return number_format($order->order_total, 2, '.', '');
        }


        /**
         * Check Response
         */
        function check_response()
        {
            global $woocommerce;

            if (isset($_GET['paysto']) && $_GET['paysto'] == 'result') {
                $orderId = $_POST['x_invoice_num'];
                $order = new WC_Order($orderId);
                if (($this->paysto_order_status == 'wc-' . $order->get_status()) ||
                    ($this->paysto_order_status == $order->get_status())
                    && $_POST['x_response_reason_code'] === '') {
                    WC()->cart->empty_cart();
                    session_start();
                    $_SESSION['paysto_pay'] = "success";
                    wp_redirect($this->get_return_url($order));
                }
                if ($this->paysto_only_from_ips == 'yes' &&
                    ((!in_array($_SERVER['HTTP_X_FORWARDED_FOR'], $this->PaystoServers)) &&
                        (!in_array($_SERVER['HTTP_CF_CONNECTING_IP'], $this->PaystoServers)) &&
                        (!in_array($_SERVER['HTTP_X_REAL_IP'], $this->PaystoServers)) &&
                        (!in_array($_SERVER['REMOTE_ADDR'], $this->PaystoServers)) &&
                        (!in_array($_SERVER['GEOIP_ADDR'], $this->PaystoServers)))) {

                    if (!isset($_SESSION['paysto_pay'])) {
                        if ($_SESSION['paysto_pay'] != 'success') {
                            wp_redirect($order->get_cancel_order_url());
                        }
                    } else {
                        session_destroy();
                    }
                }
                @ob_clean();
                $_POST = stripslashes_deep($_POST);
                $x_response_code = $_POST['x_response_code'];
                $x_trans_id = $_POST['x_trans_id'];
                $x_invoice_num = $_POST['x_invoice_num'];
                $x_MD5_Hash = $_POST['x_MD5_Hash'];
                $x_amount = $_POST['x_amount'];
                $order = new WC_Order($x_invoice_num);
                if (($this->get_x_MD5_Hash($this->paysto_x_login, $x_trans_id, $this->getOrderTotal($order)) === $x_MD5_Hash) &&
                    ($x_response_code == 1) &&
                    $x_amount == $this->getOrderTotal($order)) {
                    // Add transaction information for Paysto
                    if ($this->debug == 'yes' || $this->debug == '1') {
                        $this->add_transaction_info($_POST);
                    } else {
                        $this->add_transaction_info(__('Payment was successful with Paysto payment system, number of trunsaction is: ',
                                'woocommerce') . $_POST['x_trans_id']);
                    }
                    do_action('valid-paysto-standard-request', $_POST);
                    $order->update_status($this->paysto_order_status, __('Payment is successful!', 'woocommerce'));
                } else {
                    wp_redirect($order->get_cancel_order_url());
                }
            } elseif (isset($_GET['paysto']) and $_GET['paysto'] == 'success') {
                $orderId = $_POST['x_invoice_num'];
                $order = new WC_Order($orderId);
                WC()->cart->empty_cart();
                wp_redirect($this->get_return_url($order));
            } elseif (isset($_GET['paysto']) and $_GET['paysto'] == 'fail') {
                $orderId = $_POST['x_invoice_num'];
                $order = new WC_Order($orderId);
                $order->update_status('failed', __('Payment is not successful!', 'woocommerce'));
                wp_redirect($order->get_cancel_order_url());
                exit;
            }
        }


        /**
         * Add comment to order with info about transactions
         *
         * @param $post
         */
        private function add_transaction_info($post)
        {
            global $woocommerce;
            $orderId = $post['x_invoice_num'];
            $order = new WC_Order($orderId);
            $message = __('Server Paysto payment system return data in post: ', 'woocommerce') .
                print_r($post, true);
            $order->add_order_note($message);
            return;
        }


        /**
         * Logger function for debug
         *
         * @param  [type] $var  [description]
         * @param  string $text [description]
         * @return [type]       [description]
         */
        public function logger($var, $text = '')
        {
            // Название файла
            $loggerFile = __DIR__ . '/logger.log';
            if (is_object($var) || is_array($var)) {
                $var = (string)print_r($var, true);
            } else {
                $var = (string)$var;
            }
            $string = date("Y-m-d H:i:s") . " - " . $text . ' - ' . $var . "\n";
            file_put_contents($loggerFile, $string, FILE_APPEND);
        }

    }


    /**
     * Add the gateway to WooCommerce
     **/
    function add_paysto_gateway($methods)
    {
        $methods[] = 'WC_PAYSTO';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_paysto_gateway');
}

?>