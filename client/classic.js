import { Content } from ".";
const { createElement, render } = wp.element;

let content_loaded = false;

jQuery(document.body).on('payment_method_selected', function(e) {
  const inputs = document.querySelectorAll('input[name="payment_method"]:checked');
  if (inputs.length === 1 && inputs[0].value === "xelis_payment" && !content_loaded) {
    setTimeout(() => {
      const content = document.querySelector("#xelis_payment_content");
      render(createElement(Content), content);
    }, 500);
  }
});
