import React from "react";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from "recharts";
import ChartCard from "../ChartCard";

const SuccessRateChart = ({ data, onClick }) => {
  return (
    <ChartCard title="Success vs Failure Rate" data={data}>
      <ResponsiveContainer width="100%" height="100%">
        <BarChart
          data={data}
          margin={{ top: 20, right: 30, left: 0, bottom: 5 }}
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
          <Legend />
          <Bar
            dataKey="success"
            stackId="a"
            fill="#46b450"
            name="Successful / Processing"
            cursor="pointer"
          />
          <Bar
            dataKey="failed"
            stackId="a"
            fill="#d63638"
            name="Failed / Rejected"
            cursor="pointer"
          />
        </BarChart>
      </ResponsiveContainer>
    </ChartCard>
  );
};

export default SuccessRateChart;
