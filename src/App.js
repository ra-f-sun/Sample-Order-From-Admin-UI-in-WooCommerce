import React, { useState } from "react";
import Sidebar from "./components/Sidebar";
import CreateOrder from "./pages/CreateOrder";
import Settings from "./pages/Settings";
import Analytics from "./pages/Analytics";
import EmailLogs from "./pages/EmailLogs";

const App = () => {
  const [currentView, setCurrentView] = useState("create_order");

  // Lift Settings State so Sidebar can react to changes
  const [appSettings, setAppSettings] = useState(
    window.wcsoData.initialSettings
  );

  const updateAppSetting = (key, value) => {
    setAppSettings((prev) => ({ ...prev, [key]: value }));
  };

  const renderView = () => {
    switch (currentView) {
      case "create_order":
        // 🔥 FIX: Pass appSettings to CreateOrder
        return <CreateOrder appSettings={appSettings} />;
      case "settings":
        return (
          <Settings
            appSettings={appSettings}
            onUpdateSetting={updateAppSetting}
          />
        );
      case "analytics":
        return <Analytics />;
      case "email_logs":
        // Security check: Don't render if disabled
        return appSettings.email_logging === "1" ? (
          <EmailLogs />
        ) : (
          <CreateOrder appSettings={appSettings} />
        );
      default:
        return <CreateOrder appSettings={appSettings} />;
    }
  };

  return (
    <div className="wcso-app-wrapper">
      <Sidebar
        activeView={currentView}
        onChangeView={setCurrentView}
        settings={appSettings}
      />
      <div className="wcso-content-area">{renderView()}</div>
    </div>
  );
};

export default App;
