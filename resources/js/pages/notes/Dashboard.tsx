import AppLayout from "@/layouts/app-layout";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Head } from "@inertiajs/react";
import { type BreadcrumbItem } from "@/types";
import { FileTextIcon } from "lucide-react";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Settings", href: "/dashboard/settings" },
  { title: "Notes", href: "#" },
];

interface Note {
  title: string;
  link: string;
}

interface Props {
  notes: Note[];
}

export default function NoteList({ notes }: Props) {
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Notes" />

      <div className="p-6 space-y-6">

        {/* Summary */}
        <Card>
          <CardHeader>
            <CardTitle>Notes Summary</CardTitle>
          </CardHeader>

          <CardContent className="text-sm text-muted-foreground">
            Total Notes: {notes?.length ?? 0}
          </CardContent>
        </Card>

        {/* Notes Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-4">
          {notes?.length === 0 && (
            <div className="text-muted-foreground text-sm">
              No notes found.
            </div>
          )}

          {notes?.map((note, index) => (
            <a
              key={index}
              href={note.link}
              target="_blank"
              rel="noopener noreferrer"
            >
              <Card className="hover:shadow-md transition cursor-pointer">
                <CardHeader>
                  <CardTitle className="flex items-center gap-3 text-base text-green-500">
                    <FileTextIcon className="h-6 w-6 text-muted-foreground" />
                    {note.title}
                  </CardTitle>
                </CardHeader>

                <CardContent className="text-sm text-muted-foreground break-all">
                  {note.link}
                </CardContent>
              </Card>
            </a>
          ))}
        </div>

      </div>
    </AppLayout>
  );
}