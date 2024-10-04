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
    // overwride and request new quote with reload param
    $reload = $request->get_param('reload');
    if ($reload) {
      $xelis_state->init_payment_state();
    }

    // init payment if null
    if (!$xelis_state->get_payment_state()) {
      $xelis_state->init_payment_state();
    } else {
      $xelis_state->process_payment_state();
    }

    // reload payment if cart data changed
    if (WC()->cart) {
      $cart_hash = WC()->cart->get_cart_hash();
      $state = $xelis_state->get_payment_state();
      if ($state->hash !== $cart_hash) {
        $xelis_state->init_payment_state();
      }
    }

    $state = $xelis_state->get_payment_state();
    return new WP_REST_Response($state, 200);
  }

  function route_try_expire_refund($request)
  {
    $xelis_state = new Xelis_Payment_State();
    $state = $xelis_state->get_payment_state();
    if ($state->status == Xelis_Payment_Status::EXPIRED) {
      
    }
  }
}
