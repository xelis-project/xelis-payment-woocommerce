<?php

class Xelis_Payment_Gateway extends WC_Payment_Gateway {
    public function __construct() {
        $this->id = 'xelis_payment';
        $this->icon = ''; // URL to an icon
        $this->has_fields = false;
        $this->method_title = __('XELIS Payment', 'woocommerce');
        $this->method_description = __('A XELIS payment gateway for WooCommerce.', 'woocommerce');

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user settings
        $this->title = $this->get_option('title');
        //$this->enabled = $this->get_option('enabled');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Enable XELIS Payment', 'woocommerce'),
                'default' => 'no',
            ),
            'xelis_wallet_addr' => array(
                'title'       => __('XELIS Wallet', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Set the address of your XELIS wallet to receive funds', 'woocommerce'),
                'default'     => '',
            ),
        );
    }

    public function process_payment($order_id) {
        // Implement your payment processing logic here
        // TODO

        $order = wc_get_order($order_id);
        //$order->payment_complete();
        //$order->add_order_note('Payment received via XELIS Payment Gateway.');

        // Return the result
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }
}
