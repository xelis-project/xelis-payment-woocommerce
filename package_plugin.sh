#!/bin/bash
set -e # exit on cmd fail

cd block

npm i
npm run build

cd ../

PATHS=(
  "./xelis_wallet.php"
  "./xelis_wallet_page.php"
  "./xelis_rest.php"
  "./xelis_payment.php"
  "./xelis_payment_state.php"
  "./xelis_payment_method.php"
  "./xelis_payment_gateway.php"
  "./xelis_package.php"
  "./xelis_node.php"
  "./xelis_data.php"
  "./block/style.css"
  "./block/build/index.js"
  "./block/require.js"
  "./assets"
)

if [ "$1" == "include-tables" ]; then
  PATHS+=("./precomputed_tables")
fi

if [ "$1" == "include-wallet" ]; then
  PATHS+=("./xelis_pkg")
fi


OUTPUT="xelis_payment.zip"

rm -f $OUTPUT
zip -r "$OUTPUT" "${PATHS[@]}"

echo "Package created!"