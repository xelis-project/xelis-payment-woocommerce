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
      if ($state) {
        return new WP_REST_Response($state, 200);
      }

      return new WP_REST_Response('XELIS Gateway unavailable', 400);
    }

    $cart_hash = WC()->cart->get_cart_hash();
    if (WC()->cart->total === 0) {
      return new WP_REST_Response('Cart is empty', 400);
    }

    $state = $xelis_state->get_payment_state();

    // with reload param - force reload payment state and request new quote
    // also reload payment if cart data changed
    $reload = $request->get_param('reload');
    if ($reload || $state->hash !== $cart_hash) {
      $state = null;
    }

    if (!$state) {
      // check if the cart doesn't contains product with tags that are whitelist
      // whitlist can be set in the payment settings page - if empty it's accept all product
      if (!$gateway->can_make_payment()) {
        return new WP_REST_Response("Can't use gateway. Some items cannot be bought with XELIS.", 400);
      }

      $xelis_state->init_payment_state($timeout);
    } else {
      // this checks for xelis txs and redirect funds either to store owner or refund if an error occurs
      $xelis_state->process_payment_state();
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
