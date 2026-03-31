import AppLayout from "@/layouts/app-layout";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Head } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import { HammerIcon, HeartPulseIcon, LogsIcon } from "lucide-react";

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

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
  {/* Left Card */}
  <Card>
    <CardHeader>
      <CardTitle>Job Summary</CardTitle>
    </CardHeader>

    <CardContent className="text-sm text-muted-foreground">
      Total Jobs: {jobs?.length ?? 0}
    </CardContent>
  </Card>

  {/* Right Card */}
  <Card>
    <CardHeader>
      <CardTitle>Monitoring Logs For Jobs</CardTitle>
    </CardHeader>

    <CardContent className="text-sm text-muted-foreground">
      <div className="flex space-x-4">
      <button
        onClick={() => (window.location.href = '/log-viewer','_blank')}
        className="flex items-center cursor-pointer gap-1 px-2 py-0.5 bg-blue-500 text-white rounded hover:bg-blue-600"
      >
        <LogsIcon />
        Log Viewer
      </button>

      <button
        onClick={() => (window.location.href = '/pulse','_blank')}
        className="flex items-center cursor-pointer gap-1 px-2 py-0.5 bg-green-500 text-white rounded hover:bg-green-600"
      >
        <HeartPulseIcon className="h-4 w-4" />
        Pulse For Job
      </button>
    </div>
    </CardContent>
  </Card>
</div>

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

