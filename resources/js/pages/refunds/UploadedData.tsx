"use client";

import * as React from "react";
import { format } from "date-fns";
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
import {  CalendarIcon, Check, ChevronDown, Download, Search } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { Head, usePage, router } from "@inertiajs/react";
import { PageProps as InertiaPageProps } from "@inertiajs/core";
import {
  Command,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandEmpty,
} from "@/components/ui/command";
import { cn } from "@/lib/utils";
import { Calendar } from "@/components/ui/calendar";
import { toast } from "sonner";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Refunds", href: "/refunds" },
  { title: "Uploaded Data", href: "#" },
];

interface DateRange {
  from: Date | undefined;
  to: Date | undefined;
}

export interface UploadDataItem {
  id: number;
  norefund_upload_id: number;
  refund_upload_id: number;
  date: string;
  customer_order_reference: string;
  customer_reference_no: string;
  customer: string;
  phone: string;
  mobile: string;
  operator: string;
  pickup_man: string;
  waybill_no: string;
  from_city: string;
  origin_branch: string;
  from_analytic_account: string | null;
  to_city: string;
  destination_branch: string;
  to_analytic_account: string | null;
  other: string;
  receiver_name: string;
  receiver_mobile: string;
  receiver_address: string;
  recipient_name: string;
  recipient_phone: string;
  payment_by: string;
  payment_type: string;
  service: string;
  weight: string;
  express_income_amount: string;
  cod_total_amount: string;
  cod_express_income_amount: string;
  cod_income_amount: string;
  cod_payable_amount: string;
  refund: number;
  service_type: string;
  waybill_status: string;
  delivered_date: string | null;
  confirm_date: string | null;
  export_id: number | null;
  created_at: string;
  updated_at: string;

  [key: string]: unknown;
}

export interface UploadDataPagination {
  data: UploadDataItem[];
  current_page: number;
  last_page: number;
  total: number;
  next_page_url: string | null;
  prev_page_url: string | null;
}

export interface UploadedDataPageProps extends InertiaPageProps {
  results: UploadDataPagination;
  execution_time_ms: number;
  used_partitions: number;
  from: string | null;
  to: string | null;
}

function DateRangePicker({
  value,
  onChange,
}: {
  value: DateRange;
  onChange: (range: DateRange) => void;
}) {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button variant="outline" className="w-[240px] justify-between flex items-center font-normal">
          {value.from && value.to
            ? `${format(value.from, "MMM d, yyyy")} - ${format(value.to, "MMM d, yyyy")}`
            : "Select date range"}
            <CalendarIcon className="ml-2 h-4 w-4 opacity-70" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0">
        <Calendar
          mode="range"
          selected={value}
          onSelect={(range) => onChange(range as DateRange)}
          numberOfMonths={2}
          classNames={{
            day: "h-6 w-6",
            day_selected: "h-6 w-6",
          }}
        />
      </PopoverContent>
    </Popover>
  );
}

export default function UploadedData() {
  const { results, execution_time_ms, used_partitions, from, to, category } =
    usePage<UploadedDataPageProps>().props;

  const data: UploadDataItem[] = results?.data ?? [];

  const categories = [
    { label: "All", value: "all" },
    { label: "No Refund", value: "no-refund" },
    { label: "Refund", value: "refund" },
  ];

  const [selectedCategory, setSelectedCategory] = React.useState(
    category ?? "all"
  );

  const allColumns: string[] = data.length > 0 ? Object.keys(data[0]) : [];

  // hide columns
  const hideColumnIndexes = [0, 4, 7, 21, 23,];
  const initialInvisibleColumns = hideColumnIndexes
    .map((i) => allColumns[i])
    .filter((col): col is string => Boolean(col));

  const [invisibleColumns, setInvisibleColumns] = React.useState<string[]>(initialInvisibleColumns);
  const [columnsPopoverOpen, setColumnsPopoverOpen] = React.useState(false);

  const today = new Date();
  const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
  const lastDayOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);

  const [dateRange, setDateRange] = React.useState<DateRange>({
    from: from ? new Date(from) : firstDayOfMonth,
    to: to ? new Date(to) : lastDayOfMonth,
  });

  const displayValue = (value: unknown) =>
    value === null || value === undefined || value === "" ? "-" : String(value);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Uploaded Data" />
      <div className="p-4">
        <Card>
          <CardHeader className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <CardTitle>
                            Uploaded Data , Found Records: <span className="text-green-500 font-light">{results.total.toLocaleString()}</span>
                            <div className="flex items-center space-x-4 mt-2">
                              <div>
                                <span className="text-sm font-medium">Results In:</span>{" "}
                                <span className="inline-block border border-green-600 text-green-600 bg-transparent text-xs font-semibold px-2 py-0.5 rounded-full">{(execution_time_ms / 1000).toFixed(2)} s</span>
                              </div>
                              <div>
                                <span className="text-sm font-medium">Scan Partitions:</span>{" "}
                                <span className="inline-block border border-amber-500 text-amber-600 bg-transparent text-xs font-semibold px-2 py-0.5 rounded-full">{used_partitions}</span>
                              </div>
                            </div>
                          </CardTitle>

            <div className="basis-3/5 flex justify-end items-center gap-2 flex-wrap">
            // Vendor Type
            // Refund Type
            
              <Popover>
                <PopoverTrigger asChild>
                  <Button
                    variant="outline"
                    className="w-[140px] justify-between flex items-center"
                  >
                    <span>{categories.find(c => c.value === selectedCategory)?.label}</span>
                    <ChevronDown className="ml-2 h-4 w-4 opacity-70" />
                  </Button>
                </PopoverTrigger>

                <PopoverContent className="w-[140px] p-0">
                  <Command>
                    <CommandGroup>
                      {categories.map((cat) => (
                        <CommandItem key={cat.value} onSelect={() => setSelectedCategory(cat.value)}>
                          <Check
                            className={cn(
                              "mr-2 h-4 w-4",
                              selectedCategory === cat.value ? "opacity-100" : "opacity-0"
                            )}
                          />
                          {cat.label}
                        </CommandItem>
                      ))}
                    </CommandGroup>
                  </Command>
                </PopoverContent>
              </Popover>

              <DateRangePicker value={dateRange} onChange={setDateRange} />
              <Popover>
              <PopoverTrigger asChild>
                <Button
                  variant="outline"
                  className="w-[120px] justify-between flex items-center"
                >
                  <span>Actions</span>
                  <ChevronDown className="ml-2 h-4 w-4 opacity-70" />
                </Button>
              </PopoverTrigger>

              <PopoverContent className="w-[120px] p-0">
                <Command>
                  <CommandGroup>
                    <CommandItem 
                      className="justify-start"
                      onSelect={() => {
                        // Search action
                        const params: Record<string, string> = {};
                        if (dateRange.from) params.from = format(dateRange.from, "yyyy-MM-dd");
                        if (dateRange.to) params.to = format(dateRange.to, "yyyy-MM-dd");
                        params.category = selectedCategory as string;

                        router.visit("/refunds/uploaded-data", { method: "get", data: params });
                      }}
                    >
                      <Search className="mr-2 h-4 w-4" />
                      Search
                    </CommandItem>

                    <CommandItem
                      onSelect={() => {
                        // Download action
                        const params: Record<string, string> = {};
                        if (dateRange.from) params.from = format(dateRange.from, "yyyy-MM-dd");
                        if (dateRange.to) params.to = format(dateRange.to, "yyyy-MM-dd");
                        params.category = selectedCategory as string;
                        const queryString = new URLSearchParams(params).toString();

                        window.location.href = `/refunds/uploaded-data/download?${queryString}`;

                        toast.success("CSV export started! Check your downloads folder.");
                      }}
                    >
                      <Download className="mr-2 h-4 w-4" />
                      Download
                    </CommandItem>
                  </CommandGroup>
                </Command>
              </PopoverContent>
            </Popover>

              {/* Column Dropdown */}
              <Popover open={columnsPopoverOpen} onOpenChange={setColumnsPopoverOpen}>
                <PopoverTrigger asChild>
                  <Button variant="outline">Columns</Button>
                </PopoverTrigger>
                <PopoverContent className="w-56 p-0 min-h-[200px] max-h-[400px] overflow-y-auto">
                  <Command>
                    <CommandInput placeholder="Search column..." />
                    <CommandEmpty>No columns found</CommandEmpty>
                    <CommandGroup>
                      {allColumns.map((col) => (
                        <CommandItem
                          key={col}
                          onSelect={() => {
                            setInvisibleColumns((prev) =>
                              prev.includes(col)
                                ? prev.filter((c) => c !== col) // remove from invisible → show
                                : [...prev, col] // add to invisible → hide
                            );
                          }}
                        >
                          <Check
                            className={cn(
                              "mr-2 h-4 w-4",
                              !invisibleColumns.includes(col) ? "opacity-100" : "opacity-0"
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
      {data.length > 0 &&
        Object.keys(data[0])
          .filter((col) => !invisibleColumns.includes(col))
          .map((col) => (
            <TableHead
              key={col}
              className={cn("truncate", "w-[150px]")}
            >
              {col}
            </TableHead>
          ))}
    </TableRow>
  </TableHeader>

  <TableBody>
  {data.length > 0 ? (
    data.map((item, idx) => (
      <TableRow key={idx}>
        {Object.keys(item)
          .filter((col) => !invisibleColumns.includes(col))
          .map((col) => (
            <TableCell key={col} className={cn("truncate", "w-[150px]")}>
              {col === "refund" ? (
                <span
                  className={cn(
                    "px-2 py-1 rounded-full font-semibold text-xs",
                    item.refund === 1
                      ? "border border-green-600 text-green-600 bg-transparent rounded-2xl"
                      : "border border-amber-600 text-amber-600 bg-transparent rounded-2xl"
                  )}
                >
                  {item.refund === 1 ? "Refund" : "No Refund"}
                </span>
              ) : col === "waybill_no" ? (
                <span
                  className={cn(
                    "px-2 py-1 rounded-full font-semibold",
                    item.refund === 1 ? "text-green-500" : "text-red-500"
                  )}
                >
                  {displayValue(item[col])}
                </span>
              ) : (
                displayValue(item[col])
              )}
            </TableCell>
          ))}
      </TableRow>
    ))
  ) : (
    <TableRow>
      <TableCell
        colSpan={
          data.length > 0
            ? Object.keys(data[0]).filter((col) => !invisibleColumns.includes(col)).length
            : 1
        }
        className="text-center"
      >
        No results found
      </TableCell>
    </TableRow>
  )}
</TableBody>
</Table>

            <div className="flex justify-end items-center mt-4 gap-2">
              <Button
                size="sm"
                className="text-white bg-green-500 hover:bg-green-600"
                disabled={!results.prev_page_url}
                onClick={() => results.prev_page_url && router.visit(results.prev_page_url)}
              >
                Previous
              </Button>
              <span>Page {results.current_page} of {results.last_page}</span>
              <Button
                size="sm"
                className="text-white bg-green-500 hover:bg-green-600"
                disabled={!results.next_page_url}
                onClick={() => results.next_page_url && router.visit(results.next_page_url)}
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