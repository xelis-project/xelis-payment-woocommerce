<?php

enum Xelis_Payment_Status: string
{
  case WAITING = "waiting";
  case WRONG_AMOUNT_REFUND = "wrong_amount_refund";
  case WRONG_ASSET = "wrong_asset";
  case PROCESSING = "processing";
  case PROCESSED = "processed";
  case EXPIRED = "expired";
  case EXPIRED_REFUND = "expired_refund";
}

class Xelis_Payment_State_Object
{
  public string $payment_hash;
  public string $cart_hash;
  public int $timestamp;
  public int $start_topoheight;
  public int $expiration;
  public string $addr;
  public float $xel;
  public Xelis_Payment_Status $status;
  public string $tx;
  public string $refund_tx;
  public float $incorrect_xel;
  public string $network;
  public string $redirect_tx;
  public string $from_addr;

  public function __construct($payment_hash, $cart_hash, $start_topoheight, $expiration, $addr, $xel, $network)
  {
    $this->payment_hash = $payment_hash;
    $this->cart_hash = $cart_hash;
    $this->timestamp = time();
    $this->start_topoheight = $start_topoheight;
    $this->expiration = $expiration;
    $this->addr = $addr;
    $this->xel = $xel;
    $this->status = Xelis_Payment_Status::WAITING;
    $this->tx = "";
    $this->refund_tx = "";
    $this->redirect_tx = "";
    $this->from_addr = "";
    $this->incorrect_xel = 0;
    $this->network = $network;
  }
}

class Xelis_Payment_State
{
  public function init_payment_state(int $timeout): Xelis_Payment_State_Object|null
  {
    $total = WC()->cart->total;
    $cart_hash = WC()->cart->get_cart_hash();
    $customer_id = WC()->session->get_customer_id();
    $payment_hash = time() . ":" . $customer_id . ":" . $cart_hash;
    $gateway = new Xelis_Payment_Gateway();
    $xelis_node = new Xelis_Node($gateway->node_endpoint);

    try {
      if (!$xelis_node->is_node_responsive()) {
        throw new Exception("Looks like the XELIS node is unresponsive");
      }
    } catch (Exception $e) {
      error_log('Error in init_payment_state: ' . $e->getMessage());
      throw new Exception($e->getMessage());
    }

    $xelis_wallet = new Xelis_Wallet();

    try {
      $addr = $xelis_wallet->get_address($payment_hash);
      $start_topoheight = $xelis_wallet->get_topoheight();
    } catch (Exception $e) {
      error_log('Error in init_payment_state: ' . $e->getMessage());
      throw new Exception($e->getMessage());
    }

    $xelis_data = new Xelis_Data();
    try {
      $xel = $xelis_data->convert_usd_to_xel($total);
    } catch (Exception $e) {
      error_log('Error in init_payment_state: ' . $e->getMessage());
      throw new Exception($e->getMessage());
    }

    $expiration = time() + $timeout;
    $network = $xelis_wallet->get_network();

    $state = new Xelis_Payment_State_Object(
      $payment_hash,
      $cart_hash,
      $start_topoheight,
      $expiration,
      $addr,
      $xel,
      $network
    );

    $this->set_payment_state($state);
    return $state;
  }

  public function set_payment_state(Xelis_Payment_State_Object $state): void
  {
    WC()->session->set("payment_state", $state);
  }

  public function get_payment_state(): Xelis_Payment_State_Object|null
  {
    return WC()->session->get("payment_state");
  }

  public function clear_payment_state()
  {
    return WC()->session->set("payment_state", null);
  }

  // PHP requests are locked by session file so we don't have to create a standalone process to run this function and avoid race condition
  // https://www.php.net/manual/en/features.session.security.management.php#features.session.security.management.session-locking
  // calling the route /?rest_route=/xelis_payment/init_payment should wait every session request
  public function process_payment_state()
  {
    $state = $this->get_payment_state();
    if (!$state) {
      return;
    }

    if ($state->status === Xelis_Payment_Status::WAITING) {
      if ($state->expiration < time()) {
        $state->status = Xelis_Payment_Status::EXPIRED;
      }

      $gateway = new Xelis_Payment_Gateway();
      $owner_wallet_addr = $gateway->wallet_addr;

      $xelis_wallet = new Xelis_Wallet();
      try {
        $incoming_txs = $xelis_wallet->get_transactions(
          $state->start_topoheight,
          true,
          false,
          false,
          false
        );
      } catch (Exception $e) {
        error_log('Error in process_payment_state: ' . $e->getMessage());
        return;
      }

      for ($i = 0; $i < count($incoming_txs); $i++) {
        $tx = $incoming_txs[$i];
        $transfers = $tx->incoming->transfers;
        $from = $tx->incoming->from;

        for ($a = 0; $a < count($transfers); $a++) {
          $transfer = $transfers[$a];

          if ($transfer->extra_data === $state->payment_hash) {
            // found the matching transaction :)
            // multiple things can happend from here

            // 1. the tx is valid (we redirect the funds to the store owner addr)
            // 2. the amount is not exact (we refund and mark as wrong_amount_refund)
            // 3. the asset is not XELIS (we don't refund and mark as wrong_asset)
            // 4. the payment window is expired (we refund and mark as expired_refund)
            // if there are any errors in sending funds we log the error and reset to waiting

            // 1. we found a valid tx but the payment window expired so we refund instantly
            if ($state->status === Xelis_Payment_Status::EXPIRED) {
              try {
                $refund_tx = $xelis_wallet->redirect_xelis_funds($transfer->amount, $from);
              } catch (Exception $e) {
                error_log('Error sending funds 1: ' . $e->getMessage());
                break 2;
              }

              $state->tx = $tx->hash;
              $state->refund_tx = $refund_tx->hash;

              $state->status = Xelis_Payment_Status::EXPIRED_REFUND;
              $this->set_payment_state($state);

              break 2;
            }

            // we set the payment status as processing to avoid calling this process again
            $state->status = Xelis_Payment_Status::PROCESSING;
            $this->set_payment_state($state);

            // 2. invalid amount received
            $atomic_amount = $xelis_wallet->unshift_xel($state->xel);
            if ($transfer->amount !== $atomic_amount) {
              try {
                // we have a valid tx but the amount does not match so we instantly refund
                // this branch can also hit if the payment window is expired :)
                $refund_tx = $xelis_wallet->redirect_xelis_funds($transfer->amount, $from);
              } catch (Exception $e) {
                error_log('Error sending funds 2: ' . $e->getMessage());
                break 2;
              }

              $float_amount = $xelis_wallet->shift_xel($transfer->amount);
              $state->incorrect_xel = $float_amount;
              $state->tx = $tx->hash;
              $state->refund_tx = $refund_tx->hash;

              $state->status = Xelis_Payment_Status::WRONG_AMOUNT_REFUND;
              $this->set_payment_state($state);

              break 2;
            }

            // 3. mark as wrong asset
            if ($transfer->asset !== Xelis_Wallet::$XELIS_ASSET) {
              // do not refund if the asset is not XELIS - their loss - add disclaimer on the xelis payment plugin?
              // it's to prevent someone from draining the wallet because of tx refund fees

              $state->status = Xelis_Payment_Status::WRONG_ASSET;
              $this->set_payment_state($state);

              break 2;
            }

            // 4. all is good so we redirect funds to the store owner wallet and mark as processed
            if ($owner_wallet_addr) {
              // redirect funds only if redirect wallet addr is set in config
              // otherwise the owner needs to use the Xelis Wallet admin page and redirect funds manually in his wallet
              try {
                $redirect_tx = $xelis_wallet->redirect_xelis_funds($transfer->amount, $owner_wallet_addr);
                $state->redirect_tx = $redirect_tx->hash;
              } catch (Exception $e) {
                error_log('Error sending funds 3: ' . $e->getMessage());
                break 2;
              }
            }

            $state->status = Xelis_Payment_Status::PROCESSED;
            $state->tx = $tx->hash;
            $state->from_addr = $from;
            $this->set_payment_state($state);

            break 2;
          }
        }
      }
    }
  }
}
