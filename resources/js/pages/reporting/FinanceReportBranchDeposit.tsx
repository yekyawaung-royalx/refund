"use client";

import * as React from "react";
import { format } from "date-fns";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { Head, usePage} from "@inertiajs/react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Calendar } from "@/components/ui/calendar";
import { Command, CommandGroup, CommandInput, CommandItem, CommandEmpty } from "@/components/ui/command";
import { Check, ChevronDown, Download, EyeIcon, FileInputIcon } from "lucide-react";
import { cn } from "@/lib/utils";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { toast } from "sonner"; 
import { Badge } from "@/components/ui/badge";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Finance Report", href: "/reports" },
  { title: "Branches Bank Deposit", href: "#" },
];

// --- Types ---
type Branch = {
  id: number;
  account: string;
  reference: string;
};

type ExportedFile = {
  id: number;
  filename: string;
  filepath: string;
  category: string;
  total_rows: number;
  duration: string;
  report_date: string;
  filtered: string;
  exported_by: string;
  created_at: string;
  updated_at: string;
};

type PaginatedResponse = {
  current_page: number;
  data: ExportedFile[];
  first_page_url: string;
  last_page: number;
  last_page_url: string;
  links: { url: string | null; label: string; active: boolean }[];
  next_page_url: string | null;
  prev_page_url: string | null;
  per_page: number;
  total: number;
};


// --- Date Picker ---
function SingleDatePicker({ value, onChange }: { value: Date | undefined; onChange: (date: Date) => void; }) {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button variant="outline" className="w-full justify-start">
          {value ? format(value, "yyyy-MM-dd") : "Select date"}
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

// --- Command Select (Object-based) ---
function CommandSelect({
  value,
  setValue,
  placeholder,
  options,
}: {
  value: string | null;
  setValue: (val: string | null) => void;
  placeholder: string;
  options: { label: string; value: string }[];
}) {
  const selectedLabel =
    value === null ? "All" : options.find((o) => o.value === value)?.label ?? placeholder;

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button variant="outline" className="w-full justify-between">
          {selectedLabel}
          <ChevronDown className="h-4 w-4 opacity-60" />
        </Button>
      </PopoverTrigger>

      <PopoverContent className="min-w-[var(--radix-popover-trigger-width)] p-0">
        <Command className="max-h-100 overflow-y-auto">
          <CommandInput placeholder={`Search ${placeholder}...`} />
          <CommandEmpty>No data</CommandEmpty>

          <CommandGroup className="overflow-y-auto">
            {options.map((opt) => (
              <CommandItem key={opt.value} onSelect={() => setValue(opt.value)}>
                <Check className={cn("mr-2 h-4 w-4", value === opt.value ? "opacity-100" : "opacity-0")} />
                {opt.label}
              </CommandItem>
            ))}
          </CommandGroup>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

// --- Main Component ---
export default function FinanceReportBranchDeposit() {
  const { branches } = usePage().props as { branches: Branch[] };

  const [date, setDate] = React.useState<Date | undefined>(new Date());
  const [destinationBranch, setDestinationBranch] = React.useState<string | null>("ALL");
  const [category, setCategory] = React.useState<string | null>("all");
  const [files, setFiles] = React.useState<ExportedFile[]>([]);
  const [loading, setLoading] = React.useState<boolean>(true);
  const [currentPage, setCurrentPage] = React.useState<number>(1);
  const [lastPage, setLastPage] = React.useState<number>(1);
  const [nextPageUrl, setNextPageUrl] = React.useState<string | null>(null);
  const [prevPageUrl, setPrevPageUrl] = React.useState<string | null>(null);

  // --- Dynamic Branch Options ---
  const destinationOptions = [
    { label: "All", value: "ALL" },
    ...branches.map((b) => ({ label: `${b.account} (${b.reference})`, value: b.reference })),
  ];

  // --- Category Options ---
  const categoryOptions = [
    { label: "All", value: "all" },
    { label: "COD Payable", value: "cod-payable" },
    { label: "COD Receivable(Not Collect)", value: "cod-not-collect" },
    { label: "COD Receivable(To Collect)", value: "cod-to-collect" },
    { label: "COD Zero", value: "cod-zero" },
  ];



  const fetchFiles = async (page: number = 1) => {
    try {
      setLoading(true);
      const res = await fetch(`/finance-report/branches-deposit/exported-files?page=${page}`);
      const data: PaginatedResponse = await res.json();

      setFiles(data.data);
      setCurrentPage(data.current_page);
      setLastPage(data.last_page);

      setNextPageUrl(data.next_page_url);
      setPrevPageUrl(data.prev_page_url);
    } catch (err) {
      console.error(err);
      toast.error("Failed to fetch exported files");
    } finally {
      setLoading(false);
    }
  };

  React.useEffect(() => {
    fetchFiles();
  }, []);

  const goToPage = (page: number) => {
    if (page >= 1 && page <= lastPage) {
      fetchFiles(page);
    }
  };

  const handleDownload = (id: number) => {
    window.open(`/finance-report/branches-deposit/exported-files/${id}/download`, "_blank");
  };

  // --- Export Handler ---
  const handleExport = async () => {
    try {
      const query = new URLSearchParams({
        accounting_date: date ? format(date, "yyyy-MM-dd") : "",
        destination_branch: destinationBranch !== "ALL" ? destinationBranch! : "",
        category: category !== "ALL" ? category! : "",
      }).toString();

      const response = await fetch(`/finance-report/branches-deposit/export?${query}`, {
        method: "GET",
      });

      const result = await response.json();

      if (result.status === "queued") {
        toast.success(result.message); // ✅ Show message via Sonner
      } else {
        toast.error("Something went wrong");
      }
    } catch (err) {
      console.error(err);
      toast.error("Failed to queue the finance report job");
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Finance Report" />
      <div className="p-4 space-y-4">

        {/* --- Form --- */}
        <Card>
          <CardHeader>
            <CardTitle>Finance Report Branches Bank Deposit (
              <span className="text-green-500 font-light">CODPL</span>,  
              <span className="text-green-500 font-light">CODRN</span>, 
              <span className="text-green-500 font-light">CODRT</span>, 
              <span className="text-green-500 font-light">CODZR</span>)</CardTitle>
          </CardHeader>

          <CardContent>
            <div className="grid grid-cols-4 gap-4">
              {/* Date */}
              <div className="flex flex-col gap-1">
                <Label>Accounting Date</Label>
                <SingleDatePicker value={date} onChange={setDate} />
              </div>

              {/* Branch */}
              <div className="flex flex-col gap-1">
                <Label>Destination Branch</Label>
                <CommandSelect
                  value={destinationBranch}
                  setValue={setDestinationBranch}
                  placeholder="Select Branch"
                  options={destinationOptions}
                />
              </div>

              {/* Category */}
              <div className="flex flex-col gap-1">
                <Label>Category</Label>
                <CommandSelect
                  value={category}
                  setValue={setCategory}
                  placeholder="Select Category"
                  options={categoryOptions}
                />
              </div>

              {/* Export */}
              <div className="flex items-end">
                <Button className="w-full bg-green-500 hover:bg-green-600 text-white" onClick={handleExport}>
                  <FileInputIcon />
                  Export
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* --- Table --- */}
        <Card>
          <CardHeader>
            <CardTitle>Exported Finance Branches Bank Deposit Reports</CardTitle>
          </CardHeader>

          <CardContent className="overflow-x">
            <Table className="min-w-full table-fixed">
              <TableHeader>
                <TableRow className="bg-green-500/20 border rounded-lg">
                  <TableHead className="w-[250px]">Filename<br />&nbsp;</TableHead>
                  <TableHead className="w-[120px]">Created <br /> Date</TableHead>
                  <TableHead className="w-[120px]">Accounting <br />Date</TableHead>
                  <TableHead className="w-[120px]">Category<br />&nbsp;</TableHead>
                  <TableHead className="w-[50px]">Filtered<br />&nbsp;</TableHead>
                  <TableHead className="w-[100px] text-right">Rows<br />&nbsp;</TableHead>
                  <TableHead className="w-[120px] text-right">Duration<br />&nbsp;</TableHead>
                  <TableHead className="w-[150px]">Exported By<br />&nbsp;</TableHead>
                  <TableHead className="w-[120px]">Actions<br />&nbsp;</TableHead>
                </TableRow>
              </TableHeader>

              <TableBody>
                {loading ? (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center">
                      Loading...
                    </TableCell>
                  </TableRow>
                ) : files.length ? (
                  files.map((file) => (
                    <TableRow key={file.id}>
                      <TableCell>{file.filename}</TableCell>
                      <TableCell>
{file.created_at? format(new Date(file.created_at), "yyyy-MM-dd")
                                                  : "-"}

                      </TableCell>
                      <TableCell>{file.report_date}</TableCell>
                      <TableCell>
                        <Badge variant="outline">
                          {file.category}
                        </Badge>
                        </TableCell>
                        <TableCell>
                        <Badge variant="outline">
                          {file.filtered}
                        </Badge>
                        </TableCell>
                      <TableCell className="text-right">{file.total_rows}</TableCell>
                      <TableCell className="text-right">
                        <span className="inline-block border border-green-600 text-green-600 bg-transparent text-xs font-semibold px-2 py-0.5 rounded-full">{file.duration} s</span>
                      </TableCell>
                      <TableCell>{file.exported_by}</TableCell>
                      <TableCell className="flex gap-2">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleDownload(file.id)}
                        >
                          <Download className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => window.location.href = `/finance-report/branches-deposit/exported-files/${file.id}`}
                        >
                          <EyeIcon className="h-4 w-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center">
                      No exported files
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
    disabled={!prevPageUrl}
    onClick={() => prevPageUrl && fetchFiles(currentPage - 1)}
  >
    Previous
  </Button>

  <span className="text-sm text-muted-foreground">
    Page {currentPage} of {lastPage}
  </span>

  <Button
    size="sm"
    className="text-white bg-green-500 hover:bg-green-600"
    disabled={!nextPageUrl}
    onClick={() => nextPageUrl && fetchFiles(currentPage + 1)}
  >
    Next
  </Button>

</div>

            {/* --- Pagination --- */}
            {lastPage > 1 && (
              <div className="flex justify-center mt-4 space-x-2">
                <Button onClick={() => goToPage(currentPage - 1)} disabled={currentPage === 1}>
                  Previous
                </Button>
                {[...Array(lastPage)].map((_, i) => (
                  <Button
                    key={i + 1}
                    variant={currentPage === i + 1 ? "default" : "outline"}
                    onClick={() => goToPage(i + 1)}
                  >
                    {i + 1}
                  </Button>
                ))}
                <Button onClick={() => goToPage(currentPage + 1)} disabled={currentPage === lastPage}>
                  Next
                </Button>
              </div>
            )}

          </CardContent>
        </Card>

      </div>
    </AppLayout>
  );
}