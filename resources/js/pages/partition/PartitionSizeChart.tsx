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
  partitions: any[];
}

export default function PartitionSizeChart({ partitions }: Props) {
  const data = {
    labels: partitions.map((p) => p.PARTITION_NAME),
    datasets: [
      {
        label: "Partition Size (MB)",
        data: partitions.map((p) => p.TOTAL_MB),
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
          label: (context: any) => `${context.raw} MB`,
        },
      },
    },
    scales: {
      x: {
        ticks: {
          autoSkip: false, // show all partition labels
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
      <h2 className="text-lg font-semibold mb-4">Partition Size</h2>
      <Bar data={data} options={options} />
    </div>
  );
}