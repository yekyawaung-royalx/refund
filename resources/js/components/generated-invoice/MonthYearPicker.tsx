import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

interface MonthYearPickerProps {
    value: string;
    onChange: (value: string) => void;
}

export default function MonthYearPicker({
    value,
    onChange,
}: MonthYearPickerProps) {
    const currentYear = new Date().getFullYear();

    const years = Array.from(
        { length: 10 },
        (_, index) => currentYear - 5 + index
    );

    const months = [
        { value: "01", label: "January" },
        { value: "02", label: "February" },
        { value: "03", label: "March" },
        { value: "04", label: "April" },
        { value: "05", label: "May" },
        { value: "06", label: "June" },
        { value: "07", label: "July" },
        { value: "08", label: "August" },
        { value: "09", label: "September" },
        { value: "10", label: "October" },
        { value: "11", label: "November" },
        { value: "12", label: "December" },
    ];

    const [year, month] = value.split("/");

    const selectedYear = year
        ? 2000 + Number(year)
        : currentYear;

    const selectedMonth =
        month ||
        String(new Date().getMonth() + 1).padStart(2, "0");

    const update = (
        newYear: number,
        newMonth: string
    ) => {
        onChange(
            `${String(newYear).slice(-2)}/${newMonth}`
        );
    };

    return (
        <div className="grid grid-cols-2 gap-2">
            <Select
                value={String(selectedYear)}
                onValueChange={(value) =>
                    update(
                        Number(value),
                        selectedMonth
                    )
                }
            >
                <SelectTrigger>
                    <SelectValue />
                </SelectTrigger>

                <SelectContent>
                    {years.map((year) => (
                        <SelectItem
                            key={year}
                            value={String(year)}
                        >
                            {year}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={selectedMonth}
                onValueChange={(value) =>
                    update(selectedYear, value)
                }
            >
                <SelectTrigger>
                    <SelectValue />
                </SelectTrigger>

                <SelectContent>
                    {months.map((month) => (
                        <SelectItem
                            key={month.value}
                            value={month.value}
                        >
                            {month.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}