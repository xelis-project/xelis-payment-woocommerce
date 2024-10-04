<?php

enum Xelis_Payment_Status: string
{
  case WAITING = "waiting";
  case WRONG_AMOUNT_REFUND = "wrong_amount_refund";
  case VALID = "valid";
  case EXPIRED = "expired";
  case EXPIRED_REFUND = "expired_refund";
}

class Xelis_Payment_State_Object
{
  public string $hash;
  public int $timestamp;
  public int $topoheight;
  public int $expiration;
  public string $addr;
  public float $xel;
  public Xelis_Payment_Status $status;
  public string $tx;
  public string $refund_tx;
  public float $incorrect_xel;

  public function __construct($hash, $topoheight, $expiration, $addr, $xel)
  {
    $this->hash = $hash;
    $this->timestamp = time();
    $this->topoheight = $topoheight;
    $this->expiration = $expiration;
    $this->addr = $addr;
    $this->xel = $xel;
    $this->status = Xelis_Payment_Status::WAITING;
    $this->tx = "";
    $this->refund_tx = "";
    $this->incorrect_xel = 0;
  }
}

class Xelis_Payment_State
{
  public $session_state_key = "xelis_payment_state";

  public function init_payment_state()
  {
    if (!WC()->cart) {
      return;
    }

    $total = WC()->cart->total;
    $cart_hash = WC()->cart->get_cart_hash();

    $xelis_wallet = new Xelis_Wallet();
    $addr = $xelis_wallet->get_address($cart_hash);
    $topoheight = $xelis_wallet->get_topoheight();

    $xelis_data = new Xelis_Data();
    $xel = $xelis_data->convert_usd_to_xel($total);
    $expiration = time() + 30 * 60; // 30min TODO: configurable

    $state = new Xelis_Payment_State_Object($cart_hash, $topoheight, $expiration, $addr, $xel);
    $this->set_payment_state($state);
  }

  public function set_payment_state(Xelis_Payment_State_Object $state): void
  {
    $_SESSION[$this->session_state_key] = $state;
  }

  public function get_payment_state(): Xelis_Payment_State_Object
  {
    return $_SESSION[$this->session_state_key];
  }

  public function process_payment_state()
  {
    $state = $this->get_payment_state();

    if (
      $state->status == Xelis_Payment_Status::WAITING ||
      $state->status == Xelis_Payment_Status::EXPIRED
    ) {
      if ($state->expiration < time()) {
        $state->status = Xelis_Payment_Status::EXPIRED;
      }

      $xelis_wallet = new Xelis_Wallet();
      $txs = $xelis_wallet->get_incoming_transactions($state->topoheight);

      for ($i = 0; $i < count($txs); $i++) {
        $tx = $txs[$i];
        $transfers = $tx->incoming->transfers;

        for ($a = 0; $a < count($transfers); $a++) {
          $transfer = $transfers[$a];

          if ($transfer->extra_data == $state->hash) {
            $atomic_amount = $xelis_wallet->unshift_xel($state->xel);
            if ($transfer->amount == $atomic_amount) {
              switch ($transfer->status) {
                case Xelis_Payment_Status::WAITING:
                  $state->status = Xelis_Payment_Status::VALID;
                  $state->tx = $tx;
                case Xelis_Payment_Status::EXPIRED:
                  $state->tx = $tx->hash;
                  $state->status = Xelis_Payment_Status::EXPIRED_REFUND;

                  // we found a valid tx but the payment window expired so we refund instantly
                  $refund_tx = $xelis_wallet->send_transaction($transfer->amount, $transfer->from);
                  $state->tx = $refund_tx->hash;
              }
            } else {
              // we have a valid tx but the amount does not match so we instantly refund
              // this branch can also hit if the payment window is expired :)
              $float_amount = $xelis_wallet->shift_xel($transfer->amount);
              $state->incorrect_xel = $float_amount;
              $state->tx = $tx->hash;
              $state->status = Xelis_Payment_Status::WRONG_AMOUNT_REFUND;

              $refund_tx = $xelis_wallet->send_transaction($transfer->amount, $transfer->from);
              $state->refund_tx = $refund_tx->hash;
            }
          }
        }
      }
    }

    $this->set_payment_state($state);
  }
}
