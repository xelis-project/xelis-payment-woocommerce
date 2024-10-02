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

  public function get_transaction(string $tx_id)
  {
    return $this->fetch("get_transaction", ["hash" => $tx_id]);
  }

  public function send_funds(int $amount, string $destination)
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

  public function fetch(string $method, array $params = null)
  {
    $url = 'http://localhost:8081/json_rpc';

    $request = [
      'id' => 1,
      'jsonrpc' => '2.0',
      'method' => $method,
    ];

    if ($params) {
      $request['params'] = $params;
    }

    $jsonRequest = json_encode($request);
    $ch = curl_init($url);
    $basic_token = "admin:admin";

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Authorization: Basic ' . base64_encode($basic_token),
      'Content-Type: application/json',
      'Content-Length: ' . strlen($jsonRequest)
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonRequest);

    $response = curl_exec($ch);

    if ($response === false) {
      throw new Exception(message: curl_error($ch));
      //echo 'cURL Error: ' . curl_error($ch);
    } else {
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $jsonResponse = json_decode($response, true);
      //throw new Exception(message: $jsonResponse);
      //print_r($jsonResponse); // Print the response
    }

    curl_close($ch);
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

  public function is_process_runing($pid): bool
  {
    // TODO windows
    return file_exists('/proc/' . $pid);
  }

  public function start_wallet()
  {
    $xelis_wallet = __DIR__ . '/xelis_pkg/xelis_wallet';
    if (!file_exists($xelis_wallet)) {
      throw new Exception('xelis_wallet does not exists');
    }

    $pid = $this->get_wallet_pid();
    if ($pid) {
      if ($this->is_process_runing($pid)) {
        //throw new Exception("process is already running");
        return;
      }
    }

    $output = [];
    $status = "";
    //exec($xelis_wallet . " --version", $output, $status);

    $wallet_file_path = __DIR__ . "/wallet";
    $precomputed_table_file_path = __DIR__ . "/precomputed_table";

    $xelis_wallet_cmd = $xelis_wallet
      . " --daemon-address https://node.xelis.io "
      . " --wallet-path " . $wallet_file_path
      . " --password admin "
      . " --precomputed-tables-path " . $precomputed_table_file_path
      . " --rpc-bind-address 127.0.0.1:8081 "
      . " --rpc-username admin "
      . " --rpc-password admin ";

    //exec($xelis_wallet_cmd, $output, $status);
    //print_r($this->fetch());
    //throw new Exception(message: json_encode($output));

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