"use client";

import * as React from "react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { Head, usePage, router } from "@inertiajs/react";

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
  EyeIcon,
  Pencil,
  Trash,
} from "lucide-react";

import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";

import { toast } from "sonner";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Analytics Accounts", href: "#" }
];

export default function AnalyticsAccount() {

  const page = usePage().props as any;

  const analytics = page.analytics ?? {
    data: [],
    current_page: 1,
    last_page: 1,
    total: 0,
    next_page_url: null,
    prev_page_url: null,
  };

  const [search, setSearch] = React.useState("");

  const [deleteId, setDeleteId] = React.useState<number | null>(null);
  const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);

  const filteredData = (analytics.data ?? []).filter((item: any) =>
    Object.values(item).some((value) =>
      String(value ?? "")
        .toLowerCase()
        .includes(search.toLowerCase())
    )
  );

  const confirmDelete = (id: number) => {
    setDeleteId(id);
    setDeleteDialogOpen(true);
  };

  const handleDelete = () => {

    if (!deleteId) return;

    router.delete(`/analytics-accounts/${deleteId}`, {
      onSuccess: () => {
        toast.success("Account deleted successfully");
        setDeleteDialogOpen(false);
        setDeleteId(null);
      },

      onError: () => {
        toast.error("Delete failed");
      },
    });
  };

  const handleView = (id: number) => {
    router.visit(`/analytics-accounts/${id}`);
  };

  const handleEdit = (id: number) => {
    router.visit(`/analytics-accounts/${id}/edit`);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Analytics Accounts" />

      <div className="p-4">

        <Card>

          {/* Header */}
          <CardHeader className="flex flex-col md:flex-row md:items-center justify-between gap-4">

            <CardTitle className="text-xl">
              Analytics Accounts ({analytics.total})
            </CardTitle>

            <Input
              placeholder="Search..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full md:w-72"
            />

          </CardHeader>

          {/* Table */}
          <CardContent>

            <Table>

              <TableHeader>
                <TableRow>
                  <TableHead>ID</TableHead>
                  <TableHead>Account</TableHead>
                  <TableHead>Reference</TableHead>
                  <TableHead>Journal</TableHead>
                  <TableHead>Created</TableHead>
                  <TableHead>Actions</TableHead>
                </TableRow>
              </TableHeader>

              <TableBody>

                {filteredData.length > 0 ? (
                  filteredData.map((item: any) => (
                    <TableRow key={item.id}>

                      <TableCell>{item.id}</TableCell>

                      <TableCell className="font-medium">
                        {item.account}
                      </TableCell>

                      <TableCell>
                        <Badge variant="outline">
                          {item.reference}
                        </Badge>
                      </TableCell>

                      <TableCell>
                        {item.journal ? (
                            <Badge className="border border-green-600 text-green-600 bg-transparent rounded-2xl font-semibold">
                            {item.journal}
                            </Badge>
                        ) : (
                            <Badge className="border border-amber-600 text-amber-600 bg-transparent rounded-2xl">
                            No Set
                            </Badge>
                        )}
                        </TableCell>

                      <TableCell className="text-sm text-muted-foreground">
                        {item.created_at}
                      </TableCell>

                      {/* Actions */}
                      <TableCell className="flex gap-2">

                        <Button
                          size="sm"
                          variant="secondary"
                          onClick={() => handleView(item.id)}
                        >
                          <EyeIcon className="h-4 w-4" />
                        </Button>

                        <Button
                          size="sm"
                          variant="secondary"
                          onClick={() => handleEdit(item.id)}
                        >
                          <Pencil className="h-4 w-4" />
                        </Button>

                        <Button
                          size="sm"
                          variant="destructive"
                          onClick={() => confirmDelete(item.id)}
                        >
                          <Trash className="h-4 w-4" />
                        </Button>

                      </TableCell>

                    </TableRow>
                  ))
                ) : (

                  <TableRow>
                    <TableCell colSpan={6} className="text-center">
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
                disabled={!analytics.prev_page_url}
                onClick={() =>
                  analytics.prev_page_url &&
                  router.visit(analytics.prev_page_url)
                }
              >
                Previous
              </Button>

              <span>
                Page {analytics.current_page} of {analytics.last_page}
              </span>

              <Button
                size="sm"
                disabled={!analytics.next_page_url}
                onClick={() =>
                  analytics.next_page_url &&
                  router.visit(analytics.next_page_url)
                }
              >
                Next
              </Button>

            </div>

          </CardContent>

        </Card>

      </div>

      {/* Delete Dialog */}
      <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>

        <DialogContent>

          <DialogHeader>
            <DialogTitle>Delete Account</DialogTitle>
          </DialogHeader>

          <p>
            Are you sure you want to delete this analytics account?
          </p>

          <DialogFooter className="mt-4">

            <Button
              variant="outline"
              onClick={() => setDeleteDialogOpen(false)}
            >
              Cancel
            </Button>

            <Button
              variant="destructive"
              onClick={handleDelete}
            >
              Delete
            </Button>

          </DialogFooter>

        </DialogContent>

      </Dialog>

    </AppLayout>
  );
}