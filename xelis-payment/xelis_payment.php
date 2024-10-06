<?php

require __DIR__ . '/xelis_payment_state.php';
require __DIR__ . '/xelis_rest.php';
require __DIR__ . '/xelis_package.php';
require __DIR__ . '/xelis_node.php';
require __DIR__ . '/xelis_wallet.php';
require __DIR__ . '/xelis_payment_gateway.php';
require __DIR__ . '/xelis_payment_method.php';
require __DIR__ . '/xelis_data.php';

/**
 * Plugin Name: XELIS Payment
 * Description: A XELIS payment for WooCommerce.
 * Version: 0.1.0
 * Author: g45t345rt
 */

// don't execute this script if it's not WordPress
if (!defined(constant_name: 'ABSPATH')) {
  exit;
}

// before anything check if woocommerce plugin is installed
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

// install xelis binaries on activation
function activate_xelis_plugin()
{
  if (!check_dependencies()) {
    return;
  }

  $xelis_package = new Xelis_Package();
  $xelis_package->install_package();
}

register_activation_hook(__FILE__, 'activate_xelis_plugin');

// make sure wallet is running - start otherwise
function run_wallet() {
  $xelis_wallet = new Xelis_Wallet();
  if (!$xelis_wallet->is_running()) {
    $xelis_wallet->start_wallet();
  }
}

// add the gateway to WooCommerce payment method
function add_xelis_payment_gateway($gateways)
{
  $gateways[] = 'Xelis_Payment_Gateway';
  return $gateways;
}

add_filter('woocommerce_payment_gateways', 'add_xelis_payment_gateway');

// register woocommerce payment blocks
add_action(
  'woocommerce_blocks_payment_method_type_registration',
  function ($payment_method_registry) {
    $payment_method_registry->register(new Xelis_Payment_Method());
  }
);

// register rest routes
$xelis_rest = new Xelis_Rest();

add_action('rest_api_init', [$xelis_rest, 'register_routes']);