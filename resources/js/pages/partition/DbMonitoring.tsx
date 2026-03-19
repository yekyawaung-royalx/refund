import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { Head } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import TableSizeChart from "./TableSizeChart";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Settings", href: "/settings" },
  { title: "Database Monitoring", href: "#" },
];

interface Table {
  name: string;
  rows: number;
  data_size_mb: string;
  index_size_mb: string;
  total_size_mb: string;
}

interface Props {
  tables: Table[];
}

export default function DbMonitoring({ tables }: Props) {
  const [activeTab, setActiveTab] = useState("list");

  const formatSize = (mb: string) => `${parseFloat(mb).toLocaleString()} MB`;

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Database Monitoring" />
      <div className="p-6 space-y-6">
        <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
          <TabsList>
            <TabsTrigger value="list">Tables List</TabsTrigger>
            <TabsTrigger value="size">Tables Size</TabsTrigger>
          </TabsList>

          <TabsContent value="list">
            <Card className="h-full">
              <CardHeader>
                <CardTitle>Tables List</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="overflow-auto rounded-lg border">
                  <table className="w-full text-sm">
                    <thead className="bg-muted">
                      <tr className="border-b">
                        <th className="text-left py-3 px-3">Table</th>
                        <th className="text-left py-3 px-3">Rows</th>
                        <th className="text-left py-3 px-3">Data Size</th>
                        <th className="text-left py-3 px-3">Index Size</th>
                        <th className="text-left py-3 px-3">Total Size</th>
                      </tr>
                    </thead>
                    <tbody>
                      {tables.length === 0 && (
                        <tr>
                          <td colSpan={5} className="text-center py-6 text-muted-foreground">
                            No tables found.
                          </td>
                        </tr>
                      )}
                      {tables.map((t) => (
                        <tr key={t.name} className="border-b hover:bg-muted/40">
                          <td className="py-2 px-3 font-medium">{t.name}</td>
                          <td className="px-3">{t.rows.toLocaleString()}</td>
                          <td className="px-3">{formatSize(t.data_size_mb)}</td>
                          <td className="px-3">{formatSize(t.index_size_mb)}</td>
                          <td className="px-3">{formatSize(t.total_size_mb)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="size">
            <Card className="h-full">
              <CardHeader>
                <CardTitle>Tables Size Chart</CardTitle>
              </CardHeader>
              <CardContent>
                <TableSizeChart tables={tables} />
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </AppLayout>
  );
}