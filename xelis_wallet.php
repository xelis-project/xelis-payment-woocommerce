<?php

class Xelis_Wallet
{
  public static string $XELIS_ASSET = "0000000000000000000000000000000000000000000000000000000000000000";

  private $wallet_path = __DIR__ . '/xelis_pkg/xelis_wallet';
  private $wallet_pid_path = __DIR__ . '/xelis_pkg/pid';
  private $wallet_output_path = __DIR__ . "/wallet_output.log";

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
      $running = $this->is_process_running();
      if (!$running) {
        return "Not running";
      }

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
    $gateway = Xelis_Payment_Gateway::get_instance();
    $endpoint = 'http://localhost:' . $gateway->wallet_local_port . '/json_rpc';

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

    //curl_setopt($ch, CURLOPT_TIMEOUT, 5);
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

  /*
  // old function to get the wallet pid
  // using an alternative because pgrep can return an empty list on VPS (restrictive access)
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
  */

  public function get_wallet_pid(): int|null
  {
    if (file_exists($this->wallet_pid_path)) {
      $pid = file_get_contents($this->wallet_pid_path);
      if (!$pid) {
        return null;
      }

      return intval($pid);
    }

    return null;
  }

  public function shift_xel(int $amount)
  {
    return $amount / 100000000.0;
  }

  public function unshift_xel(float $amount)
  {
    return (int) ($amount * 100000000.0);
  }

  /*
  // old function to check if wallet is running
  // you might have restrictive access to /proc on VPS so will use something else to check
  public function is_running(): bool
  {
    $pid = $this->get_wallet_pid();
    if ($pid) {
      // TODO: windows
      return file_exists('/proc/' . $pid);
    }

    return false;
  }
  */

  public function is_process_running(): bool
  {
    $pid = $this->get_wallet_pid();
    if ($pid) {
      // TODO: windows
      $output = null;
      $code = null;
      // use kill -0 instead of preg has it may be restrictive on VPS env
      exec("kill -0 " . $pid, $output, $code);
      $is_running = $code === 0;
      if (!$is_running) {
        unlink($this->wallet_pid_path);
      }

      return $is_running;
    }

    return false;
  }

  public function restart_wallet() {
    if ($this->is_process_running()) {
      $this->close_wallet();
      sleep(2); // wait for process to free stuff or we might get port already in use
      $this->start_wallet();
    }
  }

  public function close_wallet(int $signal = 15)
  {
    $pid = $this->get_wallet_pid();
    if ($pid) {
      // TODO: windows
      $success = posix_kill($pid, $signal); // 15 is SIGTERM
      if (!$success) {
        // don't unlink PID file in case posix_kill return success even if it doesn't kill the app
        // we don't want to loose the PID so lets use kill -0 to check if running - check is_process_running func
        throw new Exception(posix_get_last_error());
      }
    } else {
      throw new Exception("No PID to close wallet.");
    }
  }

  public function get_output(int $max_read = 100)
  {
    if (!file_exists($this->wallet_output_path)) {
      return '';
    }

    $handle = fopen($this->wallet_output_path, 'r');
    if (!$handle) {
      return '';
    }

    fseek($handle, offset: 0, whence: SEEK_END);
    $buffer = '';
    $read_size = 1024;
    $read_count = 0;

    while (ftell($handle) > 0) {
      $pos = ftell($handle);
      $offset = $pos - $read_size;
      if ($offset < 0) {
        $offset = 0;
      }

      fseek($handle, $offset);
      $chunk = fread($handle, $read_size);
      $buffer = $chunk . $buffer;
      fseek($handle, $offset);
      $read_count++;

      if ($read_count > $max_read) {
        break;
      }
    }

    fclose($handle);
    return $buffer;
  }

  public function has_executable()
  {
    return file_exists($this->wallet_path);
  }

  public function set_wallet_executable()
  {
    if (!is_executable($this->wallet_path)) {
      // no effect on windows because it doesn't use permission system
      chmod($this->wallet_path, permissions: 755);
    }
  }

  public function is_port_in_use($port)
  {
    $socket = fsockopen("127.0.0.1", $port, $errno, $errstr, 1);

    if (is_resource($socket)) {
      fclose($socket);
      return true;
    }

    return false;
  }

  public function start_wallet()
  {
    $gateway = Xelis_Payment_Gateway::get_instance();
    $port = $gateway->wallet_local_port;
    if ($this->is_port_in_use($port)) {
      throw new Exception("Port " . $port . " already in use.");
    }

    // this is local only we don't care if we set password in clear and is admin/admin
    $xelis_wallet_cmd = $this->wallet_path
      . " --network " . $gateway->network
      . " --daemon-address " . $gateway->node_endpoint
      . " --wallet-path " . __DIR__ . "/wallet/" . $gateway->network
      . " --password admin "
      . " --precomputed-tables-path " . __DIR__ . "/precomputed_tables/"
      . " --rpc-bind-address 127.0.0.1:" . $gateway->wallet_local_port
      . " --rpc-username admin "
      . " --rpc-password admin "
      . " --disable-log-color "
      . " --disable-interactive-mode "
      . " --rpc-threads " . $gateway->wallet_threads
      . " --log-level " . $gateway->wallet_log_level
      . " --precomputed-tables-l1 " . $gateway->precomputed_tables_size;
    //. " --force-stable-balance ";

    // https://stackoverflow.com/questions/3819398/php-exec-command-or-similar-to-not-wait-for-result
    // TODO: windows

    // TOKIO_WORKER_THREADS -> set the tokio env var for max nbr of threads
    // nohup -> keeps process running after shell session terminates
    // setsid -> don't tie process to current shell session
    // 2>&1 -> redirects stderr to stdout
    // echo \$1 -> output the process pid

    $pid = shell_exec('bash -c "TOKIO_WORKER_THREADS=' . $gateway->wallet_threads . ' exec nohup setsid ' . $xelis_wallet_cmd . ' > ' . $this->wallet_output_path . ' 2>&1 & echo \$!"');
    file_put_contents($this->wallet_pid_path, $pid);
    return;
  }
}