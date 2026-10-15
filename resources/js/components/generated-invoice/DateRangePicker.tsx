import { CalendarIcon } from "lucide-react";
import { format } from "date-fns";
import type { DateRange } from "react-day-picker";

import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";

import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";

import { cn } from "@/lib/utils";

interface DateRangePickerProps {
    value?: DateRange;
    onChange: (value: DateRange | undefined) => void;
}

export default function DateRangePicker({
    value,
    onChange,
}: DateRangePickerProps) {
    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className={cn(
                        "h-9 w-full justify-start px-3 text-left font-normal",
                        !value?.from && "text-muted-foreground"
                    )}
                >
                    <CalendarIcon className="mr-2 h-4 w-4" />

                    {value?.from ? (
                        value.to ? (
                            <>
                                {format(value.from, "yyyy-MM-dd")}
                                {" - "}
                                {format(value.to, "yyyy-MM-dd")}
                            </>
                        ) : (
                            format(value.from, "yyyy-MM-dd")
                        )
                    ) : (
                        "Select date range"
                    )}
                </Button>
            </PopoverTrigger>

            <PopoverContent
                className="w-auto p-0"
                align="start"
            >
                <Calendar
                    mode="range"
                    selected={value}
                    onSelect={onChange}
                    numberOfMonths={2}
                    initialFocus
                    classNames={{
                        day: "h-6 w-6",
                        day_selected: "h-6 w-6",
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}