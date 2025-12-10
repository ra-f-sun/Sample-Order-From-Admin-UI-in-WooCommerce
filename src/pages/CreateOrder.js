import React, { useState } from "react";
import ProductSearch from "../components/ProductSearch";
import CartTable from "../components/CartTable";
import TierStatus from "../components/TierStatus";
import OrderForm from "../components/OrderForm";

const CreateOrder = () => {
  const [cart, setCart] = useState([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [successData, setSuccessData] = useState(null); // Stores { order_id, order_url }

  // Initial Form State
  const [formData, setFormData] = useState({
    billing_user_id: window.wcsoData.currentUserId || "",
    shipping_first_name: "",
    shipping_last_name: "",
    shipping_company: "",
    shipping_country: window.wcsoData.baseCountry || "US",
    shipping_address_1: "",
    shipping_address_2: "",
    shipping_city: "",
    shipping_state: window.wcsoData.baseState || "",
    shipping_postcode: "",
    shipping_phone: "",
    shipping_email: "",
    shipping_method: "",
    sample_category: "Customer Service",
    order_note: "",
    // Extra Shipping Data placeholders
    shipping_method_id: "",
    shipping_method_title: "",
    shipping_method_cost: "",
    shipping_method_instance_id: "",
  });

  const cartTotal = cart.reduce(
    (acc, item) => acc + parseFloat(item.price) * item.quantity,
    0
  );

  const handleAddProduct = (product) => {
    // Clear previous success message when starting a new order
    if (successData) setSuccessData(null);

    const existing = cart.find((item) => item.id === product.id);
    if (existing) handleUpdateQty(product.id, existing.quantity + 1);
    else setCart([...cart, { ...product, quantity: 1 }]);
  };

  const handleUpdateQty = (id, newQty) => {
    if (newQty < 1) return;
    setCart(
      cart.map((item) =>
        item.id === id ? { ...item, quantity: newQty } : item
      )
    );
  };

  const handleRemove = (id) => {
    setCart(cart.filter((item) => item.id !== id));
  };

  const handleSubmit = () => {
    // 1. Cart Validation
    if (cart.length === 0) return alert("Please add products.");

    // 2. Strict Field Validation
    const required = [
      "shipping_first_name",
      "shipping_last_name",
      "shipping_address_1",
      "shipping_city",
      "shipping_country",
      "shipping_postcode",
      "shipping_method",
    ];

    // Check required fields
    for (let field of required) {
      if (!formData[field] || formData[field].trim() === "") {
        const niceName = field.replace("shipping_", "").replace(/_/g, " ");
        return alert(`Field Required: ${niceName}`);
      }
    }

    // Check State if country has states
    const states = window.wcsoData.states[formData.shipping_country];
    if (states && Object.keys(states).length > 0 && !formData.shipping_state) {
      return alert("Field Required: State / District");
    }

    setIsSubmitting(true);
    setSuccessData(null); // Clear previous messages

    // 3. Generate System Note Logic
    const config = window.wcsoData.tierConfig;
    let approvalMsg = "Approval: Not Needed";

    if (cartTotal <= 15) {
      approvalMsg = "Approval: Not Needed (Tier 1 Auto-Approve)";
    } else if (cartTotal <= 100) {
      approvalMsg = `Approval: Needed by ${config.t2.name} (Tier 2)`;
    } else {
      approvalMsg = `Approval: Needed by ${config.t3.name} (Tier 3)`;
    }

    const billingUser = window.wcsoData.users.find(
      (u) => u.ID == formData.billing_user_id
    );
    const billingInfo = billingUser
      ? `${billingUser.display_name} (${billingUser.user_email})`
      : "Unknown";

    const userNote = formData.order_note;
    const systemNote = `
------------
Order by: ${billingInfo}
Order total: ${cartTotal.toFixed(2)}
${approvalMsg}`;

    const finalNote = userNote ? userNote + "\n" + systemNote : systemNote;

    // 4. Prepare Payload
    const payload = {
      action: "wcso_create_order",
      nonce: window.wcsoData.createOrderNonce,
      products: JSON.stringify(cart),
      ...formData,
      order_note: finalNote,
      billing_user_id:
        formData.billing_user_id || window.wcsoData.currentUserId,
    };

    window.jQuery.ajax({
      url: window.wcsoData.ajaxUrl,
      type: "POST",
      data: payload,
      success: (response) => {
        setIsSubmitting(false);
        if (response.success) {
          // Success! Set data for the notice
          setSuccessData(response.data);

          // Reset Form
          setCart([]);
          setFormData((prev) => ({
            ...prev,
            shipping_first_name: "",
            shipping_last_name: "",
            shipping_company: "",
            shipping_address_1: "",
            shipping_address_2: "",
            shipping_city: "",
            shipping_postcode: "",
            shipping_phone: "",
            shipping_email: "",
            order_note: "",
          }));
        } else {
          alert("Error: " + (response.data || "Unknown error"));
        }
      },
      error: (xhr, status, error) => {
        setIsSubmitting(false);
        console.error("AJAX Error:", error);
        alert("System Error: The server rejected the request.");
      },
    });
  };

  return (
    <div className="wcso-create-layout">
      <div className="wcso-col-left">
        <ProductSearch onAddProduct={handleAddProduct} />
        <OrderForm formData={formData} setFormData={setFormData} />
      </div>

      <div className="wcso-col-right">
        <TierStatus cartTotal={cartTotal} />
        <CartTable
          cart={cart}
          onUpdateQty={handleUpdateQty}
          onRemove={handleRemove}
        />

        <button
          className="button button-primary button-large wcso-submit-btn"
          onClick={handleSubmit}
          disabled={isSubmitting}
        >
          {isSubmitting ? "Creating..." : "Create Sample Order"}
        </button>

        {/* Success Notice */}
        {successData && (
          <div
            className="notice notice-success"
            style={{
              marginTop: "15px",
              padding: "12px",
              borderLeft: "4px solid #46b450",
              boxShadow: "0 1px 1px rgba(0,0,0,0.04)",
              background: "#fff",
            }}
          >
            <p style={{ margin: 0, fontSize: "14px" }}>
              <strong>✓ Order Created! </strong>
              <a
                href={successData.order_url}
                target="_blank"
                rel="noreferrer"
                style={{ textDecoration: "none" }}
              >
                View Order #{successData.order_id} &rarr;
              </a>
            </p>
          </div>
        )}
      </div>
    </div>
  );
};

export default CreateOrder;
