import React from "react";
import {
    Card,
    CardContent,
    CardTitle,
} from "@/components/ui/card";

import {
    Files,
    FileCheck,
    FileClock,
} from "lucide-react";

import { Badge } from "@/components/ui/badge";

type Stats = {
    total: number;
    total_this_month: number;

    no_refund_total: number;
    no_refund_this_month: number;

    refunded_total: number;
    refunded_this_month: number;
};

type Props = {
    stats: Stats;
};

const numberFormat = new Intl.NumberFormat();

const currentMonth = new Intl.DateTimeFormat("en-US", {
    month: "short",
    year: "numeric",
}).format(new Date());

export default function FileCards({ stats }: Props) {
    const cards = [
        {
            title: "All Files",
            all: stats.total,
            month: stats.total_this_month,
            icon: (
                <Files className="h-6 w-6 text-white" />
            ),
            bg: "bg-gradient-to-r from-sky-400 to-sky-600",
            badge: "bg-sky-400 text-white",
        },

        {
            title: "No Refund Files",
            all: stats.no_refund_total,
            month: stats.no_refund_this_month,
            icon: (
                <FileClock className="h-6 w-6 text-white" />
            ),
            bg: "bg-gradient-to-r from-orange-400 to-orange-600",
            badge: "bg-orange-400 text-white",
        },

        {
            title: "Refunded Files",
            all: stats.refunded_total,
            month: stats.refunded_this_month,
            icon: (
                <FileCheck className="h-6 w-6 text-white" />
            ),
            bg: "bg-gradient-to-r from-green-400 to-green-600",
            badge: "bg-green-400 text-white",
        },
    ];

    return (
        <div className="grid auto-rows-min gap-4 md:grid-cols-3">
            {cards.map((card) => (
                <Card
                    key={card.title}
                    className="
                        relative
                        overflow-hidden
                        rounded-xl
                        border-0
                        shadow-lg
                        transition-transform
                        duration-300
                        hover:scale-[1.02]
                    "
                >
                    {/* Background */}
                    <div
                        className={`
                            absolute
                            inset-0
                            ${card.bg}
                            opacity-90
                        `}
                    />

                    <CardContent className="relative z-10  text-white">
                        {/* Header */}
                        <div className="flex items-center gap-2">
                            {card.icon}

                            <CardTitle className="flex flex-1 items-center justify-between text-white">
                                <span>{card.title}</span>

                                <Badge
                                    className={`
                                        ${card.badge}
                                        rounded-full
                                        border-0
                                        text-xs
                                        font-medium
                                    `}
                                >
                                    {currentMonth}
                                </Badge>
                            </CardTitle>
                        </div>

                        {/* This Month */}
                        <div className="mt-2">
                            <div className="text-xs font-medium uppercase tracking-wide text-white/70">
                                This Month
                            </div>

                            <div className="mt-1 text-4xl font-bold">
                                {numberFormat.format(card.month)}
                            </div>
                        </div>

                        {/* Divider */}
                        <div className="my-2 h-px bg-white/20" />

                        {/* All Records */}
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium text-white/80">
                                All Records
                            </span>

                            <span className="text-lg font-bold">
                                {numberFormat.format(card.all)}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}