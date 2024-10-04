<?php

class Xelis_Payment_Gateway extends WC_Payment_Gateway
{
  public function __construct()
  {
    $this->id = 'xelis_payment';
    $this->icon = plugins_url('assets/icon.png');
    $this->has_fields = false;
    $this->method_title = __('XELIS Payment', 'woocommerce');
    $this->method_description = __('A XELIS payment gateway for WooCommerce.', 'woocommerce');
    $this->supports = array('products');

    // Load the settings
    $this->init_form_fields();
    $this->init_settings();

    // Define user settings
    $this->title = $this->get_option('title');
    //$this->enabled = $this->get_option('enabled');

    // Actions
    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
  }

  public function init_form_fields()
  {
    $this->form_fields = array(
      'enabled' => array(
        'title' => __('Enable/Disable', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Enable XELIS Payment', 'woocommerce'),
        'default' => 'no',
      ),
      'xelis_wallet_addr' => array(
        'title' => __('XELIS Wallet', 'woocommerce'),
        'type' => 'text',
        'description' => __('Set the address of your XELIS wallet to receive funds', 'woocommerce'),
        'default' => '',
      ),
      'payment_timeout' => array(
        'title' => __('Payment window time out', 'woocommerce'),
        'type' => 'text',
        'description' => __('Set the payment window time out. Lock the XEL/USD quote price. Default is 30min.', 'woocommerce'),
        'default' => '30',
      ),
    );
  }

  public function process_payment($order_id)
  {
    $xelis_state = new Xelis_Payment_State();
    $xelis_state->process_payment_state(); // this is call periodically from js it updates the state of payment_data
    $state = $xelis_state->get_payment_state();

    if ($state->status !== Xelis_Payment_Status::VALID) {
      //wc_add_notice( __( 'Your payment could not be processed. Please try again.', 'your-text-domain' ), 'error' );
      // TODO
      
      return array(
        'result' => 'failure',
        'redirect' => '',
      );
    }

    $order = wc_get_order($order_id);
    $order->payment_complete();
    $order->add_order_note('Payment received via XELIS Payment Gateway.');

    return array(
      'result' => 'success',
      'redirect' => $this->get_return_url($order),
    );
  }
}
