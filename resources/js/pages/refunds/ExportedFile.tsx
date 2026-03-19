"use client";

import * as React from "react";
import { format } from "date-fns";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

import { Badge } from "@/components/ui/badge";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";

import { CheckCircle, XCircle, Download, BugIcon, EyeIcon, DownloadIcon } from "lucide-react";
import { toast } from "sonner";

/* ===================== Types ===================== */

export interface ExportFile {
  id: number;
  filename: string;
  total_rows?: number;
  created_at?: string;
  start_datetime?: string;
  end_datetime?: string;
  duration?: string;
  error_message?: string;
}

export interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

export interface PaginatedExports {
  data: ExportFile[];
  current_page: number;
  last_page: number;
  total: number;
  next_page_url: string | null;
  prev_page_url: string | null;
  first_page_url?: string;
  last_page_url?: string;
  links?: PaginationLink[];
  path?: string;
  per_page?: number;
  from?: number | null;
  to?: number | null;
}

/* ===================== Breadcrumbs ===================== */

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Exported Files", href: "#" },
];

/* ===================== Component ===================== */

export default function ExportedFile() {
  const page = usePage().props as { exports?: PaginatedExports };
  const exports: PaginatedExports = page.exports ?? {
    data: [],
    current_page: 1,
    last_page: 1,
    total: 0,
    next_page_url: null,
    prev_page_url: null,
  };

  const [search, setSearch] = React.useState("");
  const [selectedError, setSelectedError] = React.useState<string | null>(null);
  const [exportDialogOpen, setExportDialogOpen] = React.useState(false);
  const [exporting, setExporting] = React.useState(false);

  /* ===================== Helpers ===================== */

  const filteredData = exports.data.filter((item) =>
    Object.values(item).some((value) =>
      String(value ?? "").toLowerCase().includes(search.toLowerCase())
    )
  );

  const display = (v?: string | number | null) => (v === null || v === undefined || v === "" ? "-" : v);

  const getStatusBadge = (item: ExportFile) => {
    if (item.error_message) {
      return (
        <Badge className="bg-red-500 text-white flex items-center gap-1">
          <XCircle className="h-4 w-4" /> Failed
        </Badge>
      );
    }
    return (
      <Badge className="bg-green-600 text-white flex items-center gap-1">
        <CheckCircle className="h-4 w-4" /> Success
      </Badge>
    );
  };

  const handleDownload = (id: number) => {
    window.open(`/download-export/${id}`, "_blank");
  };

  const handleConfirmExport = async () => {
    try {
      setExporting(true);
      const response = await fetch("/exported-files/export/download", { method: "GET" });
      if (!response.ok) throw new Error("Export failed");
      toast.success("Export successful!");
      setExportDialogOpen(false);
      router.reload({ only: ["exports"] });
    } catch (err) {
      console.error(err);
      toast.error("Export failed!");
    } finally {
      setExporting(false);
    }
  };

  const handleView = (item: ExportFile) => {
    router.visit(`/exported-files/${item.id}`);
  };

  /* ===================== UI ===================== */

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Exported Files" />

      <div className="p-4">
        <Card>
          <CardHeader className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <CardTitle className="text-xl">
              Exported Files ({exports.total})
            </CardTitle>

            <div className="flex flex-col md:flex-row items-center gap-2 w-full md:w-auto">
              <Button
                size="sm"
                className="bg-green-500 text-white hover:bg-green-600"
                onClick={() => setExportDialogOpen(true)}
              >
                <DownloadIcon />
                Export Now
              </Button>

              <Input
                placeholder="Search..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full md:w-72"
              />
            </div>
          </CardHeader>

          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>ID</TableHead>
                  <TableHead>Exported Date</TableHead>
                  <TableHead>Filename</TableHead>
                  <TableHead>Total Rows</TableHead>
                  <TableHead>Start Time</TableHead>
                  <TableHead>End Time</TableHead>
                  <TableHead>Duration</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Actions</TableHead>
                </TableRow>
              </TableHeader>

              <TableBody>
                {filteredData.length > 0 ? (
                  filteredData.map((item) => (
                    <TableRow key={item.id}>
                      <TableCell>{item.id}</TableCell>
                      <TableCell>{item.created_at ? format(new Date(item.created_at), "yyyy-MM-dd") : "-"}</TableCell>
                      <TableCell className="truncate max-w-[200px]">{item.filename}</TableCell>
                      <TableCell>{display(item.total_rows?.toLocaleString())}</TableCell>
                      <TableCell>{item.start_datetime ? format(new Date(item.start_datetime), "HH:mm:ss") : "-"}s</TableCell>
                      <TableCell>{item.end_datetime ? format(new Date(item.end_datetime), "HH:mm:ss") : "-"}s</TableCell>
                      <TableCell>
                        {(() => {
                          const durationStr = display(item.duration); // e.g. "1.35s"
                          const seconds = parseFloat(String(durationStr).replace("s", ""));
                          let colorClass = "green-600";
                          if (seconds > 30) colorClass = "red-500";
                          else if (seconds > 10) colorClass = "amber-500";
                          return (
                            <Badge className={`border border-${colorClass} text-${colorClass} bg-transparent rounded-2xl`}>
                              {durationStr}
                            </Badge>
                          );
                        })()}
                      </TableCell>
                      <TableCell>{getStatusBadge(item)}</TableCell>
                      <TableCell className="flex gap-2">
                        {item.error_message && (
                          <Button
                            size="sm"
                            className="text-white bg-red-500 hover:bg-red-600"
                            onClick={() => setSelectedError(item.error_message ?? null)}
                          >
                            <BugIcon className="h-4 w-4" />
                          </Button>
                        )}
                        <Button size="sm" variant="secondary" onClick={() => handleDownload(item.id)}>
                          <Download className="h-4 w-4" />
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => handleView(item)}>
                          <EyeIcon className="h-4 w-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={9} className="text-center">
                      No export files found
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>

            {/* Pagination */}
            <div className="flex justify-end items-center gap-2 mt-4">
              <Button
                size="sm"
                disabled={!exports.prev_page_url}
                onClick={() => exports.prev_page_url && router.visit(exports.prev_page_url)}
              >
                Previous
              </Button>
              <span>
                Page {exports.current_page} of {exports.last_page}
              </span>
              <Button
                size="sm"
                disabled={!exports.next_page_url}
                onClick={() => exports.next_page_url && router.visit(exports.next_page_url)}
              >
                Next
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Error Dialog */}
      <Dialog open={!!selectedError} onOpenChange={() => setSelectedError(null)}>
        <DialogContent className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>Error Log</DialogTitle>
          </DialogHeader>
          <div className="max-h-[400px] overflow-auto bg-slate-950 p-4 rounded-md">
            <pre className="text-sm text-white whitespace-pre-wrap">{selectedError}</pre>
          </div>
        </DialogContent>
      </Dialog>

      {/* Export Confirmation Dialog */}
      <Dialog open={exportDialogOpen} onOpenChange={setExportDialogOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Confirm Export</DialogTitle>
          </DialogHeader>
          <div className="py-4">Are you sure you want to export the data now?</div>
          <DialogFooter className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => setExportDialogOpen(false)}>
              Cancel
            </Button>
            <Button
              className="bg-green-500 text-white hover:bg-green-600"
              onClick={handleConfirmExport}
              disabled={exporting}
            >
              {exporting ? "Exporting..." : "Confirm"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}