import React from "react";
import {
    Table,
    TableHeader,
    TableRow,
    TableHead,
    TableBody,
    TableCell,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";

type Props = {
    refunds: any[];
    loading: boolean;
};

export default function RecentRefunds({
    refunds,
    loading,
}: Props) {
    return (
        <div className="rounded-xl border bg-background shadow-sm overflow-hidden">
            {loading && (
                <div className="p-6 text-sm text-muted-foreground">
                    Loading refund activities...
                </div>
            )}

            {!loading && refunds.length > 0 && (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>
                                Outbound Date
                            </TableHead>

                            <TableHead>
                                Waybill
                            </TableHead>

                            <TableHead>
                                Customer
                            </TableHead>

                            <TableHead>
                                From - To
                            </TableHead>

                            <TableHead>
                                Receiver
                            </TableHead>

                            <TableHead>
                                Refund
                            </TableHead>

                            <TableHead className="text-right">
                                Created
                            </TableHead>

                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {refunds.map((row:any, index:number)=>(
                            <TableRow key={index}>
                                <TableCell className="text-sm">
                                    {row.outbound_date}
                                </TableCell>

                                <TableCell className="font-medium">
                                    {row.waybill_no}
                                </TableCell>

                                <TableCell>
                                    <div className="flex flex-col">
                                        <span>
                                            {row.customer}
                                        </span>

                                        <span className="text-xs text-muted-foreground">
                                            {row.customer_reference_no}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell className="text-sm">
                                    {row.from_city} → {row.to_city}
                                </TableCell>
                                <TableCell>
                                    {row.receiver_name}
                                </TableCell>
                                <TableCell>
                                    {row.refund === 1 ? (
                                        <Badge className="bg-red-500 text-white">
                                            Refund
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline">
                                            No Refund
                                        </Badge>
                                    )}
                                </TableCell>
                                <TableCell className="text-right text-xs text-muted-foreground">
                                    {row.created_at}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}

            {!loading && refunds.length === 0 && (
                <div className="p-6 text-sm text-muted-foreground">
                    No refund activities found
                </div>
            )}
        </div>

    );
}