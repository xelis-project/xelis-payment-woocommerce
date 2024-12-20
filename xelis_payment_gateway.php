<?php

class Xelis_Payment_Gateway extends WC_Payment_Gateway
{
  public string $network;

  public string $wallet_addr;

  public string $payment_timeout; // in min

  public string $node_endpoint;

  public string $whitelist_tags;

  public string $wallet_threads;

  public string $wallet_log_level;

  public string $precomputed_tables_size;

  public string $payment_quote;

  public string $wallet_local_port;

  private static $instance = null;

  public function __construct()
  {
    $this->id = 'xelis_payment';
    $this->icon = plugins_url('/assets/icon.png', __FILE__);
    $this->has_fields = false;
    $this->method_title = __('XELIS Payment', 'xelis_payment');
    $this->method_description = __('A XELIS payment gateway for WooCommerce.', 'xelis_payment');
    $this->supports = array('products');
    //error_log('Constructor called at: ' . print_r(debug_backtrace(), true));
    $this->init_settings();

    $this->enabled = $this->get_option('enabled', 'no');
    $this->network = $this->get_option('network', 'mainnet');
    $this->title = __('XELIS Payment', 'xelis_payment');

    if ($this->network !== 'mainnet') {
      $this->title = $this->title . " (" . $this->network . ")";
    }

    $this->wallet_addr = $this->get_option('wallet_addr', '');
    $this->payment_timeout = $this->get_option('payment_timeout', '30');
    $this->node_endpoint = $this->get_option('node_endpoint', 'https://node.xelis.io');
    $this->whitelist_tags = $this->get_option('whitelist_tags', '');
    $this->wallet_threads = $this->get_option('wallet_threads', '4');
    $this->wallet_log_level = $this->get_option('wallet_log_level', 'info');
    $this->precomputed_tables_size = $this->get_option('precomputed_tables_size', '13');
    $this->payment_quote = $this->get_option('payment_quote', '');
    $this->wallet_local_port = $this->get_option('wallet_local_port', '8081');

    $this->init_form_fields();

    if (!isset(self::$instance)) {
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      self::$instance = $this;
    }
  }

  public static function get_instance()
  {
    // __construct is first called by the woocommerce payment gateway so we don't initialized here
    if (!isset(self::$instance)) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  // needed or payment_fields wont be called
  public function has_fields()
  {
    return true;
  }

  // for classic checkout
  public function payment_fields()
  {
    // load div and add react component client side
    wp_enqueue_script('wp-element');
    wp_enqueue_script(
      'xelis_payment_classic_require',
      plugins_url('/client/require.js', __FILE__),
      [],
      filemtime(plugin_dir_path(__FILE__) . '/client/require.js'),
      true
    );

    wp_enqueue_script(
      'xelis_payment_classic',
      plugins_url('/client/build/classic.js', __FILE__),
      [],
      filemtime(plugin_dir_path(__FILE__) . '/client/build/classic.js'),
      true
    );

    ?>
    <script type="text/javascript">
      window.XELIS_NETWORK = "<?php echo $this->network ?>";
    </script>
    <div id="xelis_payment_content"></div>
    <?php
  }

  public function init_form_fields()
  {
    $enabled = $this->enabled === "yes" ? true : false;
    // disabling the plugin does not close the wallet in case there are pending payments
    // if you want to change the network you must wait until all payments have confirmed or expired

    $this->form_fields = array(
      'enabled' => array(
        'title' => __('Enable/Disable', 'xelis_payment'),
        'type' => 'checkbox',
        'label' => __('Enable XELIS Payment', 'xelis_payment'),
        'default' => 'no',
      ),
      'network' => array(
        'title' => __('Network', 'xelis_payment'),
        'type' => 'select',
        'options' => array(
          'mainnet' => __('Mainnet', 'xelis_payment'),
          'testnet' => __('Testnet', 'xelis_payment'),
          'dev' => __('Dev', 'xelis_payment'),
        ),
        'description' => $enabled ? __('Disable the plugin if you want to change the network.', 'xelis_payment') : __('Change XELIS wallet network. You will need to set the node endpoint and wallet address again!', 'xelis_payment'),
        'disabled' => $enabled,
        'default' => 'mainnet',
      ),
      'wallet_log_level' => array(
        'title' => __('Wallet log level', 'xelis_payment'),
        'type' => 'select',
        'options' => array(
          'info' => __('Info', 'xelis_payment'),
          'warn' => __('Warn', 'xelis_payment'),
          'error' => __('Error', 'xelis_payment'),
          'debug' => __('Debug', 'xelis_payment'),
          'trace' => __('Trace', 'xelis_payment'),
          'off' => __('Off', 'xelis_payment'),
        ),
        'default' => 'info',
        'description' => "More or less log information.",
      ),
      'node_endpoint' => array(
        'title' => __('Node endpoint', 'xelis_payment'),
        'type' => 'text',
        'description' => __('Set the node endpoint url. Default is https://node.xelis.io for Mainnet.', 'xelis_payment'),
        'default' => 'https://node.xelis.io',
      ),
      'wallet_addr' => array(
        'title' => __('Wallet address', 'xelis_payment'),
        'type' => 'text',
        'description' => __('Set the address of your XELIS wallet to redirect hot wallet funds automatically.', 'xelis_payment'),
        'default' => '',
      ),
      'wallet_local_port' => array(
        'title' => __('Wallet local port', 'xelis_payment'),
        'type' => 'text',
        'description' => __('Set the port for the wallet local API. Defaults to 8081. Change it if your server is already using this port for another resource.', 'xelis_payment'),
      ),
      'payment_timeout' => array(
        'title' => __('Payment window timeout', 'xelis_payment'),
        'type' => 'text',
        'description' => __('Set the payment window timeout. Lock the XEL/USD quote price. Default is 30 min.', 'xelis_payment'),
        'default' => '30',
      ),
      'payment_quote' => array(
        'title' => __('Fixed USD/XEL Quote', 'xelis_payment'),
        'type' => 'text',
        'description' => __('Set a fixed value for the USD/XEL quote. Leaving empty will use the automatic quote. Request the 1-day avg price weighted by market volume.', 'xelis_payment'),
      ),
      'whitelist_tags' => array(
        'title' => __('Whitelist tags', 'xelis_payment'),
        'type' => 'text',
        'description' => __('Set product tags to accepts XELIS payment. Seperated with commas, for example: accept xelis, xelis, crypto. Leaving blank means that all products can be bought with XEL.', 'xelis_payment'),
        'default' => '',
      ),
      'wallet_threads' => array(
        'title' => __('Wallet threads', 'xelis_payment'),
        'type' => 'text',
        'description' => __('Maximum number of threads the wallet process will use. By default, it is set low (4) for VPS.', 'xelis_payment'),
        'default' => '4',
      ),
      'precomputed_tables_size' => array(
        'title' => __('Precomputed tables', 'xelis_payment'),
        'type' => 'text',
        'description' => __('Set the ECDLP Tables L1 size . By default, it is set to 13 for lower memory usage (low = 13, medium = 18, full = 26).', 'xelis_payment'),
        'default' => '13'
      )
    );
  }

  public function process_admin_options()
  {
    // looks like you can't remove the success message even if return false :(
    // https://github.com/woocommerce/woocommerce/blob/37903778fba449da0422207e1ce4f150f02aa0a2/plugins/woocommerce/includes/admin/class-wc-admin-settings.php#L88

    $network = $_POST['woocommerce_' . $this->id . '_network'];
    if ($network && $network !== $this->network) {
      $new_node_endpoint = '';
      switch ($network) {
        case "mainnet":
          $new_node_endpoint = "https://node.xelis.io";
          break;
        case "testnet":
          $new_node_endpoint = "https://testnet-node.xelis.io";
          break;
        case "dev":
          $new_node_endpoint = "http://127.0.0.1:8080";
          break;
        default:
          $this->add_error('Network is not a valid option');
          $this->display_errors();
          return false;
      }

      $_POST['woocommerce_' . $this->id . '_wallet_addr'] = '';
      $this->wallet_addr = '';

      $_POST['woocommerce_' . $this->id . '_node_endpoint'] = $new_node_endpoint;
      $this->node_endpoint = $new_node_endpoint;

      $this->network = $network;

      try {
        $xelis_wallet = new Xelis_Wallet();
        $xelis_wallet->restart_wallet();
      } catch (Exception $e) {
        $this->add_error("Can't restart wallet: " . $e->getMessage());
        $this->display_errors();
        return false;
      }
    } else {
      // if a field is disabled it's not included in the $_POST so we add the current value again here
      $_POST['woocommerce_' . $this->id . '_network'] = $this->network;
    }

    $node_endpoint = $_POST['woocommerce_' . $this->id . '_node_endpoint'];
    if ($node_endpoint !== $this->node_endpoint) {
      if (filter_var($node_endpoint, FILTER_VALIDATE_URL) === false) {
        $this->add_error('Node endpoint is not a valid url');
        $this->display_errors();
        return false;
      }

      try {
        $xelis_node = new Xelis_Node($node_endpoint);
        $xelis_node->get_version(); // check if you can fetch the endpoint and its valid

        $xelis_wallet = new Xelis_Wallet();
        // set offline or set_online_mode won't assign new endpoint if connected
        if ($xelis_wallet->is_online()) {
          $xelis_wallet->set_offline_mode();
        }

        $xelis_wallet->set_online_mode($node_endpoint);
      } catch (Exception $e) {
        $this->add_error($e->getMessage());
        $this->display_errors();
        return false;
      }
    }

    $wallet_addr = $_POST['woocommerce_' . $this->id . '_wallet_addr'];
    if ($wallet_addr !== $this->wallet_addr) {
      try {
        if ($wallet_addr !== "") { // possible to set wallet_addr back to empty
          $xelis_node = new Xelis_Node($this->node_endpoint);
          $result = $xelis_node->validate_address($wallet_addr);
          if ($result->is_valid !== true) {
            $this->add_error(json_encode($result));
            $this->add_error("Not a valid XELIS wallet address.");
            $this->display_errors();
            return false;
          }
        }
      } catch (Exception $e) {
        $this->add_error($e->getMessage());
        $this->display_errors();
        return false;
      }
    }

    $payment_timeout = $_POST['woocommerce_' . $this->id . '_payment_timeout'];
    if ($payment_timeout !== $this->payment_timeout) {
      if (filter_var($payment_timeout, FILTER_VALIDATE_INT) == false) {
        $this->add_error("Payment timeout window must be an number.");
        $this->display_errors();
        return false;
      }

      if (($payment_timeout) < 5) {  // cannot set less than 5min
        $this->add_error("Can't set less than 5 min for payment window.");
        $this->display_errors();
        return false;
      }
    }

    $wallet_local_port = $_POST['woocommerce_' . $this->id . '_wallet_local_port'];
    if ($wallet_local_port !== $this->wallet_local_port) {
      try {
        $xelis_wallet = new Xelis_Wallet();
        if ($xelis_wallet->is_port_in_use($wallet_local_port)) {
          $this->add_error("Port " . $wallet_local_port . " already in use by another process.");
          $this->display_errors();
          return false;
        }

        $this->wallet_local_port = $wallet_local_port;
        $xelis_wallet->restart_wallet();
      } catch (Exception $e) {
        $this->add_error("Can't restart wallet: " . $e->getMessage());
        $this->display_errors();
        return false;
      }
    }

    // don't have to validate whitelist_tags
    // it's a string seperated by comma

    return parent::process_admin_options();
  }

  public function can_make_payment(): bool
  {
    if ($this->whitelist_tags) {
      $whitelist_tags = array_map('trim', explode(',', $this->whitelist_tags));

      foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $product_tags = get_the_terms($product_id, 'product_tag');
        $match = false;
        foreach ($whitelist_tags as $tag) {
          foreach ($product_tags as $product_tag) {
            if ($tag === $product_tag->name) {
              $match = true;
              break 2;
            }
          }
        }

        if (!$match)
          return false;
      }
    }

    return true;
  }

  public function process_payment($order_id)
  {
    $xelis_state = new Xelis_Payment_State();
    $state = $xelis_state->get_payment_state();

    if ($state->status !== Xelis_Payment_Status::PROCESSED) {
      /* wc_add_notice('Your payment could not be processed. Please try again.', 'error');
      WC()->session->set('wc_notices', [array("notice" => "Your payment could not be processed. Please try again when the transaction has been confirmed by the network.")]);
      $notices = WC()->session->get('wc_notices', array());
      return array(
        'result' => 'failure',
        'redirect' => '',
      );*/

      // I'm using throw since wc_add_notice does not seem to work
      throw new Exception("Your payment could not be processed. Please try again when the transaction has been confirmed by the network.");
    }

    $order = wc_get_order($order_id);
    $order->add_meta_data("xelis_tx", $state->tx);
    $order->add_meta_data("xelis_redirect_tx", $state->redirect_tx);
    $order->add_meta_data("xelis_amount", $state->xel);
    $order->payment_complete();
    $order->add_order_note('Payment received via XELIS Payment Gateway.');
    $xelis_state->clear_payment_state();

    return array(
      'result' => 'success',
      'redirect' => $this->get_return_url($order),
    );
  }
}
