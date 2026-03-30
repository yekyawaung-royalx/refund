"use client";

import * as React from "react";
import { format } from "date-fns";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Check } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { Head, usePage, router } from "@inertiajs/react";
import { Command, CommandGroup, CommandInput, CommandItem, CommandEmpty } from "@/components/ui/command";
import { cn } from "@/lib/utils";
import { Label } from "@/components/ui/label";
import { Calendar } from "@/components/ui/calendar";
import { ChevronDown } from "lucide-react";
import { Checkbox } from "@/components/ui/checkbox";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Refunds", href: "/reports" },
  { title: "Results", href: "#" },
];

// --- Single Date Picker Component ---
function SingleDatePicker({
  value,
  onChange,
}: {
  value: Date;
  onChange: (date: Date) => void;
}) {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button variant="outline" className="w-full justify-start text-left font-normal">
          {value ? format(value, "MMM d, yyyy") : "Select date"}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0">
        <Calendar
          mode="single"
          selected={value}
          onSelect={(date) => date && onChange(date)}
          classNames={{ day: "h-6 w-6", day_selected: "h-6 w-6" }}
        />
      </PopoverContent>
    </Popover>
  );
}

export default function Reporting() {
  const { results, execution_time_ms, used_partitions, selected_date } = usePage().props as any;
  const data = results?.data ?? [];

// Pagination state
const [currentPage, setCurrentPage] = React.useState(1);
const rowsPerPage = 50;


  // --- State for filters ---
  const today = new Date();
  // --- State for selected rows ---
const [selectedRows, setSelectedRows] = React.useState<Set<number>>(new Set());
  const [selectedDate, setSelectedDate] = React.useState<Date>(today);

  const [rawData, setRawData] = React.useState<any[]>([]);
  const [filteredData, setFilteredData] = React.useState<any[]>([]);

  const [executionTime, setExecutionTime] = React.useState<number>(0);
  const [partition, setPartition] = React.useState<string>("");

  const [loading, setLoading] = React.useState(false);

  const [customerReferenceNo, setCustomerReferenceNo] = React.useState("");
  const [waybillNo, setWaybillNo] = React.useState("");

  const [refundFilter, setRefundFilter] = React.useState<string | null>(null);
  const [originBranchFilter, setOriginBranchFilter] = React.useState<string | null>(null);
  const [destinationBranchFilter, setDestinationBranchFilter] = React.useState<string | null>(null);
  const [waybillStatusFilter, setWaybillStatusFilter] = React.useState<string | null>(null);

  //const originBranches = Array.from(new Set(data.map((d: any) => d.origin_branch))).filter(Boolean);
  //const destinationBranches = Array.from(new Set(data.map((d: any) => d.destination_branch))).filter(Boolean);
  //const waybillStatuses = Array.from(new Set(data.map((d: any) => d.waybill_status))).filter(Boolean);

  const filtersDisabled = rawData.length === 0;

  const fetchData = async (date: Date) => {
    setLoading(true);

    const res = await fetch(`/reporting/search?date=${format(date, "yyyy-MM-dd")}`);
    const json = await res.json();

    const data = json.data ?? [];

    setRawData(data);
    setFilteredData(data);

    setExecutionTime(json.execution_time_ms ?? 0);
    setPartition(json.partition_scanned ?? "");

    setLoading(false);
  };

React.useEffect(() => {
    fetchData(today);
  }, []);

  const handleSearch = () => {
    fetchData(selectedDate);
  };

  const originBranches = React.useMemo(
    () => [...new Set(rawData.map((d) => d.origin_branch).filter(Boolean))],
    [rawData]
  );

  const destinationBranches = React.useMemo(
    () => [...new Set(rawData.map((d) => d.destination_branch).filter(Boolean))],
    [rawData]
  );

  const waybillStatuses = React.useMemo(
    () => [...new Set(rawData.map((d) => d.waybill_status).filter(Boolean))],
    [rawData]
  );

  const refundValues = React.useMemo(
    () => [...new Set(rawData.map((d) => String(d.refund)))],
    [rawData]
  );

  React.useEffect(() => {
    let result = [...rawData];

    if (customerReferenceNo)
      result = result.filter((r) =>
        r.customer_reference_no?.includes(customerReferenceNo)
      );

    if (waybillNo)
      result = result.filter((r) =>
        r.waybill_no?.includes(waybillNo)
      );

    if (refundFilter !== null)
      result = result.filter((r) => String(r.refund) === refundFilter);

    if (originBranchFilter)
      result = result.filter((r) => r.origin_branch === originBranchFilter);

    if (destinationBranchFilter)
      result = result.filter((r) => r.destination_branch === destinationBranchFilter);

    if (waybillStatusFilter)
      result = result.filter((r) => r.waybill_status === waybillStatusFilter);

    setFilteredData(result);
  }, [
    rawData,
    customerReferenceNo,
    waybillNo,
    refundFilter,
    originBranchFilter,
    destinationBranchFilter,
    waybillStatusFilter,
  ]);

  const displayValue = (v: any) =>
    v === null || v === undefined || v === "" ? "-" : v;

  const columns = filteredData.length ? Object.keys(filteredData[0]) : [];

  // filteredData ပြီးရင်
const totals = React.useMemo(() => {
  return filteredData.reduce(
    (acc, row) => {
      acc.express_income_amount += Number(row.express_income_amount || 0);
      acc.cod_income_amount += Number(row.cod_income_amount || 0);
      acc.cod_payable_amount += Number(row.cod_payable_amount || 0);
      return acc;
    },
    {
      express_income_amount: 0,
      cod_income_amount: 0,
      cod_payable_amount: 0,
    }
  );
}, [filteredData]); // filteredData ပြောင်းသွားရင် totals ကို auto update

// --- Update selectedRows whenever filteredData changes ---
React.useEffect(() => {
  // Default: all checked
  setSelectedRows(new Set(filteredData.map((_, i) => i)));
}, [filteredData]);

// --- Handle individual row toggle ---
const toggleRow = (index: number) => {
  setSelectedRows((prev) => {
    const newSet = new Set(prev);
    if (newSet.has(index)) newSet.delete(index);
    else newSet.add(index);
    return newSet;
  });
};

// --- Handle check all toggle ---
const toggleAllRows = () => {
  if (selectedRows.size === filteredData.length) {
    setSelectedRows(new Set());
  } else {
    setSelectedRows(new Set(filteredData.map((_, i) => i)));
  }
};

// Compute paginated data
const paginatedData = React.useMemo(() => {
  const start = (currentPage - 1) * rowsPerPage;
  const end = start + rowsPerPage;
  return filteredData.slice(start, end);
}, [filteredData, currentPage]);

// Compute total pages
const totalPages = Math.ceil(filteredData.length / rowsPerPage);

// --- Export CSV ---
const exportCSV = () => {
  const rowsToExport = filteredData.filter((_, i) => selectedRows.has(i));
  if (!rowsToExport.length) return alert("No rows selected");

  const header = Object.keys(rowsToExport[0]);
  const csvContent = [
    header.join(","), // header
    ...rowsToExport.map((row) => header.map((h) => `"${row[h]}"`).join(",")),
  ].join("\n");

  const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.setAttribute("download", `export_${format(new Date(), "yyyyMMdd_HHmmss")}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

// --- Compute totals based on selected rows ---
const selectedTotals = React.useMemo(() => {
  return filteredData.reduce(
    (acc, row, i) => {
      if (!selectedRows.has(i)) return acc; // selected rows only
      acc.express_income_amount += Number(row.express_income_amount || 0);
      acc.cod_income_amount += Number(row.cod_income_amount || 0);
      acc.cod_payable_amount += Number(row.cod_payable_amount || 0);
      return acc;
    },
    {
      express_income_amount: 0,
      cod_income_amount: 0,
      cod_payable_amount: 0,
    }
  );
}, [filteredData, selectedRows]);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Results" />
      <div className="p-4 space-y-4">
        {/* Reporting Card */}
       
          Reporting
          <div className="grid grid-cols-4 gap-4">

  {/* Left Column - Card */}
  <div className="col-span-1">
    <Card className="space-y-4 p-4">
      <CardHeader>
        <CardTitle>Search</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <div className="flex flex-col gap-1">
          <Label>Select Delivered Date</Label>
          <SingleDatePicker value={selectedDate} onChange={setSelectedDate} />
        </div>
        <Button variant="default" onClick={handleSearch}>
          Search
        </Button>
      </CardContent>
    </Card>
  </div>

  {/* Right Column - Card */}
  <div className="col-span-3">
    <Card className="space-y-4 p-4">
      <CardHeader>
        <CardTitle>Filters</CardTitle>
      </CardHeader>
      <CardContent className="grid grid-cols-3 gap-4">

        <div className="flex flex-col gap-1">
          <Label>Customer Reference No</Label>
          <input
            type="text"
            placeholder="Enter reference no..."
            className="border rounded px-2 py-1 w-full"
            value={customerReferenceNo}
            onChange={(e) => setCustomerReferenceNo(e.target.value)}
          />
        </div>

        <div className="flex flex-col gap-1">
          <Label>Waybill No</Label>
          <input
            type="text"
            placeholder="Enter waybill no..."
            className="border rounded px-2 py-1 w-full"
            value={waybillNo}
            onChange={(e) => setWaybillNo(e.target.value)}
          />
        </div>

        <div className="flex flex-col gap-1">
          <Label>Refund</Label>
          <Popover>
            <PopoverTrigger asChild>
              <Button variant="outline" size="sm" className="w-full justify-between">
                {refundFilter ?? "Select Refund"}
                <ChevronDown className="h-4 w-4 opacity-60" />
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[--radix-popover-trigger-width] p-0">
              <Command className="w-full">
                <CommandInput placeholder="Select Refund..." />
                <CommandEmpty>No options found</CommandEmpty>
                <CommandItem onSelect={() => setRefundFilter(null)}>
    <Check
      className={cn(
        "mr-2 h-4 w-4",
        refundFilter === null ? "opacity-100" : "opacity-0"
      )}
    />
    All
  </CommandItem>
                <CommandGroup>
                  {["0", "1"].map((val) => (
                    <CommandItem key={val} onSelect={() => setRefundFilter(val)}>
                      <Check className={cn("mr-2 h-4 w-4", refundFilter === val ? "opacity-100" : "opacity-0")} />
                      {val === "0" ? "No Refund" : "Refund"}
                    </CommandItem>
                  ))}
                </CommandGroup>
              </Command>
            </PopoverContent>
          </Popover>
        </div>

        <div className="flex flex-col gap-1">
          <Label>Origin Branch</Label>
          <Popover>
            <PopoverTrigger asChild>
              <Button variant="outline" size="sm" className="w-full justify-between">
                {originBranchFilter ?? "Select Origin Branch"}
                <ChevronDown className="h-4 w-4 opacity-60" />
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-60 p-0">
              <Command>
                <CommandInput placeholder="Search Origin Branch..." />
                <CommandEmpty>No options found</CommandEmpty>
                <CommandGroup>
                  <CommandItem onSelect={() => setOriginBranchFilter(null)}>
                    <Check className={cn(
                      "mr-2 h-4 w-4",
                      originBranchFilter === null ? "opacity-100" : "opacity-0"
                    )} />
                    All
                  </CommandItem>
                  {originBranches.map((val) => (
                    <CommandItem key={val} onSelect={() => setOriginBranchFilter(val)}>
                      <Check className={cn("mr-2 h-4 w-4", originBranchFilter === val ? "opacity-100" : "opacity-0")} />
                      {val}
                    </CommandItem>
                  ))}
                </CommandGroup>
              </Command>
            </PopoverContent>
          </Popover>
        </div>

        <div className="flex flex-col gap-1">
          <Label>Destination Branch</Label>
          <Popover>
            <PopoverTrigger asChild>
              <Button variant="outline" size="sm" className="w-full justify-between">
                {destinationBranchFilter ?? "Select Destination Branch"}
                <ChevronDown className="h-4 w-4 opacity-60" />
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-60 p-0">
              <Command>
                <CommandInput placeholder="Search Destination Branch..." />
                <CommandEmpty>No options found</CommandEmpty>
                <CommandGroup>
                  <CommandItem onSelect={() => setDestinationBranchFilter(null)}>
                    <Check className={cn(
                      "mr-2 h-4 w-4",
                      destinationBranchFilter === null ? "opacity-100" : "opacity-0"
                    )} />
                    All
                  </CommandItem>
                  {destinationBranches.map((val) => (
                    <CommandItem key={val} onSelect={() => setDestinationBranchFilter(val)}>
                      <Check className={cn("mr-2 h-4 w-4", destinationBranchFilter === val ? "opacity-100" : "opacity-0")} />
                      {val}
                    </CommandItem>
                  ))}
                </CommandGroup>
              </Command>
            </PopoverContent>
          </Popover>
        </div>

        <div className="flex flex-col gap-1">
          <Label>Waybill Status</Label>
          <Popover>
            <PopoverTrigger asChild>
              <Button variant="outline" size="sm" className="w-full justify-between">
                {waybillStatusFilter ?? "Select Waybill Status"}
                <ChevronDown className="h-4 w-4 opacity-60" />
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-60 p-0">
              <Command>
                <CommandInput placeholder="Search Waybill Status..." />
                <CommandEmpty>No options found</CommandEmpty>
                <CommandGroup>
                  <CommandItem onSelect={() => setWaybillStatusFilter(null)}>
                    <Check className={cn(
                      "mr-2 h-4 w-4",
                      waybillStatusFilter === null ? "opacity-100" : "opacity-0"
                    )} />
                    All
                  </CommandItem>
                  {waybillStatuses.map((val) => (
                    <CommandItem key={val} onSelect={() => setWaybillStatusFilter(val)}>
                      <Check className={cn("mr-2 h-4 w-4", waybillStatusFilter === val ? "opacity-100" : "opacity-0")} />
                      {val}
                    </CommandItem>
                  ))}
                </CommandGroup>
              </Command>
            </PopoverContent>
          </Popover>
        </div>

        {/* Export Button */}
        <div className="flex flex-col justify-end">
          <Button variant="secondary" onClick={exportCSV}>
            Export
          </Button>
        </div>

      </CardContent>
    </Card>
  </div>

</div>
       

        {/* Results Table */}
        <Card className="w-full max-w-full">
          <CardHeader className="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
            {/* Left Column */}
            <div>
              <CardTitle className="text-lg">
                Results In:{" "}
                <span className="text-sky-400 font-light">
                  {(executionTime / 1000).toFixed(2)} s
                </span>
              </CardTitle>

              <div className="flex flex-wrap items-center space-x-4 mt-2">
                <div>
  <span className="text-sm font-medium">Found Records:</span>{" "}
  <span className="inline-block bg-sky-100 text-sky-800 text-xs font-semibold px-2 py-1 rounded-full">
    {filteredData.length}
  </span>
</div>
                <div>
                  <span className="text-sm font-medium">Scan Partitions:</span>{" "}
                  <span className="inline-block bg-amber-500 text-white text-xs font-semibold px-2 py-1 rounded-full">
                    {used_partitions}
                  </span>
                </div>
              </div>
            </div>

            {/* Right Column - Income Stats */}
            <div>
  <div className="border rounded-lg p-3 grid grid-cols-3 text-center gap-4">
    
    <div className="space-y-1">
      <span className="text-xs font-medium block">EXP Income</span>
      <span className="text-sm font-semibold text-green-500">
        {selectedTotals.express_income_amount.toLocaleString()}.00
      </span>
    </div>

    <div className="space-y-1">
      <span className="text-xs font-medium block">COD Income</span>
      <span className="text-sm font-semibold text-green-500">
        {selectedTotals.cod_income_amount.toLocaleString()}.00
      </span>
    </div>

    <div className="space-y-1">
      <span className="text-xs font-medium block">COD Payable</span>
      <span className="text-sm font-semibold text-green-500">
        {selectedTotals.cod_payable_amount.toLocaleString()}.00
      </span>
    </div>

  </div>
</div>
          </CardHeader>

          <CardContent className="overflow-x-auto">
            <Table className="w-full table-auto">
<TableHeader>
  <TableRow>
    {filteredData.length > 0 && (
      <TableHead>
        <Checkbox
          checked={selectedRows.size === filteredData.length}
          onCheckedChange={toggleAllRows}
        />
      </TableHead>
    )}
    {columns.map((col) => (
      <TableHead key={col}>{col}</TableHead>
    ))}
  </TableRow>
</TableHeader>

<TableBody>
  {paginatedData.length ? (
    paginatedData.map((row, i) => {
      const originalIndex = (currentPage - 1) * rowsPerPage + i;
      return (
        <TableRow key={originalIndex}>
          <TableCell>
            <Checkbox
              checked={selectedRows.has(originalIndex)}
              onCheckedChange={() => toggleRow(originalIndex)}
            />
          </TableCell>
          {columns.map((c) => (
            <TableCell key={c}>{displayValue(row[c])}</TableCell>
          ))}
        </TableRow>
      );
    })
  ) : (
    <TableRow>
      <TableCell colSpan={columns.length + 1} className="text-center">
        No records
      </TableCell>
    </TableRow>
  )}
</TableBody>
</Table>

            <div className="flex justify-end items-center mt-4 gap-2">
  <Button
    size="sm"
    disabled={currentPage === 1}
    onClick={() => setCurrentPage((prev) => Math.max(prev - 1, 1))}
  >
    Previous
  </Button>
  <span>
    Page {currentPage} of {totalPages}
  </span>
  <Button
    size="sm"
    disabled={currentPage === totalPages}
    onClick={() => setCurrentPage((prev) => Math.min(prev + 1, totalPages))}
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