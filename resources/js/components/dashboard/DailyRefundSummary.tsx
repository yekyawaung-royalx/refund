import React from "react";
import { RefreshCcw } from "lucide-react";
type Props = {
    summaries: any[];
    loading: boolean;
    setSummaries: React.Dispatch<React.SetStateAction<any[]>>;
};
export default function DailyRefundSummary({
    summaries,
    loading,
    setSummaries,
}: Props) {
    const [loadingDate, setLoadingDate] = React.useState<string | null>(null);
    const handleRefresh = async (accountingDate: string) => {

        try {
            setLoadingDate(accountingDate);
            const startTime = Date.now();
            
            const response = await fetch(
                "/updated-recent-refund-summaries",
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN":
                            document
                                .querySelector(
                                    'meta[name="csrf-token"]'
                                )
                                ?.getAttribute("content") || "",
                    },
                    body: JSON.stringify({
                        accounting_date: accountingDate,
                    }),
                }
            );

            const result = await response.json();
            if(result.success){
                setSummaries((prev)=> 
                    prev.map((row)=>
                        row.date === accountingDate
                        ? {
                            ...row,
                            ...result.data
                          }
                        : row
                    )
                );
            }

            // minimum spin time
            const elapsed = Date.now() - startTime;
            const minSpinTime = 800;


            if(elapsed < minSpinTime){

                await new Promise(resolve =>
                    setTimeout(
                        resolve,
                        minSpinTime - elapsed
                    )
                );

            }
        } catch(error){
            console.error(error);
        }
        finally{
            setLoadingDate(null);
        }
    };


    return (
        <div className="rounded-xl border bg-background shadow-sm divide-y">
            {loading && (
                <div className="p-6 text-sm text-muted-foreground">
                    Loading refund summary...
                </div>
            )}

            {!loading && summaries.length > 0 && (
                <div className="rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted">
                            <tr>
                                <th className="text-left p-3">
                                    Accounting Date
                                </th>

                                <th className="text-right p-3">
                                    To Refund Amount
                                </th>

                                <th className="text-right p-3">
                                    Refunded Amount
                                </th>

                                <th className="text-right p-3">
                                    To Refund Waybills
                                </th>

                                <th className="text-right p-3">
                                    Refunded Waybills
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        {summaries.map((item,index)=>(
                            <tr
                              key={index}
                              className="border-t hover:bg-muted/40"
                            >
                                <td className="p-3">
                                    <div className="flex items-center justify-self-start gap-2">
                                        <span>
                                            {item.date}
                                        </span>
                                        <button
                                            onClick={()=>
                                                handleRefresh(item.date)
                                            }
                                            disabled={
                                                loadingDate === item.date
                                            }
                                            >
                                            <RefreshCcw
                                                size={16}
                                                className={`
                                                    text-green-500
                                                    ${
                                                        loadingDate === item.date
                                                        ? "animate-spin"
                                                        : ""
                                                    }
                                                `}
                                            />
                                        </button>
                                    </div>
                                </td>

                                <td className="p-3 text-right text-amber-500">
                                    {Number(
                                        item.to_refund_amount
                                    ).toLocaleString()}
                                </td>

                                <td className="p-3 text-right text-green-500">
                                    {Number(
                                        item.refund_amount
                                    ).toLocaleString()}
                                </td>

                                <td className="p-3 text-right">
                                    {Number(
                                      item.to_refund_rows
                                    ).toLocaleString()}
                                </td>

                                <td className="p-3 text-right">
                                    {Number(
                                      item.refund_rows
                                    ).toLocaleString()}
                                </td>
                            </tr>

                        ))}
                        </tbody>
                    </table>
                </div>
            )}

            {!loading && summaries.length === 0 && (
                <div className="p-6 text-muted-foreground">
                    No recent refund summaries
                </div>
            )}
        </div>
    );
}