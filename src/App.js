import React, { useState, useEffect } from "react";
import { HashRouter, Routes, Route, Navigate } from "react-router-dom";
import Sidebar from "./components/Sidebar";
import CreateOrder from "./pages/CreateOrder";
import Settings from "./pages/Settings";
import Analytics from "./pages/Analytics";
import EmailLogs from "./pages/EmailLogs";

const App = () => {
  // Lift Settings State so Sidebar can react to changes
  const [appSettings, setAppSettings] = useState(
    window.wcsoData.initialSettings
  );

  const updateAppSetting = (key, value) => {
    setAppSettings((prev) => ({ ...prev, [key]: value }));
  };

  return (
    <HashRouter>
      <div className="wcso-app-wrapper">
        <Sidebar settings={appSettings} />
        <div className="wcso-content-area">
          <Routes>
            <Route
              path="/create_order"
              element={<CreateOrder appSettings={appSettings} />}
            />
            <Route
              path="/settings"
              element={
                <Settings
                  appSettings={appSettings}
                  onUpdateSetting={updateAppSetting}
                />
              }
            />
            <Route path="/analytics" element={<Analytics />} />
            <Route
              path="/email_logs"
              element={
                appSettings.email_logging === "1" ? (
                  <EmailLogs />
                ) : (
                  <Navigate to="/create_order" replace />
                )
              }
            />
            <Route path="/" element={<Navigate to="/create_order" replace />} />
          </Routes>
        </div>
      </div>
    </HashRouter>
  );
};

export default App;
