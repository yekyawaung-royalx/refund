import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { CardTitle } from '@/components/ui/card';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from "@/components/ui/tabs";
import * as React from "react";
import DailyRefundSummary from '@/components/dashboard/DailyRefundSummary';
import RecentUploaded from '@/components/dashboard/RecentUploaded';
import RecentExported from '@/components/dashboard/RecentExported';
import RecentRefunds from '@/components/dashboard/RecentRefunds';
import DashboardCards from '@/components/dashboard/DashboardCards';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
];

type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    data: any[];
};

export default function Dashboard() {
    const { stats, execution_time_ms } = usePage().props as any;
    //const [summaries, setSummaries] = React.useState<any[]>([]);
    const [summaryPage, setSummaryPage] = React.useState<Pagination | null>(null);
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
    //const [rows, setRows] = React.useState<any[]>([]);

    const loadSummaries = async (page = 1) => {
        try {
            setLoading(true);

            const res = await fetch(`/recent-refund-summaries?page=${page}`);
            const json = await res.json();

            setSummaryPage(json);
        } catch (err) {
            console.error(err);
        } finally {
            setLoading(false);
    }
    };

    React.useEffect(() => {
        loadSummaries();

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
    }, [tab,exportedLoaded,refundLoaded,uploadedLoaded]);

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
                  
                  <DashboardCards stats={stats} />

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
                            <DailyRefundSummary
                                summaries={summaryPage}
                                loading={loading}
                                //setSummaryPage={setSummaryPage}
                                onPageChange={loadSummaries}
                            />
                        </TabsContent>

                        {/* Uploaded */}
                        <TabsContent value="uploaded" className="mt-6">
                            <RecentUploaded
                                files={uploadedFiles}
                                loading={uploadedLoading}
                            />
                        </TabsContent>

                        {/* Exported */}
                        <TabsContent value="exported" className="mt-6">
                            <RecentExported
                                files={exportedFiles}
                                loading={exportedLoading}
                            />
                        </TabsContent>
                        
                        {/* Refunds */}
                        <TabsContent value="refunds" className="mt-6">
                            <RecentRefunds
                                refunds={refunds}
                                loading={refundLoading}
                            />
                        </TabsContent>
                    </Tabs>
                </div>
            </div>
        </AppLayout>
    );
}