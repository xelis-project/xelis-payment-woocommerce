<?php

class Xelis_Wallet
{
  public static string $XELIS_ASSET = "0000000000000000000000000000000000000000000000000000000000000000";

  public function get_address($integrated_data = null)
  {
    if ($integrated_data) {
      return $this->fetch("get_address", [
        "integrated_data" => $integrated_data,
      ]);
    }

    return $this->fetch("get_address");
  }

  public function get_topoheight()
  {
    return $this->fetch("get_topoheight");
  }

  public function get_network()
  {
    return $this->fetch("get_network");
  }

  public function get_version()
  {
    return $this->fetch("get_version");
  }

  public function get_transaction(string $tx_id)
  {
    return $this->fetch("get_transaction", ["hash" => $tx_id]);
  }

  public function get_status(): string
  {
    try {
      $online = $this->is_online();
      $status = $online ? 'Online' : 'Offline';
      $network = $this->get_network();
      $status = $status . " (" . $network . ")";
      $version = $this->get_version();
      $status = $status . " - v" . $version . "";
      return $status;
    } catch (Exception $e) {
      return $e->getMessage();
    }
  }

  public function get_transactions(int $min_topoheight, bool $accept_incoming, bool $accept_outgoing, bool $accept_coinbase, bool $accept_burn, string $address = null)
  {
    $params = [
      "min_topoheight" => $min_topoheight,
      "accept_incoming" => $accept_incoming,
      "accept_outgoing" => $accept_outgoing,
      "accept_coinbase" => $accept_coinbase,
      "accept_burn" => $accept_burn,
    ];

    if ($address) {
      $params["address"] = $address;
    }

    return $this->fetch("list_transactions", $params);
  }

  public function redirect_xelis_funds(int $amount, string $destination)
  {
    $fee = $this->estimate_fees($amount, Xelis_Wallet::$XELIS_ASSET, $destination);
    $amount -= $fee;
    return $this->send_funds($amount, Xelis_Wallet::$XELIS_ASSET, $destination, $fee);
  }

  public function send_funds(int $amount, string $asset, string $destination, int $fee = null)
  {
    $transfers = [
      array(
        "amount" => $amount,
        "asset" => $asset,
        "destination" => $destination,
      )
    ];

    $params = [
      "broadcast" => true,
      "transfers" => $transfers
    ];

    if ($fee) {
      $params["fee"] = array("value" => $fee);
    }

    return $this->fetch("build_transaction", $params);
  }

  public function estimate_fees(int $amount, string $asset, string $destination)
  {
    $transfers = [
      array(
        "amount" => $amount,
        "asset" => $asset,
        "destination" => $destination,
      )
    ];

    return $this->fetch("estimate_fees", [
      "transfers" => $transfers
    ]);
  }

  public function get_balance(string $asset = null)
  {
    $params = [];
    if ($asset) {
      $params = ["hash" => $asset];
    }

    return $this->fetch("get_balance", $params);
  }

  public function is_online()
  {
    return $this->fetch("is_online");
  }

  public function rescan()
  {
    return $this->fetch("rescan");
  }

  public function set_online_mode(string $endpoint)
  {
    return $this->fetch("set_online_mode", [
      "daemon_address" => $endpoint,
      "auto_reconnect" => true,
    ]);
  }

  public function set_offline_mode()
  {
    return $this->fetch("set_offline_mode");
  }

  public function fetch(string $method, array $params = null)
  {
    $endpoint = 'http://localhost:8081/json_rpc';

    $request = [
      'id' => 1,
      'jsonrpc' => '2.0',
      'method' => $method,
    ];

    if ($params) {
      $request['params'] = $params;
    }

    $jsonRequest = json_encode($request);
    $ch = curl_init($endpoint);
    $basic_token = "admin:admin";

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Authorization: Basic ' . base64_encode($basic_token),
      'Content-Type: application/json',
      'Content-Length: ' . strlen($jsonRequest)
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonRequest);
    curl_setopt($ch, CURLOPT_FAILONERROR, true); // error out if 404 or other

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
      throw new Exception(curl_error($ch));
      //echo 'cURL Error: ' . curl_error($ch);
    }

    curl_close($ch);

    //$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $data = json_decode($response);
    if (isset($data->result)) {
      return $data->result;
    }

    if (isset($data->error)) {
      throw new Exception($data->error->message);
    }

    return $data;
  }

  public function get_wallet_pid(): int|null
  {
    // TODO: windows
    $output = [];
    exec("pgrep -f xelis_wallet", $output);
    if (empty($output)) {
      return null;
    }

    return $output[0];
  }

  public function shift_xel(int $amount)
  {
    return $amount / 100000000.0;
  }

  public function unshift_xel(float $amount)
  {
    return (int) ($amount * 100000000.0);
  }

  public function is_running(): bool
  {
    $pid = $this->get_wallet_pid();
    if ($pid) {
      // TODO: windows
      return file_exists('/proc/' . $pid);
    }

    return false;
  }

  public function close_wallet(): bool
  {
    $pid = $this->get_wallet_pid();
    if ($pid) {
      // TODO: windows
      $success = posix_kill($pid, 15); // 15 is SIGTERM
      if (!$success) {
        throw new Exception(posix_get_last_error());
      }

      //$this->del_wallet_pid();
      return true;
    }

    return false;
  }

  public function get_output()
  {
    $wallet_log_file = __DIR__ . '/wallet_output.log';
    if (!file_exists($wallet_log_file)) {
      return [];
    }

    $lines = file($wallet_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    for ($i = 0; $i < count($lines); $i++) {
      $lines[$i] = trim($lines[$i]);
    }

    return $lines;
  }

  public function get_last_output()
  {
    $wallet_log_file = __DIR__ . '/wallet_output.log';
    if (!file_exists($wallet_log_file)) {
      return "";
    }

    $file = new SplFileObject($wallet_log_file);
    $file->seek(PHP_INT_MAX);
    $file->seek(max($file->key() - 1, 0));
    $last_line = $file->current();
    return trim($last_line);
  }

  public function has_executable()
  {
    $xelis_wallet = __DIR__ . '/xelis_pkg/xelis_wallet';
    return file_exists($xelis_wallet);
  }

  public function start_wallet()
  {
    $xelis_wallet_file = __DIR__ . '/xelis_pkg/xelis_wallet';
    $gateway = new Xelis_Payment_Gateway();

    // this is local only we don't care if we set password in clear and as admin
    $xelis_wallet_cmd = $xelis_wallet_file
      . " --network " . $gateway->network
      . " --daemon-address " . $gateway->node_endpoint
      . " --wallet-path " . __DIR__ . "/wallet/" . $gateway->network
      . " --password admin "
      . " --precomputed-tables-path " . __DIR__ . "/precomputed_tables/"
      . " --rpc-bind-address 127.0.0.1:8081 "
      . " --rpc-username admin "
      . " --rpc-password admin "
      . " --disable-log-color"
      . " --disable-interactive-mode";
    //. " --force-stable-balance ";

    // https://stackoverflow.com/questions/3819398/php-exec-command-or-similar-to-not-wait-for-result
    // TODO: windows

    $wallet_output = __DIR__ . "/wallet_output.log";

    exec('bash -c "exec nohup setsid ' . $xelis_wallet_cmd . ' > ' . $wallet_output . ' 2>&1 &"');
    return;
  }
}