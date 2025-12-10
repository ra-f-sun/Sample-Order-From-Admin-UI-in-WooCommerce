import React, { useState } from "react";
import Sidebar from "./components/Sidebar";
import CreateOrder from "./pages/CreateOrder";
import Settings from "./pages/Settings";
import Analytics from "./pages/Analytics";

const App = () => {
  const [currentView, setCurrentView] = useState("create_order");

  const renderView = () => {
    switch (currentView) {
      case "create_order":
        return <CreateOrder />;
      case "settings":
        return <Settings />;
      case "analytics":
        return <Analytics />;
      default:
        return <CreateOrder />;
    }
  };

  return (
    <div className="wcso-app-wrapper">
      <Sidebar activeView={currentView} onChangeView={setCurrentView} />
      <div className="wcso-content-area">{renderView()}</div>
    </div>
  );
};

export default App;
