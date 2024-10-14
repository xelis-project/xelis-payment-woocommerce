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
  $status = $xelis_wallet->get_status();
  $is_online = $xelis_wallet->is_online();
  $xelis_gateway = new Xelis_Payment_Gateway();

  $filter_address = null;
  $accept_incoming = true;
  $accept_outgoing = true;
  $accept_coinbase = true;
  $accept_burn = true;
  $filter_type = 'all';

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["send_funds"])) {
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

    if (isset($_POST["filter_transactions"])) {
      $filter_address = $_POST["address"];
      $filter_type = $_POST["type"];

      switch ($filter_type) {
        case "all":
          // do nothing 
          break;
        case "incoming":
          $accept_incoming = true;
          $accept_outgoing = false;
          $accept_coinbase = false;
          $accept_burn = false;
          break;
        case "outgoing":
          $accept_incoming = false;
          $accept_outgoing = true;
          $accept_coinbase = false;
          $accept_burn = false;
          break;
        case "coinbase":
          $accept_incoming = false;
          $accept_outgoing = false;
          $accept_coinbase = true;
          $accept_burn = false;
          break;
        case "burn":
          $accept_incoming = false;
          $accept_outgoing = false;
          $accept_coinbase = false;
          $accept_burn = true;
          break;
      }
    }

    if (isset($_POST["rescan"])) {
      try {
        $xelis_wallet->rescan();
      } catch (Exception $e) {
      }
    }

    if (isset($_POST["reconnect"])) {
      try {
        $xelis_wallet->set_online_mode($xelis_gateway->node_endpoint);
      } catch (Exception $e) {
      }
    }
  }

  try {
    $txs = $xelis_wallet->get_transactions(
      0,
      $accept_incoming,
      $accept_outgoing,
      $accept_coinbase,
      $accept_burn,
      $filter_address
    );
  } catch (Exception $e) {
  }

  ?>
  <style>
    table,
    td,
    th {
      border: .1rem solid;
      padding: .5rem;
    }

    table th {
      text-align: left;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    .config {
      font-size: 1.2rem;
      display: flex;
      flex-direction: column;
      gap: .25rem;
    }
  </style>
  <div class="wrap">
    <h1><?php esc_html_e('XELIS Wallet', 'xelis_payment'); ?></h1>
    <h2>Config</h2>
    <div class="config">
      <div>Status: <?php echo $status ?></div>
      <div>Enabled: <?php echo $xelis_gateway->enabled ?></div>
      <div>Network: <?php echo $xelis_gateway->network ?></div>
      <div>Node endpoint: <?php echo $xelis_gateway->node_endpoint ?></div>
    </div>
    <?php if (!$is_online): ?>
      <br>
      <form method="post" action="">
        <input type="submit" name="reconnect" value="Reconnect">
      </form>
    <?php endif; ?>
    <br>
    <div>
      <a href="/wp-admin/admin.php?page=wc-settings&tab=checkout&section=xelis_payment">Go to XELIS Payment settings</a>
    </div>
    <h2>Balance</h2>
    <div style="font-size: 1.2rem;"><?php echo $balance ?> XEL</div>
    <h2>Send funds</h2>
    <form method="post" action="">
      <label for="amount">Amount:</label><br>
      <input type="text" id="amount" name="amount" required>
      <br>
      <label for="asset">Asset:</label><br>
      <input type="text" id="asset" value="<?php echo Xelis_Wallet::$XELIS_ASSET ?>" name="asset" required>
      <br>
      <label for="destination">Destination:</label><br>
      <input type="text" id="destination" name="destination" required>
      <br><br>
      <input type="submit" name="send_funds" value="Submit">
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
    <div>
      <form method="post" action="">
        <input type="text" name="address" value="<?php echo $filter_address ?>" placeholder="Filter by address" />
        <select name="type">
          <option value="all" <?php if ($filter_type === 'all')
            echo 'selected'; ?>>All types</option>
          <option value="incoming" <?php if ($filter_type === 'incoming')
            echo 'selected'; ?>>Incoming</option>
          <option value="outgoing" <?php if ($filter_type === 'outgoing')
            echo 'selected'; ?>>Outgoing</option>
          <option value="coinbase" <?php if ($filter_type === 'coinbase')
            echo 'selected'; ?>>Coinbase</option>
          <option value="burn" <?php if ($filter_type === 'burn')
            echo 'selected'; ?>>Burn</option>
        </select>
        <input type="submit" name="filter_transactions" value="Filter">
      </form>
      <form method="post" action="">
        <input type="submit" name="rescan" value="Rescan">
      </form>
    </div>
    <?php if (!empty($txs)): ?>
      <table>
        <tr>
          <th>Tx ID</th>
          <th>Topoheight</th>
          <th>Type</th>
          <th>Transfers</th>
        </tr>
        <?php foreach ($txs as $tx): ?>
          <tr>
            <td><?php echo esc_html($tx->hash); ?></td>
            <td><?php echo $tx->topoheight ?></td>
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
            <?php foreach ($tx->incoming->transfers as $idx => $transfer): ?>
              <tr>
                <td colspan="4">
                  <?php echo $idx + 1 ?>.
                  Amount: <?php echo esc_html($transfer->amount); ?>
                  From: <?php echo esc_html($tx->incoming->from); ?>
                  Extra Data: <?php echo esc_html($transfer->extra_data); ?>
                  Asset: <?php echo esc_html($transfer->asset); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if (isset($tx->outgoing)): ?>
            <?php foreach ($tx->outgoing->transfers as $idx => $transfer): ?>
              <tr>
                <td colspan="4">
                  <?php echo $idx + 1 ?>.
                  Amount: <?php echo esc_html($transfer->amount); ?>
                  Destination: <?php echo esc_html($transfer->destination); ?>
                  Extra Data: <?php echo esc_html($transfer->extra_data); ?>
                  Asset: <?php echo esc_html($transfer->asset); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php endforeach; ?>
        <!-- TODO: display for coinbase and burn -->
      </table>
    <?php else: ?>
      <p>No transactions.</p>
    <?php endif; ?>
    <h2>Logs</h2>
    <?php
    echo '<textarea style="width: 100%;" rows="10">';
    foreach ($logs as $line) {
      echo htmlspecialchars($line) . "\n";
    }
    echo ' </textarea>';
    ?>
  </div>
  <?php
}
