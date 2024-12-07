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

### Network

Change the network on which the wallet will run. Default is Mainnet, with other available options being Testnet and Dev.
If you want to connect to a Testnet node, you must set this value accordingly.

### Wallet log level

The last print log by the internal wallet. For extensive output check out the wallet page log section.

### Node endpoint

The node server JSON RPC endpoint. Important to be assign to the correct network and avoid mistmatch.

### Wallet address

The cold wallet address that you want to autommatically redirect funds.
Leave empty if you want to manually redirect funds from time to time and keep in the hot wallet.

### Wallet local port

The hot wallet serves the API locally and requires a port. Sometimes, the hosting environment may already be using the default port (8081) to serve other resources.
In such cases, you can specify a different port, typically within the range of 1024 - 65535.

### Fixed USD/XEL quote

The plugin automatically retrieves the 1-day USD avg price from a trusted source.
To set a fixed quote instead, use this input.

### Payment window timeout

The amount of the customer must pay to confirm his order until it expires and new XEL/USD quote is needed.

### Whitelist tags

Only if you to specific which product from your product can be bought with XELIS.
Set one or multiple tags sperated by commas ex: accect_xelis, xelis. The products need to have the tag assigned.
Leave empty to whitelist all products.

### Wallet threads

Set the number of threads the wallet process can use. By default, it is set to 4, which is relatively low.
This settings is in place because online shops often run on VPSs with limited resources.

### Precomputed tables

Upon first launch, the wallet needs to create tables to decrypt the amount. The larger the size, the faster the decryption process.
By default, it is set to 13 for lower memory usage (low = 13, medium = 18, full = 26).

## Wallet Page

The wallet page allows store owners to view/manage their hot wallet.
It can be accessed through the WooCommerce menu tab by clicking on XELIS Wallet.

The page provides an overview of the wallet's status, including applied configurations, wallet address, balance, transactions, and logs.

If you are not automatically redirecting funds, you can use the send funds section to transfer or refund orders.
