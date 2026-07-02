"use client";

import * as React from "react";
import AppLayout from "@/layouts/app-layout";
import { Head, usePage } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import { PageProps as InertiaPageProps } from "@inertiajs/core";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Finance Report", href: "/finance-report" },
  { title: "Branches Deposit", href: "/finance-report/branches-deposit" },
  { title: "View File", href: "#" },
];

interface PageProps extends InertiaPageProps {
  filename: string;
  headers: string[];
  rows: string[][];
}

export default function View() {
  const { filename, headers, rows } =  usePage<PageProps>().props;

  const [search, setSearch] = React.useState("");
  const [page, setPage] = React.useState(1);

  const perPage = 200;

  // search filter
  const filteredRows = React.useMemo(() => {
    if (!search) return rows;

    return rows.filter((row) =>
      row.some((cell) =>
        String(cell).toLowerCase().includes(search.toLowerCase())
      )
    );
  }, [search, rows]);
  
  const ACCOUNT_HEADER = "Account";

  const formatCell = (cell: any, colIndex: number) => {
  const header = headers[colIndex];

  if (header === ACCOUNT_HEADER) {
    const str = String(cell);
    if (/^\d+\.00$/.test(str)) {
      return str.replace(".00", "");
    }
  }

  return String(cell ?? "");
};

  // pagination
  const totalPages = Math.ceil(filteredRows.length / perPage);

  const paginatedRows = filteredRows.slice(
    (page - 1) * perPage,
    page * perPage
  );

  React.useEffect(() => {
    setPage(1);
  }, [search]);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={filename} />

      <div className="p-4">
        <Card>

          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle className="text-xl">
              {filename}
              <span className="ml-3 text-sm text-muted-foreground">
                ({filteredRows.length} rows)
              </span>
            </CardTitle>

            <Input
              placeholder="Search ..."
              className="w-[250px]"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </CardHeader>

          <CardContent>

            <div className="border rounded-md">

              <Table>
                <TableHeader>
                  <TableRow>
                    {headers.map((h, idx) => (
                      <TableHead key={idx} className="whitespace-nowrap">
                        {h}
                      </TableHead>
                    ))}
                  </TableRow>
                </TableHeader>

                <TableBody>
                  {paginatedRows.length > 0 ? (
                    paginatedRows.map((row, i) => (
                      <TableRow key={i}>
                        {row.map((cell, j) => (
                          <TableCell key={j} className="whitespace-nowrap">
                            {formatCell(cell, j)}
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
                        No results found
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>

              </Table>

            </div>

            {/* pagination */}

            <div className="flex justify-end items-center mt-4 gap-3">

              <Button
                size="sm"
                className="text-white bg-green-500 hover:bg-green-600"
                disabled={page === 1}
                onClick={() => setPage((p) => p - 1)}
              >
                Previous
              </Button>

              <span className="text-sm">
                Page {page} of {totalPages}
              </span>

              <Button
                size="sm"
                className="text-white bg-green-500 hover:bg-green-600"
                disabled={page === totalPages}
                onClick={() => setPage((p) => p + 1)}
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