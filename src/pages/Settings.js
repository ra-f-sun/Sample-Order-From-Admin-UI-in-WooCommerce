import React, { useState } from "react";

const Settings = () => {
  // Initialize state with data passed from PHP
  const [formData, setFormData] = useState(window.wcsoData.initialSettings);
  const [isSaving, setIsSaving] = useState(false);
  const [message, setMessage] = useState(null);

  // Handle Deep Nested Changes (for Tiers)
  const handleTierChange = (tierKey, field, value) => {
    setFormData((prev) => ({
      ...prev,
      tiers: {
        ...prev.tiers,
        [tierKey]: {
          ...prev.tiers[tierKey],
          [field]: value,
        },
      },
    }));
  };

  // Handle Top Level Changes (General)
  const handleGeneralChange = (field, value) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSave = () => {
    setIsSaving(true);
    setMessage(null);

    window.jQuery.post(
      window.wcsoData.ajaxUrl,
      {
        action: "wcso_save_settings",
        nonce: window.wcsoData.saveSettingsNonce,
        settings: formData,
      },
      (response) => {
        setIsSaving(false);
        if (response.success) {
          setMessage({ type: "success", text: "Settings Saved!" });
          // Hide message after 3 seconds
          setTimeout(() => setMessage(null), 3000);
        } else {
          setMessage({ type: "error", text: "Error saving settings." });
        }
      }
    );
  };

  return (
    <div className="wcso-settings-wrapper">
      <h2>Plugin Configuration</h2>

      {message && (
        <div className={`wcso-notice ${message.type}`}>{message.text}</div>
      )}

      {/* General Section */}
      <div className="wcso-card">
        <h3>General Options</h3>
        <div className="wcso-field-row">
          <label>Discount Coupon Code</label>
          <input
            type="text"
            value={formData.coupon_code}
            onChange={(e) => handleGeneralChange("coupon_code", e.target.value)}
            placeholder="e.g. flat100"
          />
          <small>The coupon applied to make the order $0.00.</small>
        </div>
        <div className="wcso-field-row">
          <label>
            <input
              type="checkbox"
              checked={formData.barcode_scanner === "yes"}
              onChange={(e) =>
                handleGeneralChange(
                  "barcode_scanner",
                  e.target.checked ? "yes" : "no"
                )
              }
            />
            Enable Barcode Scanner Mode
          </label>
        </div>
      </div>

      {/* Tier 1 */}
      <div className="wcso-card" style={{ borderLeft: "4px solid #46b450" }}>
        <h3>Tier 1: Auto-Approval</h3>
        <div className="wcso-grid-2">
          <div>
            <label>Label Name</label>
            <input
              type="text"
              value={formData.tiers.t1.name}
              onChange={(e) => handleTierChange("t1", "name", e.target.value)}
            />
          </div>
          <div>
            <label>Limit ($)</label>
            <input
              type="number"
              value={formData.tiers.t1.limit}
              onChange={(e) => handleTierChange("t1", "limit", e.target.value)}
            />
          </div>
        </div>
      </div>

      {/* Tier 2 */}
      <div className="wcso-card" style={{ borderLeft: "4px solid #f0b849" }}>
        <h3>Tier 2: Manager Approval</h3>
        <div className="wcso-grid-2">
          <div>
            <label>Label Name</label>
            <input
              type="text"
              value={formData.tiers.t2.name}
              onChange={(e) => handleTierChange("t2", "name", e.target.value)}
            />
          </div>
          <div>
            <label>Approver Email</label>
            <input
              type="email"
              value={formData.tiers.t2.approver}
              onChange={(e) =>
                handleTierChange("t2", "approver", e.target.value)
              }
            />
          </div>
        </div>
      </div>

      {/* Tier 3 */}
      <div className="wcso-card" style={{ borderLeft: "4px solid #d63638" }}>
        <h3>Tier 3: Executive Approval</h3>
        <div className="wcso-grid-2">
          <div>
            <label>Label Name</label>
            <input
              type="text"
              value={formData.tiers.t3.name}
              onChange={(e) => handleTierChange("t3", "name", e.target.value)}
            />
          </div>
          <div>
            <label>Approver Email</label>
            <input
              type="email"
              value={formData.tiers.t3.approver}
              onChange={(e) =>
                handleTierChange("t3", "approver", e.target.value)
              }
            />
          </div>
        </div>
      </div>

      <button
        className="button button-primary button-large"
        onClick={handleSave}
        disabled={isSaving}
      >
        {isSaving ? "Saving..." : "Save Settings"}
      </button>
    </div>
  );
};

export default Settings;
