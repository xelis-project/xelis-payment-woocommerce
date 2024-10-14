# Store owner user manual

## Installing the XELIS WooCommerce plugin

### Step 1: Download the Plugin

Obtain the custom plugin ZIP file from the release page and save it to your computer.

### Step 2: Install the Plugin

1. Log in to your WordPress admin dashboard.
2. Navigate to Plugins > Add New.
3. Click on the Upload Plugin button at the top.
4. Select the ZIP file you downloaded and click Install Now.
5. Once installed, click on Activate Plugin (WooCommerce plugin is required to be installed).

### Step 3: Configure the Plugin

1. Go to WooCommerce in the left sidebar of the dashboard.
2. Look for your Settings and click on it Payments tab.
3. Check the list for XELIS Payment and click "Finish setup" or "Manage" depending if the plugin is enabled or not.
4. Update the necessary fields to configure the plugin. Check out below for more info.

## Settings Page

In this section, you can configure various settings for the XELIS Payment plugin.

### Enable/Disable

Enable or disable the plugin.
If disabled the customers won't be able to initiate new payment window. However, all payment window will

### Wallet Status

The wallet status. This includes online status, network type and wallet version.

### Wallet log

The last print log by the internal wallet. For extensive output check out the wallet page log section.

### Node endpoint

The node server JSON RPC endpoint. Important to be assign to the correct network and avoid mistmatch.

### Wallet address

The cold wallet address that you want to autommatically redirect funds.
Leave empty if you want to manually redirect funds from time to time and keep in the hot wallet.

### Payment window timeout

The amount of the customer must pay to confirm his order until it expires and new XEL/USD quote is needed.

### Whitelist tags

Only if you to specific which product from your product can be bought with XELIS.
Set one or multiple tags sperated by commas ex: accect_xelis, xelis. The products need to have the tag assigned.
Leave empty to whitelist all products.

## Wallet Page

The wallet page allows store owners to view/manage their hot wallet.

TODO
