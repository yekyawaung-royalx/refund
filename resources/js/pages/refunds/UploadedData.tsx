"use client";
import { useState } from "react"
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
import {  CalendarIcon, Check, ChevronDown, Download, Filter, Search } from "lucide-react";
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
  detail?: UploadDataDetail;
  [key: string]: unknown;
}

interface UploadDataDetail {
    phone: string;
    mobile: string;
    operator: string;
    pickup_man: string;
    from_city: string;
    to_city: string;
    other: string;
    receiver_name: string;
    receiver_mobile: string;
    receiver_address: string;
    recipient_name: string;
    recipient_phone: string;
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
  //const data: UploadDataItem[] = results?.data ?? [];
  const data: UploadDataItem[] = (results?.data ?? []).map((item: UploadDataItem) => ({
    ...item,
    ...(item.detail ?? {}),
}));

const columnOrder = [
  "norefund_id",
  "refund_id",
  "outbound_date",
  "customer_order_reference",
  "customer_reference_no",
  "customer",
  "phone",
  "mobile",
  "operator",
  "pickup_man",
  "waybill_no",
  "from_city",
  "origin_branch",
  "from_analytic_account",
  "to_city",
  "destination_branch",
  "to_analytic_account",
  "payment_by",
  "payment_type",
  "vendor_type",
  "other",
  "receiver_name",
  "receiver_mobile",
  "receiver_address",
  "recipient_name",
  "recipient_phone",
  "service",
  "weight",
  "express_income_amount",
  "cod_total_amount",
  "cod_express_income_amount",
  "cod_income_amount",
  "cod_payable_amount",
  "refund",
  "service_type",
  "waybill_status",
  "delivered_date",
  "confirm_date",
  "payment_date",
  "accounting_date",
  "export_id",
  "branch_bank_deposit_export",
  "cod_refund_export",
  "sender_receiver_export",
  "created_at",
  "updated_at"
];

  const categories = [
    { label: "All", value: "all" },
    { label: "No Refund", value: "no-refund" },
    { label: "Refunded", value: "refunded" },
  ];
  const payment_by = [
    { label: "All", value: "all" },
    { label: "Sender Pay", value: "sender-pay" },
    { label: "Receiver Pay", value: "receiver-pay" },
  ];
  const payment_type = [
    { label: "All", value: "all" },
    { label: "Prepaid", value: "prepaid" },
    { label: "Postpaid", value: "postpaid" },
  ];

  const [filterOpen, setFilterOpen] = useState(false)
  const [selectedCategory, setSelectedCategory] = React.useState(
    category ?? "all"
  );
  const [selectedPaymentBy, setSelectedPaymentBy] = React.useState("all");
  const [selectedPaymentType, setSelectedPaymentType] = React.useState("all");;

  //const allColumns: string[] = data.length > 0 ? Object.keys(data[0]).filter(col => col !== "detail") : [];
  const allColumns: string[] = columnOrder.filter(
  (col) => data.length > 0 && col in data[0]
);
  // hide columns
  const hideColumnIndexes = [3, 6, 7, 20, 22, 23, 24, 27, 40, 41, 42];
  const initialInvisibleColumns = [
      "detail",
      ...hideColumnIndexes
          .map(i => allColumns[i])
          .filter(Boolean),
  ];

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
              {/* Filter Accordion Button */}
  <Button
    variant="outline"
    onClick={() => setFilterOpen(!filterOpen)}
    className="flex items-center gap-2"
  >
    <Filter className="h-4 w-4" />
    Filter
    <ChevronDown
      className={cn(
        "h-4 w-4 transition-transform duration-300",
        filterOpen && "rotate-180"
      )}
    />
  </Button>
              
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
                        params.payment_by = selectedPaymentBy as string;
                        params.payment_type = selectedPaymentType as string;

                        router.visit("/refunds/uploaded-data", { method: "get", data: params,preserveState: true, preserveScroll: true, });
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
                        params.payment_by = selectedPaymentBy as string;
                        params.payment_type = selectedPaymentType as string;
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
            {/* Accordion Content */}
            <div
              className={cn(
                "overflow-hidden transition-all duration-300 ease-in-out",
                filterOpen ? "max-h-[500px] opacity-100 mt-4" : "max-h-0 opacity-0"
              )}
            >
              <div className="w-full rounded-xl border bg-background/50 p-4">
                
                {/* Your Filters Here */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                  <div>
                    <label className="text-sm font-medium mb-2 block">
                      Payment By
                    </label>
                    <Popover>
                            <PopoverTrigger asChild>
                              <Button
                                variant="outline"
                                className="w-full justify-between flex items-center"
                              >
                                <span>{payment_by.find(c => c.value === selectedPaymentBy)?.label}</span>
                                <ChevronDown className="ml-2 h-4 w-4 opacity-70" />
                              </Button>
                            </PopoverTrigger>

                            <PopoverContent className="w-[140px] p-0">
                              <Command>
                                <CommandGroup>
                                  {payment_by.map((cat) => (
                                    <CommandItem key={cat.value} onSelect={() => setSelectedPaymentBy(cat.value)}>
                                      <Check
                                        className={cn(
                                          "mr-2 h-4 w-4",
                                          selectedPaymentBy === cat.value ? "opacity-100" : "opacity-0"
                                        )}
                                      />
                                      {cat.label}
                                    </CommandItem>
                                  ))}
                                </CommandGroup>
                              </Command>
                            </PopoverContent>
                          </Popover>
                  </div>
                  <div>
                    <label className="text-sm font-medium mb-2 block">
                      Payment Type
                    </label>
                    <Popover>
                            <PopoverTrigger asChild>
                              <Button
                                variant="outline"
                                className="w-full justify-between flex items-center"
                              >
                                <span>{payment_type.find(c => c.value === selectedPaymentType)?.label}</span>
                                <ChevronDown className="ml-2 h-4 w-4 opacity-70" />
                              </Button>
                            </PopoverTrigger>

                            <PopoverContent className="w-[140px] p-0">
                              <Command>
                                <CommandGroup>
                                  {payment_type.map((cat) => (
                                    <CommandItem key={cat.value} onSelect={() => setSelectedPaymentType(cat.value)}>
                                      <Check
                                        className={cn(
                                          "mr-2 h-4 w-4",
                                          selectedPaymentType === cat.value ? "opacity-100" : "opacity-0"
                                        )}
                                      />
                                      {cat.label}
                                    </CommandItem>
                                  ))}
                                </CommandGroup>
                              </Command>
                            </PopoverContent>
                          </Popover>
                  </div>
                  <div>
                    <label className="text-sm font-medium mb-2 block">
                      Refund Status
                    </label>
                    <Popover>
                            <PopoverTrigger asChild>
                              <Button
                                variant="outline"
                                className="w-full justify-between flex items-center"
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

                    
                  </div>

                  <div>
                    <label className="text-sm font-medium mb-2 block">
                      Accounting Date
                    </label>
            <DateRangePicker value={dateRange} onChange={setDateRange} />
                    
                  </div>

                  

                </div>
              </div>
            </div>
            <Table>
  <TableHeader>
    <TableRow>
      {data.length > 0 &&
        allColumns
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
        {allColumns
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
                  {item.refund === 1 ? "Refunded" : "No Refund"}
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