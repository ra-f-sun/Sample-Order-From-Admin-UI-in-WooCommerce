import React from "react";
import { NavLink, useLocation } from "react-router-dom";

const Sidebar = ({ settings }) => {
  // Check setting passed from App.js
  const isLoggingEnabled = settings.email_logging === "1";
  const location = useLocation();

  return (
    <div className="wcso-sidebar">
      <ul>
        <li>
          <NavLink
            to="/create_order"
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            <span className="dashicons dashicons-cart"></span> Create Order
          </NavLink>
        </li>

        <li>
          <NavLink
            to="/settings"
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            <span className="dashicons dashicons-admin-settings"></span>{" "}
            Settings
          </NavLink>
        </li>

        <li>
          <NavLink
            to="/analytics"
            className={({ isActive }) => (isActive ? "active" : "")}
          >
            <span className="dashicons dashicons-chart-bar"></span> Analytics
          </NavLink>
        </li>

        {/* Email Log Item - Conditionally Styled */}
        <li
          className={`${
            location.pathname === "/email_logs" ? "active" : ""
          } ${!isLoggingEnabled ? "wcso-disabled" : ""}`}
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
          {isLoggingEnabled ? (
            <NavLink
              to="/email_logs"
              className={({ isActive }) => (isActive ? "active" : "")}
            >
              <span className="dashicons dashicons-email-alt"></span> Email Log
            </NavLink>
          ) : (
            <span>
              <span className="dashicons dashicons-email-alt"></span> Email Log
            </span>
          )}
        </li>
      </ul>
    </div>
  );
};

export default Sidebar;
