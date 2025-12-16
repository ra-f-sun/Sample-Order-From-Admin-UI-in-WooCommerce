import React from "react";
import { exportToCsv } from "../../utils/analyticsHelpers";

const ChartCard = ({ title, data, children }) => {
  return (
    <div
      className="wcso-card"
      style={{
        height: "100%",
        display: "flex",
        flexDirection: "column",
        minHeight: "350px",
      }}
    >
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          marginBottom: "15px",
          alignItems: "center",
        }}
      >
        <h3
          style={{
            margin: 0,
            fontSize: "14px",
            fontWeight: "600",
            color: "#1d2327",
          }}
        >
          {title}
        </h3>
        <button
          className="button button-small"
          onClick={() =>
            exportToCsv(data, title.replace(/\s+/g, "_").toLowerCase())
          }
          title="Export to CSV"
        >
          <span
            className="dashicons dashicons-download"
            style={{ lineHeight: "26px" }}
          ></span>
        </button>
      </div>
      <div style={{ flexGrow: 1, position: "relative" }}>{children}</div>
    </div>
  );
};

export default ChartCard;
