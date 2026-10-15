"use client";

import * as React from "react";

import { Head } from "@inertiajs/react";
import { format } from "date-fns";
import type { DateRange } from "react-day-picker";
import { FileText } from "lucide-react";
import { toast } from "sonner";

import { generateInvoiceDescription } from "@/utils/invoiceDescription";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

import { numberToWords } from "@/utils/numberToWords";

import InvoiceInformation from "@/components/generated-invoice/InvoiceInformation";
import AmountInformation from "@/components/generated-invoice/AmountInformation";
import InvoiceOptions from "@/components/generated-invoice/InvoiceOptions";
import InvoiceActions from "@/components/generated-invoice/InvoiceActions";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Dashboard",
        href: "/dashboard",
    },
    {
        title: "Generated Invoice",
        href: "/generated-invoices",
    },
    {
        title: "Create",
        href: "#",
    },
];

interface User {
    id: number;
    name: string;
}

interface Props {
    users?: User[];
}

export default function CreateGeneratedInvoice({
    users = [],
}: Props) {
    /*
    |--------------------------------------------------------------------------
    | Invoice Information
    |--------------------------------------------------------------------------
    */

    const [waybills, setWaybills] = React.useState(0);

    const [expressIncomeAmount, setExpressIncomeAmount] =
        React.useState(0);

    const [invoiceDate, setInvoiceDate] =
        React.useState<Date | undefined>(
            new Date()
        );

    const [dueDate, setDueDate] =
        React.useState<Date | undefined>();

    const [customerRefNo, setCustomerRefNo] =
        React.useState("");

    const [refNo, setRefNo] = React.useState(
        format(new Date(), "yy/MM")
    );

    const [dateRange, setDateRange] =
        React.useState<DateRange | undefined>();

    const [paymentTerm, setPaymentTerm] =
        React.useState("");

    const [description, setDescription] =
        React.useState("");

    const [attention, setAttention] =
        React.useState("");

    const [contactNo, setContactNo] = 
        React.useState("");

    /*
    |--------------------------------------------------------------------------
    | Generate Description
    |--------------------------------------------------------------------------
    */

    React.useEffect(() => {
        console.log("Selected Ref No:", refNo);

        const generatedDescription =
            generateInvoiceDescription(refNo);

        console.log(
            "Generated Description:",
            generatedDescription
        );

        setDescription(generatedDescription);
    }, [refNo]);

    /*
    |--------------------------------------------------------------------------
    | Amount Information
    |--------------------------------------------------------------------------
    */

    const [expressAmount, setExpressAmount] =
        React.useState("");

    const [untaxedAmount, setUntaxedAmount] =
        React.useState("");

    const [taxAmount, setTaxAmount] =
        React.useState("");

    const [tax, setTax] =
        React.useState("");

    const [currency, setCurrency] =
        React.useState("");

    const [approvedBy, setApprovedBy] =
        React.useState("");

    const [totalAmount, setTotalAmount] =
        React.useState("");

    const [totalAmountWords, setTotalAmountWords] =
        React.useState("");

    /*
    |--------------------------------------------------------------------------
    | Options
    |--------------------------------------------------------------------------
    */

    const [waybillDetails, setWaybillDetails] =
        React.useState(false);

    const [submitting, setSubmitting] =
        React.useState(false);

    /*
    |--------------------------------------------------------------------------
    | Total Amount
    |--------------------------------------------------------------------------
    */

    React.useEffect(() => {
        const express = Number(expressAmount) || 0;
        const taxPercent = Number(tax) || 0;

        const calculatedTax =
            express * (taxPercent / 100);

        setUntaxedAmount(
            express > 0
                ? express.toFixed(2)
                : ""
        );

        setTaxAmount(
            express > 0
                ? calculatedTax.toFixed(2)
                : ""
        );

        setTotalAmount(
            express > 0
                ? (express + calculatedTax).toFixed(2)
                : ""
        );
    }, [expressAmount, tax]);

    /*
    |--------------------------------------------------------------------------
    | Amount To Words
    |--------------------------------------------------------------------------
    */

    React.useEffect(() => {
        const amount =
            parseFloat(totalAmount);

        if (
            !totalAmount ||
            Number.isNaN(amount)
        ) {
            setTotalAmountWords("");
            return;
        }

        setTotalAmountWords(
            numberToWords(amount)
        );
    }, [totalAmount]);

    /*
    |--------------------------------------------------------------------------
    | Customer Summary
    |
    | Customer + Date From + Date To
    |        ↓
    | API
    |        ↓
    | waybills
    | express_income_amount
    |--------------------------------------------------------------------------
    */

   React.useEffect(() => {
    if (
        !customerRefNo ||
        !dateRange?.from ||
        !dateRange?.to
    ) {
        setWaybills(0);
        setExpressIncomeAmount(0);
        setExpressAmount("");

        return;
    }

    const fetchCustomerSummary = async () => {
        
        try {
            const dateFrom = format(
                dateRange.from!,
                "yyyy-MM-dd"
            );

            const dateTo = format(
                dateRange.to!,
                "yyyy-MM-dd"
            );

            const params = new URLSearchParams({
                customer_ref_no: customerRefNo,
                date_from: dateFrom,
                date_to: dateTo,
            });

            const response = await fetch(
                `/invoices/customer-summary?${params.toString()}`,
                {
                    method: "GET",

                    headers: {
                        Accept: "application/json",
                        "X-Requested-With":
                            "XMLHttpRequest",
                    },

                    credentials: "same-origin",
                }
            );

            const responseText =
                await response.text();

            if (!response.ok) {
                throw new Error(
                    `Customer summary API failed: ${response.status} ${response.statusText}`
                );
            }

            let result: {
                waybills?: number;
                express_income_amount?: number;
            };

            try {
                result = JSON.parse(
                    responseText
                );
            } catch {
                throw new Error(
                    "Customer summary API returned invalid JSON."
                );
            }

            const waybillCount = Number(
                result.waybills ?? 0
            );

            const incomeAmount = Number(
                result.express_income_amount ?? 0
            );

            setWaybills(waybillCount);

            if(waybillCount == 0){
                toast.info("No available waybills found for this customer within the selected date range.");
            }
            

            setExpressIncomeAmount(
                incomeAmount
            );

            setExpressAmount(
                incomeAmount > 0
                    ? incomeAmount.toFixed(2)
                    : ""
            );
        } catch (error) {
            console.error(
                "Failed to fetch customer summary:",
                error
            );

            setWaybills(0);
            setExpressIncomeAmount(0);
            setExpressAmount("");
        }
        };

        fetchCustomerSummary();
    }, [
        customerRefNo,
        dateRange?.from,
        dateRange?.to,
    ]);

    /*
    |--------------------------------------------------------------------------
    | Submit
    |--------------------------------------------------------------------------
    */

    const handleSubmit = async (
        e: React.FormEvent
    ) => {
        e.preventDefault();

        if (!invoiceDate) {
            toast.error(
                "Please select invoice date."
            );

            return;
        }

        if (!dueDate) {
            toast.error(
                "Please select due date."
            );

            return;
        }

        if (!customerRefNo) {
            toast.error(
                "Please select customer."
            );

            return;
        }

        if (!paymentTerm) {
            toast.error(
                "Please select payment term."
            );

            return;
        }

        if (!tax) {
            toast.error(
                "Please select tax."
            );

            return;
        }

        if (!currency) {
            toast.error(
                "Please select currency."
            );

            return;
        }

        if (!approvedBy) {
            toast.error(
                "Please select approved by."
            );

            return;
        }

        setSubmitting(true);

        try {
            const response =
                await fetch(
                    "/generated-invoice",
                    {
                        method: "POST",

                        headers: {
                            "Content-Type":
                                "application/json",

                            Accept:
                                "application/json",

                            "X-CSRF-TOKEN":
                                document
                                    .querySelector(
                                        'meta[name="csrf-token"]'
                                    )
                                    ?.getAttribute(
                                        "content"
                                    ) || "",
                        },

                        body: JSON.stringify({
                            invoice_date:
                                format(
                                    invoiceDate,
                                    "yyyy-MM-dd"
                                ),

                            due_date:
                                format(
                                    dueDate,
                                    "yyyy-MM-dd"
                                ),

                            customer_ref_no:
                                customerRefNo,

                            ref_no: refNo,

                            date_from:
                                dateRange?.from
                                    ? format(
                                          dateRange.from,
                                          "yyyy-MM-dd"
                                      )
                                    : null,

                            date_to:
                                dateRange?.to
                                    ? format(
                                          dateRange.to,
                                          "yyyy-MM-dd"
                                      )
                                    : null,

                            payment_term:
                                paymentTerm,

                            description,

                            attention,

                            contactNo,

                            express_amount:
                                expressAmount,

                            untaxed_amount:
                                untaxedAmount,

                            tax_amount:
                                taxAmount,

                            tax,

                            currency,

                            approved_by:
                                approvedBy,

                            total_amount:
                                totalAmount,

                            total_amount_words:
                                totalAmountWords,

                            waybill_details:
                                waybillDetails,

                            /*
                            |--------------------------------------------------------------------------
                            | Customer Summary
                            |--------------------------------------------------------------------------
                            */

                            waybills,

                            express_income_amount:
                                expressIncomeAmount,
                        }),
                    }
                );

            const data =
                await response.json();

            if (!response.ok) {
                throw new Error(
                    data?.message ||
                        "Failed to create invoice."
                );
            }

            toast.success(
                data?.message ||
                    "Invoice generated successfully."
            );

            /*
            |--------------------------------------------------------------------------
            | Reset
            |--------------------------------------------------------------------------
            */

            setCustomerRefNo("");

            setDescription("");

            setAttention("");

            setExpressAmount("");

            setUntaxedAmount("");

            setTaxAmount("");

            setTotalAmount("");

            setTotalAmountWords("");

            setWaybills(0);

            setExpressIncomeAmount(0);

            setWaybillDetails(false);

        } catch (error) {
            console.error(error);

            toast.error(
                error instanceof Error
                    ? error.message
                    : "Something went wrong."
            );
        } finally {
            setSubmitting(false);
        }
    };

    /*
    |--------------------------------------------------------------------------
    | Render
    |--------------------------------------------------------------------------
    */

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
        >
            <Head title="Create Generated Invoice" />

            <div className="p-6">
                <Card className="rounded-2xl border-muted shadow-md">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-xl text-green-500">
                            <FileText className="h-5 w-5" />

                            Generated Invoice
                        </CardTitle>

                        <p className="text-sm text-muted-foreground">
                            Create a new generated invoice.
                        </p>
                    </CardHeader>

                    <CardContent>
                        <form
                            onSubmit={handleSubmit}
                        >
                            <InvoiceInformation
                                invoiceDate={
                                    invoiceDate
                                }

                                setInvoiceDate={
                                    setInvoiceDate
                                }

                                dueDate={
                                    dueDate
                                }

                                setDueDate={
                                    setDueDate
                                }

                                customerRefNo={
                                    customerRefNo
                                }

                                setCustomerRefNo={
                                    setCustomerRefNo
                                }

                                onCustomerSelect={(
                                    customer
                                ) => {
                                    setAttention(
                                        customer.customer
                                    );
                                }}

                                refNo={refNo}

                                setRefNo={
                                    setRefNo
                                }

                                dateRange={
                                    dateRange
                                }

                                setDateRange={
                                    setDateRange
                                }

                                paymentTerm={
                                    paymentTerm
                                }

                                setPaymentTerm={
                                    setPaymentTerm
                                }

                                description={
                                    description
                                }

                                setDescription={
                                    setDescription
                                }

                                attention={
                                    attention
                                }

                                setAttention={
                                    setAttention
                                }

                                contactNo={
                                    contactNo
                                }

                                setContactNo={
                                    setContactNo
                                }
                            />

                            <AmountInformation
                                expressAmount={
                                    expressAmount
                                }

                                setExpressAmount={
                                    setExpressAmount
                                }

                                untaxedAmount={
                                    untaxedAmount
                                }

                                setUntaxedAmount={
                                    setUntaxedAmount
                                }

                                taxAmount={
                                    taxAmount
                                }

                                setTaxAmount={
                                    setTaxAmount
                                }

                                tax={tax}

                                setTax={
                                    setTax
                                }

                                currency={
                                    currency
                                }

                                setCurrency={
                                    setCurrency
                                }

                                approvedBy={
                                    approvedBy
                                }

                                setApprovedBy={
                                    setApprovedBy
                                }

                                totalAmount={
                                    totalAmount
                                }

                                setTotalAmount={
                                    setTotalAmount
                                }

                                totalAmountWords={
                                    totalAmountWords
                                }

                                users={users}
                            />

                            <InvoiceOptions
                                waybillDetails={
                                    waybillDetails
                                }

                                setWaybillDetails={
                                    setWaybillDetails
                                }

                                waybills={waybills}
                            />

                            <InvoiceActions
                                submitting={
                                    submitting
                                }

                                // onCancel={() =>
                                //     window.history.back()
                                // }
                            />
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
