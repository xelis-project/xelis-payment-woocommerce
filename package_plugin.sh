cd block

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

OUTPUT="xelis_payment.zip"

zip -r "$OUTPUT" "${PATHS[@]}"

echo "Package created!"