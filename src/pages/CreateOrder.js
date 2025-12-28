import React, { useState } from "react";
import ProductSearch from "../components/ProductSearch";
import CartTable from "../components/CartTable";
import TierStatus from "../components/TierStatus";
import OrderForm from "../components/OrderForm";
import { createOrder } from "../utils/apiClient";

const CreateOrder = ({ appSettings }) => {
  const [cart, setCart] = useState([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [successData, setSuccessData] = useState(null);

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

  const handleSubmit = async () => {
    if (cart.length === 0) return alert("Please add products.");

    const required = [
      "shipping_first_name",
      "shipping_last_name",
      "shipping_address_1",
      "shipping_city",
      "shipping_country",
      "shipping_postcode",
      "shipping_method",
    ];

    for (let field of required) {
      if (!formData[field] || formData[field].trim() === "") {
        const niceName = field.replace("shipping_", "").replace(/_/g, " ");
        return alert(`Field Required: ${niceName}`);
      }
    }

    const states = window.wcsoData.states[formData.shipping_country];
    if (states && Object.keys(states).length > 0 && !formData.shipping_state) {
      return alert("Field Required: State / District");
    }

    setIsSubmitting(true);
    setSuccessData(null);

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

    const payload = {
      products: cart,
      ...formData,
      order_note: finalNote,
      billing_user_id:
        formData.billing_user_id || window.wcsoData.currentUserId,
    };

    try {
      const response = await createOrder(payload);
      setIsSubmitting(false);
      setSuccessData(response);
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
    } catch (error) {
      setIsSubmitting(false);
      console.error("API Error:", error);
      alert("Error: " + (error.message || "Unknown error"));
    }
  };

  return (
    <div className="wcso-create-layout">
      <div className="wcso-col-left">
        {/* 🔥 FIX: Pass scannerEnabled prop */}
        <ProductSearch
          onAddProduct={handleAddProduct}
          scannerEnabled={appSettings.barcode_scanner === "yes"}
        />
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

        {successData && (
          <div className="notice notice-success wcso-success-notice">
            <p>
              <strong>✓ Order Created! </strong>
              <a href={successData.order_url} target="_blank" rel="noreferrer">
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
