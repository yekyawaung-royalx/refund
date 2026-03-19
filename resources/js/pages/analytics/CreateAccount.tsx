"use client";

import * as React from "react";
import { Head, router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Loader2, UserPlus } from "lucide-react";
import { toast } from "sonner";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Create Analytics Account", href: "#" }
];

export default function CreateAccountPage() {
  const [form, setForm] = React.useState({
    account: "",
    reference: "",
    journal: "",
  });

  const [loading, setLoading] = React.useState(false);

  const handleChange = (key: string, value: string) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    // CSRF token from meta tag
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    router.post("/analytics-accounts", {
      ...form,
      _token: token,
    }, {
      onSuccess: () => {
        toast.success("Analytics account created successfully 🎉");
        setForm({ account: "", reference: "", journal: "" });
      },
      onError: () => {
        toast.error("Failed to create analytics account.");
      },
      onFinish: () => setLoading(false),
    });
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Create Analytics Account" />

      <div className="p-6">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <Card className="rounded-2xl shadow-md">
          <CardHeader>
            <CardTitle className="text-xl text-green-500 flex items-center gap-2">
              <UserPlus className="h-5 w-5" />
              Create Analytics Account
            </CardTitle>
          </CardHeader>

          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-6">

              {/* Account */}
              <div className="space-y-2">
                <label className="text-sm font-medium">Account</label>
                <Input
                  value={form.account}
                  onChange={(e) => handleChange("account", e.target.value)}
                  placeholder="Enter account name"
                  required
                />
              </div>

              {/* Reference */}
              <div className="space-y-2">
                <label className="text-sm font-medium">Reference</label>
                <Input
                  value={form.reference}
                  onChange={(e) => handleChange("reference", e.target.value)}
                  placeholder="Enter reference code"
                  required
                />
              </div>

              {/* Journal */}
              <div className="space-y-2">
                <label className="text-sm font-medium">Journal</label>
                <Input
                  value={form.journal}
                  onChange={(e) => handleChange("journal", e.target.value)}
                  placeholder="Enter journal info"
                />
              </div>

              <Button
                type="submit"
                disabled={loading}
                className="w-full bg-green-500 hover:bg-green-600 text-white"
              >
                {loading ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Saving...
                  </>
                ) : (
                  "Create Account"
                )}
              </Button>
            </form>
          </CardContent>
        </Card>

        {/* Optional Instructions */}
        <Card className="rounded-2xl shadow-sm border border-muted">
          <CardHeader>
            <CardTitle className="text-lg text-green-500">
              Instructions
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4 text-sm text-muted-foreground">
            <ul className="list-disc pl-5 space-y-2">
              <li>Account name must be unique.</li>
              <li>Reference code should be concise and unique.</li>
              <li>Journal info is optional but recommended for tracking.</li>
            </ul>
            <div className="rounded-lg bg-amber-50 p-4 text-amber-700">
              Tip: Keep journal info consistent with company accounting.
            </div>
          </CardContent>
        </Card>
        </div>
      </div>
    </AppLayout>
  );
}