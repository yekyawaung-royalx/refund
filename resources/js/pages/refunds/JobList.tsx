import AppLayout from "@/layouts/app-layout";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Head } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import { HammerIcon } from "lucide-react";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Settings", href: "/dashboard/settings" },
  { title: "Jobs", href: "#" },
];

interface Props {
  jobs: string[];
}

export default function JobList({ jobs }: Props) {
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Job List" />

      <div className="p-6 space-y-6">

        {/* Summary */}
        <Card>
          <CardHeader>
            <CardTitle>Job Summary</CardTitle>
          </CardHeader>

          <CardContent className="text-sm text-muted-foreground">
            Total Jobs: {jobs?.length ?? 0}
          </CardContent>
        </Card>

        {/* Job Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {jobs?.length === 0 && (
            <div className="text-muted-foreground text-sm">
              No jobs found.
            </div>
          )}

          {jobs?.map((job, index) => (
        <Card key={index} className="hover:shadow-md transition">
            <CardHeader>
            <CardTitle className="flex items-center gap-3 text-base text-green-500">
                <HammerIcon className="h-7 w-7 text-muted-foreground" />
                {job.split("\\").pop()}
            </CardTitle>
            </CardHeader>

            <CardContent className="text-sm text-muted-foreground break-all">
            {job}
            </CardContent>
        </Card>
        ))}
        </div>

      </div>
    </AppLayout>
  );
}

