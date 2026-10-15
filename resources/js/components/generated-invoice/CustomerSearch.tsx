import * as React from "react";

import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";

import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";

import { Button } from "@/components/ui/button";
import { Check, ChevronsUpDown } from "lucide-react";
import { cn } from "@/lib/utils";

import type { Customer } from "@/types/generated-invoice";

interface CustomerSearchProps {
    value: string;
    onChange: (value: string) => void;

    selectedCustomer?: Customer;

    customers: Customer[];
    setCustomers: React.Dispatch<
        React.SetStateAction<Customer[]>
    >;
}

export default function CustomerSearch({
    value,
    onChange,
    selectedCustomer,
    customers,
    setCustomers,
}: CustomerSearchProps) {
    const [open, setOpen] = React.useState(false);
    const [search, setSearch] = React.useState("");
    const [loading, setLoading] = React.useState(false);

    const searchCustomers = async () => {
        const term = search.trim();

        if (!term) {
            setCustomers([]);
            return;
        }

        try {
            setLoading(true);

            const response = await fetch(
                `/customers?search=${encodeURIComponent(term)}&limit=10`,
                {
                    headers: {
                        Accept: "application/json",
                    },
                }
            );

            if (!response.ok) {
                throw new Error(
                    "Failed to search customers."
                );
            }

            const result = await response.json();

            setCustomers(result.data ?? []);
            setOpen(true);
        } catch (error) {
            console.error(
                "Customer search failed:",
                error
            );

            setCustomers([]);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="space-y-2">
            <label className="text-sm font-medium">
                Customer Ref No
            </label>

            <Popover
                open={open}
                onOpenChange={setOpen}
            >
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        className="w-full justify-between font-normal"
                    >
                        {selectedCustomer
                            ? `${selectedCustomer.ref_no} - ${selectedCustomer.name}`
                            : "Search customer..."}

                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                    className="w-[--radix-popover-trigger-width] p-0"
                    align="start"
                >
                    <Command shouldFilter={false}>
                        <div className="flex items-center gap-2 border-b p-2">
                            <CommandInput
                                placeholder="Enter customer ref no or name..."
                                value={search}
                                onValueChange={setSearch}
                            />

                            <Button
                                type="button"
                                size="sm"
                                onClick={searchCustomers}
                                disabled={
                                    loading ||
                                    !search.trim()
                                }
                            >
                                {loading
                                    ? "Searching..."
                                    : "Search"}
                            </Button>
                        </div>

                        <CommandList>
                            {loading ? (
                                <div className="p-4 text-center text-sm text-muted-foreground">
                                    Searching customers...
                                </div>
                            ) : customers.length === 0 ? (
                                <CommandEmpty>
                                    {search.trim()
                                        ? "No customer found."
                                        : "Enter a search term and click Search."}
                                </CommandEmpty>
                            ) : (
                                <CommandGroup>
                                    {customers.map(
                                        (customer) => (
                                            <CommandItem
                                                key={
                                                    customer.id
                                                }
                                                value={String(
                                                    customer.id
                                                )}
                                                onSelect={() => {
                                                    onChange(
                                                        customer.ref_no
                                                    );

                                                    setOpen(
                                                        false
                                                    );
                                                }}
                                            >
                                                <Check
                                                    className={cn(
                                                        "mr-2 h-4 w-4",
                                                        value ===
                                                            customer.ref_no
                                                            ? "opacity-100"
                                                            : "opacity-0"
                                                    )}
                                                />

                                                <div>
                                                    <div className="font-medium">
                                                        {
                                                            customer.ref_no
                                                        }
                                                    </div>

                                                    <div className="text-xs text-muted-foreground">
                                                        {
                                                            customer.name
                                                        }
                                                    </div>
                                                </div>
                                            </CommandItem>
                                        )
                                    )}
                                </CommandGroup>
                            )}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>
    );
}