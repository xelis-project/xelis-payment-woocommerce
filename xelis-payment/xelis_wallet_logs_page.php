<?php

if (!defined(constant_name: 'ABSPATH')) {
  exit;
}

add_action('admin_menu', 'xelis_wallet_logs_menu');

function xelis_wallet_logs_menu()
{
  add_submenu_page(
    'woocommerce',              // Parent slug
    'XELIS Wallet Logs',     // Page title
    'XELIS Wallet Logs',     // Menu title
    'manage_options',           // Capability
    'xelis-wallet-logs',     // Menu slug
    'render_logs_page' // Callback function
  );
}

function render_logs_page()
{
  ?>
  <div class="wrap">
    <h1><?php esc_html_e('XELIS Wallet Logs', 'xelis_payment'); ?></h1>
    <?php

    $xelis_wallet = new Xelis_Wallet();
    $logs = $xelis_wallet->get_output();

    foreach ($logs as $line) {
      echo '<div>' . htmlspecialchars($line) . '</div>';
    }
    ?>
  </div>
  <?php
}
