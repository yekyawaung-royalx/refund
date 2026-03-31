import AppLayout from "@/layouts/app-layout";
import { Head } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Logs", href: "/logs" },
  { title: "View Log", href: "#" },
];

interface Props {
  path: string;
  content: string;
}

export default function LogView({ path, content }: Props) {
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Log Viewer" />

      <div className="p-6 space-y-6">

        {/* Header */}
        <Card>
          <CardHeader>
            <CardTitle className="text-green-500">
              Log Viewer
            </CardTitle>
          </CardHeader>

          <CardContent className="text-sm text-muted-foreground break-all">
            {path}
          </CardContent>
        </Card>

        {/* Log Content */}
        <Card>
          <CardContent className="p-0">
            <pre className="p-4 text-xs text-green-400 overflow-auto max-h-[600px]">
              {content}
            </pre>
          </CardContent>
        </Card>

      </div>
    </AppLayout>
  );
}