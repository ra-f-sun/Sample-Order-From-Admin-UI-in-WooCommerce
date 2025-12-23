// src/utils/analyticsHelpers.js

export const fetchAnalyticsData = (startDate, endDate, callback) => {
  window.jQuery.post(
    window.wcsoData.ajaxUrl,
    {
      action: "wcso_get_analytics_data",
      nonce: window.wcsoData.analyticsNonce,
      start_date: startDate,
      end_date: endDate,
    },
    (response) => {
      if (response.success) {
        callback(response.data);
      } else {
        console.error("Analytics Error:", response);
        callback(null);
      }
    }
  );
};

export const fetchDrillDownData = (filterType, filterValue, callback, statusFilter = null) => {
  window.jQuery.post(
    window.wcsoData.ajaxUrl,
    {
      action: "wcso_get_analytics_drilldown",
      nonce: window.wcsoData.analyticsNonce,
      filter_type: filterType, // 'category', 'date', or 'status'
      filter_value: filterValue, // e.g., 'Customer Service' or '2025-12-01'
      status_filter: statusFilter, // 'success' or 'failed' for success rate chart
    },
    (response) => {
      if (response.success) {
        callback(response.data);
      } else {
        console.error("Drilldown Error:", response);
        callback([]);
      }
    }
  );
};

export const exportToCsv = (data, filename) => {
  if (!data || !data.length) return alert("No data to export");

  const csvRows = [];
  const headers = Object.keys(data[0]);
  csvRows.push(headers.join(","));

  for (const row of data) {
    const values = headers.map((header) => {
      const escaped = ("" + row[header]).replace(/"/g, '\\"');
      return `"${escaped}"`;
    });
    csvRows.push(values.join(","));
  }

  const blob = new Blob([csvRows.join("\n")], { type: "text/csv" });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.setAttribute("hidden", "");
  a.setAttribute("href", url);
  a.setAttribute("download", `${filename}.csv`);
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
};
