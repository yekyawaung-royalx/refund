"use client";

import * as React from "react";
import { format } from "date-fns";
import { router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Popover, PopoverTrigger, PopoverContent } from "@/components/ui/popover";
import { Command, CommandGroup, CommandItem, CommandInput, CommandEmpty } from "@/components/ui/command";
import { Calendar } from "@/components/ui/calendar";
import { Label } from "@/components/ui/label";
import { Check, ChevronDown } from "lucide-react";
import { cn } from "@/lib/utils";

export default function ReportingFilters({ data, selected_date }: any) {

  const today = new Date();

  const [selectedDate, setSelectedDate] = React.useState<Date>(
    selected_date ? new Date(selected_date) : today
  );

  const [customerReferenceNo, setCustomerReferenceNo] = React.useState("");
  const [waybillNo, setWaybillNo] = React.useState("");
  const [refundFilter, setRefundFilter] = React.useState<string | null>(null);

  const originBranches = Array.from(
    new Set(data.map((d: any) => d.origin_branch))
  ).filter(Boolean);

  const destinationBranches = Array.from(
    new Set(data.map((d: any) => d.destination_branch))
  ).filter(Boolean);

  const waybillStatuses = Array.from(
    new Set(data.map((d: any) => d.waybill_status))
  ).filter(Boolean);

  const [originBranchFilter, setOriginBranchFilter] = React.useState<string | null>(null);
  const [destinationBranchFilter, setDestinationBranchFilter] = React.useState<string | null>(null);
  const [waybillStatusFilter, setWaybillStatusFilter] = React.useState<string | null>(null);

  const handleSearch = () => {

    const params: Record<string, string> = {};

    if (selectedDate) params.date = format(selectedDate, "yyyy-MM-dd");
    if (customerReferenceNo) params.customer_reference_no = customerReferenceNo;
    if (waybillNo) params.waybill_no = waybillNo;
    if (refundFilter) params.refund = refundFilter;
    if (originBranchFilter) params.origin_branch = originBranchFilter;
    if (destinationBranchFilter) params.destination_branch = destinationBranchFilter;
    if (waybillStatusFilter) params.waybill_status = waybillStatusFilter;

    router.visit("/reporting/search", {
      method: "get",
      data: params,
    });
  };

  return (
    <div className="grid grid-cols-4 gap-3">

      {/* LEFT */}
      <Card className="p-3">
        <CardHeader className="p-0 pb-2">
          <CardTitle className="text-sm">Search</CardTitle>
        </CardHeader>

        <CardContent className="space-y-3 p-0">

          <div>
            <Label className="text-xs">Select Outbound Date</Label>

            <Popover>
              <PopoverTrigger asChild>
                <Button variant="outline" className="w-full justify-start text-left text-xs">
                  {format(selectedDate, "MMM d, yyyy")}
                </Button>
              </PopoverTrigger>

              <PopoverContent className="p-0">
                <Calendar
                  mode="single"
                  selected={selectedDate}
                  onSelect={(date) => date && setSelectedDate(date)}
                />
              </PopoverContent>
            </Popover>
          </div>

          <Button size="sm" onClick={handleSearch}>
            Search
          </Button>

        </CardContent>
      </Card>

      {/* RIGHT */}
      <Card className="col-span-3 p-3">

        <CardHeader className="p-0 pb-2">
          <CardTitle className="text-sm">Filters</CardTitle>
        </CardHeader>

        <CardContent className="grid grid-cols-3 gap-3 p-0">

          <div>
            <Label className="text-xs">Customer Ref</Label>
            <input
              className="border rounded px-2 py-1 text-xs w-full"
              value={customerReferenceNo}
              onChange={(e) => setCustomerReferenceNo(e.target.value)}
            />
          </div>

          <div>
            <Label className="text-xs">Waybill</Label>
            <input
              className="border rounded px-2 py-1 text-xs w-full"
              value={waybillNo}
              onChange={(e) => setWaybillNo(e.target.value)}
            />
          </div>

          <div>
            <Label className="text-xs">Refund</Label>

            <Popover>
              <PopoverTrigger asChild>
                <Button variant="outline" size="sm" className="w-full justify-between text-xs">
                  {refundFilter ?? "Select"}
                  <ChevronDown className="h-3 w-3" />
                </Button>
              </PopoverTrigger>

              <PopoverContent className="p-0">
                <Command>
                  <CommandInput placeholder="Search..." />
                  <CommandEmpty>No results</CommandEmpty>

                  <CommandGroup>
                    {["0", "1"].map((val) => (
                      <CommandItem key={val} onSelect={() => setRefundFilter(val)}>
                        <Check
                          className={cn(
                            "mr-2 h-4 w-4",
                            refundFilter === val ? "opacity-100" : "opacity-0"
                          )}
                        />
                        {val === "0" ? "No Refund" : "Refund"}
                      </CommandItem>
                    ))}
                  </CommandGroup>
                </Command>
              </PopoverContent>
            </Popover>
          </div>

        </CardContent>
      </Card>
    </div>
  );
}