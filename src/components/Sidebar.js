import React from "react";

const Sidebar = ({ activeView, onChangeView }) => {
  // Define our navigation items
  const menuItems = [
    { id: "create_order", label: "Create Order", icon: "dashicons-cart" },
    { id: "settings", label: "Settings", icon: "dashicons-admin-settings" },
    { id: "analytics", label: "Analytics", icon: "dashicons-chart-bar" },
  ];

  return (
    <div className="wcso-sidebar">
      <ul>
        {menuItems.map((item) => (
          <li
            key={item.id}
            className={activeView === item.id ? "active" : ""}
            onClick={() => onChangeView(item.id)}
          >
            <span className={`dashicons ${item.icon}`}></span>
            {item.label}
          </li>
        ))}
      </ul>
    </div>
  );
};

export default Sidebar;
