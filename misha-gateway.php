<?php
/*
 * Plugin Name: WooCommerce Custom Payment Gateway
 * Plugin URI: https://rudrastyh.com/woocommerce/payment-gateway-plugin.html
 * Description: Take credit card payments on your store.
 * Author: Misha Rudrastyh
 * Author URI: http://rudrastyh.com
 * Version: 1.0.1
 */


/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'misha_add_gateway_class');
function misha_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Misha_Gateway'; // your class name is here
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'misha_init_gateway_class');
function misha_init_gateway_class()
{

    class WC_Misha_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {

            $this->id = 'misha'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Misha Gateway';
            $this->method_description = 'Description of Misha payment gateway'; // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->private_key = $this->testmode ? $this->get_option('test_private_key') : $this->get_option('private_key');
            $this->publishable_key = $this->testmode ? $this->get_option('test_publishable_key') : $this->get_option('publishable_key');

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable Misha Gateway',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Credit Card',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay with your credit card via our super-cool payment gateway.',
                ),
                'testmode' => array(
                    'title' => 'Test mode',
                    'label' => 'Enable Test Mode',
                    'type' => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'test_publishable_key' => array(
                    'title' => 'Test Publishable Key',
                    'type' => 'text'
                ),
                'test_private_key' => array(
                    'title' => 'Test Private Key',
                    'type' => 'password',
                ),
                'publishable_key' => array(
                    'title' => 'Live Publishable Key',
                    'type' => 'text'
                ),
                'private_key' => array(
                    'title' => 'Live Private Key',
                    'type' => 'password'
                )
            );

        }

        /**
         * You will need it if you want your custom credit card form, Step 4 is about it
         */
        public function payment_fields()
        {

            // ok, let's display some description before the payment form
            if ($this->description) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ($this->testmode) {
                    $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#">documentation</a>.';
                    $this->description = trim($this->description);
                }
                // display the description with <p> tags etc.
                echo wpautop(wp_kses_post($this->description));
            }

            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

            // Add this action hook if you want your custom payment gateway to support it
            do_action('woocommerce_credit_card_form_start', $this->id);

            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            echo '<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
		<input id="misha_ccNo" type="text" autocomplete="off">
		</div>
		<div class="form-row form-row-first">
			<label>Expiry Date <span class="required">*</span></label>
			<input id="misha_expdate" type="text" autocomplete="off" placeholder="MM / YY">
		</div>
		<div class="form-row form-row-last">
			<label>Card Code (CVC) <span class="required">*</span></label>
			<input id="misha_cvv" type="password" autocomplete="off" placeholder="CVC">
		</div>
		<div class="clear"></div>';

            do_action('woocommerce_credit_card_form_end', $this->id);

            echo '<div class="clear"></div></fieldset>';

        }

        /*
         * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
         */
        public function payment_scripts()
        {

            // we need JavaScript to process a token only on cart/checkout pages, right?
            if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
                return;
            }

            // if our payment gateway is disabled, we do not have to enqueue JS too
            if ('no' === $this->enabled) {
                return;
            }

            // no reason to enqueue JavaScript if API keys are not set
            if (empty($this->private_key) || empty($this->publishable_key)) {
                return;
            }

            // do not work with card detailes without SSL unless your website is in a test mode
            if (!$this->testmode && !is_ssl()) {
                return;
            }

            // let's suppose it is our payment processor JavaScript that allows to obtain a token
            wp_enqueue_script('misha_js', 'some payment processor site/api/token.js');

            // and this is our custom JS in your plugin directory that works with token.js
            wp_register_script('woocommerce_misha', plugins_url('misha.js', __FILE__), array('jquery', 'misha_js'));

            // in most payment processors you have to use PUBLIC KEY to obtain a token
            wp_localize_script('woocommerce_misha', 'misha_params', array(
                'publishableKey' => $this->publishable_key
            ));

            wp_enqueue_script('woocommerce_misha');


        }

        /*
          * Fields validation, more in Step 5
         */
        public function validate_fields()
        {
            if( empty( $_POST[ 'billing_first_name' ] ) ) {
                wc_add_notice( 'First name is required!', 'error' );
                return false;
            }

            return true;
        }

        /*
         * We're processing the payments here, everything about it is in Step 5
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            // Generate Nonce
            $nonce = wp_create_nonce('wp_rest');

            // API endpoint
            $endpoint = '{payment processor endpoint}';

            // Parameters for API interaction
            $args = array(
                'body' => json_encode(array(
                    'nonce' => $nonce, // Include nonce in request body or headers
                    'amount' => $order->get_total(),
                    'currency' => get_woocommerce_currency(),
                    // Add other necessary parameters
                )),
                'timeout' => 45,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-WP-Nonce' => $nonce, // Include nonce in headers
                ),
            );

            // Perform API request
            $response = wp_remote_post($endpoint, $args);

            if (is_wp_error($response)) {
                wc_add_notice('Connection error.', 'error');
                return;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ('APPROVED' === $body['response']['responseCode']) {
                // Mark order as paid and reduce stock
                $order->payment_complete();
                $order->reduce_order_stock();

                // Add note to customer
                $order->add_order_note('Hey, your order is paid! Thank you!', true);

                // Empty cart
                WC()->cart->empty_cart();

                // Redirect to thank you page
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order),
                );
            } else {
                wc_add_notice('Please try again.', 'error');
                return;
            }
        }



        /*
         * In case you need a webhook, like PayPal IPN etc
         */
        public function webhook()
        {

            $order = wc_get_order( $_GET[ 'id' ] );
            $order->payment_complete();
            $order->reduce_order_stock();

            update_option( 'webhook_debug', $_GET );
        }
    }
}


add_action( 'woocommerce_blocks_loaded', 'rudr_gateway_block_support' );
function rudr_gateway_block_support() {

     if( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
     	return;
     }

    // here we're including our "gateway block support class"
    require_once __DIR__ . '/includes/class-wc-misha-gateway-blocks-support.php';

    // registering the PHP class we have just included
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            $payment_method_registry->register( new WC_Misha_Gateway_Blocks_Support );
        }
    );

}

add_action( 'before_woocommerce_init', 'rudr_cart_checkout_blocks_compatibility' );

function rudr_cart_checkout_blocks_compatibility() {

    if( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            false // true (compatible, default) or false (not compatible)
        );
    }

}