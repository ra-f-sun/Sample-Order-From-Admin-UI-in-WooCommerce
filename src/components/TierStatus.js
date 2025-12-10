import React from "react";

const TierStatus = ({ cartTotal }) => {
  // 1. Get Config from PHP
  const config = window.wcsoData.tierConfig;

  // 2. Calculate Logic
  let status = { msg: "", color: "", icon: "" };

  if (cartTotal <= 0) {
    status = {
      msg: "Add products to calculate tier...",
      color: "grey",
      icon: "dashicons-minus",
    };
  } else if (cartTotal <= 15) {
    status = {
      msg: `Auto-Approved (${config.t1.name})`,
      color: "#46b450", // Green
      icon: "dashicons-yes-alt",
    };
  } else if (cartTotal <= 100) {
    status = {
      msg: `Approval Needed: ${config.t2.name}`,
      color: "#f0b849", // Orange
      icon: "dashicons-warning",
    };
  } else {
    status = {
      msg: `Approval Needed: ${config.t3.name}`,
      color: "#d63638", // Red
      icon: "dashicons-shield",
    };
  }

  // 3. Render
  return (
    <div className="wcso-tier-box" style={{ borderLeftColor: status.color }}>
      <span
        className={`dashicons ${status.icon}`}
        style={{ color: status.color }}
      ></span>
      <span className="wcso-tier-text">{status.msg}</span>
    </div>
  );
};

export default TierStatus;
