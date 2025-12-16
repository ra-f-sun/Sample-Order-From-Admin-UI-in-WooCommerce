import React, { useState, useEffect } from "react";
import {
  fetchAnalyticsData,
  fetchDrillDownData,
} from "../utils/analyticsHelpers";
import DrillDownModal from "../components/analytics/DrillDownModal";

// Placeholder imports (We will create these next)
import VolumeByPurposeChart from "../components/analytics/charts/VolumeByPurposeChart";
import SpendByPurposeChart from "../components/analytics/charts/SpendByPurposeChart";
import VolumeTrendChart from "../components/analytics/charts/VolumeTrendChart";
import SpendTrendChart from "../components/analytics/charts/SpendTrendChart";
import SuccessRateChart from "../components/analytics/charts/SuccessRateChart";

const Analytics = () => {
  const [dateRange, setDateRange] = useState("30");
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  // Drill Down State
  const [modalOpen, setModalOpen] = useState(false);
  const [modalTitle, setModalTitle] = useState("");
  const [modalData, setModalData] = useState([]);

  useEffect(() => {
    // Calculate dates based on range
    const end = new Date();
    const start = new Date();

    if (dateRange === "7") start.setDate(end.getDate() - 7);
    if (dateRange === "30") start.setDate(end.getDate() - 30);
    if (dateRange === "90") start.setDate(end.getDate() - 90);
    if (dateRange === "year") start.setFullYear(end.getFullYear() - 1);

    const fmt = (d) => d.toISOString().split("T")[0];

    setLoading(true);
    fetchAnalyticsData(fmt(start), fmt(end), (result) => {
      setData(result);
      setLoading(false);
    });
  }, [dateRange]);

  // Handler for clicking a chart
  const handleChartClick = (type, pointData) => {
    if (!pointData) return;

    const value = type === "category" ? pointData.name : pointData.date;
    const title = type === "category" ? `Category: ${value}` : `Date: ${value}`;

    setModalTitle("Loading...");
    setModalOpen(true);
    setModalData([]);

    fetchDrillDownData(type, value, (orders) => {
      setModalTitle(title);
      setModalData(orders);
    });
  };

  return (
    <div className="wcso-analytics-wrapper" style={{ maxWidth: "1200px" }}>
      {/* Header */}
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          marginBottom: "20px",
        }}
      >
        <h2 style={{ margin: 0 }}>Analytics Dashboard</h2>
        <select
          className="wcso-input"
          value={dateRange}
          onChange={(e) => setDateRange(e.target.value)}
          style={{ width: "auto", cursor: "pointer" }}
        >
          <option value="7">Last 7 Days</option>
          <option value="30">Last 30 Days</option>
          <option value="90">Last 3 Months</option>
          <option value="year">Last Year</option>
        </select>
      </div>

      {loading || !data ? (
        <div style={{ textAlign: "center", padding: "50px" }}>
          <span className="spinner is-active" style={{ float: "none" }}></span>{" "}
          Loading Data...
        </div>
      ) : (
        <div
          style={{
            display: "grid",
            gridTemplateColumns: "1fr 1fr",
            gap: "20px",
          }}
        >
          {/* Row 1: Categories */}
          <VolumeByPurposeChart
            data={data.volume_by_category}
            onClick={(d) => handleChartClick("category", d)}
          />
          <SpendByPurposeChart
            data={data.spend_by_category}
            onClick={(d) => handleChartClick("category", d)}
          />

          {/* Row 2: Trends */}
          <VolumeTrendChart
            data={data.trends}
            onClick={(d) => handleChartClick("date", d)}
          />
          <SpendTrendChart
            data={data.trends}
            onClick={(d) => handleChartClick("date", d)}
          />

          {/* Row 3: Success Rate (Full Width) */}
          <div style={{ gridColumn: "1 / -1" }}>
            <SuccessRateChart
              data={data.success_rate}
              onClick={(d) => handleChartClick("date", d)}
            />
          </div>
        </div>
      )}

      {/* Drill Down Modal */}
      {modalOpen && (
        <DrillDownModal
          title={modalTitle}
          orders={modalData}
          onClose={() => setModalOpen(false)}
        />
      )}
    </div>
  );
};

export default Analytics;
