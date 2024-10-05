<?php

class Xelis_Rest
{
  function register_routes()
  {
    register_rest_route('xelis_payment', 'payment_state', array(
      'methods' => 'GET',
      'callback' => [$this, 'route_payment_state'],
      'permission_callback' => '__return_true',
    ));

    register_rest_route('xelis_payment', 'try_expire_refund', array(
      'methods' => 'GET',
      'callback' => [$this, 'route_try_expire_refund'],
      'permission_callback' => '__return_true',
    ));
  }

  function route_payment_state($request)
  {
    $xelis_state = new Xelis_Payment_State();

    $gateway = new Xelis_Payment_Gateway();
    $timeout = (int) $gateway->payment_timeout * 60; // payment_timeout in min so we multiply by 60 for timeout in seconds

    // don't initiate payment window if disabled
    // this checks if enabled, if cart is available and total is higher than 0
    if (!$gateway->is_available()) {
      $state = $xelis_state->get_payment_state();
      // check if there is an ongoing payment state
      // it can happen if store owner disable the gateway while some are still pending
      if ($state)
        return new WP_REST_Response($state, 200);
      return new WP_REST_Response('XELIS Gateway unavailable', 400);
    }

    // with reload parm - force reload payment state and request new quote
    $reload = $request->get_param('reload');
    if ($reload) {
      $xelis_state->init_payment_state($timeout);
    }

    $cart_hash = WC()->cart->get_cart_hash();
    $state = $xelis_state->get_payment_state();

    // init payment if null or process payment state (which means checking xelis txs)
    if (!$state) {
      $xelis_state->init_payment_state($timeout);
    } else {
      $xelis_state->process_payment_state();
    }

    // reload payment if cart data changed
    if ($state->hash !== $cart_hash) {
      $xelis_state->init_payment_state($timeout);
    }

    $state = $xelis_state->get_payment_state();
    return new WP_REST_Response($state, 200);
  }

  function route_try_expire_refund($request)
  {
    $xelis_state = new Xelis_Payment_State();
    $state = $xelis_state->get_payment_state();
    if ($state->status === Xelis_Payment_Status::EXPIRED) {

    }
  }
}
