import type { DateRange } from "react-day-picker";
export interface Customer {
    id: number;
    ref_no: string;
    name: string;
}

export interface User {
    id: number;
    name: string;
}

export interface GeneratedInvoiceFormData {
    invoiceDate?: Date;
    dueDate?: Date;
    customerRefNo: string;
    refNo: string;
    dateRange?: DateRange;
    paymentTerm: string;
    description: string;
    attention: string;
    expressAmount: string;
    untaxedAmount: string;
    taxAmount: string;
    tax: string;
    currency: string;
    approvedBy: string;
    totalAmount: string;
    totalAmountWords: string;
    waybillDetails: boolean;
}