import { Loader2, Save } from "lucide-react";

import { Button } from "@/components/ui/button";

interface InvoiceActionsProps {
    submitting: boolean;
    onCancel?: () => void;
}

export default function InvoiceActions({
    submitting,
    onCancel,
}: InvoiceActionsProps) {
    return (
        <div className="flex justify-end gap-3 pt-2">
            {/* Cancel */}
            <Button
                type="button"
                variant="outline"
                disabled={submitting}
                onClick={onCancel}
            >
                Cancel
            </Button>

            {/* Generate Invoice */}
            <Button
                type="submit"
                disabled={submitting}
                className="min-w-[160px] bg-green-500 text-white hover:bg-green-600"
            >
                {submitting ? (
                    <>
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        Generating...
                    </>
                ) : (
                    <>
                        <Save className="mr-2 h-4 w-4" />
                        Generate Invoice
                    </>
                )}
            </Button>
        </div>
    );
}