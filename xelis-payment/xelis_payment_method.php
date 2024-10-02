<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Xelis_Payment_Method extends AbstractPaymentMethodType
{
  protected $name = 'xelis_payment';

  public function initialize()
  {
    $this->settings = get_option('woocommerce_xelis_payment_settings', []);
  }

  public function is_active()
  {
    return filter_var($this->get_setting('enabled', false), FILTER_VALIDATE_BOOLEAN);
  }

  public function get_payment_method_script_handles()
  {
    wp_register_script(
      'payment_method',
      plugins_url('payment_method.js', __FILE__),
      [],
      filemtime(plugin_dir_path(__FILE__) . 'payment_method.js'),
      true
    );
    return ['payment_method'];
  }

  public function get_payment_method_script_handles_for_admin()
  {
    return $this->get_payment_method_script_handles();
  }

  public function get_payment_method_data()
  {
    return [
      'title' => $this->get_setting('title'),
      'description' => $this->get_setting('description'),
      'supports' => $this->get_supported_features(),
    ];
  }
}