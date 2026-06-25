import React from "react";
import { Badge } from "@/components/ui/badge";

type Props = {
    files: any[];
    loading: boolean;
};

export default function RecentExported({
    files,
    loading,
}: Props) {

    return (
        <div className="rounded-xl border bg-background shadow-sm divide-y">
            {loading && (
                <div className="p-6 text-sm text-muted-foreground">
                    Loading exported files...
                </div>
            )}

            {!loading && files.length > 0 && (
                files.map((file:any)=>(
                    <div
                        key={file.id}
                        className="flex items-center justify-between p-4 hover:bg-muted/40 transition"
                    >

                        {/* LEFT */}
                        <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-2">
                                <span className="font-medium text-sm">
                                    {file.filename}
                                </span>

                                <Badge variant="outline">
                                    Rows: {Number(
                                        file.total_rows
                                    ).toLocaleString()}
                                </Badge>

                                <Badge
                                    className={
                                        file.error_message
                                        ? "bg-red-500 text-white"
                                        : "bg-green-500 text-white"
                                    }
                                >

                                    {file.error_message
                                        ? "failed"
                                        : "success"
                                    }
                                </Badge>
                            </div>
                            <div className="text-xs text-muted-foreground">
                                Exported by {file.exported_by}
                                {" • "}
                                Duration {file.duration}
                            </div>
                        </div>

                        {/* RIGHT */}
                        <div className="flex flex-col items-end gap-1">
                            <Badge
                                variant="secondary"
                                className="text-xs"
                            >
                                {file.created_at}
                            </Badge>

                            <span className="text-xs text-muted-foreground">

                                start {file.start_datetime}

                            </span>
                        </div>
                    </div>
                ))
            )}

            {!loading && files.length === 0 && (
                <div className="p-6 text-sm text-muted-foreground">
                    No export history found
                </div>
            )}
        </div>
    );
}