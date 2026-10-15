"use client";

import * as React from "react";

import { Head, Link, router, usePage } from "@inertiajs/react";
import { InertiaPageProps } from "@inertiajs/core";

import { format } from "date-fns";
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    FileText,
    Hash,
    Printer,
    Receipt,
    User,
    Waypoints,
} from "lucide-react";

import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";

import { cn } from "@/lib/utils";

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

export interface Invoice {
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

export interface InvoiceWaybill {
    id: number;
    waybill_no: string;
    created_at: string;
}

interface PageProps extends InertiaPageProps {
    invoice: Invoice;
    waybills: InvoiceWaybill[];
}

/*
|--------------------------------------------------------------------------
| Breadcrumbs
|--------------------------------------------------------------------------
*/

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Dashboard",
        href: "/dashboard",
    },
    {
        title: "Invoices",
        href: "/refunds/invoices",
    },
    {
        title: "View Invoice",
        href: "#",
    },
];

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const formatDate = (
    date: string | null | undefined
): string => {
    if (!date) {
        return "-";
    }

    try {
        return format(new Date(date), "yyyy-MM-dd");
    } catch {
        return "-";
    }
};

const formatDateTime = (
    date: string | null | undefined
): string => {
    if (!date) {
        return "-";
    }

    try {
        return format(
            new Date(date),
            "yyyy-MM-dd HH:mm"
        );
    } catch {
        return "-";
    }
};

const formatAmount = (
    amount: string | number | null | undefined,
    currency: string = "MMK"
): string => {
    const value = Number(amount ?? 0);

    return `${value.toLocaleString()} ${currency}`;
};

/*
|--------------------------------------------------------------------------
| Info Item
|--------------------------------------------------------------------------
*/

interface InfoItemProps {
    label: string;
    value?: React.ReactNode;
    icon?: React.ElementType;
    className?: string;
}

function InfoItem({
    label,
    value = "-",
    icon: Icon,
    className,
}: InfoItemProps) {
    return (
        <div
            className={cn(
                "rounded-xl border bg-muted/20 p-3",
                className
            )}
        >
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                {Icon && (
                    <Icon className="h-3.5 w-3.5" />
                )}

                <span>{label}</span>
            </div>

            <div className="mt-1.5 truncate text-sm font-semibold">
                {value || "-"}
            </div>
        </div>
    );
}

/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

export default function InvoiceView() {
    const { invoice, waybills } =
        usePage<PageProps>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`Invoice ${invoice.invoice_no}`}
            />

            <div className="space-y-4 p-4">

                {/* ---------------------------------------------------------
                    Page Header
                --------------------------------------------------------- */}

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10">
                            <FileText className="h-5 w-5 text-primary" />
                        </div>

                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold">
                                    Invoice {invoice.invoice_no}
                                </h1>

                                <Badge
                                    variant="outline"
                                    className="font-medium"
                                >
                                    {invoice.currency}
                                </Badge>
                            </div>

                            <p className="text-sm text-muted-foreground">
                                Invoice details and associated waybills
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {/* Print */}
                        <Button
                            className="bg-green-500 hover:bg-green-600 text-white"
                            size="sm"
                            onClick={() =>
                                    router.get(
                                        route("refunds.invoices.print", invoice.id)
                                    )
                                }
                        >
                            <Printer className="mr-2 h-4 w-4" />
                            Print
                        </Button>

                        {/* Back */}
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                        >
                            <Link href="/refunds/invoices">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Invoices
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* ---------------------------------------------------------
                    Invoice Overview
                --------------------------------------------------------- */}

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Receipt className="h-5 w-5 text-muted-foreground" />

                            <CardTitle>
                                Invoice Information
                            </CardTitle>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">

                            <InfoItem
                                label="Invoice No"
                                value={invoice.invoice_no}
                                icon={Hash}
                            />

                            <InfoItem
                                label="Invoice Date"
                                value={formatDate(
                                    invoice.invoice_date
                                )}
                                icon={CalendarDays}
                            />

                            <InfoItem
                                label="Due Date"
                                value={formatDate(
                                    invoice.due_date
                                )}
                                icon={CalendarDays}
                            />

                            <InfoItem
                                label="Payment Term"
                                value={
                                    invoice.payment_term
                                        ? invoice.payment_term
                                        : "-"
                                }
                            />

                            <InfoItem
                                label="Customer Ref No"
                                value={
                                    invoice.customer_ref_no
                                }
                                icon={User}
                            />

                            <InfoItem
                                label="Reference No"
                                value={
                                    invoice.ref_no ? (
                                        <Badge
                                            variant="outline"
                                            className="font-normal"
                                        >
                                            {invoice.ref_no}
                                        </Badge>
                                    ) : (
                                        "-"
                                    )
                                }
                            />

                            <InfoItem
                                label="Date From"
                                value={formatDate(
                                    invoice.date_from
                                )}
                                icon={CalendarDays}
                            />

                            <InfoItem
                                label="Date To"
                                value={formatDate(
                                    invoice.date_to
                                )}
                                icon={CalendarDays}
                            />

                            <InfoItem
                                label="Customer"
                                value={
                                    invoice.description ||
                                    "-"
                                }
                                className="sm:col-span-2"
                            />

                            <InfoItem
                                label="Attention"
                                value={
                                    invoice.attention || "-"
                                }
                                className="sm:col-span-2"
                            />

                        </div>
                    </CardContent>
                </Card>

                {/* ---------------------------------------------------------
                    Amount Summary
                --------------------------------------------------------- */}

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Receipt className="h-5 w-5 text-muted-foreground" />

                            <CardTitle>
                                Amount Information
                            </CardTitle>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">

                            <div className="rounded-xl border bg-muted/20 p-4">
                                <p className="text-xs text-muted-foreground">
                                    Express Amount
                                </p>

                                <p className="mt-1 text-lg font-semibold">
                                    {formatAmount(
                                        invoice.express_amount,
                                        invoice.currency
                                    )}
                                </p>
                            </div>

                            <div className="rounded-xl border bg-muted/20 p-4">
                                <p className="text-xs text-muted-foreground">
                                    Untaxed Amount
                                </p>

                                <p className="mt-1 text-lg font-semibold">
                                    {formatAmount(
                                        invoice.untaxed_amount,
                                        invoice.currency
                                    )}
                                </p>
                            </div>

                            <div className="rounded-xl border bg-muted/20 p-4">
                                <p className="text-xs text-muted-foreground">
                                    Tax Amount
                                </p>

                                <p className="mt-1 text-lg font-semibold">
                                    {formatAmount(
                                        invoice.tax_amount,
                                        invoice.currency
                                    )}
                                </p>
                            </div>

                            <div className="rounded-xl border bg-primary/5 p-4">
                                <p className="text-xs text-muted-foreground">
                                    Total Amount
                                </p>

                                <p className="mt-1 text-xl font-bold text-primary">
                                    {formatAmount(
                                        invoice.total_amount,
                                        invoice.currency
                                    )}
                                </p>
                            </div>

                        </div>

                        {invoice.total_amount_words && (
                            <div className="mt-3 rounded-xl border bg-muted/20 p-4">
                                <p className="text-xs text-muted-foreground">
                                    Amount in Words
                                </p>

                                <p className="mt-1 text-sm font-medium">
                                    {invoice.total_amount_words}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ---------------------------------------------------------
                    Waybills
                --------------------------------------------------------- */}

                <Card>
                    <CardHeader>
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                <Waypoints className="h-5 w-5 text-muted-foreground" />

                                <CardTitle>
                                    Invoice Waybills
                                </CardTitle>

                                <Badge variant="secondary">
                                    {waybills.length}
                                </Badge>
                            </div>

                            <div className="text-sm text-muted-foreground">
                                Invoice #{invoice.invoice_no}
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent>
                        {waybills.length > 0 ? (
                            <div className="overflow-hidden rounded-xl border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[80px]">
                                                #
                                            </TableHead>

                                            <TableHead>
                                                Waybill No
                                            </TableHead>

                                            <TableHead className="w-[220px]">
                                                Created At
                                            </TableHead>

                                            <TableHead className="w-[140px] text-right">
                                                Status
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {waybills.map(
                                            (
                                                waybill,
                                                index
                                            ) => (
                                                <TableRow
                                                    key={
                                                        waybill.id
                                                    }
                                                >
                                                    <TableCell className="text-muted-foreground">
                                                        {index +
                                                            1}
                                                    </TableCell>

                                                    <TableCell>
                                                        <div className="flex items-center gap-2">
                                                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                                                                <ClipboardList className="h-4 w-4 text-primary" />
                                                            </div>

                                                            <span className="font-semibold">
                                                                {
                                                                    waybill.waybill_no
                                                                }
                                                            </span>
                                                        </div>
                                                    </TableCell>

                                                    <TableCell className="text-sm text-muted-foreground">
                                                        {formatDateTime(
                                                            waybill.created_at
                                                        )}
                                                    </TableCell>

                                                    <TableCell className="text-right">
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1"
                                                        >
                                                            <CheckCircle2 className="h-3.5 w-3.5" />
                                                            Linked
                                                        </Badge>
                                                    </TableCell>
                                                </TableRow>
                                            )
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center">
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-muted">
                                    <Waypoints className="h-6 w-6 text-muted-foreground" />
                                </div>

                                <h3 className="mt-3 font-semibold">
                                    No Waybills
                                </h3>

                                <p className="mt-1 text-sm text-muted-foreground">
                                    No waybills are linked to this
                                    invoice.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ---------------------------------------------------------
                    Additional Information
                --------------------------------------------------------- */}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Additional Information
                        </CardTitle>
                    </CardHeader>

                    <CardContent>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">

                            <InfoItem
                                label="Approved By"
                                value={
                                    invoice.approved_by
                                        ? `User #${invoice.approved_by}`
                                        : "-"
                                }
                                icon={CheckCircle2}
                            />

                            <InfoItem
                                label="Waybill Details"
                                value={
                                    invoice.waybill_details ? (
                                        <Badge>
                                            Enabled
                                        </Badge>
                                    ) : (
                                        <Badge variant="secondary">
                                            Disabled
                                        </Badge>
                                    )
                                }
                            />

                            <InfoItem
                                label="Created At"
                                value={formatDateTime(
                                    invoice.created_at
                                )}
                            />

                            <InfoItem
                                label="Updated At"
                                value={formatDateTime(
                                    invoice.updated_at
                                )}
                            />

                        </div>

                        {invoice.description && (
                            <div className="mt-3 rounded-xl border bg-muted/20 p-4">
                                <p className="text-xs text-muted-foreground">
                                    Description
                                </p>

                                <p className="mt-1 text-sm font-medium">
                                    {invoice.description}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

            </div>
        </AppLayout>
    );
}
