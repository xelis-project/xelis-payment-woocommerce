<?php

class Xelis_Node
{
  public string $endpoint;

  public function __construct(string $endpoint)
  {
    $this->endpoint = $endpoint;
  }

  public function get_version()
  {
    return $this->fetch("get_version");
  }

  public function get_top_block()
  {
    return $this->fetch("get_top_block");
  }

  public function is_node_responsive() {
    $top_block = $this->get_top_block();
    // consider having blockchain or node issues if no new block appears after a generous 5min (XELIS is 15s block time)
    $block_time = (int)($top_block->timestamp / 1000);
    return $block_time + 300 > time();
  }

  public function validate_address(string $addr)
  {
    return $this->fetch("validate_address", ["address" => $addr]);
  }

  public function fetch(string $method, array $params = null)
  {
    $request = [
      'id' => 1,
      'jsonrpc' => '2.0',
      'method' => $method,
    ];

    if ($params) {
      $request['params'] = $params;
    }

    $jsonRequest = json_encode($request);
    $ch = curl_init($this->endpoint . "/json_rpc");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'Content-Length: ' . strlen($jsonRequest)
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonRequest);
    curl_setopt($ch, CURLOPT_FAILONERROR, true); // error out if 404 or other

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
      throw new Exception(message: curl_error(handle: $ch));
    }

    curl_close($ch);
    $data = json_decode($response);
    if (isset($data->result)) {
      return $data->result;
    }

    if (isset($data->error)) {
      throw new Exception($data->error->message);
    }

    return $data;
  }
}