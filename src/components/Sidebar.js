import React from "react";

const Sidebar = ({ activeView, onChangeView, settings }) => {
  // Check setting passed from App.js
  const isLoggingEnabled = settings.email_logging === "1";

  const handleNav = (view) => {
    if (view === "email_logs" && !isLoggingEnabled) return;
    onChangeView(view);
  };

  return (
    <div className="wcso-sidebar">
      <ul>
        <li
          className={activeView === "create_order" ? "active" : ""}
          onClick={() => handleNav("create_order")}
        >
          <span className="dashicons dashicons-cart"></span> Create Order
        </li>

        <li
          className={activeView === "settings" ? "active" : ""}
          onClick={() => handleNav("settings")}
        >
          <span className="dashicons dashicons-admin-settings"></span> Settings
        </li>

        <li
          className={activeView === "analytics" ? "active" : ""}
          onClick={() => handleNav("analytics")}
        >
          <span className="dashicons dashicons-chart-bar"></span> Analytics
        </li>

        {/* Email Log Item - Conditionally Styled */}
        <li
          className={`${activeView === "email_logs" ? "active" : ""} ${
            !isLoggingEnabled ? "wcso-disabled" : ""
          }`}
          onClick={() => handleNav("email_logs")}
          title={
            !isLoggingEnabled
              ? "Enable 'Email Debug Logging' in Settings to access this feature."
              : ""
          }
          style={
            !isLoggingEnabled
              ? { opacity: 0.5, cursor: "not-allowed", background: "#f8f8f8" }
              : {}
          }
        >
          <span className="dashicons dashicons-email-alt"></span> Email Log
        </li>
      </ul>
    </div>
  );
};

export default Sidebar;
