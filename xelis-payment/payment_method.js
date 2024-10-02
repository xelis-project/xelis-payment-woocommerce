const { registerPaymentMethod } = wc.wcBlocksRegistry;
console.log(wc.wcSettings.getSetting('xelis_payment_data'));

const Content = () => {
  return React.createElement('div', null, `Pay with XELIS`);
}

const Label = () => {
  return React.createElement('span', {
  }, `XELIS Payment`);
}

registerPaymentMethod({
  name: "xelis_payment",
  label: Label(),
  ariaLabel: "",
  content: Content(),
  edit: Content(),
  canMakePayment: () => {
    return true;
  }
});