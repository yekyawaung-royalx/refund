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

export default function DashboardCards({
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
        <div className="grid auto-rows-min gap-4 md:grid-cols-3">
            {cards.map(card=>(
                <Card key={card.title} className="relative overflow-hidden rounded-xl shadow-lg hover:scale-105 transition-transform duration-300">
                    <div className={` ${card.bg} absolute inset-0 opacity-80`} />
                    <CardContent className="relative z-10 text-white space-y-4">
                        <div className="flex items-center gap-2">
                            {card.icon}
                            <CardTitle className="flex flex-1 items-center justify-between text-white">
                                <span>{card.title}</span>
                                <Badge className={`${card.badge} rounded-full text-xs font-medium`}>
                                    {currentMonth}
                                </Badge>
                            </CardTitle>
                        </div>
                        <div className="flex flex-col gap-1 mt-2">
                            {/* Row 1 - 2 cols */}
                            <div className="grid grid-cols-2 items-center gap-4">
                                {/* Col 1 */}
                                <div className="text-3xl md:text-4xl font-bold">
                                    {numberFormat.format(card.month)}
                                </div>

                                {/* Col 2 */}
                                {card.export && (
                                    <div className="flex items-center justify-between rounded-lg bg-white/10 border border-white/20 px-1.5 py-0.5">
                                        <span className="text-sm text-slate-300 font-medium">
                                            To Export:
                                        </span>

                                        <span className="text-lg font-bold text-white">
                                            {numberFormat.format(card.export)}
                                        </span>

                                        {/* Pulse */}
                                        <span className="relative flex h-3 w-3">
                                            <span className="absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75 animate-ping"/>
                                            <span className="relative inline-flex rounded-full h-3 w-3 bg-amber-500"/>
                                        </span>
                                    </div>
                                )}
                            </div>

                            {/* Bottom */}
                            <div className="text-sm mt-2">
                                <span className="font-semibold">
                                    All Records:
                                </span>
                                {" "}
                                {numberFormat.format(card.all)}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}