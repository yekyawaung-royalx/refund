"use client";

import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { router } from "@inertiajs/react";

export default function ReportingTable({ results, execution_time_ms, used_partitions }: any) {

  const data = results?.data ?? [];

  const displayValue = (value: any) =>
    value === null || value === undefined || value === "" ? "-" : value;

  return (
    <Card>

      <CardHeader>

        <CardTitle>
          Results In {(execution_time_ms / 1000).toFixed(2)} s
        </CardTitle>

        <div className="flex gap-4 text-sm">

          <div>
            Records:
            <span className="ml-2 px-2 py-1 bg-sky-100 text-sky-700 rounded">
              {results.total}
            </span>
          </div>

          <div>
            Partitions:
            <span className="ml-2 px-2 py-1 bg-green-700 text-white rounded">
              {used_partitions}
            </span>
          </div>

        </div>

      </CardHeader>

      <CardContent className="overflow-x-auto">

        <Table>

          <TableHeader>
            <TableRow>
              {data.length > 0 &&
                Object.keys(data[0]).map((col) => (
                  <TableHead key={col}>{col}</TableHead>
                ))}
            </TableRow>
          </TableHeader>

          <TableBody>

            {data.length > 0 ? (
              data.map((item: any, idx: number) => (
                <TableRow key={idx}>
                  {Object.keys(item).map((col) => (
                    <TableCell key={col}>
                      {displayValue(item[col])}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={10} className="text-center">
                  No results
                </TableCell>
              </TableRow>
            )}

          </TableBody>

        </Table>

        {/* Pagination */}

        <div className="flex justify-end items-center mt-4 gap-2">

          <Button
            size="sm"
            disabled={!results.prev_page_url}
            onClick={() => router.visit(results.prev_page_url)}
          >
            Previous
          </Button>

          <span>
            Page {results.current_page} of {results.last_page}
          </span>

          <Button
            size="sm"
            disabled={!results.next_page_url}
            onClick={() => router.visit(results.next_page_url)}
          >
            Next
          </Button>

        </div>

      </CardContent>

    </Card>
  );
}