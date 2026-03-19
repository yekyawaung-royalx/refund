import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Card, CardContent, CardTitle } from '@/components/ui/card';
import { Users, CreditCard, CheckCircle } from 'lucide-react';
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs";
import * as React from "react";
import { Badge } from "@/components/ui/badge";
import {
  Table,
  TableHeader,
  TableRow,
  TableHead,
  TableBody,
  TableCell
} from "@/components/ui/table";

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
];

export default function Dashboard() {
    const { stats, execution_time_ms } = usePage().props as any;
    const [summaries, setSummaries] = React.useState<any[]>([]);
    const [loading, setLoading] = React.useState(true); 
    const [tab, setTab] = React.useState("refund-summary");

    const [uploadedFiles, setUploadedFiles] = React.useState<any[]>([]);
  const [uploadedLoading, setUploadedLoading] = React.useState(false);
  const [uploadedLoaded, setUploadedLoaded] = React.useState(false);

  const [exportedFiles, setExportedFiles] = React.useState<any[]>([]);
const [exportedLoading, setExportedLoading] = React.useState(false);
const [exportedLoaded, setExportedLoaded] = React.useState(false);

const [refunds, setRefunds] = React.useState<any[]>([]);
const [refundLoading, setRefundLoading] = React.useState(false);
const [refundLoaded, setRefundLoaded] = React.useState(false);

    const cardData = [
        {
            title: 'Uploaded Files',
            all: stats.refund0.all_time,
            month: stats.refund0.this_month,
            icon: <CreditCard className="w-6 h-6 text-white" />,
            bg: 'bg-gradient-to-r from-rose-400 to-rose-600',
        },
        {
            title: 'Exported Files',
            all: stats.refund1.all_time,
            month: stats.refund1.this_month,
            icon: <CheckCircle className="w-6 h-6 text-white" />,
            bg: 'bg-gradient-to-r from-emerald-400 to-emerald-600',
        },
        {
            title: 'Users',
            all: stats.total.all_time,
            month: stats.total.this_month,
            icon: <Users className="w-6 h-6 text-white" />,
            bg: 'bg-gradient-to-r from-sky-400 to-sky-600',
        },
    ];

    React.useEffect(() => {
  fetch("/recent-refund-summaries")
    .then((res) => res.json())
    .then((data) => {
      setSummaries(data);
      setLoading(false);
    })
    .catch(() => {
      setLoading(false);
    });

    if (tab === "uploaded" && !uploadedLoaded) {
    setUploadedLoading(true);

    fetch("/recent-uploaded-files")
      .then((res) => res.json())
      .then((data) => {
        setUploadedFiles(data);
        setUploadedLoading(false);
        setUploadedLoaded(true);
      })
      .catch(() => {
        setUploadedLoading(false);
      });
  }

  // exported
  if (tab === "exported" && !exportedLoaded) {
    setExportedLoading(true);

    fetch("/recent-exported-files")
      .then((res) => res.json())
      .then((data) => {
        setExportedFiles(data);
        setExportedLoading(false);
        setExportedLoaded(true);
      });
  }

  if (tab === "refunds" && !refundLoaded) {
    setRefundLoading(true);

    fetch("/recent-uploaded-data")
      .then((res) => res.json())
      .then((data) => {
        setRefunds(data);
        setRefundLoading(false);
        setRefundLoaded(true);
      })
      .catch(() => {
        setRefundLoading(false);
      });
  }

}, [tab]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Refund Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Title + Results In */}
    <div className="flex flex-col gap-1">
      {/* Main Title */}
      <CardTitle className="text-xl md:text-2xl">
        Dashboard
      </CardTitle>

      {/* Results In + Execution Time */}
      <div className="flex items-center gap-2 text-sm md:text-base">
        <span>Results In:</span>
        <span className="text-green-500 font-light">
          {(execution_time_ms / 1000).toFixed(2)} s
        </span>
      </div>
    </div>
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    {cardData.map((card) => (
                        <Card
    key={card.title}
    className={`relative overflow-hidden rounded-xl shadow-lg hover:scale-105 transition-transform duration-300`}
>
    {/* Gradient Background */}
    <div className={`${card.bg} absolute inset-0 opacity-80`}></div>

    <CardContent className="relative z-10 text-white space-y-4">
        {/* Icon + Title */}
        <div className="flex items-center gap-2">
            {card.icon}
            <CardTitle className="text-white text-lg md:text-xl">{card.title}</CardTitle>
        </div>

        {/* Main Number Row */}
        <div className="flex flex-col gap-1 mt-2">
            {/* This Month */}
            <div className="text-3xl md:text-4xl font-bold">
                {new Intl.NumberFormat().format(card.month)}{' '}
                <span className="text-base font-medium ml-2">
                    Mar 2026
                </span>
            </div>

            {/* All Records */}
            <div className="text-sm mt-2">
                <span className="font-semibold">All Records:</span> {new Intl.NumberFormat().format(card.all)}
            </div>
        </div>
    </CardContent>
</Card>
                    ))}
                </div>

                {/* Main content placeholder */}
                <div className="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border md:min-h-min p-6">
  
  <Tabs value={tab} onValueChange={setTab} className="w-full">
    
    <TabsList className="grid w-full grid-cols-4">
      <TabsTrigger value="refund-summary">Daily Refund Summary</TabsTrigger>
      <TabsTrigger value="uploaded">Recent Uploaded</TabsTrigger>
      <TabsTrigger value="exported">Recent Exported</TabsTrigger>
      <TabsTrigger value="refunds">Recent Refunds</TabsTrigger>
    </TabsList>

    {/* Daily Refund */}
    <TabsContent value="refund-summary" className="mt-6">
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
          <th className="text-left p-3">Summary Date</th>
          <th className="text-left p-3">Payment Date</th>
          <th className="text-right p-3">To Refund Amount</th>
          <th className="text-right p-3">Refund Amount</th>
          <th className="text-right p-3">To Refund Waybills</th>
          <th className="text-right p-3">Refund Waybills</th>
        </tr>
      </thead>

      <tbody>
        {summaries.map((item: any, index: number) => (
          <tr
            key={index}
            className="border-t hover:bg-muted/40 transition-colors"
          >
            <td className="p-3">{item.date}</td>

            <td className="p-3">
              {item.payment_date ?? (
                <span className="text-muted-foreground italic">-</span>
              )}
            </td>

            <td className="p-3 text-right font-medium text-amber-500">
              {Number(item.to_refund_amount).toLocaleString()}
            </td>

            <td className="p-3 text-right font-medium text-green-500">
              {Number(item.refund_amount).toLocaleString()}
            </td>

            <td className="p-3 text-right">
              {item.to_refund_rows.toLocaleString()}
            </td>

            <td className="p-3 text-right">
              {item.refund_rows.toLocaleString()}
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  </div>
)}

    {!loading && summaries.length === 0 && (
      <div className="p-6 text-sm text-muted-foreground">
        No recent refund summaries
      </div>
    )}

  </div>
</TabsContent>

    {/* Uploaded */}
    <TabsContent value="uploaded" className="mt-6">
  <div className="rounded-xl border bg-background shadow-sm divide-y">

    {uploadedLoading && (
      <div className="p-6 text-sm text-muted-foreground">
        Loading uploaded files...
      </div>
    )}

    {!uploadedLoading && uploadedFiles.length > 0 && (
      uploadedFiles.map((file: any) => (
        <div
          key={file.id}
          className="flex items-center justify-between p-4 hover:bg-muted/40 transition"
        >

          {/* left section */}
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
              Title: {file.title} • Rows: {file.total_rows} • Uploaded by {file.uploaded_by_name}
            </div>
          </div>

          {/* right side */}
          <Badge variant="secondary" className="text-xs whitespace-nowrap">
            {file.created_at}
          </Badge>

        </div>
      ))
    )}

    {!uploadedLoading && uploadedFiles.length === 0 && (
      <div className="p-6 text-sm text-muted-foreground">
        No uploaded files found
      </div>
    )}

  </div>
</TabsContent>

    {/* Exported */}
    <TabsContent value="exported" className="mt-6">
  <div className="rounded-xl border bg-background shadow-sm divide-y">

    {exportedLoading && (
      <div className="p-6 text-sm text-muted-foreground">
        Loading exported files...
      </div>
    )}

    {!exportedLoading && exportedFiles.length > 0 && (
      exportedFiles.map((file: any) => (
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
                rows: {file.total_rows}
              </Badge>

              <Badge
                className={
                  file.error_message
                    ? "bg-red-500 text-white"
                    : "bg-green-500 text-white"
                }
              >
                {file.error_message ? "failed" : "success"}
              </Badge>
            </div>

            <div className="text-xs text-muted-foreground">
              Exported by {file.exported_by} • Duration {file.duration}
            </div>

          </div>

          {/* RIGHT */}
          <div className="flex flex-col items-end gap-1">

            <Badge variant="secondary" className="text-xs">
              {file.created_at}
            </Badge>

            <span className="text-xs text-muted-foreground">
              start {file.start_datetime}
            </span>

          </div>

        </div>
      ))
    )}

    {!exportedLoading && exportedFiles.length === 0 && (
      <div className="p-6 text-sm text-muted-foreground">
        No export history found
      </div>
    )}

  </div>
</TabsContent>

    {/* Refunds */}
    <TabsContent value="refunds" className="mt-6">

  <div className="rounded-xl border bg-background shadow-sm overflow-hidden">

    {refundLoading && (
      <div className="p-6 text-sm text-muted-foreground">
        Loading refund activities...
      </div>
    )}

    {!refundLoading && refunds.length > 0 && (

      <Table>

        <TableHeader>
          <TableRow>
            <TableHead>Date</TableHead>
            <TableHead>Waybill</TableHead>
            <TableHead>Customer</TableHead>
            <TableHead>From - To</TableHead>
            <TableHead>Receiver</TableHead>
            <TableHead>Refund</TableHead>
            <TableHead className="text-right">Created</TableHead>
          </TableRow>
        </TableHeader>

        <TableBody>

          {refunds.map((row: any, index: number) => (
            <TableRow key={index}>

              <TableCell className="text-sm">
                {row.date}
              </TableCell>

              <TableCell className="font-medium">
                {row.waybill_no}
              </TableCell>

              <TableCell>
                <div className="flex flex-col">
                  <span>{row.customer}</span>
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

    {!refundLoading && refunds.length === 0 && (
      <div className="p-6 text-sm text-muted-foreground">
        No refund activities found
      </div>
    )}

  </div>

</TabsContent>

  </Tabs>

</div>
            </div>
        </AppLayout>
    );
}