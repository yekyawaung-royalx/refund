"use client";
import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage, router } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import { PageProps as InertiaPageProps } from "@inertiajs/core";

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
import { Input } from "@/components/ui/input";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Exports", href: "/exports" },
  { title: "View File", href: "#" },
];

interface PageProps extends InertiaPageProps {
  filename: string;
  headers: string[];
  rows: string[][];
  page: number;
  perPage: number;
  totalRows: number;
  totalPages: number;
  search: string;
}

export default function View() {
  const {
    filename,
    headers,
    rows,
    page,
    totalRows,
    totalPages,
    search,
  } = usePage<PageProps>().props;
  const [keyword, setKeyword] = useState(search ?? "");

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={filename} />

      <div className="p-4">
        <Card>

          <CardHeader className="flex flex-row items-center justify-between">

  <CardTitle className="text-xl">
    {filename}

    <span className="ml-3 text-sm text-muted-foreground">
      ({totalRows.toLocaleString()} rows)
    </span>
  </CardTitle>

  <Input
    className="w-[300px]"
    placeholder="Search..."
    value={keyword}
    onChange={(e) => setKeyword(e.target.value)}
    onKeyDown={(e) => {
      if (e.key === "Enter") {
        router.get(
          window.location.pathname,
          {
            page: 1,
            search: keyword,
          },
          {
            preserveScroll: true,
          }
        );
      }
    }}
  />

</CardHeader>

          <CardContent>

            <div className="border rounded-md overflow-auto">

              <Table>
                <TableHeader>
                  <TableRow>
                    {headers.map((header, index) => (
                      <TableHead
                        key={index}
                        className="whitespace-nowrap"
                      >
                        {header}
                      </TableHead>
                    ))}
                  </TableRow>
                </TableHeader>

                <TableBody>

                  {rows.length > 0 ? (
                    rows.map((row, rowIndex) => (
                      <TableRow key={rowIndex}>
                        {row.map((cell, cellIndex) => (
                          <TableCell
                            key={cellIndex}
                            className="whitespace-nowrap"
                          >
                            {cell || "-"}
                          </TableCell>
                        ))}
                      </TableRow>
                    ))
                  ) : (
                    <TableRow>
                      <TableCell
                        colSpan={headers.length}
                        className="text-center"
                      >
                        No data found
                      </TableCell>
                    </TableRow>
                  )}

                </TableBody>
              </Table>

            </div>

            <div className="flex items-center justify-between mt-4">

              <div className="text-sm text-muted-foreground">
                Showing{" "}
                {((page - 1) * 200) + 1}
                {" - "}
                {Math.min(page * 200, totalRows)}
                {" of "}
                {totalRows.toLocaleString()}
              </div>

              <div className="flex items-center gap-3">

                <Button
                  size="sm"
                  disabled={page === 1}
                  onClick={() =>
                    router.get(
                      window.location.pathname,
                      { page: page - 1, search: keyword, },
                      {
                        preserveState: true,
                        preserveScroll: true,
                      }
                    )
                  }
                >
                  Previous
                </Button>

                <span className="text-sm">
                  Page {page} of {totalPages}
                </span>

                <Button
                  size="sm"
                  disabled={page >= totalPages}
                  onClick={() =>
                    router.get(
                      window.location.pathname,
                      { page: page + 1, search: keyword, },
                      {
                        preserveState: true,
                        preserveScroll: true,
                      }
                    )
                  }
                >
                  Next
                </Button>

              </div>

            </div>

          </CardContent>

        </Card>
      </div>
    </AppLayout>
  );
}