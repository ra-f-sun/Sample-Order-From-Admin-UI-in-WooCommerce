import { createRoot } from "@wordpress/element";
import App from "./App";
import "./index.scss";

document.addEventListener("DOMContentLoaded", () => {
  const container = document.getElementById("wcso-root");
  if (container) {
    const root = createRoot(container);
    root.render(<App />);
  }
});
