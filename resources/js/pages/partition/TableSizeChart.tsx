"use client";

import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Tooltip,
  Legend,
} from "chart.js";
import { Bar } from "react-chartjs-2";

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

interface Props {
  tables: {
    name: string;
    rows: number;
    data_size_mb: string;
    index_size_mb: string;
    total_size_mb: string;
  }[];
}

export default function TableSizeChart({ tables }: Props) {
  const data = {
    labels: tables.map((t) => t.name),
    datasets: [
      {
        label: "Table Size (MB)",
        data: tables.map((t) => parseFloat(t.total_size_mb)),
        backgroundColor: "#16a34a",
      },
    ],
  };

  const options = {
    responsive: true,
    plugins: {
      legend: {
        display: true,
        position: "top" as const,
      },
      tooltip: {
        callbacks: {
          label: (context: any) => {
            const table = tables[context.dataIndex];
            return `${table.total_size_mb} MB • Rows: ${table.rows}`;
          },
        },
      },
    },
    scales: {
      x: {
        ticks: {
          autoSkip: false, // show all table labels
          maxRotation: 45,
          minRotation: 0,
        },
      },
      y: {
        beginAtZero: true,
        title: {
          display: true,
          text: "Size (MB)",
        },
      },
    },
  };

  return (
    <div className="p-4 rounded-xl shadow mb-4">
      <h2 className="text-lg font-semibold mb-4">Database Tables Size</h2>
      <Bar data={data} options={options} />
    </div>
  );
}