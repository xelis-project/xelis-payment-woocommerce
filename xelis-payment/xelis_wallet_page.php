<?php

if (!defined(constant_name: 'ABSPATH')) {
  exit;
}

add_action('admin_menu', 'xelis_wallet_menu');

function xelis_wallet_menu()
{
  add_submenu_page(
    'woocommerce',              // Parent slug
    'XELIS Wallet',     // Page title
    'XELIS Wallet',     // Menu title
    'manage_options',           // Capability
    'xelis-wallet',     // Menu slug
    'render_page' // Callback function
  );
}

function render_page()
{
  $xelis_wallet = new Xelis_Wallet();
  $balance_atomic = $xelis_wallet->get_balance();
  $balance = $xelis_wallet->shift_xel($balance_atomic);
  $logs = $xelis_wallet->get_output();

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $amount = $_POST['amount'];
    $asset = $_POST['asset'];
    $destination = $_POST['destination'];

    try {
      $amount_atomic = $xelis_wallet->unshift_xel($amount);
      $tx = $xelis_wallet->send_funds($amount_atomic, $asset, $destination);
      $send_funds_msg = $amount . " sent to " . $destination . " - " . $tx->hash;
    } catch (Exception $e) {
      $send_funds_err_msg = $e->getMessage();
    }
  }

  try {
    $txs = $xelis_wallet->get_transactions(
      0,
      true,
      true,
      true,
      true
    );
  } catch (Exception $e) {
  }

  ?>
  <div class="wrap">
    <h1><?php esc_html_e('XELIS Wallet', 'xelis_payment'); ?></h1>
    <h2>Balance</h2>
    <div style="font-weight: bold; font-size: 1.5rem;"><?php echo $balance ?> XEL</div>
    <h2>Send funds</h2>
    <form method="post" action="">
      <label for="amount">Amount:</label><br>
      <input type="number" id="amount" name="amount" required>
      <br>
      <label for="asset">Asset:</label><br>
      <input type="text" id="asset" value="<?php echo Xelis_Wallet::$XELIS_ASSET ?>" name="asset" required>
      <br>
      <label for="destination">Destination:</label><br>
      <input type="text" id="destination" name="destination" required>
      <br><br>
      <input type="submit" value="Submit">
    </form>
    <?php
    if (isset($send_funds_msg)) {
      echo '<div style="color: green;">';
      echo esc_html($send_funds_msg);
      echo '</div>';
    }
    ?>
    <?php
    if (isset($send_funds_err_msg)) {
      echo '<div style="color: red;">';
      echo esc_html($send_funds_err_msg);
      echo '</div>';
    }
    ?>
    <h2>Transactions</h2>
    <?php if (!empty($txs)): ?>
      <table>
        <tr>
          <th>Tx ID</th>
          <th>Type</th>
          <th>Transfers</th>
        </tr>
        <?php foreach ($txs as $tx): ?>
          <tr>
            <td><?php echo esc_html($tx->hash); ?></td>
            <?php if (isset($tx->incoming)): ?>
              <td>incoming</td>
              <td>
                <?php echo count($tx->incoming->transfers) ?>
              </td>
            <?php endif; ?>
            <?php if (isset($tx->outgoing)): ?>
              <td>outgoing</td>
              <td>
                <?php echo count($tx->outgoing->transfers) ?>
              </td>
            <?php endif; ?>
          </tr>
          <?php if (isset($tx->incoming)): ?>
            <?php foreach ($tx->incoming->transfers as $transfer): ?>
              <tr>
                <td colspan="3">
                  <?php echo esc_html($transfer->amount); ?>
                  <?php echo esc_html($tx->incoming->from); ?>
                  <?php echo esc_html($transfer->extra_data); ?>
                  <?php echo esc_html($transfer->asset); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if (isset($tx->outgoing)): ?>
            <?php foreach ($tx->outgoing->transfers as $transfer): ?>
              <tr>
                <td colspan="3">
                  <?php echo esc_html($transfer->amount); ?>
                  <?php echo esc_html($transfer->destination); ?>
                  <?php echo esc_html($transfer->extra_data); ?>
                  <?php echo esc_html($transfer->asset); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p>No transactions.</p>
    <?php endif; ?>
    <h2>Logs</h2>
    <textarea style="width: 100%;" rows="10">
          <?php
          foreach ($logs as $line) {
            echo htmlspecialchars($line) . "\n";
          }
          ?>
        </textarea>
  </div>
  <?php
}
