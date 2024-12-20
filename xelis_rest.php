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
  }

  function route_payment_state($request)
  {
    $xelis_wallet = new Xelis_Wallet();
    $xelis_state = new Xelis_Payment_State();
    $gateway = Xelis_Payment_Gateway::get_instance();
    $timeout = (int) $gateway->payment_timeout * 60; // payment_timeout in min so we multiply by 60 for timeout in seconds

    try {
      if (!$xelis_wallet->is_online()) {
        return new WP_REST_Response('The node is offline.', 400);
      }
    } catch (Exception $e) {
      return new WP_REST_Response($e->getMessage(), 400);
    }

    // don't initiate payment window if disabled
    // this checks if enabled, if cart is available and total is higher than 0
    if (!$gateway->is_available()) {
      $state = $xelis_state->get_payment_state();
      // check if there is an ongoing payment state
      // it can happen if store owner disable the gateway while some are still pending
      if ($state) {
        return new WP_REST_Response($state, 200);
      }

      return new WP_REST_Response('XELIS Gateway unavailable.', 400);
    }

    $cart_hash = WC()->cart->get_cart_hash();
    if (WC()->cart->total === 0) {
      return new WP_REST_Response('Cart is empty.', 400);
    }

    $state = $xelis_state->get_payment_state();

    // with reload param - force reload payment state and request new quote
    // also reload payment if cart data changed
    $reload = $request->get_param('reload');
    if ($reload) {
      $state = null;
    }

    if ($state && $state->cart_hash !== $cart_hash) {
      $state = null;
    }

    if (!$state) {
      // check if the cart doesn't contains product with tags that are whitelist
      // whitlist can be set in the payment settings page - if empty it's accept all product
      if (!$gateway->can_make_payment()) {
        return new WP_REST_Response("You can't use the gateway because there are items in the cart that cannot be purchased with XELIS. Remove them or contact the store owner for support.", 400);
      }

      try {
        $xelis_state->init_payment_state($timeout);
      } catch (Exception $e) {
        return new WP_REST_Response($e->getMessage(), 400);
      }
    }

    // this checks for xelis txs and redirect funds either to store owner or refund if an error occurs
    $xelis_state->process_payment_state();

    $state = $xelis_state->get_payment_state();
    return new WP_REST_Response($state, 200);
  }
}
