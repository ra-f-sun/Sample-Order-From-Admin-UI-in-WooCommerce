import React from "react";

const DrillDownModal = ({ title, orders, onClose }) => {
  return (
    <div className="wcso-modal-overlay" style={styles.overlay}>
      <div className="wcso-modal-content" style={styles.modal}>
        {/* Header */}
        <div style={styles.header}>
          <h2 style={{ margin: 0, fontSize: "18px" }}>Details: {title}</h2>
          <button onClick={onClose} style={styles.closeBtn}>
            &times;
          </button>
        </div>

        {/* Table */}
        <div style={styles.tableWrapper}>
          <table className="wcso-table widefat fixed striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Category</th>
                <th>Status</th>
                <th>Total</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {orders.length === 0 ? (
                <tr>
                  <td colspan="6">No orders found.</td>
                </tr>
              ) : (
                orders.map((order) => (
                  <tr key={order.order_id}>
                    <td>#{order.order_id}</td>
                    <td>{order.formatted_date}</td>
                    <td>{order.category}</td>
                    <td>
                      <span className={`wcso-badge status-${order.status}`}>
                        {order.status}
                      </span>
                    </td>
                    <td>{order.total_amount}</td>
                    <td>
                      <a
                        href={order.edit_url}
                        target="_blank"
                        className="button button-small"
                      >
                        View
                      </a>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
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
    backgroundColor: "rgba(0,0,0,0.6)",
    zIndex: 10000,
    display: "flex",
    justifyContent: "center",
    alignItems: "center",
  },
  modal: {
    background: "#fff",
    padding: "20px",
    borderRadius: "5px",
    width: "800px",
    maxWidth: "95%",
    maxHeight: "80vh",
    display: "flex",
    flexDirection: "column",
    boxShadow: "0 5px 15px rgba(0,0,0,0.3)",
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
    fontSize: "24px",
    cursor: "pointer",
    color: "#666",
  },
  tableWrapper: {
    overflowY: "auto",
    flexGrow: 1,
  },
};

export default DrillDownModal;
