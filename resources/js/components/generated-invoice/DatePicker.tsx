import { CalendarIcon } from "lucide-react";
import { format } from "date-fns";

import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";

import { cn } from "@/lib/utils";

interface DatePickerProps {
    date?: Date;
    setDate: (date: Date | undefined) => void;
    placeholder?: string;
}

export default function DatePicker({
    date,
    setDate,
    placeholder = "Select date",
}: DatePickerProps) {
    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className={cn(
                        "h-9 w-full justify-start px-3 text-left font-normal",
                        !date && "text-muted-foreground"
                    )}
                >
                    <CalendarIcon className="mr-2 h-4 w-4" />

                    {date
                        ? format(date, "yyyy-MM-dd")
                        : placeholder}
                </Button>
            </PopoverTrigger>

            <PopoverContent
                className="w-auto p-0"
                align="start"
            >
                <Calendar
                    mode="single"
                    selected={date}
                    onSelect={setDate}
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