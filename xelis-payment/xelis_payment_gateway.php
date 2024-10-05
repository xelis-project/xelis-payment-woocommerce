<?php

class Xelis_Payment_Gateway extends WC_Payment_Gateway
{
  public string $wallet_addr;

  public string $payment_timeout; // in min

  public string $node_endpoint;

  public function __construct()
  {
    $this->id = 'xelis_payment';
    $this->icon = plugins_url('assets/icon.png');
    $this->has_fields = false;
    $this->method_title = __('XELIS Payment', 'woocommerce');
    $this->method_description = __('A XELIS payment gateway for WooCommerce.', 'woocommerce');
    $this->supports = array('products');

    $this->init_form_fields();
    $this->init_settings();

    $this->title = $this->get_option('title');
    $this->enabled = $this->get_option('enabled');
    $this->wallet_addr = $this->get_option('wallet_addr');
    $this->payment_timeout = $this->get_option('payment_timeout');
    $this->node_endpoint = $this->get_option('node_endpoint');

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
  }

  public function get_settings() {
    return get_option('woocommerce_' . $this->id .'_settings', []);
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
      'node_endpoint' => array(
        'title' => __('XELIS Node endpoint', 'woocommerce'),
        'type' => 'text',
        'description' => __('Set the node endpoint url. Default is https://node.xelis.io', 'woocommerce'),
        'default' => 'https://node.xelis.io',
      ),
      'wallet_addr' => array(
        'title' => __('XELIS Wallet', 'woocommerce'),
        'type' => 'text',
        'description' => __('Set the address of your XELIS wallet to receive funds', 'woocommerce'),
        'default' => '',
      ),
      'payment_timeout' => array(
        'title' => __('Payment window timeout', 'woocommerce'),
        'type' => 'text',
        'description' => __('Set the payment window timeout. Lock the XEL/USD quote price. Default is 30min', 'woocommerce'),
        'default' => '30',
      ),
    );
  }

  public function process_admin_options()
  {
    // looks like you can't remove the success message even if return false :(
    // https://github.com/woocommerce/woocommerce/blob/37903778fba449da0422207e1ce4f150f02aa0a2/plugins/woocommerce/includes/admin/class-wc-admin-settings.php#L88
    
    // also errors are printed twice :S ???

    $node_endpoint = $_POST['woocommerce_' . $this->id . '_node_endpoint'];
    if ($node_endpoint !== $this->node_endpoint) {
      if (filter_var($node_endpoint, FILTER_VALIDATE_URL) === false) {
        $this->add_error('Node endpoint is not a valid url');
        $this->display_errors();
        return false;
      }
  
      try {
        $node = new Xelis_Node($node_endpoint);
        $node->get_version(); // check if you can fetch the endpoint and its valid

        $xelis_wallet = new Xelis_Wallet();
        $xelis_wallet->set_online_mode($node_endpoint);
      } catch (Exception $e) {
        $this->add_error($e);
        $this->add_error("Not a valid XELIS node.");
        $this->display_errors();
        return false;
      }
    }

    $wallet_addr = $_POST['woocommerce_' . $this->id . '_wallet_addr'];
    if ($wallet_addr !== $this->wallet_addr) {
      try {
        $node = new Xelis_Node($this->node_endpoint);
        $result = $node->validate_address($wallet_addr);
        if ($result->is_valid !== true) {
          $this->add_error(json_encode($result));
          $this->add_error("Not a valid XELIS wallet address.");
          $this->display_errors();
          return false;
        }
      } catch (Exception $e) {
        $this->add_error($e);
        $this->display_errors();
        return false;
      }
    }

    $payment_timeout = $_POST['woocommerce_' . $this->id . '_payment_timeout'];
    if ($payment_timeout !== $this->payment_timeout) {
      if (filter_var($payment_timeout, FILTER_VALIDATE_INT) == false) {
        $this->add_error("Payment timeout window must be an number.");
        $this->display_errors();
        return false;
      }
  
      if (($payment_timeout) < 5) {  // cannot set less than 5min
        $this->add_error("Can't set less than 5min for payment window.");
        $this->display_errors();
        return false;
      };
    }

    return parent::process_admin_options();
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
