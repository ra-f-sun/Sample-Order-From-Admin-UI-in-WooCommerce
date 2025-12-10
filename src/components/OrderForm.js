import React, { useState, useEffect } from "react";

const OrderForm = ({ formData, setFormData }) => {
  const { countries, states, shippingZones, users } = window.wcsoData;
  const [availableStates, setAvailableStates] = useState({});
  const [availableMethods, setAvailableMethods] = useState([]);

  // 1. Update States when Country changes
  useEffect(() => {
    const countryCode = formData.shipping_country;
    if (states[countryCode]) {
      setAvailableStates(states[countryCode]);
    } else {
      setAvailableStates({});
    }
  }, [formData.shipping_country, states]);

  // 2. Update Shipping Methods when Country changes
  useEffect(() => {
    let matchedZone = null;
    for (let zone of shippingZones) {
      for (let loc of zone.zone_locations) {
        if (loc.type === "country" && loc.code === formData.shipping_country) {
          matchedZone = zone;
          break;
        }
      }
      if (matchedZone) break;
    }

    const methods = matchedZone ? matchedZone.shipping_methods : [];
    setAvailableMethods(methods);

    // Auto-select first method
    if (methods.length > 0) {
      const firstMethod = methods[0];
      setFormData((prev) => ({
        ...prev,
        shipping_method: firstMethod.method_id,
        shipping_method_id: firstMethod.method_id,
        shipping_method_title: firstMethod.instance_title,
        shipping_method_cost: firstMethod.instance_cost,
        shipping_method_instance_id: firstMethod.instance_id,
      }));
    } else {
      setFormData((prev) => ({
        ...prev,
        shipping_method: "",
        shipping_method_id: "",
        shipping_method_title: "",
        shipping_method_cost: "",
        shipping_method_instance_id: "",
      }));
    }
  }, [formData.shipping_country, shippingZones, setFormData]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleShippingChange = (e) => {
    const selectedId = e.target.value;
    const method = availableMethods.find((m) => m.method_id === selectedId);

    if (method) {
      setFormData((prev) => ({
        ...prev,
        shipping_method: selectedId,
        shipping_method_id: method.method_id,
        shipping_method_title: method.instance_title,
        shipping_method_cost: method.instance_cost,
        shipping_method_instance_id: method.instance_id,
      }));
    } else {
      setFormData((prev) => ({ ...prev, shipping_method: "" }));
    }
  };

  return (
    <div className="wcso-form-wrapper">
      {/* --- SECTION 1: BILLING --- */}
      <h3 className="wcso-section-header">
        <span className="dashicons dashicons-admin-users"></span> Billing
        Information
      </h3>

      <div className="wcso-form-group">
        <label>Billed To (Sales Rep / Employee)</label>
        <select
          name="billing_user_id"
          value={formData.billing_user_id}
          onChange={handleChange}
          className="wcso-input"
          style={{ fontWeight: "bold" }}
        >
          {users.map((user) => (
            <option key={user.ID} value={user.ID}>
              {/* FIX: Use user_email explicitly to ensure it shows */}
              {user.display_name} ({user.user_email})
            </option>
          ))}
        </select>
        <p
          className="description"
          style={{ marginTop: "5px", color: "#666", fontSize: "12px" }}
        >
          Defaults to current user.
        </p>
      </div>

      <hr
        className="wcso-divider"
        style={{ margin: "25px 0", border: "0", borderTop: "1px solid #eee" }}
      />

      {/* --- SECTION 2: SHIPPING --- */}
      <h3 className="wcso-section-header">
        <span className="dashicons dashicons-location"></span> Shipping
        Information
      </h3>

      {/* Name Fields */}
      <div className="wcso-grid-2">
        <div className="wcso-form-group">
          <label>
            First Name <span className="wcso-required">*</span>
          </label>
          <input
            type="text"
            name="shipping_first_name"
            value={formData.shipping_first_name}
            onChange={handleChange}
            className="wcso-input"
          />
        </div>
        <div className="wcso-form-group">
          <label>
            Last Name <span className="wcso-required">*</span>
          </label>
          <input
            type="text"
            name="shipping_last_name"
            value={formData.shipping_last_name}
            onChange={handleChange}
            className="wcso-input"
          />
        </div>
      </div>

      {/* Company */}
      <div className="wcso-form-group">
        <label>Company (optional)</label>
        <input
          type="text"
          name="shipping_company"
          value={formData.shipping_company}
          onChange={handleChange}
          className="wcso-input"
        />
      </div>

      {/* Country */}
      <div className="wcso-form-group">
        <label>
          Country / Region <span className="wcso-required">*</span>
        </label>
        <select
          name="shipping_country"
          value={formData.shipping_country}
          onChange={handleChange}
          className="wcso-input"
        >
          <option value="">Select Country...</option>
          {Object.entries(countries).map(([code, name]) => (
            <option key={code} value={code}>
              {name}
            </option>
          ))}
        </select>
      </div>

      {/* Address */}
      <div className="wcso-form-group">
        <label>
          Street address <span className="wcso-required">*</span>
        </label>
        <input
          type="text"
          name="shipping_address_1"
          value={formData.shipping_address_1}
          onChange={handleChange}
          className="wcso-input"
          placeholder="House number and street name"
        />
      </div>
      <div className="wcso-form-group">
        <input
          type="text"
          name="shipping_address_2"
          value={formData.shipping_address_2}
          onChange={handleChange}
          className="wcso-input"
          placeholder="Apartment, suite, unit, etc. (optional)"
        />
      </div>

      {/* City / State / Zip */}
      <div className="wcso-form-group">
        <label>
          Town / City <span className="wcso-required">*</span>
        </label>
        <input
          type="text"
          name="shipping_city"
          value={formData.shipping_city}
          onChange={handleChange}
          className="wcso-input"
        />
      </div>

      <div className="wcso-grid-2">
        {/* FIX: Conditional Rendering for State Input */}
        <div className="wcso-form-group">
          <label>
            State / District{" "}
            {Object.keys(availableStates).length > 0 && (
              <span className="wcso-required">*</span>
            )}
          </label>

          {Object.keys(availableStates).length > 0 ? (
            // Scenario A: Country has States -> Show Dropdown
            <select
              name="shipping_state"
              value={formData.shipping_state}
              onChange={handleChange}
              className="wcso-input"
            >
              <option value="">Select State...</option>
              {Object.entries(availableStates).map(([code, name]) => (
                <option key={code} value={code}>
                  {name}
                </option>
              ))}
            </select>
          ) : (
            // Scenario B: No States -> Show Text Input (Manual Entry)
            <input
              type="text"
              name="shipping_state"
              value={formData.shipping_state}
              onChange={handleChange}
              className="wcso-input"
              placeholder="Enter state / district"
            />
          )}
        </div>

        <div className="wcso-form-group">
          <label>
            Postcode / ZIP <span className="wcso-required">*</span>
          </label>
          <input
            type="text"
            name="shipping_postcode"
            value={formData.shipping_postcode}
            onChange={handleChange}
            className="wcso-input"
          />
        </div>
      </div>

      {/* Phone / Email */}
      <div className="wcso-grid-2">
        <div className="wcso-form-group">
          <label>Phone (optional)</label>
          <input
            type="text"
            name="shipping_phone"
            value={formData.shipping_phone}
            onChange={handleChange}
            className="wcso-input"
          />
        </div>
        <div className="wcso-form-group">
          <label>Email address (optional)</label>
          <input
            type="email"
            name="shipping_email"
            value={formData.shipping_email}
            onChange={handleChange}
            className="wcso-input"
            placeholder="recipient@example.com"
          />
        </div>
      </div>

      {/* Shipping Method */}
      <div className="wcso-form-group">
        <label>
          Shipping Method <span className="wcso-required">*</span>
        </label>
        <select
          name="shipping_method"
          value={formData.shipping_method}
          onChange={handleShippingChange}
          className="wcso-input"
        >
          {availableMethods.length === 0 && (
            <option value="">No methods available</option>
          )}
          {availableMethods.map((m) => (
            <option key={m.method_id} value={m.method_id}>
              {m.instance_title} (
              {m.instance_cost > 0 ? m.instance_cost : "Free"})
            </option>
          ))}
        </select>
      </div>

      <hr
        className="wcso-divider"
        style={{ margin: "25px 0", border: "0", borderTop: "1px solid #eee" }}
      />

      {/* --- SECTION 3: EXTRA --- */}
      <h3 className="wcso-section-header">
        <span className="dashicons dashicons-clipboard"></span> Additional
        Information
      </h3>

      {/* Category */}
      <div className="wcso-form-group">
        <label>
          Sample Category <span className="wcso-required">*</span>
        </label>
        <select
          name="sample_category"
          value={formData.sample_category}
          onChange={handleChange}
          className="wcso-input"
        >
          <option value="Customer Service">Customer Service</option>
          <option value="Sales Reps">Sales Reps</option>
          <option value="Promotions">Promotions</option>
        </select>
      </div>

      {/* Note */}
      <div className="wcso-form-group">
        <label>Order Note</label>
        <textarea
          name="order_note"
          value={formData.order_note}
          onChange={handleChange}
          className="wcso-input"
          rows="4"
          placeholder="Add any special instructions..."
        ></textarea>
      </div>
    </div>
  );
};

export default OrderForm;
