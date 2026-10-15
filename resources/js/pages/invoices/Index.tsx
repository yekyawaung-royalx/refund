"use client";

import * as React from "react";

import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";

import { Head, router, usePage } from "@inertiajs/react";
import { InertiaPageProps } from "@inertiajs/core";

import { format } from "date-fns";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from "@/components/ui/tooltip";

import {
    EyeIcon,
    FileText,
} from "lucide-react";

import { cn } from "@/lib/utils";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Dashboard",
        href: "/dashboard",
    },
    {
        title: "Invoices",
        href: "/refunds/invoices",
    },
];

export interface InvoiceItem {
    id: number;

    invoice_no: string;
    invoice_date: string;
    due_date: string | null;

    customer_ref_no: string;
    ref_no: string | null;

    date_from: string | null;
    date_to: string | null;

    payment_term: string | null;

    description: string | null;
    attention: string | null;

    express_amount: string | number;
    cod_amount: string | number;
    cod_payable_amount: string | number;

    untaxed_amount: string | number;
    tax_amount: string | number;

    tax: string | number;

    currency: string;

    approved_by: number | null;

    total_amount: string | number;
    total_amount_words: string | null;

    waybill_details: boolean | number;

    created_at: string;
    updated_at: string;
}

export interface InvoicePagination {
    data: InvoiceItem[];

    current_page: number;
    last_page: number;
    total: number;

    from?: number | null;
    to?: number | null;

    per_page?: number;

    next_page_url: string | null;
    prev_page_url: string | null;
}

interface PageProps extends InertiaPageProps {
    invoices: InvoicePagination;
}

export default function InvoiceIndex() {
    const { invoices } = usePage<PageProps>().props;

    const [search, setSearch] = React.useState("");

    /*
    |--------------------------------------------------------------------------
    | Column Widths
    |--------------------------------------------------------------------------
    */

    const columnWidths: Record<string, string> = {
        invoice_no: "w-[60px]",
        invoice_date: "w-[100px]",
        customer: "w-[220px]",
        ref_no: "w-[80px]",
        date_range: "w-[120px]",
        express_amount: "w-[120px]",
        cod_amount: "w-[120px]",
        cod_payable_amount: "w-[120px]",
        untaxed_amount: "w-[120px]",
        tax_amount: "w-[120px]",
        approved_by: "w-[100px]",
        total_amount: "w-[120px]",
        actions: "w-[100px]",
    };

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    const filteredData = React.useMemo(() => {
        const keyword = search.trim().toLowerCase();

        if (!keyword) {
            return invoices.data ?? [];
        }

        return (invoices.data ?? []).filter(
            (item: InvoiceItem) => {
                return (
                    item.invoice_no
                        ?.toLowerCase()
                        .includes(keyword) ||

                    item.customer_ref_no
                        ?.toLowerCase()
                        .includes(keyword) ||

                    item.description
                        ?.toLowerCase()
                        .includes(keyword) ||

                    item.ref_no
                        ?.toLowerCase()
                        .includes(keyword)
                );
            }
        );
    }, [invoices.data, search]);

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    const formatDate = (
        date: string | null | undefined
    ) => {
        if (!date) {
            return "-";
        }

        try {
            return format(
                new Date(date),
                "yyyy-MM-dd"
            );
        } catch {
            return "-";
        }
    };

    const formatAmount = (
        amount: string | number | null | undefined,
        currency: string = "MMK"
    ) => {
        const value = Number(amount ?? 0);

        return (
            <div className="flex items-center justify-end gap-1 whitespace-nowrap">
                <span className="font-medium">
                    {value.toLocaleString()}
                </span>

                <span className="text-xs text-muted-foreground">
                    {currency}
                </span>
            </div>
        );
    };

    /*
    |--------------------------------------------------------------------------
    | View Invoice
    |--------------------------------------------------------------------------
    */

    const handleView = (invoice_no: string) => {
        router.visit(
            `/refunds/invoices/${invoice_no}`
        );
    };

    /*
    |--------------------------------------------------------------------------
    | Render
    |--------------------------------------------------------------------------
    */

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invoices" />

            <div className="p-4">
                <Card>
                    {/* -----------------------------------------------------
                        Header
                    ----------------------------------------------------- */}

                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-2">
                            <FileText className="h-5 w-5 text-muted-foreground" />

                            <CardTitle className="text-xl">
                                Invoices ({invoices.total})
                            </CardTitle>
                        </div>

                        <Input
                            placeholder="Search invoice, customer, ref no..."
                            value={search}
                            onChange={(e) =>
                                setSearch(
                                    e.target.value
                                )
                            }
                            className="w-full md:w-80"
                        />
                    </CardHeader>

                    {/* -----------------------------------------------------
                        Content
                    ----------------------------------------------------- */}

                    <CardContent>
                        <div className="w-full overflow-x-auto">
                            <Table className="table-fixed min-w-[2200px]">
                                <TableHeader>
                                    <TableRow>
                                        {/* Invoice No */}

                                        <TableHead
                                            className={
                                                columnWidths.invoice_no
                                            }
                                        >
                                            Invoice
                                        </TableHead>

                                        {/* Invoice Date */}

                                        <TableHead
                                            className={
                                                columnWidths.invoice_date
                                            }
                                        >
                                            Invoice Date
                                        </TableHead>

                                        {/* Customer */}

                                        <TableHead
                                            className={
                                                columnWidths.customer
                                            }
                                        >
                                            Customer
                                        </TableHead>

                                        {/* Ref No */}

                                        <TableHead
                                            className={
                                                columnWidths.ref_no
                                            }
                                        >
                                            Ref No
                                        </TableHead>

                                        {/* Date Range */}

                                        <TableHead
                                            className={
                                                columnWidths.date_range
                                            }
                                        >
                                            Date (From - To)
                                        </TableHead>

                                        {/* Express */}

                                        <TableHead
                                            className={cn(
                                                "text-right",
                                                columnWidths.express_amount
                                            )}
                                        >
                                            Express Amount
                                        </TableHead>

                                        {/* COD */}

                                        <TableHead
                                            className={cn(
                                                "text-right",
                                                columnWidths.cod_amount
                                            )}
                                        >
                                            COD Amount
                                        </TableHead>

                                        {/* COD Payable */}

                                        <TableHead
                                            className={cn(
                                                "text-right",
                                                columnWidths.cod_payable_amount
                                            )}
                                        >
                                            COD Payable
                                        </TableHead>

                                        {/* Untaxed */}

                                        <TableHead
                                            className={cn(
                                                "text-right",
                                                columnWidths.untaxed_amount
                                            )}
                                        >
                                            Untaxed Amount
                                        </TableHead>

                                        {/* Tax */}

                                        <TableHead
                                            className={cn(
                                                "text-right",
                                                columnWidths.tax_amount
                                            )}
                                        >
                                            Tax Amount
                                        </TableHead>

                                        {/* Approved By */}

                                        <TableHead
                                            className={
                                                columnWidths.approved_by
                                            }
                                        >
                                            Approved By
                                        </TableHead>

                                        {/* Total */}

                                        <TableHead
                                            className={cn(
                                                "text-right",
                                                columnWidths.total_amount
                                            )}
                                        >
                                            Total Amount
                                        </TableHead>

                                        {/* Actions */}

                                        <TableHead
                                            className={
                                                columnWidths.actions
                                            }
                                        >
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {filteredData.length > 0 ? (
                                        filteredData.map(
                                            (
                                                item: InvoiceItem
                                            ) => (
                                                <TableRow
                                                    key={item.id}
                                                >
                                                    {/* -------------------------------------------------
                                                        Invoice No
                                                    ------------------------------------------------- */}

                                                    <TableCell
                                                        className={cn(
                                                            "font-semibold",
                                                            columnWidths.invoice_no
                                                        )}
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <span>
                                                                {
                                                                    item.invoice_no
                                                                }
                                                            </span>
                                                        </div>
                                                    </TableCell>

                                                    {/* -------------------------------------------------
                                                        Invoice Date
                                                    ------------------------------------------------- */}

                                                    <TableCell
                                                        className={
                                                            columnWidths.invoice_date
                                                        }
                                                    >
                                                        {formatDate(
                                                            item.invoice_date
                                                        )}
                                                    </TableCell>

                                                    {/* -------------------------------------------------
                                                        Customer
                                                    ------------------------------------------------- */}

                                                    <TableCell
                                                        className={
                                                            columnWidths.customer
                                                        }
                                                    >
                                                        <div className="min-w-0">
                                                            <div className="truncate font-medium">
                                                                {
                                                                    item.description ||
                                                                    "-"
                                                                }
                                                            </div>

                                                            <div className="truncate text-xs text-muted-foreground">
                                                                {
                                                                    item.customer_ref_no
                                                                }
                                                            </div>
                                                        </div>
                                                    </TableCell>

                                                    {/* -------------------------------------------------
                                                        Ref No
                                                    ------------------------------------------------- */}

                                                    <TableCell
                                                        className={
                                                            columnWidths.ref_no
                                                        }
                                                    >
                                                        {item.ref_no ? (
                                                            <Badge
                                                                variant="outline"
                                                                className="font-normal"
                                                            >
                                                                {
                                                                    item.ref_no
                                                                }
                                                            </Badge>
                                                        ) : (
                                                            "-"
                                                        )}
                                                    </TableCell>

                                                    {/* -------------------------------------------------
                                                        Date Range
                                                    ------------------------------------------------- */}

                                                    <TableCell
                                                        className={
                                                            columnWidths.date_range
                                                        }
                                                    >
                                                        <div className="text-sm">
                                                            {formatDate(
                                                                item.date_from
                                                            )}
                                                        </div>

                                                        <div className="text-xs text-muted-foreground">
                                                            to{" "}
                                                            {formatDate(
                                                                item.date_to
                                                            )}
                                                        </div>
                                                    </TableCell>

                                                    {/* -------------------------------------------------
                                                        Express Amount
                                                    ------------------------------------------------- */}

                                                    <TableCell
                                                        className={cn(
                                                            "text-right",
                                                            columnWidths.express_amount
                                                        )}
                                                    >
                                                        {formatAmount(
                                                            item.express_amount,
                                                            item.currency
                                                        )}
                                                    </TableCell>

                                                    {/* -------------------------------------------------
                                                        COD Amount
                                                    ------------------------------------------------- */}

                                                    <TableCell
                                                        className={cn(
                                                            "text-right",
                                                            columnWidths.cod_amount
                                                        )}
                                                    >
                                                        {formatAmount(
                                                            item.cod_amount,
                                                            item.currency
                                                        )}
                                                    </TableCell>

                                                    {/* -------------------------------------------------
                                                        COD Payable Amount
                                                    ------------------------------------------------- */}

                                                    <TableCell
                                                        className={cn(
                                                            "text-right",
                                                            columnWidths.cod_payable_amount
                                                        )}
                                                    >
                                                        {formatAmount(
                                                            item.cod_payable_amount,
                                                            item.currency
                                                        )}
                                                    </TableCell>

                                                    {/* -------------------------------------------------
                                                        Untaxed Amount
                                                    ------------------------------------------------- */}

                                                    <TableCell
                                                        className={cn(
                                                            "text-right",
                                                            columnWidths.untaxed_amount
                                                        )}
                                                    >
                                                        {formatAmount(
                                                            item.untaxed_amount,
                                                            item.currency
                                                        )}
                                                    </TableCell>

                                                    {/* -------------------------------------------------
                                                        Tax Amount
                                                    ------------------------------------------------- */}

                                                    <TableCell
                                                        className={cn(
                                                            "text-right",
                                                            columnWidths.tax_amount
                                                        )}
                                                    >
                                                        {formatAmount(
                                                            item.tax_amount,
                                                            item.currency
                                                        )}
                                                    </TableCell>

                                                    {/* -------------------------------------------------
                                                        Approved By
                                                    ------------------------------------------------- */}

                                                    <TableCell
                                                        className={
                                                            columnWidths.approved_by
                                                        }
                                                    >
                                                        {item.approved_by ? (
                                                            <Badge variant="outline">
                                                                {/* {
                                                                    item.approved_by
                                                                } */}
                                                                Aye Aye Thin
                                                            </Badge>
                                                        ) : (
                                                            "-"
                                                        )}
                                                    </TableCell>

                                                    {/* -------------------------------------------------
                                                        Total Amount
                                                    ------------------------------------------------- */}

                                                    <TableCell
                                                        className={cn(
                                                            "text-right",
                                                            columnWidths.total_amount
                                                        )}
                                                    >
                                                        <div className="flex items-center justify-end gap-1 whitespace-nowrap">
                                                            <span className="font-bold">
                                                                {Number(
                                                                    item.total_amount ??
                                                                        0
                                                                ).toLocaleString()}
                                                            </span>

                                                            <span className="text-xs font-medium text-muted-foreground">
                                                                {
                                                                    item.currency
                                                                }
                                                            </span>
                                                        </div>
                                                    </TableCell>

                                                    {/* -------------------------------------------------
                                                        Actions
                                                    ------------------------------------------------- */}

                                                    <TableCell
                                                        className={
                                                            columnWidths.actions
                                                        }
                                                    >
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <Button
                                                                    size="sm"
                                                                    variant="secondary"
                                                                    onClick={() => handleView(item.invoice_no)}
                                                                >
                                                                    <EyeIcon className="h-4 w-4" />
                                                                </Button>
                                                                </TooltipTrigger>

                                                                <TooltipContent>
                                                                    <p>
                                                                        View
                                                                        Invoice
                                                                    </p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    </TableCell>
                                                </TableRow>
                                            )
                                        )
                                    ) : (
                                        <TableRow>
                                            <TableCell
                                                colSpan={13}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No invoices found
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        {/* -----------------------------------------------------
                            Pagination
                        ----------------------------------------------------- */}

                        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="text-sm text-muted-foreground">
                                Showing{" "}
                                <span className="font-medium text-foreground">
                                    {invoices.from ?? 0}
                                </span>{" "}
                                to{" "}
                                <span className="font-medium text-foreground">
                                    {invoices.to ?? 0}
                                </span>{" "}
                                of{" "}
                                <span className="font-medium text-foreground">
                                    {invoices.total}
                                </span>{" "}
                                invoices
                            </div>

                            <div className="flex items-center gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={
                                        !invoices.prev_page_url
                                    }
                                    onClick={() => {
                                        if (
                                            invoices.prev_page_url
                                        ) {
                                            router.visit(
                                                invoices.prev_page_url
                                            );
                                        }
                                    }}
                                >
                                    Previous
                                </Button>

                                <span className="text-sm">
                                    Page{" "}
                                    <span className="font-medium">
                                        {
                                            invoices.current_page
                                        }
                                    </span>{" "}
                                    of{" "}
                                    <span className="font-medium">
                                        {
                                            invoices.last_page
                                        }
                                    </span>
                                </span>

                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={
                                        !invoices.next_page_url
                                    }
                                    onClick={() => {
                                        if (
                                            invoices.next_page_url
                                        ) {
                                            router.visit(
                                                invoices.next_page_url
                                            );
                                        }
                                    }}
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
