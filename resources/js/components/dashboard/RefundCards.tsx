import React from "react";
import {
    Card,
    CardContent,
    CardTitle,
} from "@/components/ui/card";

import {
    CreditCard,
    CheckCircle,
    TimerIcon,
} from "lucide-react";
import { Badge } from "../ui/badge";

type Props = {
    stats:any;
};

const numberFormat = new Intl.NumberFormat();
const currentMonth = new Intl.DateTimeFormat("en-US", {
    month: "short",
    year: "numeric",
}).format(new Date());

export default function RefundCards({
    stats
}:Props){
    const cards = [
        {
            title:"All Waybills",
            all:stats.total.all_time,
            month:stats.total.this_month,
            icon:<CreditCard className="w-6 h-6 text-white"/>,
            bg:"bg-gradient-to-r from-rose-400 to-rose-600",
            badge:"bg-rose-400 text-white"
        },

        {
            title:"No Refund",
            all:stats.refund0.all_time,
            month:stats.refund0.this_month,
            export:stats.refund0.this_month_export,
            icon:<TimerIcon className="w-6 h-6 text-white"/>,
            bg:"bg-gradient-to-r from-amber-400 to-amber-600",
            badge:"bg-amber-400 text-white"
        },

        {
            title:"Refunded",
            all:stats.refund1.all_time,
            month:stats.refund1.this_month,
            icon:<CheckCircle className="w-6 h-6 text-white"/>,
            bg:"bg-gradient-to-r from-emerald-400 to-emerald-600",
            badge:"bg-emerald-400 text-white"
        }
    ];


    return (
        <>
        
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
        </>
    );
}