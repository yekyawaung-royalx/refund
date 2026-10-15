import * as React from "react";
import type { DateRange } from "react-day-picker";

import { Input } from "@/components/ui/input";

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

import DatePicker from "./DatePicker";
import DateRangePicker from "./DateRangePicker";
import MonthYearPicker from "./MonthYearPicker";
import CustomerCombobox, {
    type Customer,
} from "./CustomerCombobox";

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

import { FileText } from "lucide-react";

interface InvoiceInformationProps {
    invoiceDate?: Date;
    setInvoiceDate: (date: Date | undefined) => void;

    dueDate?: Date;
    setDueDate: (date: Date | undefined) => void;

    customerRefNo: string;
    setCustomerRefNo: (value: string) => void;

    onCustomerSelect?: (customer: Customer) => void;

    refNo: string;
    setRefNo: (value: string) => void;

    dateRange?: DateRange;
    setDateRange: (value: DateRange | undefined) => void;

    paymentTerm: string;
    setPaymentTerm: (value: string) => void;

    description: string;
    setDescription: (value: string) => void;

    attention: string;
    setAttention: (value: string) => void;

    // Contact No
    contactNo: string;
    setContactNo: (value: string) => void;
}

export default function InvoiceInformation({
    invoiceDate,
    setInvoiceDate,
    dueDate,
    setDueDate,
    customerRefNo,
    setCustomerRefNo,
    onCustomerSelect,
    refNo,
    setRefNo,
    dateRange,
    setDateRange,
    paymentTerm,
    setPaymentTerm,
    description,
    setDescription,
    attention,
    setAttention,
    contactNo,
    setContactNo,
}: InvoiceInformationProps) {
    return (
        <Card className="overflow-hidden border-border/60 p-0 shadow-sm">
            <CardHeader className="border-b bg-muted/20 px-2 py-1.5">
                <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                        <FileText className="h-4 w-4 text-primary" />
                    </div>

                    <div>
                        <CardTitle className="text-base font-semibold">
                            Invoice Information
                        </CardTitle>

                        <CardDescription className="text-xs">
                            Enter the invoice details and payment information.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>

            <CardContent>
                <div className="grid grid-cols-1 gap-2 md:grid-cols-3">
                    {/* Invoice Date */}
                    <div className="space-y-2">
                        <Label>Invoice Date</Label>

                        <DatePicker
                            date={invoiceDate}
                            setDate={setInvoiceDate}
                            placeholder="Select invoice date"
                        />
                    </div>

                    {/* Due Date */}
                    <div className="space-y-2">
                        <Label>Due Date</Label>

                        <DatePicker
                            date={dueDate}
                            setDate={setDueDate}
                            placeholder="Select due date"
                        />
                    </div>

                    {/* Ref No */}
                    <div className="space-y-2">
                        <Label>Ref No.</Label>

                        <MonthYearPicker
                            value={refNo}
                            onChange={setRefNo}
                        />
                    </div>

                    {/* Customer Ref No */}
                    <div className="space-y-2">
                        <Label>
                            Customer Ref No (eg. I3738557)
                        </Label>

                        <CustomerCombobox
                            value={customerRefNo}
                            onChange={setCustomerRefNo}
                            onCustomerSelect={onCustomerSelect}
                        />
                    </div>

                    {/* Date Range */}
                    <div className="space-y-2">
                        <Label>Date From - Date To</Label>

                        <DateRangePicker
                            value={dateRange}
                            onChange={setDateRange}
                        />
                    </div>

                    {/* Payment Term */}
                    <div className="space-y-2">
                        <Label>Payment Term</Label>

                        <Select
                            value={paymentTerm}
                            onValueChange={setPaymentTerm}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select payment term" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="cash">
                                    Immediate Payment
                                </SelectItem>

                                <SelectItem value="7-days">
                                    14 Days from the invoice issued date
                                </SelectItem>

                                <SelectItem value="15-days">
                                    30 Days from the invoice issued date
                                </SelectItem>

                                <SelectItem value="30-days">
                                    45 Days from the invoice issued date
                                </SelectItem>

                                <SelectItem value="45-days">
                                    2 Months from the invoice issued date
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {/* Additional Information */}
                <div className="mb-4 mt-2">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {/* Description */}
                        <div className="space-y-2">
                            <Label>Description</Label>

                            <Input
                                value={description}
                                onChange={(e) =>
                                    setDescription(e.target.value)
                                }
                                placeholder="Enter description"
                            />
                        </div>

                        {/* Attention */}
                        <div className="space-y-2">
                            <Label>Attention</Label>

                            <Input
                                value={attention}
                                onChange={(e) =>
                                    setAttention(e.target.value)
                                }
                                placeholder="Enter attention"
                            />
                        </div>

                        {/* Contact No - New Field */}
                        <div className="space-y-2">
                            <Label>Contact No</Label>

                            <Input
                                type="tel"
                                value={contactNo}
                                onChange={(e) =>
                                    setContactNo(e.target.value)
                                }
                                placeholder="09450042749"
                            />
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function Label({
    children,
}: {
    children: React.ReactNode;
}) {
    return (
        <label className="text-sm font-medium">
            {children}
        </label>
    );
}
