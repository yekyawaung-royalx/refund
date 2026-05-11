import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Card, CardContent, CardTitle } from '@/components/ui/card';
import { Users, CreditCard, CheckCircle } from 'lucide-react';
import * as React from "react";
import SplashModal from "@/components/SplashModal";

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
];

export default function Dashboard() {
    const { stats, execution_time_ms } = usePage().props as any;
    const cardData = [
        {
            title: 'No Refund Files',
            all: stats.refund0.all_time,
            month: stats.refund0.this_month,
            icon: <CreditCard className="w-6 h-6 text-white" />,
            bg: 'bg-gradient-to-r from-rose-400 to-rose-600',
        },
        {
            title: 'Refund Files',
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
        {
            title: 'Users',
            all: stats.total.all_time,
            month: stats.total.this_month,
            icon: <Users className="w-6 h-6 text-white" />,
            bg: 'bg-gradient-to-r from-sky-400 to-sky-600',
        },
        {
            title: 'Users',
            all: stats.total.all_time,
            month: stats.total.this_month,
            icon: <Users className="w-6 h-6 text-white" />,
            bg: 'bg-gradient-to-r from-sky-400 to-sky-600',
        },
        {
            title: 'Users',
            all: stats.total.all_time,
            month: stats.total.this_month,
            icon: <Users className="w-6 h-6 text-white" />,
            bg: 'bg-gradient-to-r from-sky-400 to-sky-600',
        },
    ];


    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SplashModal duration={9} />
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Title + Results In */}
    <div className="flex flex-col gap-1">
      {/* Main Title */}
      <CardTitle className="text-xl md:text-2xl">
        Main Dashboard
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

            </div>
        </AppLayout>
    );
}