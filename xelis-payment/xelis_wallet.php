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

  public function get_incoming_transactions(int $start_topoheight)
  {
    return $this->fetch("list_transactions", [
      "min_topoheight" => $start_topoheight,
      "accept_incoming" => true,
      "accept_outgoing" => false,
      "accept_coinbase" => false,
      "accept_burn" => false
    ]);
  }

  public function redirect_xelis_funds(int $amount, string $destination)
  {
    $test = $this->get_balance();
    error_log($test);

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

  public function get_balance()
  {
    return $this->fetch("get_balance");
  }

  public function is_online()
  {
    return $this->fetch("is_online");
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
      throw new Exception(message: curl_error($ch));
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
    $lines = file(__DIR__ . '/wallet_output.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    for ($i = 0; $i < count($lines); $i++) {
      $lines[$i] = trim($this->remove_ansi_color_codes($lines[$i]));
    }

    return $lines;
  }

  public function get_last_output()
  {
    $file = new SplFileObject(__DIR__ . '/wallet_output.log');
    $file->seek(PHP_INT_MAX);
    $file->seek(max($file->key() - 1, 0));
    $last_line = $file->current();
    return trim($this->remove_ansi_color_codes($last_line));
  }

  public function remove_ansi_color_codes($string)
  {
    $value = preg_replace('/\x1B\[[0-?9;]*m/', '', $string);
    $value = str_replace('[2K', '', $value);
    return $value;
  }

  public function start_wallet()
  {
    $xelis_wallet = __DIR__ . '/xelis_pkg/xelis_wallet';
    if (!file_exists($xelis_wallet)) {
      throw new Exception('xelis_wallet does not exists');
    }

    $gateway = new Xelis_Payment_Gateway();

    // this is local only we don't care if we set password in clear and as admin
    $xelis_wallet_cmd = $xelis_wallet
      . " --network " . $gateway->network
      . " --daemon-address " . $gateway->node_endpoint
      . " --wallet-path " . __DIR__ . "/wallet/" . $gateway->network
      . " --password admin "
      . " --precomputed-tables-path " . __DIR__ . "/precomputed_tables/"
      . " --rpc-bind-address 127.0.0.1:8081 "
      . " --rpc-username admin "
      . " --rpc-password admin ";
      //. " --force-stable-balance ";

    // https://stackoverflow.com/questions/3819398/php-exec-command-or-similar-to-not-wait-for-result
    // TODO: windows

    $wallet_output = __DIR__ . "/wallet_output.log";

    exec('bash -c "exec nohup setsid ' . $xelis_wallet_cmd . ' > ' . $wallet_output . ' 2>&1 &"');
    return;
  }
}