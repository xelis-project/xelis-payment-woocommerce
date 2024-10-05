<?php

class Xelis_Wallet
{
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

  public function get_transaction(string $tx_id)
  {
    return $this->fetch("get_transaction", ["hash" => $tx_id]);
  }

  public function get_incoming_transactions(int $start_topo)
  {
    return $this->fetch("list_transactions", [
      "min_topoheight" => $start_topo,
      "accept_incoming" => true,
    ]);
  }

  public function send_transaction(int $amount, string $destination)
  {
    return $this->fetch("build_transaction", [
      "broadcast" => true,
      "transfers" => [
        "amount" => $amount,
        "asset" => "",
        "destination" => $destination,
      ]
    ]);
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

    return $data;
  }

  public function get_wallet_pid(): int|null
  {
    $pid_file = __DIR__ . '/wallet_pid.txt';
    if (file_exists($pid_file)) {
      $value = file($pid_file);
      if (!$value) {
        return null;
      }

      return $value[0];
    }

    return null;
  }

  public function store_wallet_pid($pid): bool|int
  {
    return file_put_contents(__DIR__ . '/wallet_pid.txt', $pid);
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
      // TODO: window
      return file_exists('/proc/' . $pid);
    }

    return false;
  }

  public function close_wallet(): bool
  {
    $pid = $this->get_wallet_pid();
    if ($pid) {
      // TODO: window
      return posix_kill($pid, SIGTERM);
    }

    return false;
  }

  public function start_wallet()
  {
    $xelis_wallet = __DIR__ . '/xelis_pkg/xelis_wallet';
    if (!file_exists($xelis_wallet)) {
      throw new Exception('xelis_wallet does not exists');
    }

    $output = [];
    $gateway = new Xelis_Payment_Gateway();

    // this is local only we don't care if we set password in clear and as admin
    $xelis_wallet_cmd = $xelis_wallet
      . " --daemon-address " . $gateway->node_endpoint
      . " --wallet-path /wallet"
      . " --password admin "
      . " --precomputed-tables-path " . __DIR__ . "/precomputed_tables/"
      . " --rpc-bind-address 127.0.0.1:8081 "
      . " --rpc-username admin "
      . " --rpc-password admin "
      . " --force-stable-balance";

    $descriptorspec = [
      0 => ["pipe", "r"], // stdin
      1 => ["pipe", "w"], // stdout
      2 => ["pipe", "w"]  // stderr
    ];

    $process = proc_open($xelis_wallet_cmd, $descriptorspec, $pipes);
    $process_info = proc_get_status($process);
    $pid = $process_info["pid"];

    if ($this->store_wallet_pid($pid) === false) {
      throw new Exception("can't store xelis wallet process id");
    }

    if (is_resource($process)) {
      // Close the stdin pipe since we don't need to send anything
      fclose($pipes[0]);

      // Read the output from stdout
      $output = stream_get_contents($pipes[1]);
      fclose($pipes[1]);

      // Read the output from stderr (if any)
      $errorOutput = stream_get_contents($pipes[2]);
      fclose($pipes[2]);

      // Close the process
      $return_value = proc_close($process);

      // Print the outputs
      echo "Output:\n$output\n";
      if ($errorOutput) {
        echo "Error Output:\n$errorOutput\n";
      }
      echo "Return Value: $return_value\n";
    } else {

    }
  }
}