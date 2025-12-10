import { useEffect, useRef } from "react";

const useBarcodeScanner = (onScan) => {
  const buffer = useRef("");
  const lastKeyTime = useRef(0);

  useEffect(() => {
    const handleKeyDown = (e) => {
      // 1. Ignore input if user is typing in a real text box (don't capture normal typing)
      const target = e.target;
      if (target.tagName === "INPUT" || target.tagName === "TEXTAREA") {
        return;
      }

      const now = Date.now();
      const char = e.key;

      // 2. Filter: Only accept single characters (A-Z, 0-9) or Enter
      if (char.length > 1 && char !== "Enter") return;

      // 3. Timing Check: Barcode2Win sends keys extremely fast (< 30ms)
      const timeDiff = now - lastKeyTime.current;
      lastKeyTime.current = now;

      // If delay is > 100ms, assume it's a human typing manually -> Reset
      if (timeDiff > 100) {
        buffer.current = "";
      }

      if (char === "Enter") {
        // Trigger only if we have a valid buffer
        if (buffer.current.length > 2) {
          e.preventDefault();
          onScan(buffer.current);
          buffer.current = "";
        }
      } else {
        buffer.current += char;
      }
    };

    window.document.addEventListener("keydown", handleKeyDown);
    return () => window.document.removeEventListener("keydown", handleKeyDown);
  }, [onScan]);
};

export default useBarcodeScanner;
