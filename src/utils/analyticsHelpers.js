// src/utils/analyticsHelpers.js

import { getAnalyticsData, getDrilldownData } from './apiClient';

export const fetchAnalyticsData = async (startDate, endDate, callback) => {
  try {
    const data = await getAnalyticsData(startDate, endDate);
    callback(data);
  } catch (error) {
    console.error("Analytics Error:", error);
    callback(null);
  }
};

export const fetchDrillDownData = async (filterType, filterValue, callback, statusFilter = null) => {
  try {
    // Map filter_type and filter_value to REST API parameters
    const category = filterType === 'category' ? filterValue : null;
    const date = filterType === 'date' ? filterValue : null;
    
    const data = await getDrilldownData(category, date, statusFilter);
    callback(data);
  } catch (error) {
    console.error("Drilldown Error:", error);
    callback([]);
  }
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
