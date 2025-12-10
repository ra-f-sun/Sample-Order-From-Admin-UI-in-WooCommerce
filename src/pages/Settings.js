import React, { useState } from "react";

// Accept Props from App.js
const Settings = ({ appSettings, onUpdateSetting }) => {
  // Local state for the form, initialized from Props
  const [formData, setFormData] = useState(appSettings);
  const [isSaving, setIsSaving] = useState(false);
  const [message, setMessage] = useState(null);

  // Sync prop changes if they happen externally (rare but good practice)
  React.useEffect(() => {
    setFormData(appSettings);
  }, [appSettings]);

  const handleTierChange = (tierKey, field, value) => {
    setFormData((prev) => ({
      ...prev,
      tiers: {
        ...prev.tiers,
        [tierKey]: { ...prev.tiers[tierKey], [field]: value },
      },
    }));
  };

  const handleGeneralChange = (field, value) => {
    const newData = { ...formData, [field]: value };
    setFormData(newData);

    // Update Global App State IMMEDIATELY for the Sidebar toggle
    if (field === "email_logging") {
      onUpdateSetting(field, value);
    }
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
          // Ensure App state matches saved state
          onUpdateSetting("email_logging", formData.email_logging);
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
          />
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

        {/* --- NEW LOGGING TOGGLE --- */}
        <div className="wcso-field-row">
          <label
            style={{
              fontWeight: "bold",
              color: formData.email_logging === "1" ? "#2271b1" : "inherit",
            }}
          >
            <input
              type="checkbox"
              checked={formData.email_logging === "1"}
              onChange={(e) =>
                handleGeneralChange(
                  "email_logging",
                  e.target.checked ? "1" : "0"
                )
              }
            />
            Enable Email Debug Logging
          </label>
          <small>
            Enables the "Email Log" menu in the sidebar. Useful for checking
            approval links.
          </small>
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
              value={formData.tiers.t2.email}
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
              value={formData.tiers.t3.email}
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
