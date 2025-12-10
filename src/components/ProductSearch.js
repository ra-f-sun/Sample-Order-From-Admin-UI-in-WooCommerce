import React, { useState, useEffect, useRef } from "react";
import ScanConflictModal from "./ScanConflictModal";

const ProductSearch = ({ onAddProduct }) => {
  const [searchTerm, setSearchTerm] = useState("");
  const [allProducts, setAllProducts] = useState([]);
  const [results, setResults] = useState([]);

  const [isLoading, setIsLoading] = useState(false);
  const [cacheStatus, setCacheStatus] = useState("checking");
  const [conflictResults, setConflictResults] = useState(null);

  // Reference to the Blue Box
  const scannerInputRef = useRef(null);

  const CACHE_KEY = "wcso_all_products";
  const TIME_KEY = "wcso_cache_time";
  const EXPIRY = 24 * 60 * 60 * 1000;

  const scannerEnabled =
    window.wcsoData.initialSettings.barcode_scanner === "yes";

  // --- 1. SETUP: LOAD & FOCUS ---
  useEffect(() => {
    loadProductCache();
  }, []);

  // Auto-focus the Blue Box whenever products load or scanner is enabled
  useEffect(() => {
    if (scannerEnabled && scannerInputRef.current && allProducts.length > 0) {
      scannerInputRef.current.focus();
    }
  }, [scannerEnabled, allProducts]);

  // --- 2. SCANNER LOGIC (Blue Box Only) ---
  const handleScannerKeyDown = (e) => {
    // Barcode2Win sends characters followed by "Enter"
    if (e.key === "Enter") {
      e.preventDefault(); // Stop page refresh

      const code = e.target.value.trim().toLowerCase();

      // Debugging Log
      console.log("[SCANNER] Enter detected. Code:", code);

      if (!code || allProducts.length === 0) return;

      // Strict Exact Match
      const matches = allProducts.filter((p) => {
        const sku = p.sku ? p.sku.toLowerCase() : "";
        const id = String(p.id);
        return sku === code || id === code;
      });

      if (matches.length === 1) {
        // MATCH FOUND
        onAddProduct(matches[0]);
        e.target.value = ""; // Clear for next scan

        // Success Flash
        e.target.style.backgroundColor = "#eaffea";
        e.target.style.borderColor = "#46b450";
        setTimeout(() => {
          if (e.target) {
            e.target.style.backgroundColor = "#f0f6fc";
            e.target.style.borderColor = "#2271b1";
          }
        }, 300);
      } else if (matches.length > 1) {
        // CONFLICT
        setConflictResults(matches);
        e.target.value = "";
      } else {
        // NO MATCH
        console.warn("[SCANNER] No match for:", code);
        e.target.style.backgroundColor = "#ffebeb";
        e.target.style.borderColor = "#d63638";
        setTimeout(() => {
          if (e.target) {
            e.target.style.backgroundColor = "#f0f6fc";
            e.target.style.borderColor = "#2271b1";
          }
        }, 300);
      }
    }
  };

  // --- 3. MANUAL SEARCH LOGIC (White Box Only) ---
  useEffect(() => {
    if (searchTerm.length < 2) {
      setResults([]);
      return;
    }
    const lowerTerm = searchTerm.toLowerCase();

    const filtered = allProducts.filter((p) => {
      const name = p.name.toLowerCase();
      const sku = p.sku ? p.sku.toLowerCase() : "";
      const id = String(p.id);

      // Fuzzy match for manual typing
      return (
        name.includes(lowerTerm) ||
        sku.includes(lowerTerm) ||
        id.includes(lowerTerm)
      );
    });
    setResults(filtered.slice(0, 20));
  }, [searchTerm, allProducts]);

  // --- 4. CACHE SYSTEM ---
  const loadProductCache = () => {
    const cachedData = localStorage.getItem(CACHE_KEY);
    const cachedTime = localStorage.getItem(TIME_KEY);
    const now = Date.now();

    if (cachedData && cachedTime && now - cachedTime < EXPIRY) {
      try {
        setAllProducts(JSON.parse(cachedData));
        setCacheStatus("loaded");
      } catch (e) {
        fetchFromApi();
      }
    } else {
      fetchFromApi();
    }
  };

  const fetchFromApi = () => {
    setCacheStatus("loading");
    setIsLoading(true);
    window.jQuery
      .post(
        window.wcsoData.ajaxUrl,
        { action: "wcso_get_all_products", nonce: window.wcsoData.cacheNonce },
        (response) => {
          setIsLoading(false);
          if (response.success) {
            setAllProducts(response.data);
            setCacheStatus("loaded");
            localStorage.setItem(CACHE_KEY, JSON.stringify(response.data));
            localStorage.setItem(TIME_KEY, Date.now());
          } else {
            setCacheStatus("error");
          }
        }
      )
      .fail(() => {
        setIsLoading(false);
        setCacheStatus("error");
      });
  };

  const handleRefresh = () => {
    localStorage.removeItem(CACHE_KEY);
    localStorage.removeItem(TIME_KEY);
    setAllProducts([]);
    setResults([]);
    fetchFromApi();
  };

  return (
    <div className="wcso-search-box">
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          marginBottom: "10px",
        }}
      >
        <h3>
          <span className="dashicons dashicons-search"></span> Add Products
        </h3>
        <div style={{ fontSize: "12px" }}>
          {cacheStatus === "loading" && (
            <span
              className="spinner is-active"
              style={{ float: "none", margin: "0 5px" }}
            ></span>
          )}
          {cacheStatus === "loaded" && (
            <span style={{ color: "#46b450", marginRight: "10px" }}>
              ● {allProducts.length} Cached
            </span>
          )}
          {cacheStatus === "error" && (
            <span style={{ color: "#dc3232", marginRight: "10px" }}>
              ● Failed
            </span>
          )}
          <button
            type="button"
            onClick={handleRefresh}
            className="button button-small"
            disabled={cacheStatus === "loading"}
          >
            Refresh
          </button>
        </div>
      </div>

      {/* --- BLUE SCANNER BOX --- */}
      {scannerEnabled && (
        <div style={{ marginBottom: "20px" }}>
          <label
            style={{
              display: "block",
              marginBottom: "5px",
              fontWeight: "600",
              color: "#2271b1",
            }}
          >
            <span className="dashicons dashicons-smartphone"></span> Scan
            Barcode (Auto-Focused)
          </label>
          <input
            ref={scannerInputRef} // Used for Auto-Focus
            type="text"
            placeholder="Click here & scan..."
            onKeyDown={handleScannerKeyDown} // Only listens here
            className="wcso-input-large"
            style={{
              backgroundColor: "#f0f6fc",
              borderColor: "#2271b1",
              borderWidth: "2px",
              transition: "all 0.2s",
              boxShadow: "0 0 5px rgba(34, 113, 177, 0.2)",
            }}
          />
          <div
            className="wcso-or-divider"
            style={{
              margin: "15px 0",
              textAlign: "center",
              color: "#ccc",
              fontSize: "11px",
              textTransform: "uppercase",
              letterSpacing: "1px",
            }}
          >
            — OR —
          </div>
        </div>
      )}

      {/* --- WHITE MANUAL SEARCH BOX --- */}
      <div className="wcso-search-input-wrapper">
        <label
          style={{
            display: "block",
            marginBottom: "5px",
            fontSize: "12px",
            color: "#666",
          }}
        >
          Manual Search
        </label>
        <input
          type="text"
          placeholder="Type Name or SKU..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          // No onKeyDown handler here = Separate!
          className="wcso-input-large"
        />
      </div>

      {/* Manual Search Results */}
      {results.length > 0 && (
        <div className="wcso-search-results">
          {results.map((product) => (
            <div
              key={product.id}
              className="wcso-result-item"
              onClick={() => {
                onAddProduct(product);
                setSearchTerm("");
                setResults([]);
              }}
            >
              <div>
                <strong>{product.name}</strong>
                <div style={{ fontSize: "11px", color: "#666" }}>
                  SKU: {product.sku || "N/A"}
                </div>
              </div>
              <div style={{ textAlign: "right" }}>
                <span className={`wcso-badge status-${product.status}`}>
                  {product.status}
                </span>
                <div className="wcso-price">{product.price_html}</div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Conflict Modal */}
      {conflictResults && (
        <ScanConflictModal
          results={conflictResults}
          onSelect={(p) => {
            onAddProduct(p);
            setConflictResults(null);
          }}
          onClose={() => setConflictResults(null)}
        />
      )}
    </div>
  );
};

export default ProductSearch;
