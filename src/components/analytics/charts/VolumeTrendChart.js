import React from "react";
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";
import ChartCard from "../ChartCard";

const VolumeTrendChart = ({ data, onClick }) => {
  return (
    <ChartCard title="Order Volume Trend" data={data}>
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart
          data={data}
          margin={{ top: 10, right: 30, left: 0, bottom: 0 }}
          onClick={(e) =>
            e && e.activePayload && onClick(e.activePayload[0].payload)
          }
        >
          <CartesianGrid strokeDasharray="3 3" vertical={false} />
          <XAxis dataKey="date" tick={{ fontSize: 11 }} minTickGap={30} />
          <YAxis allowDecimals={false} />
          <Tooltip
            contentStyle={{
              borderRadius: "8px",
              border: "none",
              boxShadow: "0 4px 12px rgba(0,0,0,0.1)",
            }}
          />
          <Area
            type="monotone"
            dataKey="count"
            stroke="#2271b1"
            fill="#e6f0f8"
            name="Orders"
            activeDot={{
              r: 6,
              onClick: (e, payload) => onClick(payload.payload),
              cursor: "pointer",
            }}
          />
        </AreaChart>
      </ResponsiveContainer>
    </ChartCard>
  );
};

export default VolumeTrendChart;
