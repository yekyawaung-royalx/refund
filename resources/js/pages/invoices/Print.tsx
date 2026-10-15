import * as React from "react";
import { Head } from "@inertiajs/react";

interface Invoice {
    invoice_no?: string;
    invoice_date?: string;
    due_date?: string;
    ref_no?: string;

    company?: string;
    attention?: string;
    customer_ref_no?: string;
    contact_no?: string;
    address?: string;

    date_from?: string;
    date_to?: string;

    currency?: string;

    subtotal?: number;
    tax?: number;
    tax_rate?: number;
    grand_total?: number;
    total_amount?: number;

    total_amount_words?: string;

    authorized_by?: string;
    authorized_department?: string;
}

interface Waybill {
    description?: string;
    waybill_no?: string;
    outbound_date?: string;
    from_city?: string;
    to_city?: string;
    weight?: string;
    express_income_amount: string;
    from?: string;
    to?: string;
    quantity?: number;
    price?: number;
    currency?: string;
    amount?: number;
}

interface PrintProps {
    invoice: Invoice;
    waybills: Waybill[];
}

export default function Print({
    invoice,
    waybills,
}: PrintProps) {
    React.useEffect(() => {
        const timer = window.setTimeout(() => {
            window.print();
        }, 500);

        return () => window.clearTimeout(timer);
    }, []);

    const formatAmount = (
        value: number | string | null | undefined
    ) => {
        return Number(value ?? 0).toLocaleString(
            "en-US",
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }
        );
    };

    return (
        <>
            <Head
                title={`Invoice - ${
                    invoice.invoice_no ?? ""
                }`}
            />

            <style>{`
                @page {
                    size: A4;
                    margin: 0;
                }

                * {
                    box-sizing: border-box;
                }

                html,
                body {
                    margin: 0;
                    padding: 0;
                    background: #e5e7eb;
                    color: #111827;
                    font-family:
                        Arial,
                        Helvetica,
                        sans-serif;
                    font-size: 12px;
                }

                body {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                /* =====================================================
                   PAGE
                ===================================================== */

                .invoice-page {
                    width: 210mm;
                    min-height: 297mm;

                    margin: 18px auto;

                    padding: 11mm 9mm 9mm;

                    background: #ffffff;

                    position: relative;

                    box-shadow:
                        0 12px 35px
                        rgba(15, 23, 42, 0.12);
                }

                /* =====================================================
                   HEADER
                ===================================================== */

                .header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;

                    padding-bottom: 5mm;

                    border-bottom: 1px solid #d1d5db;
                }

                .logo-wrapper {
                    width: 72mm;
                }

                .logo {
                    width: 68mm;
                    max-height: 30mm;
                    object-fit: contain;
                    object-position: left center;
                }

                .company-info {
                    width: 76mm;

                    line-height: 1.5;

                    font-size: 11px;

                    color: #374151;
                }

                .company-name {
                    margin-bottom: 3px;

                    font-size: 15px;
                    font-weight: 700;

                    color: #111827;
                    letter-spacing: 0.1px;
                }

                .company-info strong {
                    color: #111827;
                    font-weight: 700;
                }

                /* =====================================================
                   INVOICE TITLE
                ===================================================== */

                .invoice-title {
                    margin-top: 4mm;
                    margin-bottom: 4mm;

                    text-align: center;

                    font-size: 26px;
                    font-weight: 800;

                    letter-spacing: 2px;

                    color: #111827;
                }

                .invoice-title-line {
                    width: 18mm;
                    height: 2px;

                    margin: 0 auto;

                    background: #111827;
                }

                /* =====================================================
                   BILL TO / META
                ===================================================== */

                .invoice-info {
                    display: grid;

                    grid-template-columns:
                        minmax(0, 1fr)
                        minmax(0, 1fr);

                    gap: 18mm;

                    margin-bottom: 6mm;
                }

                .bill-to,
                .invoice-meta {
                    padding: 4mm 0;
                }

                .invoice-meta {
                    padding-left: 8mm;
                }

                .section-label {
                    margin-bottom: 2mm;

                    font-size: 10px;
                    font-weight: 700;

                    text-transform: uppercase;
                    letter-spacing: 0.8px;

                    color: #6b7280;
                }

                .info-row {
                    display: flex;

                    min-height: 6mm;

                    line-height: 1.55;
                }

                .info-label {
                    flex: 0 0 auto;

                    font-weight: 700;

                    color: #374151;

                    white-space: nowrap;
                }

                .info-value {
                    min-width: 0;

                    margin-left: 4px;

                    color: #111827;

                    overflow-wrap: anywhere;
                }

                /* =====================================================
                   MAIN INVOICE TABLE
                ===================================================== */

                .invoice-table {
                    width: 100%;

                    border-collapse: collapse;

                    table-layout: fixed;

                    border: 1px solid #374151;

                    overflow: hidden;
                }

                /*
                    Fixed 7 columns
                    -------------------------------
                    Description = 40%
                    From        = 10%
                    To          = 10%
                    Quantity    = 8%
                    Price       = 7%
                    Currency    = 11%
                    Amount      = 14%
                    -------------------------------
                    Total       = 100%
                */

                .invoice-table col.description-col {
                    width: 40%;
                }

                .invoice-table col.from-col {
                    width: 10%;
                }

                .invoice-table col.to-col {
                    width: 10%;
                }

                .invoice-table col.quantity-col {
                    width: 10%;
                }

                .invoice-table col.price-col {
                    width: 7%;
                }

                .invoice-table col.currency-col {
                    width: 11%;
                }

                .invoice-table col.amount-col {
                    width: 12%;
                }

                .invoice-table th,
                .invoice-table td {
                    border: 1px solid #9ca3af;

                    padding: 6px 6px;

                    vertical-align: middle;
                }

                /* =====================================================
                   TABLE HEADER
                ===================================================== */

                .invoice-table thead th {
                    height: 10mm;

                    background: #f3f4f6;

                    color: #111827;

                    font-size: 10.5px;
                    font-weight: 700;

                    text-align: center;

                    text-transform: uppercase;

                    letter-spacing: 0.35px;

                    white-space: nowrap;
                }

                .invoice-table thead th:first-child {
                    text-align: left;
                }

                /* =====================================================
                   TABLE BODY
                ===================================================== */

                .invoice-table tbody td {
                    min-height: 18mm;

                    background: #ffffff;

                    font-size: 11px;

                    color: #1f2937;
                }

                .description-cell {
                    text-align: left;

                    line-height: 1.5;

                    overflow-wrap: anywhere;
                    word-break: normal;
                }

                .center-cell {
                    text-align: center;
                }

                .right-cell {
                    text-align: right;
                }

                .amount-cell {
                    text-align: right;

                    font-weight: 700;

                    white-space: nowrap;
                }

                /* =====================================================
                   AMOUNT WORD + SUMMARY
                   
                   Row 3:
                   LEFT  = colspan 5
                   RIGHT = colspan 2
                ===================================================== */

                .amount-summary-row td {
                    vertical-align: top;

                    border-top: 0;
                }

                .amount-word-cell {
                    width: 76%;

                    padding: 7px 8px !important;

                    font-size: 10.5px;

                    line-height: 1.55;

                    color: #374151;

                    overflow-wrap: anywhere;
                    word-break: normal;
                }

                .amount-word-label {
                    font-weight: 700;

                    color: #111827;
                }

                .summary-cell {
                    width: 24%;

                    padding: 0 !important;

                    vertical-align: top;
                }

                .summary-table {
                    width: 100%;

                    border-collapse: collapse;

                    table-layout: fixed;
                }

                .summary-table td {
                    padding: 6px 7px;

                    height: 7mm;

                    border: 0;

                    border-bottom: 1px solid #d1d5db;

                    font-size: 10.5px;
                }

                .summary-table tr:last-child td {
                    border-bottom: 0;
                }

                .summary-label {
                    width: 58%;

                    text-align: left;

                    color: #4b5563;

                    font-weight: 600;
                }

                .summary-value {
                    width: 42%;

                    text-align: right;

                    color: #111827;

                    font-weight: 600;

                    white-space: nowrap;
                }

                .summary-grand .summary-label,
                .summary-grand .summary-value {
                    font-weight: 800;

                    color: #111827;
                }

                /* =====================================================
                   BANK DETAILS
                ===================================================== */

                .bank-section {
                    margin-top: 6mm;
                }

                .bank-section-title {
                    margin-bottom: 2.5mm;

                    font-size: 10px;
                    font-weight: 700;

                    text-transform: uppercase;

                    letter-spacing: 0.7px;

                    color: #374151;
                }

                .bank-table {
                    width: 88%;

                    border-collapse: collapse;

                    table-layout: fixed;

                    font-size: 9px;

                    border: 1px solid #d1d5db;
                }

                .bank-table th,
                .bank-table td {
                    padding: 3px 5px;

                    border: 1px solid #d1d5db;

                    text-align: left;

                    line-height: 1.35;
                }

                .bank-table th {
                    background: #f9fafb;

                    color: #374151;

                    font-weight: 700;
                }

                .bank-table th:nth-child(1),
                .bank-table td:nth-child(1) {
                    width: 6%;

                    text-align: center;
                }

                .bank-table th:nth-child(2),
                .bank-table td:nth-child(2) {
                    width: 11%;
                }

                .bank-table th:nth-child(3),
                .bank-table td:nth-child(3) {
                    width: 14%;
                }

                .bank-table th:nth-child(4),
                .bank-table td:nth-child(4) {
                    width: 28%;
                }

                .bank-table th:nth-child(5),
                .bank-table td:nth-child(5) {
                    width: 41%;
                }

                /* =====================================================
                   TERMS
                ===================================================== */

                .terms {
                    margin-top: 6mm;

                    padding-top: 4mm;

                    border-top: 1px solid #d1d5db;

                    font-size: 9.5px;

                    line-height: 1.65;

                    color: #4b5563;
                }

                .term {
                    margin-bottom: 1.5mm;
                }

                .term strong {
                    color: #111827;

                    font-weight: 700;
                }

                /* =====================================================
                   AUTHORIZED
                ===================================================== */

                .authorized {
                    width: 48mm;

                    margin-left: auto;

                    margin-top: 10mm;

                    text-align: center;

                    font-size: 10.5px;

                    line-height: 1.7;

                    color: #374151;
                }

                .authorized-line {
                    width: 42mm;

                    margin: 0 auto 3mm;

                    border-top: 1px solid #6b7280;
                }

                .authorized-name {
                    margin-top: 1mm;

                    font-weight: 700;

                    color: #111827;
                }

                /* =====================================================
                   FOOTER
                ===================================================== */

                .footer {
                    position: absolute;

                    left: 9mm;
                    right: 9mm;
                    bottom: 5mm;

                    display: flex;

                    justify-content: space-between;

                    padding-top: 2mm;

                    border-top: 1px solid #e5e7eb;

                    font-size: 7px;

                    color: #6b7280;
                }

                /* =====================================================
                   PRINT
                ===================================================== */

                @media print {
                    html,
                    body {
                        width: 210mm;
                        min-height: 297mm;

                        margin: 0;
                        padding: 0;

                        background: #ffffff;
                    }

                    .invoice-page {
                        width: 210mm;
                        min-height: 297mm;

                        margin: 0;

                        padding: 11mm 9mm 9mm;

                        box-shadow: none;
                    }

                    .no-print {
                        display: none !important;
                    }

                    .invoice-table {
                        break-inside: avoid;
                        page-break-inside: avoid;
                    }

                    .bank-section {
                        break-inside: avoid;
                        page-break-inside: avoid;
                    }

                    .authorized {
                        break-inside: avoid;
                        page-break-inside: avoid;
                    }
                }
            `}</style>

            <style>{`
    /* =====================================================
       WAYBILL TABLE
    ===================================================== */

    .waybill-section {
        margin-top: 10mm;
    }

    .waybill-title {
        margin-bottom: 5mm;
        margin-bottom: 2.5mm;

        font-size: 10px;
        font-weight: 700;

        text-transform: uppercase;
        letter-spacing: 0.7px;

        color: #374151;
    }

    .waybill-table {
        width: 100%;

        border-collapse: collapse;

        table-layout: fixed;

        border: 1px solid #374151;

        font-size: 9.5px;
    }

    .waybill-table th,
    .waybill-table td {
        border: 1px solid #d1d5db;

        padding: 4px 6px;

        line-height: 1.4;
    }

    .waybill-table th {
        background: #f3f4f6;

        color: #374151;

        font-size: 9px;
        font-weight: 700;

        text-align: center;

        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .waybill-table td {
        color: #1f2937;

        background: #ffffff;
    }

    .waybill-no {
        width: 18%;

        font-weight: 700;

        text-align: center;

        white-space: nowrap;
    }

    .waybill-description {
        width: 42%;

        text-align: left;

        overflow-wrap: anywhere;
    }

    .waybill-from {
        width: 15%;

        text-align: center;

        white-space: nowrap;
    }

    .waybill-to {
        width: 15%;

        text-align: center;

        white-space: nowrap;
    }

    .waybill-quantity {
        width: 10%;

        text-align: center;
    }

    .waybill-empty {
        padding: 7px !important;

        text-align: center;

        color: #6b7280;

        font-style: italic;
    }

    @media print {
        .waybill-section {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .waybill-table {
            break-inside: auto;
        }

        .waybill-table tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }
    }
`}
</style>

            <div className="invoice-page">

                {/* =================================================
                    HEADER
                ================================================= */}

                <div className="header">

                    <div className="logo-wrapper">
                        <img
                            src="/royal-logo.webp"
                            alt="Royal Express"
                            className="logo"
                        />
                    </div>

                    <div className="company-info">

                        <div className="company-name">
                            Royal Express Services Ltd.
                        </div>

                        <div>
                            <strong>HO:</strong>{" "}
                            No (77), Corner of Amara Road and
                            Padonmar Road,(Nga) Ward
                        </div>

                        <div>
                            North Okkalarpa Township
                        </div>

                        <div>
                            Yangon, Myanmar.
                        </div>

                        <div>
                            <strong>Tel:</strong>{" "}
                            09765400781, 0949246614
                        </div>

                        <div>
                            019699788, 09765421714
                        </div>

                    </div>

                </div>


                {/* =================================================
                    TITLE
                ================================================= */}

                <div className="invoice-title">
                    INVOICE

                    <div className="invoice-title-line" />
                </div>


                {/* =================================================
                    BILL TO / INVOICE INFO
                ================================================= */}

                <div className="invoice-info">

                    <div className="bill-to">

                        <div className="section-label">
                            Bill To
                        </div>

                        <div className="info-row">

                            <span className="info-label">
                                Company :
                            </span>

                            <span className="info-value">
                                {invoice.company ?? "-"}
                            </span>

                        </div>

                        <div className="info-row">

                            <span className="info-label">
                                Attention :
                            </span>

                            <span className="info-value">
                                {invoice.attention ?? "-"}
                            </span>

                        </div>

                        <div className="info-row">

                            <span className="info-label">
                                Customer's Code :
                            </span>

                            <span className="info-value">
                                {invoice.customer_ref_no ?? "-"}
                            </span>

                        </div>

                        <div className="info-row">

                            <span className="info-label">
                                Contact No :
                            </span>

                            <span className="info-value">
                                {invoice.contact_no ?? "-"}
                            </span>

                        </div>

                        <div className="info-row">

                            <span className="info-label">
                                Address :
                            </span>

                            <span className="info-value">
                                {invoice.address ?? "-"}
                            </span>

                        </div>

                    </div>


                    <div className="invoice-meta">

                        <div className="section-label">
                            Invoice Details
                        </div>

                        <div className="info-row">

                            <span className="info-label">
                                Invoice No :
                            </span>

                            <span className="info-value">
                                {invoice.invoice_no ?? "-"}
                            </span>

                        </div>

                        <div className="info-row">

                            <span className="info-label">
                                Invoice Date :
                            </span>

                            <span className="info-value">
                                {invoice.invoice_date ?? "-"}
                            </span>

                        </div>

                        <div className="info-row">

                            <span className="info-label">
                                Due Date :
                            </span>

                            <span className="info-value">
                                {invoice.due_date ?? "-"}
                            </span>

                        </div>

                        <div className="info-row">

                            <span className="info-label">
                                Reference :
                            </span>

                            <span className="info-value">
                                {invoice.ref_no ?? "-"}
                            </span>

                        </div>

                    </div>

                </div>


                {/* =================================================
                    MAIN TABLE
                    ROW 1 = 7 COLUMNS
                    ROW 2 = 7 COLUMNS
                    ROW 3 = COLSPAN 5 + COLSPAN 2
                ================================================= */}

                <table className="invoice-table">

                    <colgroup>
                        <col className="description-col" />
                        <col className="from-col" />
                        <col className="to-col" />
                        <col className="quantity-col" />
                        <col className="price-col" />
                        <col className="currency-col" />
                        <col className="amount-col" />
                    </colgroup>

                    <thead>

                        {/* ROW 1 */}

                        <tr>

                            <th>
                                Description
                            </th>

                            <th>
                                From
                            </th>

                            <th>
                                To
                            </th>

                            <th>
                                Quantity
                            </th>

                            <th>
                                Price
                            </th>

                            <th>
                                Currency
                            </th>

                            <th>
                                Amount
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        {/* ROW 2 */}

                        <tr>

                            <td className="description-cell">

                                Express Mail Service Charges For{" "}

                                {invoice.invoice_date ?? "-"}

                            </td>

                            <td className="center-cell">
                                {invoice.date_from ?? "-"}
                            </td>

                            <td className="center-cell">
                                {invoice.date_to ?? "-"}
                            </td>

                            <td className="center-cell">
                                1
                            </td>

                            <td className="right-cell">
                                -
                            </td>

                            <td className="center-cell">
                                {invoice.currency ?? "MMK"}
                            </td>

                            <td className="amount-cell">
                                {formatAmount(
                                    invoice.total_amount
                                )}
                            </td>

                        </tr>


                        {/* ROW 3
                            LEFT  = 5 columns
                            RIGHT = 2 columns
                        */}

                        <tr className="amount-summary-row">

                            {/* LEFT */}
                            <td
                                colSpan={5}
                                className="amount-word-cell"
                            >

                                <span className="amount-word-label">
                                    Amount In Word :
                                </span>{" "}

                                {invoice.total_amount_words ??
                                    "-"}

                            </td>


                            {/* RIGHT */}
                            <td
                                colSpan={2}
                                className="summary-cell"
                            >

                                <table className="summary-table">

                                    <tbody>

                                        <tr>

                                            <td className="summary-label">
                                                Sub Total
                                            </td>

                                            <td className="summary-value">
                                                {formatAmount(
                                                    invoice.subtotal
                                                )}
                                            </td>

                                        </tr>


                                        <tr>

                                            <td className="summary-label">
                                                Tax{" "}
                                                {invoice.tax_rate ??
                                                    0} %
                                            </td>

                                            <td className="summary-value">
                                                {formatAmount(
                                                    invoice.tax
                                                )}
                                            </td>

                                        </tr>


                                        <tr className="summary-grand">

                                            <td className="summary-label">
                                                Grand Total
                                            </td>

                                            <td className="summary-value">
                                                {formatAmount(
                                                    invoice.grand_total
                                                )}
                                            </td>

                                        </tr>

                                    </tbody>

                                </table>

                            </td>

                        </tr>

                    </tbody>

                </table>


                {/* =================================================
                    BANK DETAILS
                ================================================= */}

                <div className="bank-section">

                    <div className="bank-section-title">
                        Bank Details
                    </div>

                    <table className="bank-table">

                        <thead>

                            <tr>

                                <th>No</th>
                                <th>Currency</th>
                                <th>Bank No</th>
                                <th>Account No</th>
                                <th>Account Name</th>

                            </tr>

                        </thead>

                        <tbody>

                            <tr>
                                <td>1</td>
                                <td>MMK</td>
                                <td>AYA Bank</td>
                                <td>20018300629</td>
                                <td>
                                    Royal Express Services Ltd
                                </td>
                            </tr>

                            <tr>
                                <td>2</td>
                                <td>MMK</td>
                                <td>CB Bank</td>
                                <td>0019600100099686</td>
                                <td>
                                    Royal Express Services Ltd
                                </td>
                            </tr>

                            <tr>
                                <td>3</td>
                                <td>MMK</td>
                                <td>KBZ Bank</td>
                                <td>20851202700585902</td>
                                <td>
                                    UMinMinOo and DawAyeAyeThin
                                </td>
                            </tr>

                            <tr>
                                <td>4</td>
                                <td>USD</td>
                                <td>MAB Bank</td>
                                <td>9960213996002442014</td>
                                <td>
                                    Royal Express Services Ltd
                                </td>
                            </tr>

                            <tr>
                                <td>5</td>
                                <td>USD</td>
                                <td>KBZ Bank</td>
                                <td>26210926200494701</td>
                                <td>
                                    Royal Express Services Ltd
                                </td>
                            </tr>

                            <tr>
                                <td>6</td>
                                <td>MMK</td>
                                <td>MAB Bank</td>
                                <td>0020131002005407028</td>
                                <td>
                                    MIN MIN OO
                                </td>
                            </tr>

                            <tr>
                                <td>7</td>
                                <td>MMK</td>
                                <td>KBZ PAY</td>
                                <td>09401788782</td>
                                <td>
                                    Ma Thwe Hnin Aye
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>


                {/* =================================================
                    TERMS
                ================================================= */}

                <div className="terms">

                    <div className="term">

                        <strong>
                            Payment Term :
                        </strong>{" "}

                        14 Days from the invoice issued date

                    </div>

                    <div className="term">

                        <strong>
                            Note :
                        </strong>{" "}

                        After completing the transfer,
                        please send the bank transfer slip
                        to the Viber number 09-765661443.
                        Thank You!

                    </div>

                    <div className="term">

                        <strong>
                            Credit Term :
                        </strong>{" "}

                        2% interest on overdue amount
                        will be charged to customer

                    </div>

                </div>


                {/* =================================================
                    AUTHORIZED
                ================================================= */}

                <div className="authorized">

                   

                    <div>
                        Authorized By
                    </div>

                    <div className="authorized-name">

                        {invoice.authorized_by ??
                            "Aye Aye Thin"}

                    </div>

                    <div>

                        {invoice.authorized_department ??
                            "Finance Department"}

                    </div>

                </div>

                {/* =================================================
    WAYBILL DETAILS
================================================= */}

<div className="waybill-section">

    <div className="waybill-title">
        Waybill Details
    </div>

    <table className="waybill-table">

        <colgroup>
            <col style={{ width: "20%" }} />
            <col style={{ width: "20%" }} />
            <col style={{ width: "15%" }} />
            <col style={{ width: "15%" }} />
            <col style={{ width: "15%" }} />
            <col style={{ width: "15%" }} />
        </colgroup>

        <thead>

            <tr>

                <th>
                    Waybill No.
                </th>

                <th>
                    Invoice Date
                </th>

                <th>
                    FromCity
                </th>

                <th>
                    ToCity
                </th>

                <th>
                    Weight
                </th>

                <th>
                    Amount
                </th>

            </tr>

        </thead>

        <tbody>

            {waybills.length > 0 ? (

                waybills.map((waybill, index) => (

                    <tr key={index}>

                        <td className="waybill-no">
                            {waybill.waybill_no ??
                                waybill.waybill_no ??
                                "-"}
                        </td>

                        <td className="waybill-description">
                            {waybill.outbound_date ?? "-"}
                        </td>

                        <td className="waybill-from">
                            {waybill.from_city ?? "-"}
                        </td>

                        <td className="waybill-to">
                            {waybill.to_city ?? "-"}
                        </td>

                        <td className="waybill-quantity">
                            {waybill.weight ?? "0"}
                        </td>

                        <td className="waybill-quantity">
                            {waybill.express_income_amount ?? "0"}
                        </td>

                    </tr>

                ))

            ) : (

                <tr>

                    <td
                        colSpan={5}
                        className="waybill-empty"
                    >
                        No waybill records
                    </td>

                </tr>

            )}

        </tbody>

    </table>

</div>


                {/* =================================================
                    FOOTER
                ================================================= */}

                <div className="footer">

                    <div>
                        {new Date().toLocaleString()}
                    </div>

                    <div>
                        Invoice{" "}
                        {invoice.invoice_no ?? "-"}
                    </div>

                </div>

            </div>
        </>
    );
}