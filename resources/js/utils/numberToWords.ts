export const numberToWords = (num: number): string => {
    if (!Number.isFinite(num)) {
        return "";
    }

    const ones = [
        "",
        "ONE",
        "TWO",
        "THREE",
        "FOUR",
        "FIVE",
        "SIX",
        "SEVEN",
        "EIGHT",
        "NINE",
        "TEN",
        "ELEVEN",
        "TWELVE",
        "THIRTEEN",
        "FOURTEEN",
        "FIFTEEN",
        "SIXTEEN",
        "SEVENTEEN",
        "EIGHTEEN",
        "NINETEEN",
    ];

    const tens = [
        "",
        "",
        "TWENTY",
        "THIRTY",
        "FORTY",
        "FIFTY",
        "SIXTY",
        "SEVENTY",
        "EIGHTY",
        "NINETY",
    ];

    const convertHundreds = (n: number): string => {
        let result = "";

        if (n >= 100) {
            result += ones[Math.floor(n / 100)] + " HUNDRED";

            n %= 100;

            if (n > 0) {
                result += " AND ";
            }
        }

        if (n >= 20) {
            result += tens[Math.floor(n / 10)];

            if (n % 10 > 0) {
                result += "-" + ones[n % 10];
            }
        } else if (n > 0) {
            result += ones[n];
        }

        return result;
    };

    if (num === 0) {
        return "ZERO";
    }

    const integerPart = Math.floor(num);

    const million = Math.floor(integerPart / 1_000_000);
    const thousand = Math.floor((integerPart % 1_000_000) / 1_000);
    const remainder = integerPart % 1_000;

    const parts: string[] = [];

    if (million > 0) {
        parts.push(`${convertHundreds(million)} MILLION`);
    }

    if (thousand > 0) {
        parts.push(`${convertHundreds(thousand)} THOUSAND`);
    }

    if (remainder > 0) {
        parts.push(convertHundreds(remainder));
    }

    return parts.join(", ");
};