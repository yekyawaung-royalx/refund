"use client";

import { useState } from "react";
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { router } from "@inertiajs/react";
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Button } from "@/components/ui/button";
import { Check, SlidersHorizontal } from "lucide-react";

export default function AppSidebarLayout({ children, breadcrumbs = [] }: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {

    const [query, setQuery] = useState("");
    const [filterBy, setFilterBy] = useState<"waybill_no" | "reference_no" | "customer">("waybill_no");
    const [popoverOpen, setPopoverOpen] = useState(false);

    const handleSearch = () => {
        if (!query.trim()) return;
        router.get("/search", { [filterBy]: query });
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === "Enter") handleSearch();
    };

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar">
                <div className="flex items-center justify-between">
                    {/* LEFT */}
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />

                    {/* RIGHT */}
                    <div className="flex items-center gap-2">
  {/* Filter popover */}
  <Popover open={popoverOpen} onOpenChange={setPopoverOpen}>
    <PopoverTrigger asChild>
      <Button variant="outline" size="sm" className="w-[160px] justify-start text-left font-normal">
        <SlidersHorizontal className="h-4 w-4" />
        {filterBy}
      </Button>
    </PopoverTrigger>
    <PopoverContent className="w-60 p-1">
      {["waybill_no", "reference_no", "customer"].map((item) => (
        <Button
          key={item}
          variant="ghost"
          size="sm"
          className="justify-start w-full"
          onClick={() => {
            setFilterBy(item as "waybill_no" | "reference_no" | "customer");
            setPopoverOpen(false);
          }}
        >
          <Check className={`mr-2 h-4 w-4 ${filterBy === item ? "opacity-100" : "opacity-0"}`} />
          {item}
        </Button>
      ))}
    </PopoverContent>
  </Popover>

  {/* Search input wrapper */}
  <div className="relative w-50 me-4">
    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        className="h-4 w-4"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="m21 21-4.35-4.35M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"
        />
      </svg>
    </span>

    <input
      type="text"
      value={query}
      onChange={(e) => setQuery(e.target.value)}
      onKeyDown={handleKeyDown}
      placeholder={`Search ${filterBy} ...`}
      className="w-full rounded-lg border border-muted pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
    />
  </div>
</div>
                </div>

                {children}
            </AppContent>
        </AppShell>
    );
}