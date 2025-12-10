import React, { useState, useEffect } from "react";

const EmailLogs = () => {
  const [logContent, setLogContent] = useState("Loading logs...");
  const [isClearing, setIsClearing] = useState(false);

  useEffect(() => {
    fetchLogs();
  }, []);

  const fetchLogs = () => {
    window.jQuery.post(
      window.wcsoData.ajaxUrl,
      {
        action: "wcso_get_email_log",
        nonce: window.wcsoData.saveSettingsNonce,
      },
      (res) => {
        if (res.success) setLogContent(res.data);
        else setLogContent("Failed to load logs.");
      }
    );
  };

  const handleClear = () => {
    if (!confirm("Are you sure you want to clear the logs?")) return;
    setIsClearing(true);

    window.jQuery.post(
      window.wcsoData.ajaxUrl,
      {
        action: "wcso_clear_log",
        nonce: window.wcsoData.saveSettingsNonce,
      },
      (res) => {
        setIsClearing(false);
        if (res.success) {
          setLogContent("No emails logged yet.");
        }
      }
    );
  };

  return (
    <div className="wcso-settings-wrapper" style={{ maxWidth: "100%" }}>
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          marginBottom: "20px",
        }}
      >
        <h2 style={{ margin: 0 }}>System Email Logs</h2>
        <div style={{ display: "flex", gap: "10px" }}>
          <button className="button" onClick={fetchLogs}>
            Refresh
          </button>
          <button
            className="button"
            onClick={handleClear}
            disabled={isClearing}
          >
            {isClearing ? "Clearing..." : "Clear Log"}
          </button>
        </div>
      </div>

      <div className="wcso-card" style={{ padding: 0, overflow: "hidden" }}>
        <textarea
          readOnly
          value={logContent}
          style={{
            width: "100%",
            height: "600px",
            padding: "20px",
            fontFamily: "monospace",
            fontSize: "12px",
            border: "none",
            resize: "none",
            background: "#f6f7f7",
            color: "#333",
            lineHeight: "1.5",
          }}
        />
      </div>
    </div>
  );
};

export default EmailLogs;
