import React from "react";
import { Badge } from "@/components/ui/badge";

type Props = {
    files: any[];
    loading: boolean;
};

export default function RecentUploaded({
    files,
    loading,
}: Props) {
    return (
        <div className="rounded-xl border bg-background shadow-sm divide-y">
            {loading && (
                <div className="p-6 text-sm text-muted-foreground">
                    Loading uploaded files...
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
                                    {file.category}
                                </Badge>

                                <Badge
                                    className={
                                        file.status === "completed"
                                        ? "bg-green-500 text-white"
                                        : "bg-red-500 text-white"
                                    }
                                >
                                    {file.status}
                                </Badge>
                            </div>

                            <div className="text-xs text-muted-foreground">
                                Title: {file.title}
                                {" • "}
                                Rows: {Number(
                                    file.total_rows
                                ).toLocaleString()}
                                {" • "}
                                Uploaded by {file.uploaded_by_name}
                            </div>
                        </div>

                        {/* RIGHT */}
                        <Badge
                            variant="secondary"
                            className="text-xs whitespace-nowrap"
                        >
                            {file.created_at}
                        </Badge>
                    </div>
                ))
            )}

            {!loading && files.length === 0 && (
                <div className="p-6 text-sm text-muted-foreground">
                    No uploaded files found
                </div>
            )}
        </div>
    );
}