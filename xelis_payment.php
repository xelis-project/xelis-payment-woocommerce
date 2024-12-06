<?php

require_once __DIR__ . '/xelis_rest.php';
require_once __DIR__ . '/xelis_package.php';
require_once __DIR__ . '/xelis_node.php';
require_once __DIR__ . '/xelis_wallet.php';
require_once __DIR__ . '/xelis_data.php';
require_once __DIR__ . '/xelis_payment_state.php';
require_once __DIR__ . '/xelis_wallet_page.php';

/**
 * Plugin Name: XELIS Payment
 * Description: A XELIS payment for WooCommerce.
 * Version: 0.2.4
 * Author: g45t345rt
 */

// don't execute this script if it's not WordPress
if (!defined(constant_name: 'ABSPATH')) {
  exit;
}

function load_style() {
  wp_register_style('xelis_payment_style', plugins_url('/client/style.css', __FILE__));

  wp_enqueue_style(
    'xelis_payment_style',
    plugins_url('/client/style.css', __FILE__),
    [],
    filemtime(plugin_dir_path(__FILE__) . '/client/style.css'),
  );
}

add_action('wp_enqueue_scripts', 'load_style', 999);

function echo_err(string $message)
{
  add_action('admin_notices', function () use ($message) {
    echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
  });
}

// before anything check if woocommerce plugin is installed
if (!class_exists('WooCommerce')) {
  //add_action('admin_notices', 'missing_woocommerce_err_notice');
  echo_err(sprintf(esc_html__('XELIS Payment requires WooCommerce to be installed and active. You can download %s here.', 'xelis_payment'), '<a href="https://woo.com/" target="_blank">WooCommerce</a>'));
  return;
}

require_once __DIR__ . '/xelis_payment_gateway.php';
require_once __DIR__ . '/xelis_payment_method.php';

function xelis_payment_activation() {
  // on activation make sure wallet is installed and running
  $xelis_wallet = new Xelis_Wallet();
  if (!$xelis_wallet->is_process_running()) {
    if (!$xelis_wallet->has_executable()) {
        $xelis_package = new Xelis_Package();
        $xelis_package->install_package();
    }

    $xelis_wallet->set_wallet_executable();
    $xelis_wallet->start_wallet();
  }
}

register_activation_hook(__FILE__, 'xelis_payment_activation');

function xelis_payment_deactivation() {
  // the store owner should deactivate the plugin after disabling it for 30m
  // avoid pending payments issues
  try {
    $xelis_wallet = new Xelis_Wallet();
    $xelis_wallet->close_wallet();
  } catch (Exception $e) {
    echo_err($e->getMessage());
    return;
  }
}

register_deactivation_hook(__FILE__, 'xelis_payment_deactivation');

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