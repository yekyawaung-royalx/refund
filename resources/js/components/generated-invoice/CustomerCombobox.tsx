import * as React from "react";
import {
    Check,
    ChevronsUpDown,
} from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";

import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandItem,
    CommandList,
} from "@/components/ui/command";

import { cn } from "@/lib/utils";

export interface Customer {
    customer_reference_no: string;
    customer: string;
}

interface CustomerComboboxProps {
    value: string;
    onChange: (value: string) => void;
    onCustomerSelect?: (customer: Customer) => void;
}

export default function CustomerCombobox({
    value,
    onChange,
    onCustomerSelect,
}: CustomerComboboxProps) {
    const [search, setSearch] = React.useState("");

    const [customers, setCustomers] =
        React.useState<Customer[]>([]);

    const [selectedCustomer, setSelectedCustomer] =
        React.useState<Customer | null>(null);

    const [loading, setLoading] =
        React.useState(false);

    const [open, setOpen] =
        React.useState(false);

   
const searchCustomers = async () => {
    const keyword = search.trim();

    if (!keyword) {
        setCustomers([]);
        return;
    }

    try {
        setLoading(true);

        const response = await fetch(
            `/customers?search=${encodeURIComponent(
                keyword
            )}&limit=10`,
            {
                headers: {
                    Accept: "application/json",
                },
            }
        );

        console.log(keyword);

        if (!response.ok) {
            throw new Error(
                "Failed to search customers."
            );
        }

        const result = await response.json();

        const customerData: Customer[] =
            result.data ?? [];

        setCustomers(customerData);
        setOpen(true);
        
        // No customers found
        if (customerData.length === 0) {
            toast.info(
                "No available customers within the selected date range."
            );
        }
    } catch (error) {
        console.error(
            "Customer search failed:",
            error
        );

        setCustomers([]);
        toast.error("Failed to search customers.");
    } finally {
        setLoading(false);
    }
};


    return (
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
                        ? `${selectedCustomer.customer_reference_no} (${selectedCustomer.customer})`
                        : value
                        ? value
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
                        <Input
                            placeholder="Enter customer ref no or name..."
                            value={search}
                            onChange={(e) =>
                                setSearch(e.target.value)
                            }
                            onKeyDown={(e) => {
                                if (e.key === "Enter") {
                                    e.preventDefault();
                                    searchCustomers();
                                }
                            }}
                            className="border-0 shadow-none focus-visible:ring-0"
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
                                    (
                                        customer,
                                        index
                                    ) => (
                                        <CommandItem
                                            key={`${customer.customer_reference_no}-${index}`}
                                            value={
                                                customer.customer_reference_no
                                            }
                                            onSelect={() => {
                                                setSelectedCustomer(
                                                    customer
                                                );

                                                onChange(
                                                    customer.customer_reference_no
                                                );

                                                onCustomerSelect?.(
                                                    customer
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
                                                        customer.customer_reference_no
                                                        ? "opacity-100"
                                                        : "opacity-0"
                                                )}
                                            />

                                            <div>
                                                <div className="font-medium">
                                                    {
                                                        customer.customer_reference_no
                                                    }
                                                </div>

                                                <div className="text-xs text-muted-foreground">
                                                    {
                                                        customer.customer
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
    );
}