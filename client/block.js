import { Label, Content } from ".";

const { createElement } = wp.element;
const { registerPaymentMethod } = wc.wcBlocksRegistry;
const { getSetting } = wc.wcSettings;

const settings = getSetting('xelis_payment_data');

registerPaymentMethod({
  name: "xelis_payment",
  label: Label({ settings }),
  ariaLabel: "",
  content: createElement(Content),
  edit: createElement(Content),
  canMakePayment: () => {
    // always return true and use the payment state error message if you can't use it
    // this function hides the gateway from the payment options (I prefer display error msg instead)
    return true;
  }
});
