import React from "react";
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";
import ChartCard from "../ChartCard";

const SpendTrendChart = ({ data, onClick }) => {
  return (
    <ChartCard title="Daily Spending Trend" data={data}>
      <ResponsiveContainer width="100%" height="100%">
        <LineChart
          data={data}
          margin={{ top: 10, right: 30, left: 0, bottom: 0 }}
          onClick={(e) =>
            e && e.activePayload && onClick(e.activePayload[0].payload)
          }
        >
          <CartesianGrid strokeDasharray="3 3" vertical={false} />
          <XAxis dataKey="date" tick={{ fontSize: 11 }} minTickGap={30} />
          <YAxis />
          <Tooltip
            formatter={(value) => `$${value}`}
            contentStyle={{
              borderRadius: "8px",
              border: "none",
              boxShadow: "0 4px 12px rgba(0,0,0,0.1)",
            }}
          />
          <Line
            type="monotone"
            dataKey="amount"
            stroke="#d63638"
            strokeWidth={2}
            dot={{ r: 3 }}
            activeDot={{
              r: 6,
              onClick: (e, payload) => onClick(payload.payload),
              cursor: "pointer",
            }}
            name="Spend ($)"
          />
        </LineChart>
      </ResponsiveContainer>
    </ChartCard>
  );
};

export default SpendTrendChart;
