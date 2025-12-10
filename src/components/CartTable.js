import React from "react";

const CartTable = ({ cart, onUpdateQty, onRemove }) => {
  // Calculate total on the fly
  const total = cart.reduce(
    (acc, item) => acc + parseFloat(item.price) * item.quantity,
    0
  );

  return (
    <div className="wcso-cart-wrapper">
      <h3>
        <span className="dashicons dashicons-cart"></span> Selected Items (
        {cart.length})
      </h3>

      {cart.length === 0 ? (
        <div className="wcso-empty-cart">
          <p>No products selected yet.</p>
        </div>
      ) : (
        <>
          <table className="wcso-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Qty</th>
                <th>Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {cart.map((item) => (
                <tr key={item.id}>
                  <td>
                    <strong>{item.name}</strong>
                    <br />
                    <small className="wcso-sku">SKU: {item.sku}</small>
                  </td>
                  <td>{item.price}</td>
                  <td>
                    <input
                      type="number"
                      min="1"
                      value={item.quantity}
                      onChange={(e) =>
                        onUpdateQty(item.id, parseInt(e.target.value))
                      }
                      className="wcso-qty-input"
                    />
                  </td>
                  <td>{(item.price * item.quantity).toFixed(2)}</td>
                  <td>
                    <button
                      className="wcso-remove-btn"
                      onClick={() => onRemove(item.id)}
                    >
                      &times;
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          <div className="wcso-cart-total">
            <span>Original Total:</span>
            <strong>{total.toFixed(2)}</strong>
          </div>
        </>
      )}
    </div>
  );
};

export default CartTable;
