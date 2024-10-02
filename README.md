# XELIS Woocommerce plugin - WIP

Accept XELIS as a means of payment for the popular WordPress eCommerce platform.

## How it works

The plugin should be easy to integrate for store owner and seamless to use for client.

## Store owner

1. The store owner download the plugin from this repo releases and manually use the 'add plugin in wordpress'.
2. The store owner activate the plugin and wait for things to load.
3. The store owner goes to settings and add the address of his wallet.
4. The store owner enable the payment.

- The plugin automatically download & install xelis package (wallet) based on his operating system.
- The plugin run a hot wallet in the background as a proxy (check if the wallet is already running, generate precomputed table and start rpc server to access wallet api).

### Refund

TODO

## Clients / buyers

1. The client add products in the cart and goes to checkout.
2. The client choose `Xelis Payment` from the selection list.
3. The client click `Request address and amount`.

- The plugin fetch xelis/us exchange rate from stats.xelis.io and determine the amount to send in XEL.
- The plugin generate an integrated address with the order_id to receive funds.
- The integrated address and amount in XEL is shown to the user with also a QR code.
- The plugin shows a loading icon waiting for payment and also displays an input box for manual verification.
- The user send funds to the address and wait for verification. If nothing happens, the user can input the transaction id for manual verification.
- The interface shows success and redirect to the order completed page.
- The plugin update database tables (order, payment status and product stocking).
- The plugin create a transaction to transfer funds from the hot wallet to the store owner wallet.
