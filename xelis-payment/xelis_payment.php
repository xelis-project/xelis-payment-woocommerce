<?php

require __DIR__ . '/xelis_package.php';
require __DIR__ . '/xelis_wallet.php';
require __DIR__ . '/xelis_payment_gateway.php';
require __DIR__ . '/xelis_payment_method.php';

/**
 * Plugin Name: XELIS Payment Gateway
 * Description: A XELIS payment gateway for WooCommerce.
 * Version: 0.1.0
 * Author: g45t345rt
 */

// don't execute this script if it's not WordPress
if (!defined(constant_name: 'ABSPATH')) {
  exit;
}

function missing_woocommerce_notice()
{
  echo '<div class="error"><p><strong>' . sprintf(esc_html__('XELIS Payment requires WooCommerce to be installed and active. You can download %s here.', 'xelis_payment'), '<a href="https://woo.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
}

function check_dependencies(): bool
{
  if (!class_exists('WooCommerce')) {
    add_action('admin_notices', 'missing_woocommerce_notice');
    return false;
  }

  return true;
}

function load_xelis_plugin()
{
  check_dependencies();
}

add_action('plugins_loaded', 'load_xelis_plugin');

function activate_xelis_plugin()
{
  if (!check_dependencies()) {
    return;
  }

  $xelis_package = new Xelis_Package();
  $xelis_package->install_package();
}

register_activation_hook(__FILE__, 'activate_xelis_plugin');

$xelis_wallet = new Xelis_Wallet();
$xelis_wallet->start_wallet();

$xelis_wallet->get_address("testing");

// add the gateway to WooCommerce payment method
function add_xelis_payment_gateway($gateways)
{
  $gateways[] = 'Xelis_Payment_Gateway';
  return $gateways;
}

add_filter('woocommerce_payment_gateways', 'add_xelis_payment_gateway');

// add woocommerce blocks
add_action(
  'woocommerce_blocks_payment_method_type_registration',
  function ($payment_method_registry) {
    $payment_method_registry->register(new Xelis_Payment_Method());
  }
);


/*
function block_assets()
{
  wp_enqueue_script(
    'xelis-payment-block-js',
    plugins_url('block.js', __FILE__),
    array('wp-blocks', 'wp-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wc-components'), // dependencies
    filemtime(plugin_dir_path(__FILE__) . 'block.js'),
    true
  );

  wp_enqueue_style(
    'xelis-payment-block-css',
    plugins_url('style.css', __FILE__),
    //array('wp-edit-blocks')
  );
}

add_action('enqueue_block_assets', 'block_assets');
*/
