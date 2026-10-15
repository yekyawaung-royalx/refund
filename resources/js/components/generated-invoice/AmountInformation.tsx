import * as React from "react";

import { FileDigit } from "lucide-react";

import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

import type { User } from "@/types/generated-invoice";

interface AmountInformationProps {
    expressAmount: string;
    setExpressAmount: (value: string) => void;

    untaxedAmount: string;
    setUntaxedAmount: (value: string) => void;

    taxAmount: string;
    setTaxAmount: (value: string) => void;

    tax: string;
    setTax: (value: string) => void;

    currency: string;
    setCurrency: (value: string) => void;

    approvedBy: string;
    setApprovedBy: (value: string) => void;

    totalAmount: string;
    setTotalAmount: (value: string) => void;

    totalAmountWords: string;

    users: User[];
}

export default function AmountInformation({
    expressAmount,
    setExpressAmount,

    untaxedAmount,
    setUntaxedAmount,

    taxAmount,
    setTaxAmount,

    tax,
    setTax,

    currency,
    setCurrency,

    approvedBy,
    setApprovedBy,

    totalAmount,
    setTotalAmount,

    totalAmountWords,

    users,
}: AmountInformationProps) {
    return (
        <Card className="overflow-hidden border-border/60 shadow-sm p-0 mt-4">
            {/* Card Header */}
            <CardHeader className="border-b bg-muted/20 px-2 py-1.5">
                <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                        <FileDigit className="h-4 w-4 text-primary" />
                    </div>

                    <div>
                        <CardTitle className="text-base font-semibold">
                            Amount Information
                        </CardTitle>

                        <CardDescription className="text-xs">
                            Enter invoice amounts, tax,
                            currency and approval details.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>

            {/* Card Content */}
            <CardContent>
                <div className="grid grid-cols-1 gap-2 md:grid-cols-3">
                    {/* Express Amount */}
                    <div className="space-y-2">
                        <Label>Express Amount</Label>

                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={expressAmount}
                            onChange={(e) =>
                                setExpressAmount(
                                    e.target.value
                                )
                            }
                            placeholder="0.00"
                        />
                    </div>

                    {/* Untaxed Amount */}
                    <div className="space-y-2">
                        <Label>Untaxed Amount</Label>

                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={untaxedAmount}
                            onChange={(e) =>
                                setUntaxedAmount(
                                    e.target.value
                                )
                            }
                            placeholder="0.00"
                        />
                    </div>

                    {/* Tax Amount */}
                    <div className="space-y-2">
                        <Label>Tax Amount</Label>

                        <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={taxAmount}
                            onChange={(e) =>
                                setTaxAmount(
                                    e.target.value
                                )
                            }
                            placeholder="0.00"
                        />
                    </div>

                    {/* Tax */}
                    <div className="space-y-2">
                        <Label>Tax</Label>

                        <Select
                            value={tax}
                            onValueChange={setTax}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select tax" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="0">
                                    0%
                                </SelectItem>

                                <SelectItem value="5">
                                    5%
                                </SelectItem>

                                <SelectItem value="10">
                                    10%
                                </SelectItem>

                                <SelectItem value="15">
                                    15%
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Currency */}
                    <div className="space-y-2">
                        <Label>Currency</Label>

                        <Select
                            value={currency}
                            onValueChange={setCurrency}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select currency" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="MMK">
                                    MMK
                                </SelectItem>

                                <SelectItem value="USD">
                                    USD
                                </SelectItem>

                                <SelectItem value="THB">
                                    THB
                                </SelectItem>

                                <SelectItem value="SGD">
                                    SGD
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Approved By */}
                    <div className="space-y-2">
                        <Label>Approved By</Label>

                        <Select
                            value={approvedBy}
                            onValueChange={setApprovedBy}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select approver" />
                            </SelectTrigger>

                            <SelectContent>
                                {users.map((user) => (
                                    <SelectItem
                                        key={user.id}
                                        value={String(user.id)}
                                    >
                                        {user.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {/* Total Section */}
                <div className="mt-2 mb-4">
                    <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
                        {/* Total Amount */}
                        <div className="space-y-2">
                            <Label>Total Amount</Label>

                            <Input
                                type="number"
                                step="0.01"
                                value={totalAmount}
                                onChange={(e) =>
                                    setTotalAmount(
                                        e.target.value
                                    )
                                }
                                className="font-semibold"
                                placeholder="0.00"
                            />
                        </div>

                        {/* Total Amount in Words */}
                        <div className="space-y-2">
                            <Label>
                                Total Amount in Words
                            </Label>

                            <Input
                                value={totalAmountWords}
                                readOnly
                                placeholder="Amount in words"
                                className="bg-muted/30"
                            />
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

