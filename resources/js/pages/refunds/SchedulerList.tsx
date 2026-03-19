import AppLayout from "@/layouts/app-layout";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Head } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import { AlarmClock } from "lucide-react";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Settings", href: "/dashboard/settings" },
  { title: "Schedulers", href: "#" },
];

interface Props {
  schedulers: string[];
}

export default function SchedulerList({ schedulers }: Props) {
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Schedulers List" />

      <div className="p-6 space-y-6">

        {/* Summary */}
        <Card>
          <CardHeader>
            <CardTitle>Scheduler Summary</CardTitle>
          </CardHeader>

          <CardContent className="text-sm text-muted-foreground">
            Total Schedulers: {schedulers?.length ?? 0}
          </CardContent>
        </Card>

        {/* Job Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {schedulers?.length === 0 && (
            <div className="text-muted-foreground text-sm">
              No jobs found.
            </div>
          )}

          {schedulers?.map((scheduler, index) => (
        <Card key={index} className="hover:shadow-md transition">
            <CardHeader>
            <CardTitle className="flex items-center gap-3 text-base text-green-500">
                <AlarmClock className="h-7 w-7 text-muted-foreground" />
                {scheduler.split("\\").pop()}
            </CardTitle>
            </CardHeader>

            <CardContent className="text-sm text-muted-foreground break-all">
            {scheduler}
            </CardContent>
        </Card>
        ))}
        </div>

      </div>
    </AppLayout>
  );
}

