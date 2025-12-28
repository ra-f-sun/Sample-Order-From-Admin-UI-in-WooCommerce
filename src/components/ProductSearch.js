import React, { useState, useEffect, useRef } from "react";
import ScanConflictModal from "./ScanConflictModal";
import useBarcodeScanner from "../hooks/useBarcodeScanner";
import { getAllProducts } from "../utils/apiClient";

const ProductSearch = ({ onAddProduct, scannerEnabled }) => {
  const [searchTerm, setSearchTerm] = useState("");
  const [allProducts, setAllProducts] = useState([]);
  const [results, setResults] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [cacheStatus, setCacheStatus] = useState("checking");
  const [conflictResults, setConflictResults] = useState(null);
  const [scanFeedback, setScanFeedback] = useState(null);

  const CACHE_KEY = "wcso_all_products";
  const TIME_KEY = "wcso_cache_time";
  const EXPIRY = 24 * 60 * 60 * 1000;

  // 🔥 scannerEnabled now comes from props (reactive to settings changes)

  const handleBarcodeScan = (code) => {
    if (!scannerEnabled || allProducts.length === 0) return;

    const lowerCode = code.toLowerCase().trim();
    const matches = allProducts.filter((p) => {
      const sku = p.sku ? p.sku.toLowerCase() : "";
      const id = String(p.id);
      return sku === lowerCode || id === lowerCode;
    });

    if (matches.length === 1) {
      onAddProduct(matches[0]);
      showScanFeedback("success", `Added: ${matches[0].name}`);
    } else if (matches.length > 1) {
      setConflictResults(matches);
      showScanFeedback("warning", `${matches.length} products found`);
    } else {
      showScanFeedback("error", `No match for: ${code}`);
    }
  };

  useBarcodeScanner(handleBarcodeScan);

  const showScanFeedback = (type, message) => {
    setScanFeedback({ type, message });
    setTimeout(() => setScanFeedback(null), 2000);
  };

  useEffect(() => {
    loadProductCache();
  }, []);

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
      return (
        name.includes(lowerTerm) ||
        sku.includes(lowerTerm) ||
        id.includes(lowerTerm)
      );
    });
    setResults(filtered.slice(0, 20));
  }, [searchTerm, allProducts]);

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

  const fetchFromApi = async () => {
    setCacheStatus("loading");
    setIsLoading(true);
    try {
      const data = await getAllProducts();
      setIsLoading(false);
      setAllProducts(data);
      setCacheStatus("loaded");
      localStorage.setItem(CACHE_KEY, JSON.stringify(data));
      localStorage.setItem(TIME_KEY, Date.now());
    } catch (error) {
      setIsLoading(false);
      setCacheStatus("error");
      console.error("API Error:", error);
    }
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
      <div className="wcso-search-header">
        <h3>
          <span className="dashicons dashicons-search"></span> Add Products
        </h3>
        <div className="wcso-cache-status">
          {cacheStatus === "loading" && (
            <span className="spinner is-active"></span>
          )}
          {cacheStatus === "loaded" && (
            <span className="wcso-cache-badge success">
              ● {allProducts.length} Cached
            </span>
          )}
          {cacheStatus === "error" && (
            <span className="wcso-cache-badge error">● Failed</span>
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

      {scannerEnabled && (
        <div
          className={`wcso-scanner-status ${
            scanFeedback ? scanFeedback.type : ""
          }`}
        >
          <div className="wcso-scanner-content">
            <span className="dashicons dashicons-smartphone"></span>
            <div className="wcso-scanner-text">
              <strong>
                {scanFeedback
                  ? scanFeedback.message
                  : "Barcode Scanner Active - Just scan!"}
              </strong>
              {!scanFeedback && (
                <small>
                  No need to click - Barcode2Win sends directly to this page
                </small>
              )}
            </div>
            {scanFeedback && (
              <span className="wcso-scanner-icon">
                {scanFeedback.type === "success" ? "✓" : "✗"}
              </span>
            )}
          </div>
        </div>
      )}

      <div className="wcso-search-input-wrapper">
        <label>
          <span className="dashicons dashicons-edit"></span> Manual Search
        </label>
        <input
          type="text"
          placeholder="Type Name, SKU, or ID..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className="wcso-input-large"
        />
      </div>

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
                <div className="wcso-result-meta">
                  SKU: {product.sku || "N/A"} | ID: {product.id}
                </div>
              </div>
              <div className="wcso-result-right">
                <span className={`wcso-badge status-${product.status}`}>
                  {product.status}
                </span>
                <div className="wcso-price">{product.price_html}</div>
              </div>
            </div>
          ))}
        </div>
      )}

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
