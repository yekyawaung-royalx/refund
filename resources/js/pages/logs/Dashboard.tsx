import AppLayout from "@/layouts/app-layout";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Head, Link } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import { FileTextIcon } from "lucide-react";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Settings", href: "/dashboard/settings" },
  { title: "Logs", href: "#" },
];

interface Log {
  title: string;
  path: string;
}

interface Props {
  logs: Log[];
}

export default function LogList({ logs }: Props) {
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Logs" />

      <div className="p-6 space-y-6">

        {/* Summary */}
        <Card>
          <CardHeader>
            <CardTitle>Logs Summary</CardTitle>
          </CardHeader>

          <CardContent className="text-sm text-muted-foreground">
            Total Logs: {(logs?.length ?? 0) + 1}
          </CardContent>
        </Card>

        {/* Notes Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-4">
          {logs?.length === 0 && (
            <div className="text-muted-foreground text-sm">
              No logs found.
            </div>
          )}
          <a href="/log-viewer" target="_blank">
    <Card className="hover:shadow-md transition cursor-pointer">
      <CardHeader>
        <CardTitle className="flex items-center gap-3 text-base text-green-500">
          <FileTextIcon className="h-6 w-6 text-muted-foreground" />
          Laravel Log Viewer
        </CardTitle>
      </CardHeader>

      <CardContent className="text-sm text-muted-foreground break-all">
        logs/laravel.log
      </CardContent>
    </Card>
  </a>

          {logs?.map((log, index) => (
  <Link
    key={index}
    href={route("logs.view", { path: log.path })}
  >
    <Card className="hover:shadow-md transition cursor-pointer">
      <CardHeader>
        <CardTitle className="flex items-center gap-3 text-base text-green-500">
          <FileTextIcon className="h-6 w-6 text-muted-foreground" />
          {log.title}
        </CardTitle>
      </CardHeader>

      <CardContent className="text-sm text-muted-foreground break-all">
        {log.path}
      </CardContent>
    </Card>
  </Link>
))}


        </div>

      </div>
    </AppLayout>
  );
}