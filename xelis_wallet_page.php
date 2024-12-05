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
  $errors = [];
  $xelis_wallet = new Xelis_Wallet();
  $xelis_gateway = Xelis_Payment_Gateway::get_instance();

  $filter_address = null;
  $accept_incoming = true;
  $accept_outgoing = true;
  $accept_coinbase = true;
  $accept_burn = true;
  $filter_type = 'all';
  $filter_txid = null;
  $filter_type = null;

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["send_funds"])) {
      $amount = $_POST['amount'];
      $asset = $_POST['asset'];
      $destination = $_POST['destination'];

      try {
        $amount_atomic = $xelis_wallet->unshift_xel($amount);
        $tx = $xelis_wallet->send_funds($amount_atomic, $asset, $destination);
        $success_msg = $amount . " sent to " . $destination . " - " . $tx->hash;
      } catch (Exception $e) {
        $err_msg = $e->getMessage();
      }
    }

    if (isset($_POST["filter_transactions"])) {
      $filter_address = $_POST["address"];
      $filter_txid = $_POST["txid"];
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
        $success_msg = "Rescan started";
      } catch (Exception $e) {
        $errors[] = "Rescan: " . $e->getMessage();
      }
    }

    if (isset($_POST["reconnect"])) {
      try {
        $xelis_wallet->set_online_mode($xelis_gateway->node_endpoint);
        $success_msg = "Wallet is now online";
        sleep(1);
      } catch (Exception $e) {
        $errors[] = "Reconnect endpoint: " . $e->getMessage();
      }
    }

    if (isset($_POST["start"])) {
      try {
        $xelis_wallet->start_wallet();
        $success_msg = "Wallet started";
        sleep(1);
      } catch (Exception $e) {
        $errors[] = "Start wallet: " . $e->getMessage();
      }
    }

    if (isset($_POST["stop"])) {
      try {
        $xelis_wallet->close_wallet();
        $success_msg = "Wallet is closed";
        sleep(1);
      } catch (Exception $e) {
        $errors[] = "Stop wallet: " . $e->getMessage();
      }
    }
  }

  try {
    if ($filter_txid) {
      $tx = $xelis_wallet->get_transaction($filter_txid);
      if ($tx) {
        $txs = [$tx];
      }
    } else {
      $txs = $xelis_wallet->get_transactions(
        0,
        $accept_incoming,
        $accept_outgoing,
        $accept_coinbase,
        $accept_burn,
        $filter_address
      );
    }
  } catch (Exception $e) {
    $errors[] = "Get transactions: " . $e->getMessage();
  }

  try {
    $logs = $xelis_wallet->get_output();
  } catch (Exception $e) {
    $errors[] = "Get output: " . $e->getMessage();
  }

  try {
    $status = $xelis_wallet->get_status();
    $is_running = $xelis_wallet->is_process_running();
    $is_online = $xelis_wallet->is_online();

    try {
      $balance_atomic = $xelis_wallet->get_balance();
      $balance = $xelis_wallet->shift_xel($balance_atomic);
    } catch (Exception $e) {
      $errors[] = "Get balance: " . $e->getMessage();
    }

    try {
      $addr = $xelis_wallet->get_address();
    } catch (Exception $e) {
      $errors[] = "Get address: " . $e->getMessage();
    }
  } catch (Exception $e) {
    $errors[] = "Get status: " . $e->getMessage();
  }

  ?>
  <style>
    h2 {
      font-weight: normal;
      font-size: 1.5rem;
    }

    h2:after {
      display: block;
      content: '';
      width: 100%;
      height: .1rem;
      background-color: #cfcfcf;
      margin-top: 1rem;
    }

    .xelis-wallet-body {
      margin: 2rem 1rem;
    }

    .xelis-wallet-overview {
      background-color: white;
      padding: 1rem;
      display: flex;
      gap: 2rem;
      justify-content: space-between;
      flex-wrap: wrap;
      border: .1rem solid #cfcfcf;
    }

    .xelis-wallet-overview>div> :nth-child(1) {
      font-size: 1.1rem;
      margin-bottom: .5rem;
      font-weight: 500;
    }

    .xelis-wallet-overview>div> :nth-child(2) {
      font-size: 1rem;
      word-break: break-all;
    }

    .xelis-wallet-addr {
      background-color: white;
      border: .1rem solid #cfcfcf;
      padding: 1rem;
      font-size: 1.2rem;
      word-break: break-all;
    }

    .xelis-wallet-balance {
      background-color: white;
      border: .1rem solid #cfcfcf;
      padding: 1rem;
      font-size: 1.5rem;
    }

    .xelis-wallet-send-funds {
      display: flex;
      gap: .5rem;
      align-items: center;
      background: white;
      padding: 1rem;
      flex-wrap: wrap;
      border: .1rem solid #cfcfcf;
    }

    .xelis-wallet-send-funds label {
      font-size: 1rem;
    }

    .xelis-wallet-filter {
      background: white;
      border: .1rem solid #cfcfcf;
      padding: 1rem;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
    }

    .xelis-wallet-filter> :nth-child(1) {
      display: flex;
      align-items: center;
      gap: .5rem;
      flex-wrap: wrap;
    }

    table,
    td,
    th {
      border: .1rem solid #cfcfcf;
      padding: .5rem;
    }

    table th {
      text-align: left;
    }

    table {
      width: 100%;
      background: white;
      border-collapse: collapse;
    }

    .overflow-table {
      overflow: auto;
      width: 100%;
    }

    .errors {
      display: flex;
      gap: .5rem;
      flex-direction: column;
    }

    .errors div {
      color: red;
      padding: .5rem;
      font-size: 1rem;
      border: .1rem solid red;
    }

    .success {
      color: green;
      padding: .5rem;
      font-size: 1rem;
      border: .1rem solid green;
      margin-bottom: .5rem;
    }
  </style>
  <div class="xelis-wallet-body">
    <h1><?php esc_html_e('XELIS Wallet', 'xelis_payment'); ?></h1>
    <?php
    if (isset($success_msg)) {
      echo '<div class="success">';
      echo esc_html($success_msg);
      echo '</div>';
    }
    ?>
    <?php
    echo '<div class="errors">';
    foreach ($errors as $err) {
      echo '<div>' . esc_html($err) . '</div>';
    }
    echo '</div>';
    ?>
    <h2>Overview</h2>
    <div class="xelis-wallet-overview">
      <div>
        <div>Status</div>
        <div><?php echo $status ?></div>
      </div>
      <div>
        <div>Enabled</div>
        <div><?php echo $xelis_gateway->enabled ?></div>
      </div>
      <div>
        <div>Network</div>
        <div><?php echo $xelis_gateway->network ?></div>
      </div>
      <div>
        <div>Node endpoint</div>
        <div><?php echo $xelis_gateway->node_endpoint ?></div>
      </div>
      <div>
        <div>Redirect funds</div>
        <div><?php echo $xelis_gateway->wallet_addr ? $xelis_gateway->wallet_addr : 'not set' ?></div>
      </div>
    </div>
    <?php if ($is_running && !$is_online): ?>
      <br>
      <form method="post" action="">
        <span>Wallet is offline: </span>
        <input type="submit" name="reconnect" value="Reconnect">
      </form>
    <?php endif; ?>
    <?php if (!$is_running): ?>
      <br>
      <form method="post" action="">
        <span>Wallet is not running: </span>
        <input type="submit" name="start" value="Start">
      </form>
    <?php endif; ?>
    <?php if ($is_running): ?>
      <br>
      <form method="post" action="">
        <span>Wallet is running: </span>
        <input type="submit" name="stop" value="Stop">
      </form>
    <?php endif; ?>
    <br>
    <div>
      <a href="/wp-admin/admin.php?page=wc-settings&tab=checkout&section=xelis_payment">Go to XELIS Payment settings</a>
    </div>
    <h2>Address</h2>
    <div class="xelis-wallet-addr"><?php echo $addr ?></div>
    <h2>Balance</h2>
    <div class="xelis-wallet-balance"><?php echo $balance ?> XEL</div>
    <h2>Send funds</h2>
    <form method="post" action="" class="xelis-wallet-send-funds">
      <div>
        <label for="amount">Amount:</label>
        <input type="text" id="amount" name="amount" required>
      </div>
      <div>
        <label for="asset">Asset:</label>
        <input type="text" id="asset" value="<?php echo Xelis_Wallet::$XELIS_ASSET ?>" name="asset" required>
      </div>
      <div>
        <label for="destination">Destination:</label>
        <input type="text" id="destination" name="destination" required>
      </div>
      <input type="submit" name="send_funds" value="Submit">
    </form>
    <h2>Transactions</h2>
    <div class="xelis-wallet-filter">
      <form method="post" action="">
        <input type="text" name="txid" value="<?php echo $filter_txid ?>" placeholder="Filter by txid" />
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
      <div class="overflow-table">
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
                    <div>
                      <?php echo $idx + 1 ?>.
                      Amount: <?php echo esc_html($transfer->amount); ?>
                      From: <?php echo esc_html($tx->incoming->from); ?>
                      Extra Data: <?php echo esc_html($transfer->extra_data); ?>
                      Asset: <?php echo esc_html($transfer->asset); ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            <?php if (isset($tx->outgoing)): ?>
              <?php foreach ($tx->outgoing->transfers as $idx => $transfer): ?>
                <tr>
                  <td colspan="4">
                    <div>
                      <?php echo $idx + 1 ?>.
                      Amount: <?php echo esc_html($transfer->amount); ?>
                      Destination: <?php echo esc_html($transfer->destination); ?>
                      Extra Data: <?php echo esc_html($transfer->extra_data); ?>
                      Asset: <?php echo esc_html($transfer->asset); ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          <?php endforeach; ?>
          <!-- TODO: display for coinbase and burn -->
        </table>
      </div>
    <?php else: ?>
      <p>No transactions.</p>
    <?php endif; ?>
    <h2>Logs</h2>
    <?php
    echo '<textarea style="width: 100%;" rows="10">';
    echo htmlspecialchars($logs);
    echo ' </textarea>';
    ?>
  </div>
  <?php
}
