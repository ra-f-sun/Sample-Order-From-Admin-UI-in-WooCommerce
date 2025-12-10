import React from "react";

const ScanConflictModal = ({ results, onSelect, onClose }) => {
  if (!results || results.length === 0) return null;

  return (
    <div className="wcso-modal-overlay" style={styles.overlay}>
      <div className="wcso-modal-content" style={styles.modal}>
        <div style={styles.header}>
          <h2 style={{ margin: 0 }}>Multiple Products Found</h2>
          <button onClick={onClose} style={styles.closeBtn}>
            &times;
          </button>
        </div>

        <p>
          The scanned code matched <strong>{results.length}</strong> products.
          Please select one:
        </p>

        <div style={styles.list}>
          {results.map((p) => (
            <div key={p.id} onClick={() => onSelect(p)} style={styles.item}>
              <strong>{p.name}</strong>
              <br />
              <small>SKU: {p.sku}</small>
              <span style={{ float: "right" }}>{p.price_html}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

const styles = {
  overlay: {
    position: "fixed",
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: "rgba(0,0,0,0.5)",
    zIndex: 10000,
    display: "flex",
    justifyContent: "center",
    alignItems: "center",
  },
  modal: {
    background: "#fff",
    padding: "20px",
    borderRadius: "5px",
    width: "400px",
    maxWidth: "90%",
    boxShadow: "0 4px 6px rgba(0,0,0,0.1)",
  },
  header: {
    display: "flex",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: "15px",
    borderBottom: "1px solid #eee",
    paddingBottom: "10px",
  },
  closeBtn: {
    background: "none",
    border: "none",
    fontSize: "20px",
    cursor: "pointer",
  },
  list: {
    maxHeight: "300px",
    overflowY: "auto",
    border: "1px solid #eee",
  },
  item: {
    padding: "10px",
    borderBottom: "1px solid #eee",
    cursor: "pointer",
    transition: "background 0.2s",
  },
};

export default ScanConflictModal;
