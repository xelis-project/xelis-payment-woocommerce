//import { createElement, useState, useEffect, useCallback } from 'react';
import pretty_ms from 'pretty-ms';
import { QRCodeSVG } from 'qrcode.react';
import Icons from './icons.js';

const { useState, useEffect, useCallback } = wp.element;

async function fetch_payment_state({ reload } = { reload: false, update: false }) {
  let endpoint = `/?rest_route=/xelis_payment/payment_state`;
  if (reload) endpoint = `${endpoint}&reload=true`;
  return await fetch(endpoint);
}

const get_explorer_tx_link = ({ tx, network }) => {
  switch (network) {
    case "mainnet":
      return `https://explorer.xelis.io/txs/${tx}`;
    case "testnet":
      return `https://testnet-explorer.xelis.io/txs/${tx}`;
    default:
      return "";
  }
}

export const Content = (props) => {
  const { settings } = props;

  const [init_loading, set_init_loading] = useState(false);
  const [init_error, set_init_error] = useState();

  const [duration, set_duration] = useState(0);
  const [payment_state, set_payment_state] = useState();

  const init_payment_state = useCallback(async (options) => {
    set_init_loading(true);
    const res = await fetch_payment_state(options);
    set_init_loading(false);

    if (res.ok) {
      const data = await res.json();
      set_payment_state(data);
    } else {
      set_init_error(await res.json());
    }
  }, []);

  const update_payment_state = useCallback(async () => {
    const res = await fetch_payment_state();
    if (res.ok) {
      const data = await res.json();
      set_payment_state(data);
    }
  }, []);

  const reset_payment = useCallback(async () => {
    const yes = window.confirm(`Are you sure? Do not request a new quote if you sent a transaction!`)
    if (yes) {
      init_payment_state({ reload: true });
    }
  }, []);

  const copy_address = useCallback(() => {
    navigator.clipboard.writeText(payment_state.addr);
    window.alert(`The integrated address was copied.`);
  }, [payment_state]);

  const copy_amount = useCallback(() => {
    navigator.clipboard.writeText(payment_state.xel);
    window.alert(`The amount was copied.`);
  }, [payment_state])

  useEffect(() => {
    if (!payment_state) return;

    let timeout_id = null;
    function check_duration() {
      set_payment_state((s) => {
        const duration = (s.expiration * 1000) - Date.now();
        if (duration < 0) {
          return { ...s, status: `expired` };
        }

        set_duration(duration);
        return s;
      });

      timeout_id = setTimeout(check_duration, 1000);
    }

    timeout_id = check_duration();
    return () => {
      clearTimeout(timeout_id);
    }
  }, [payment_state]);

  useEffect(() => {
    if (!payment_state) init_payment_state();
    else update_payment_state();
  }, []);

  useEffect(() => {
    if (!payment_state) return;

    const interval_id = setInterval(() => {
      if (payment_state.status === "waiting") {
        update_payment_state();
      }
    }, 15000);

    return () => {
      clearInterval(interval_id);
    }
  }, [payment_state]);

  let render = null;

  if (init_error) {
    render = [<div>{init_error}</div>];
  } else if (!payment_state || init_loading) {
    render = [
      <div className="xelis-payment-init-loading">
        <Icons.Loading className="xelis-payment-loading-icon" />
        Loading data...
      </div>
    ];
  } else if (payment_state) {
    const request_new_quote = <button type="button" onClick={() => reset_payment()} className="xelis-payment-button">
      <Icons.TimerReset />
      Request new quote
    </button>;

    switch (payment_state.status) {
      case `waiting`:
      case `processing`:
        render = [
          <div className="xelis-payment-data">
            <div>Please send the exact amount of XEL to this address.</div>
            <div className="xelis-payment-addr">
              <QRCodeSVG value={payment_state.addr} className="xelis-payment-addr-qrcode" />
              <div className="xelis-payment-addr-value">
                <div>{payment_state.addr}</div>
                <button type="button" onClick={copy_address} title="Copy integrated address">
                  <Icons.Copy />
                </button>
              </div>
            </div>
            <div className="xelis-payment-amount">
              <Icons.Xelis fillColor1="transparent" fillColor2="black" />
              {payment_state.xel} XEL
              <button type="button" onClick={copy_amount} title="Copy amount">
                <Icons.Copy />
              </button>
            </div>
          </div>,
          <div className="xelis-payment-waiting">
            <Icons.Loading className="xelis-payment-loading-icon" />
            Waiting for transaction...
          </div>,
          <div>
            You have <span className="xelis-payment-highlight">
              <Icons.Timer />
              {pretty_ms(duration, { secondsDecimalDigits: 0 })}
            </span> to send your transaction and place your order.
          </div>,
          <div className="xelis-payment-divider" />,
          <div>Don't have enough time? Request a new quote ONLY if you haven't sent a transaction yet.</div>,
          request_new_quote
        ];
        break;
      case `expired`:
        render = [
          <div className="xelis-payment-message-error">
            <Icons.Warning />
            <div>The payment window has timed out. Click the button below to get a new quote.</div>
          </div>,
          request_new_quote,
          <div className="xelis-payment-divider" />,
          <div>
            Did you sent a transaction before the window expired?
            Do not reset the checkout and try to click the button below to check for the transaction and issue a refund.
          </div>,
          <button type="button" onClick={init_payment_state} className="xelis-payment-button">
            <Icons.Transaction />
            Check transaction
          </button>
        ];
        break;
      case `expired_refund`:
        render = [
          <div className="xelis-payment-message-error">
            <Icons.Warning />
            <div>We found a valid transaction that was confirmed after the expiration payment window. A refund has been issued.</div>
          </div>,
          <div className="xelis-payment-divider" />,
          <div>
            <div>Your transaction</div>
            <a href={get_explorer_tx_link({ tx: payment_state.tx })} target="_blank">
              {payment_state.tx}
            </a>
          </div>,
          <div className="xelis-payment-divider" />,
          <div>
            <div>Refund transaction</div>
            <a href={get_explorer_tx_link({ tx: payment_state.refund_tx })} target="_blank">
              {payment_state.refund_tx}
            </a>
          </div>,
          request_new_quote
        ];
        break;
      case `wrong_asset`:
        render = [
          <div className="xelis-payment-message-error">
            <Icons.Warning />
            <div>
              <div>We found a valid transaction with the incorrect asset. A refund cannot be issued for this.</div>
              <div>Contact store owner for support.</div>
            </div>
          </div>,
          <div className="xelis-payment-divider" />,
          <div>
            <div>Your transaction</div>
            <a href={get_explorer_tx_link({ tx: payment_state.tx })} target="_blank">
              {payment_state.tx}
            </a>
          </div>,
          request_new_quote
        ];
        break;
      case `wrong_amount_refund`:
        render = [
          <div className="xelis-payment-message-error">
            <Icons.Warning />
            <div>
              <div>We found a valid transaction with the incorrect amount of {payment_state.incorrect_amount} XEL. A refund has been issued.</div>
              <div>You sent {payment_state.incorrect_xel} XEL, but {payment_state.xel} XEL was expected.</div>
            </div>
          </div>,
          <div className="xelis-payment-divider" />,
          <div>
            <div>Your transaction</div>
            <a href={get_explorer_tx_link({ tx: payment_state.tx })} target="_blank">
              {payment_state.tx}
            </a>
          </div>,
          <div className="xelis-payment-divider" />,
          <div>
            <div>Refund transaction</div>
            <a href={get_explorer_tx_link({ tx: payment_state.refund_tx })} target="_blank">
              {payment_state.refund_tx}
            </a>
          </div>,
          request_new_quote
        ];
        break;
      case `processed`:
        render = [
          <div className="xelis-payment-message-success">
            <Icons.Check />
            <div>We have succesfully comfirmed your XELIS transaction. You can now finish placing your order.</div>
          </div>,
          <div>
            You still have <span className="xelis-payment-highlight">
              <Icons.Timer />
              {pretty_ms(duration, { secondsDecimalDigits: 0 })}
            </span> to place your order.
          </div>,
          <div className="xelis-payment-divider" />,
          <div>
            <div>Your transaction</div>
            <a href={get_explorer_tx_link({ tx: payment_state.tx })} target="_blank">
              {payment_state.tx}
            </a>
          </div>,
        ];
        break;
    }
  }

  return <div className="xelis-payment-content">
    {render}
  </div>;
}

export const Label = (props) => {
  const { settings } = props;

  return <div className="xelis-payment-label">
    <div className="xelis-payment-label-value">
      <Icons.Xelis fillColor1="#02FFCF" fillColor2="black" />
      <div>XELIS Payment</div>
    </div>
    {settings.network !== "mainnet" && <div className="xelis-payment-label-network">
      {settings.network}
    </div>}
  </div>;
}
