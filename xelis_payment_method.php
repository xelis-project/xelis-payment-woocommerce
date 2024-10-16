<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Xelis_Payment_Method extends AbstractPaymentMethodType
{
  protected $name = 'xelis_payment';

  public function initialize()
  {
    $gateway = new Xelis_Payment_Gateway();
    $this->settings = $gateway->get_settings();
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
      plugins_url('/block/require.js', __FILE__),
      [],
      filemtime(plugin_dir_path(__FILE__) . '/block/require.js'),
      true
    );

    wp_register_script(
      'xelis_payment_method',
      plugins_url('/block/build/index.js', __FILE__),
      [],
      filemtime(plugin_dir_path(__FILE__) . '/block/build/index.js'),
      true
    );

    wp_enqueue_style(
      'xelis_payment_style',
      plugins_url('/block/style.css', __FILE__),
      [],
      filemtime(plugin_dir_path(__FILE__) . '/block/style.css'),
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