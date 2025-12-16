import { useEffect, useRef } from "react";

const useBarcodeScanner = (onScan) => {
  const buffer = useRef("");
  const lastKeyTime = useRef(0);
  const scanTimeout = useRef(null);
  const isScanning = useRef(false);

  useEffect(() => {
    // Method 1: Handle Keyboard Mode (if Barcode2Win types characters)
    const handleKeyDown = (e) => {
      const target = e.target;
      if (target.tagName === "INPUT" || target.tagName === "TEXTAREA") {
        return;
      }

      const now = Date.now();
      const char = e.key;

      // Detect Ctrl+V (paste from Barcode2Win clipboard mode)
      if ((e.ctrlKey || e.metaKey) && e.key === "v") {
        isScanning.current = true;
        return; // Let the paste event handle it
      }

      // Only accept single printable characters
      if (char.length > 1) {
        return;
      }

      const timeDiff = now - lastKeyTime.current;
      lastKeyTime.current = now;

      if (timeDiff > 150) {
        buffer.current = "";
      }

      buffer.current += char;

      if (scanTimeout.current) {
        clearTimeout(scanTimeout.current);
      }

      scanTimeout.current = setTimeout(() => {
        if (buffer.current.length >= 3) {
          onScan(buffer.current);
          buffer.current = "";
        } else {
          buffer.current = "";
        }
      }, 100);
    };

    // Method 2: Handle Clipboard/Paste Mode (Ctrl+V from Barcode2Win)
    const handlePaste = (e) => {
      const target = e.target;
      if (target.tagName === "INPUT" || target.tagName === "TEXTAREA") {
        return;
      }

      // Prevent default paste behavior
      e.preventDefault();

      // Get pasted text from clipboard
      const pastedText = e.clipboardData.getData("text/plain").trim();

      if (pastedText.length >= 3) {
        onScan(pastedText);
        isScanning.current = false;
      }
    };

    window.document.addEventListener("keydown", handleKeyDown);
    window.document.addEventListener("paste", handlePaste);

    return () => {
      window.document.removeEventListener("keydown", handleKeyDown);
      window.document.removeEventListener("paste", handlePaste);
      if (scanTimeout.current) {
        clearTimeout(scanTimeout.current);
      }
    };
  }, [onScan]);
};

export default useBarcodeScanner;
