"use client";

import * as React from "react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { format } from "date-fns";
import { Head, usePage, router } from "@inertiajs/react";
import { PageProps as InertiaPageProps } from "@inertiajs/core";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
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
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import {
  CheckCircle,
  XCircle,
  Loader2,
  Download,
  BugIcon,
  EyeIcon,
  Trash,
  DownloadCloudIcon,
} from "lucide-react";
import { toast } from "sonner";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Refunds", href: "/refunds" },
  { title: "Uploaded Files", href: "#" },
];

export interface FileItem {
  id: number;
  title: string;
  category: string;
  type: string;
  filename: string;
  folder: string;
  file_path: string;
  failed_path: string;
  total_rows: number;
  processed_rows: number;
  failed_rows: number;
  status: string;
  attempts: number;
  processed_duration: string;
  error_message: string | null;
  uploaded_by_id: number;
  uploaded_by_name: string;
  created_at: string;
  updated_at: string;
}

export interface FilePagination {
  data: FileItem[];
  current_page: number;
  last_page: number;
  total: number;
  next_page_url: string | null;
  prev_page_url: string | null;
}

export interface PageProps extends InertiaPageProps {
  files: FilePagination;
}

export default function UploadedFile() {
  const { files, auth } = usePage<PageProps>().props as any;
  const [search, setSearch] = React.useState("");
  const [selectedError, setSelectedError] = React.useState<string | null>(null);

  const columnWidths: Record<string, string> = {
  created_at: "w-[120px]",
  title: "w-[180px]",
  category: "w-[100px]",
  type: "w-[140px]",
  total_rows: "w-[120px]",
  process_stats: "w-[140px]",
  job_stats: "w-[140px]",
  status: "w-[140px]",
  actions: "w-[200px]",
};

  const [deleteId, setDeleteId] = React.useState<number | null>(null);
  const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);

  const filteredData = (files.data ?? []).filter((item: FileItem) =>
    Object.values(item).some((value) =>
      String(value ?? "")
        .toLowerCase()
        .includes(search.toLowerCase())
    )
  );

  // Status Badge with Icon + Animation
  const getStatusBadge = (status: string) => {
    switch (status) {
      case "completed":
        return (
          <Badge className="bg-green-500 text-white flex items-center gap-1">
            <CheckCircle className="h-4 w-4" />
            Completed
          </Badge>
        );
      case "failed":
        return (
          <Badge className="bg-red-500 text-white flex items-center gap-1">
            <XCircle className="h-4 w-4" />
            Failed
          </Badge>
        );
      case "processing":
        return (
          <Badge className="bg-yellow-500 text-white flex items-center gap-1">
            <Loader2 className="h-4 w-4 animate-spin" />
            Processing
          </Badge>
        );
      default:
        return (
          <Badge className="bg-yellow-500 text-white flex items-center gap-1">
            <Loader2 className="h-4 w-4 animate-spin" />
            {status}
          </Badge>
        );
    }
  };

 const confirmDelete = (id: number) => {
    setDeleteId(id);
    setDeleteDialogOpen(true);
  };

  const handleDelete = () => {
    if (!deleteId) return;

    router.delete(`/refunds/uploaded-files/${deleteId}`, {
      onSuccess: () => {
        toast.success("File and related data deleted successfully.");
        setDeleteDialogOpen(false);
        setDeleteId(null);
      },

      onError: () => {
        toast.error("Failed to delete file");
      },
    });
  };

  const handleDownload = (id: number) => {
    window.open(`/refunds/uploaded-files/${id}/download`, "_blank");
  };

  const handleFailedDownload = (id: number) => {
  window.open(`/refunds/failed-files/${id}/download`, "_blank");
};

  const handleView = (id: number) => {
    router.visit(`/refunds/uploaded-files/${id}`);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Uploaded Files" />

      <div className="p-4">
        <Card>
          <CardHeader className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <CardTitle className="text-xl">
              Uploaded Files ({files.total})
            </CardTitle>

            <Input
              placeholder="Search..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full md:w-72"
            />
          </CardHeader>

          <CardContent>
            <Table className="table-fixed">
              <TableHeader>
                <TableRow>
<TableHead className={columnWidths.created_at}>Created Date</TableHead>
<TableHead className={columnWidths.title}>Title</TableHead>
<TableHead className={columnWidths.category}>Category</TableHead>
<TableHead className={columnWidths.type}>Type</TableHead>
<TableHead className={columnWidths.total_rows}>Total Rows</TableHead>
<TableHead className={columnWidths.process_stats}>Process Stats</TableHead>
<TableHead className={columnWidths.job_stats}>Job Stats</TableHead>
<TableHead className={columnWidths.status}>Status</TableHead>
<TableHead className={columnWidths.actions}>Actions</TableHead>
                </TableRow>
              </TableHeader>

              <TableBody>
                {filteredData.length > 0 ? (
                  filteredData.map((item: FileItem) => (
                    <TableRow key={item.id}>
                      <TableCell className={columnWidths.created_at}>
                        {item.created_at? format(new Date(item.created_at), "yyyy-MM-dd")
                                                  : "-"}
                      </TableCell>
                      <TableCell className={cn("truncate", columnWidths.title)}>{item.title}</TableCell>
                      <TableCell className={columnWidths.category}>
                        {item.category === "refunded" ? (
                          <Badge className="border border-green-600 text-green-600 bg-transparent rounded-2xl">
                            Refunded
                          </Badge>
                        ) : (
                          <Badge className="border border-amber-500 text-amber-500 bg-transparent rounded-2xl">
                            No Refund
                          </Badge>
                        )}
                      </TableCell>
                      <TableCell className={columnWidths.category}>
                        {item.category === "refunded" ? (
                          <Badge className="border border-green-600 text-green-600 bg-transparent rounded-2xl">
                            {item.type}
                          </Badge>
                        ) : (
                          <Badge className="border border-amber-500 text-amber-500 bg-transparent rounded-2xl">
                            {item.type}
                          </Badge>
                        )}
                      </TableCell>
                      <TableCell className={cn("font-semibold", columnWidths.total_rows)}>{item.total_rows.toLocaleString()}</TableCell>
                      <TableCell className={columnWidths.process_stats}>
                        <span className="text-muted-foreground">Passed:</span> <span className="text-green-500">{item.processed_rows.toLocaleString()}</span> <br />
                        <span className="text-muted-foreground">Failed:</span> <span className="text-red-500">{item.failed_rows.toLocaleString()}</span>
                        </TableCell>
                        <TableCell className={columnWidths.job_stats}>
                          <span className="text-muted-foreground">Attempts:</span> {item.attempts} <br />
                          <span className="text-muted-foreground">Duration:</span> {item.processed_duration? Number(item.processed_duration).toFixed(2):0} s
                          </TableCell>
                      <TableCell className={columnWidths.status}>{getStatusBadge(item.status)}</TableCell>
                    <TableCell className={cn("flex gap-2", columnWidths.actions)}>
                      {item.error_message && (
                        <Button
                          size="sm"
                          className="text-white cursor-pointer bg-red-500 hover:bg-red-600"
                          onClick={() => setSelectedError(item.error_message)}
                        >
                          <BugIcon className="h-4 w-4" />
                        </Button>
                      )}

                      
<TooltipProvider>
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
                        size="sm"
                        variant="secondary"
                        onClick={() => handleDownload(item.id)}
                        className="flex cursor-pointer items-center gap-1"
                      >
                        <Download className="h-4 w-4" />
                      </Button>
      </TooltipTrigger>

      <TooltipContent>
        <p>Download Original CSV ({item.total_rows.toLocaleString()})</p>
      </TooltipContent>
    </Tooltip>
  </TooltipProvider>

  <TooltipProvider>
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
                        size="sm"
                        variant="secondary"
                        onClick={() => handleView(item.id)}
                        className="flex cursor-pointer items-center gap-1"
                      >
                        <EyeIcon className="h-4 w-4" />
                      </Button>
      </TooltipTrigger>

      <TooltipContent>
        <p>View Passed Data ({item.processed_rows.toLocaleString()})</p>
      </TooltipContent>
    </Tooltip>
  </TooltipProvider>
                      

                      

                      {/* Failed CSV Download Button */}
                      {item.failed_rows > 0 && item.failed_path && (
  <TooltipProvider>
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          size="sm"
          className="bg-orange-500 hover:bg-orange-600 cursor-pointer text-white"
          onClick={() => handleFailedDownload(item.id)}
        >
          <DownloadCloudIcon className="h-4 w-4" />
        </Button>
      </TooltipTrigger>

      <TooltipContent>
        <p>Download Failed CSV ({item.failed_rows.toLocaleString()})</p>
      </TooltipContent>
    </Tooltip>
  </TooltipProvider>
)}


                      {/* Delete Button */}
                      {auth?.user?.role === 'admin' && (
                      <TooltipProvider>
    <Tooltip>
      <TooltipTrigger asChild>
        <Button size="sm"
                        className="text-white bg-red-500 hover:bg-red-600 cursor-pointer flex items-center gap-1"
                        onClick={() => confirmDelete(item.id)}
                      >
                        <Trash className="h-4 w-4" /> 
                      </Button>
      </TooltipTrigger>

      <TooltipContent>
        <p>Delete File & Data</p>
      </TooltipContent>
    </Tooltip>
  </TooltipProvider>
                      )}
                      
                    </TableCell>
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={10} className="text-center">
                      No results found
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>

            {/* Pagination */}
            <div className="flex justify-end items-center gap-2 mt-4">
              <Button
                size="sm"
                className="text-white bg-green-500 hover:bg-green-600"
                disabled={!files.prev_page_url}
                onClick={() =>
                  files.prev_page_url &&
                  router.visit(files.prev_page_url)
                }
              >
                Previous
              </Button>

              <span>
                Page {files.current_page} of {files.last_page}
              </span>

              <Button
                size="sm"
                className="text-white bg-green-500 hover:bg-green-600"
                disabled={!files.next_page_url}
                onClick={() =>
                  files.next_page_url &&
                  router.visit(files.next_page_url)
                }
              >
                Next
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Delete Confirm Dialog */}
      <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete File</DialogTitle>
          </DialogHeader>

          <p>
            Are you sure you want to delete this file? This action cannot be
            undone.
          </p>

          <DialogFooter className="mt-4">
            <Button
              variant="outline"
              onClick={() => setDeleteDialogOpen(false)}
            >
              Cancel
            </Button>

            <Button variant="destructive" className="text-white" onClick={handleDelete}>
              Delete
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Error Dialog */}
      <Dialog
        open={!!selectedError}
        onOpenChange={() => setSelectedError(null)}
      >
        <DialogContent className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>Error Log</DialogTitle>
          </DialogHeader>

          <div className="max-h-[400px] overflow-auto bg-red-500 p-4 rounded-md">
            <pre className="text-sm text-white whitespace-pre-wrap">
              {selectedError}
            </pre>
          </div>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}