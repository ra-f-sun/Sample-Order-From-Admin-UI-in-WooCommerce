import React, { useState, useEffect } from "react";

const ProductSearch = ({ onAddProduct }) => {
  const [searchTerm, setSearchTerm] = useState("");
  const [results, setResults] = useState([]);
  const [isLoading, setIsLoading] = useState(false);

  // Debounce Search (Wait 500ms after typing stops)
  useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
      if (searchTerm.length > 2) {
        performSearch(searchTerm);
      } else {
        setResults([]);
      }
    }, 500);

    return () => clearTimeout(delayDebounceFn);
  }, [searchTerm]);

  const performSearch = (term) => {
    setIsLoading(true);

    window.jQuery.ajax({
      url: window.wcsoData.ajaxUrl,
      type: "POST",
      data: {
        action: "wcso_search_products",
        search: term,
        // UPDATE THIS LINE:
        nonce: window.wcsoData.searchNonce,
      },
      success: (response) => {
        setIsLoading(false);
        if (response.success) {
          setResults(response.data);
        } else {
          // Optional: Log error if permission denied
          console.log("Search error:", response);
        }
      },
      error: (err) => {
        setIsLoading(false);
        console.log("AJAX error:", err);
      },
    });
  };

  return (
    <div className="wcso-search-box">
      <h3>
        <span className="dashicons dashicons-search"></span> Add Products
      </h3>
      <div className="wcso-search-input-wrapper">
        <input
          type="text"
          placeholder="Search by Name or SKU..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className="wcso-input-large"
        />
        {isLoading && (
          <span
            className="spinner is-active"
            style={{ float: "none", margin: "10px" }}
          ></span>
        )}
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
              <strong>{product.name}</strong>
              <span className={`wcso-badge status-${product.status}`}>
                {product.status}
              </span>
              <div className="wcso-price">{product.price_html}</div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default ProductSearch;
