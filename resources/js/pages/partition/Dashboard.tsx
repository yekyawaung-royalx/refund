import AppLayout from "@/layouts/app-layout";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { router, Head } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import PartitionSizeChart from "./PartitionSizeChart";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Settings", href: "/settings" },
  { title: "Partitions", href: "#" },
];

export default function Dashboard({
  partitions,
  total_partitions,
  next_partition_exists,
  next_partition_name,
}: any) {

  /* =========================
     Helpers
  ========================== */

  const formatSize = (bytes: number) => {
    if (!bytes) return "0 MB";
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + " KB";
    if (bytes < 1024 * 1024 * 1024)
      return (bytes / 1024 / 1024).toFixed(2) + " MB";
    return (bytes / 1024 / 1024 / 1024).toFixed(2) + " GB";
  };

  const formatPartitionDesc = (desc: string) => {
    if (!desc || desc === "MAXVALUE") return desc;
    if (desc.length === 6) {
      const year = desc.slice(0, 4);
      const month = desc.slice(4, 6);
      return `${year}-${month}`;
    }
    return desc;
  };

  const formatPartitionName = (name: string) => {
    if (name?.length === 7) {
      return `${name.slice(1, 5)}-${name.slice(5)}`;
    }
    return name;
  };

  const currentMonth = new Date().toISOString().slice(0, 7); // YYYY-MM

  /* =========================
     Sorted partitions
  ========================== */

  const sortedPartitions = [...(partitions.data || [])].sort((a, b) =>
    a.PARTITION_NAME.localeCompare(b.PARTITION_NAME)
  );

  const totalSizeMB = sortedPartitions.reduce(
    (sum, p) => sum + ((p.DATA_LENGTH + p.INDEX_LENGTH) / 1024 / 1024),
    0
  ).toFixed(2);

  const nextMonthLabel =
    next_partition_name?.length === 7
      ? `${next_partition_name.slice(1, 5)}-${next_partition_name.slice(5)}`
      : next_partition_name;

  /* =========================
     UI
  ========================== */

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Partitions" />

      <div className="p-6 space-y-6">

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
  {/* Summary Card */}
  <Card>
    <CardHeader>
      <CardTitle>Partition Summary</CardTitle>
    </CardHeader>
    <CardContent className="flex items-center justify-between">
      <div className="text-sm text-muted-foreground">
        Show Partitions: <span className="font-semibold text-green-500">{sortedPartitions.length}</span>
      </div>
      <div className="text-sm text-muted-foreground">
        Total Size: <span className="font-semibold text-green-500">{totalSizeMB}</span> MB
      </div>
    </CardContent>
  </Card>

  {/* Next Partition Status */}
  <Card>
    <CardHeader>
      <CardTitle>Next Month Partition</CardTitle>
    </CardHeader>
    <CardContent className="flex items-center justify-between">
      <div className="text-sm text-muted-foreground">
        <div className="flex items-center gap-3">
        {next_partition_exists ? (
          <Badge className="bg-green-600 text-white">Ready</Badge>
        ) : (
          <Badge variant="destructive">Missing</Badge>
        )}

        <span className="text-sm text-muted-foreground">
          {nextMonthLabel}
        </span>
      </div>
      </div>
      <div className="text-sm text-muted-foreground">
        Total Partitions: <span className="font-semibold text-green-500">{total_partitions}</span>
      </div>
    </CardContent>
  </Card>
</div>

        {/* Table + Chart */}
        <Card>
  <CardHeader>
    <CardTitle>Partitions</CardTitle>
  </CardHeader>

  <CardContent>

    <Tabs defaultValue="list" className="w-full">

      <TabsList className="mb-4">
        <TabsTrigger value="list">Partition List</TabsTrigger>
        <TabsTrigger value="size">Partition Size</TabsTrigger>
      </TabsList>

      {/* Partition List */}
      <TabsContent value="list" className="space-y-4">

        <div className="overflow-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-muted">
              <tr className="border-b">
                <th className="text-left py-3 px-3">Partition</th>
                <th className="text-left py-3 px-3">Month</th>
                <th className="text-left py-3 px-3">Less Than</th>
                <th className="text-left py-3 px-3">Rows</th>
                <th className="text-left py-3 px-3">Data Size</th>
                <th className="text-left py-3 px-3">Index Size</th>
              </tr>
            </thead>

            <tbody>
              {sortedPartitions.length === 0 && (
                <tr>
                  <td colSpan={6} className="text-center py-6 text-muted-foreground">
                    No partitions found.
                  </td>
                </tr>
              )}

              {sortedPartitions.map((p: any) => {
                const monthLabel = formatPartitionName(p.PARTITION_NAME);
                const isCurrent = monthLabel === currentMonth;

                return (
                  <tr
                    key={p.PARTITION_NAME}
                    className={`border-b hover:bg-muted/40 ${
                      isCurrent ? "bg-green-500" : ""
                    }`}
                  >
                    <td className="px-3">{p.PARTITION_NAME}</td>

                    <td className="py-2 px-3 font-medium">
                      {monthLabel}

                      {isCurrent && (
                        <Badge className="ml-2 text-green-600 bg-white text-xs">
                          Current
                        </Badge>
                      )}
                    </td>

                    <td className="px-3">
                      {formatPartitionDesc(p.PARTITION_DESCRIPTION)}
                    </td>

                    <td className="px-3">
                      {p.TABLE_ROWS?.toLocaleString()}
                    </td>

                    <td className="px-3">
                      {formatSize(p.DATA_LENGTH)}
                    </td>

                    <td className="px-3">
                      {formatSize(p.INDEX_LENGTH)}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <div className="flex justify-end items-center gap-4 pt-2">
          <Button
            size="sm"
            variant="outline"
            disabled={!partitions.prev_page_url}
            onClick={() =>
              partitions.prev_page_url &&
              router.visit(partitions.prev_page_url)
            }
          >
            Previous
          </Button>

          <span className="text-sm text-muted-foreground">
            Page {partitions.current_page} of {partitions.last_page}
          </span>

          <Button
            size="sm"
            variant="outline"
            disabled={!partitions.next_page_url}
            onClick={() =>
              partitions.next_page_url &&
              router.visit(partitions.next_page_url)
            }
          >
            Next
          </Button>
        </div>

      </TabsContent>

      {/* Partition Size Chart */}
      <TabsContent value="size">
        <PartitionSizeChart partitions={sortedPartitions} />
      </TabsContent>

    </Tabs>

  </CardContent>
</Card>
      </div>
    </AppLayout>
  );
}