import * as React from "react";

import { Checkbox } from "@/components/ui/checkbox";

interface InvoiceOptionsProps {
    waybillDetails: boolean;
    setWaybillDetails: (value: boolean) => void;
    waybills: number;
}

export default function InvoiceOptions({
    waybillDetails,
    setWaybillDetails,
    waybills,
}: InvoiceOptionsProps) {
    return (
        <div className="mt-4 rounded-xl border p-4">
            <div className="flex items-center gap-3">
                <Checkbox
                    id="waybill-details"
                    checked={waybillDetails}
                    onCheckedChange={(checked) =>
                        setWaybillDetails(
                            checked === true
                        )
                    }
                />

                <label
                    htmlFor="waybill-details"
                    className="cursor-pointer text-sm font-medium"
                >
                    Waybill Details
                </label>

                {/* Waybill Count */}
                <span className="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                    {waybills} Waybills
                </span>
            </div>

            <p className="ml-7 mt-1 text-xs text-muted-foreground">
                Include waybill details in the generated
                invoice.
            </p>
        </div>
    );
}