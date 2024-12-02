<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Xelis_Payment_Method extends AbstractPaymentMethodType
{
  protected $name = 'xelis_payment';

  // note: this func is called before Xelis_Payment_Gateway gets initialized
  // meaning do not call gateway instance
  public function initialize()
  {
    $this->settings = get_option('woocommerce_' . $this->name . '_settings', []);
  }

  public function is_active()
  {
    return filter_var($this->get_setting('enabled', false), FILTER_VALIDATE_BOOLEAN);
  }

  public function get_payment_method_script_handles()
  {
    // import the require script needed to map react with wp.element global window variable
    wp_register_script(
      'xelis_payment_method_require',
      plugins_url('/client/require.js', __FILE__),
      [],
      filemtime(plugin_dir_path(__FILE__) . '/client/require.js'),
      true
    );

    wp_register_script(
      'xelis_payment_method',
      plugins_url('/client/build/block.js', __FILE__),
      [],
      filemtime(plugin_dir_path(__FILE__) . '/client/build/block.js'),
      true
    );


    return ['xelis_payment_method_require', 'xelis_payment_method'];
  }

  public function get_payment_method_script_handles_for_admin()
  {
    return $this->get_payment_method_script_handles();
  }

  public function get_payment_method_data()
  {
    // $xelis_state = new Xelis_Payment_State();
    // $state = $xelis_state->get_payment_state();

    return [
      'title' => $this->get_setting('title'),
      'description' => $this->get_setting('description'),
      'network' => $this->get_setting('network'),
      'supports' => $this->get_supported_features(),
      // 'payment_state' => $state
    ];
  }
}