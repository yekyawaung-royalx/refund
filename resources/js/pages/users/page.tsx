"use client";

import * as React from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  Command,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandEmpty,
} from "@/components/ui/command";
import { Check, Eye, Pencil, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Users", href: "/users" },
];

export default function UsersPage() {
  const { users, auth } = usePage().props as any;

  const data = users?.data ?? [];
  const allColumns = data.length > 0 ? Object.keys(data[0]) : [];

  const [visibleColumns, setVisibleColumns] = React.useState(
    allColumns.slice(0, 8)
  );
  const [columnsPopoverOpen, setColumnsPopoverOpen] = React.useState(false);

  const displayValue = (value: any) =>
    value === null || value === undefined || value === "" ? "-" : value;

  const handleDelete = (id: number) => {
    if (!confirm("Are you sure you want to delete this user?")) return;

    router.delete(`/users/${id}`, {
      onSuccess: () => {
        console.log("User deleted");
      },
    });
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Users" />

      <div className="p-4">
        <Card>
            <CardHeader className="flex flex-col md:flex-row md:items-center gap-4">
                        <div className="basis-2/5">
                          <CardTitle>
              Users List
              <div className="mt-2 text-sm font-normal">
                Total Records:{" "}
                <span className="inline-block bg-sky-100 text-sky-800 text-xs font-semibold px-2 py-1 rounded-full">
                  {users.total}
                </span>
              </div>
            </CardTitle>
                        </div>
            
                        <div className="basis-3/5 flex justify-end items-center gap-2 flex-wrap">
                        <Popover
              open={columnsPopoverOpen}
              onOpenChange={setColumnsPopoverOpen}
            >
              <PopoverTrigger asChild>
                <Button variant="outline">Columns</Button>
              </PopoverTrigger>
              <PopoverContent className="w-56 p-0">
                <Command>
                  <CommandInput placeholder="Search column..." />
                  <CommandEmpty>No columns found</CommandEmpty>
                  <CommandGroup>
                    {allColumns.map((col: string) => (
                      <CommandItem
                        key={col}
                        onSelect={() =>
                          setVisibleColumns((prev) =>
                            prev.includes(col)
                              ? prev.filter((c) => c !== col)
                              : [...prev, col]
                          )
                        }
                      >
                        <Check
                          className={cn(
                            "mr-2 h-4 w-4",
                            visibleColumns.includes(col)
                              ? "opacity-100"
                              : "opacity-0"
                          )}
                        />
                        {col}
                      </CommandItem>
                    ))}
                  </CommandGroup>
                </Command>
              </PopoverContent>
            </Popover>
                        </div>
                      </CardHeader>

          <CardContent>
             <Table>
              <TableHeader>
                <TableRow>
                  {visibleColumns.map((col) => (
                    <TableHead key={col}>{col}</TableHead>
                  ))}
                  <TableHead className="text-right">Action</TableHead>
                </TableRow>
              </TableHeader>

              <TableBody>
                {data.length > 0 ? (
                  data.map((item: any, idx: number) => (
                    <TableRow key={idx}>
                      {visibleColumns.map((col) => (
                        <TableCell key={col}>
                          {displayValue(item[col])}
                        </TableCell>
                      ))}

                      {/* Action Buttons */}
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-2">

                          {/* View */}
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => router.visit(`/users/${item.id}`)}
                            className="flex items-center gap-1"
                          >
                            <Eye className="h-4 w-4" />
                            View
                          </Button>

                          {/* Edit */}
                          {auth?.user?.role === "admin" ? (
                          <Button
                            size="sm"
                            variant="secondary"
                            onClick={() => router.visit(`/users/${item.id}/edit`)}
                            className="flex items-center gap-1"
                          >
                            <Pencil className="h-4 w-4" />
                            Edit
                          </Button>
                          ) : null}

                          {/* Delete */}
                          {auth?.user?.role === "admin" ? (
                          <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => handleDelete(item.id)}
                            className="flex items-center gap-1"
                          >
                            <Trash2 className="h-4 w-4" />
                            Delete 
                          </Button>
                          ) : null}

                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell
                      colSpan={visibleColumns.length + 1}
                      className="text-center"
                    >
                      No users found
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>

            {/* Pagination */}
            <div className="flex justify-end items-center mt-4 gap-2">
              <Button
                size="sm"
                disabled={!users.prev_page_url}
                onClick={() =>
                  users.prev_page_url &&
                  router.visit(users.prev_page_url)
                }
              >
                Previous
              </Button>

              <span>
                Page {users.current_page} of {users.last_page}
              </span>

              <Button
                size="sm"
                disabled={!users.next_page_url}
                onClick={() =>
                  users.next_page_url &&
                  router.visit(users.next_page_url)
                }
              >
                Next
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}