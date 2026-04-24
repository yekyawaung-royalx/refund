"use client";

import * as React from "react";
import { Head, router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

import { Loader2, UserPlus } from "lucide-react";
import { toast } from "sonner";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Users", href: "/users" },
  { title: "Create User", href: "#" },
];

const roles = [
  { value: "admin", label: "Admin" },
  { value: "manager", label: "Manager" },
  { value: "norefund-staff", label: "Staff (Refund)" },
  { value: "refund-staff", label: "Staff (No Refund)" },
];

const statuses = [
  { value: "active", label: "Active" },
  { value: "inactive", label: "Inactive" },
];

export default function UserCreatePage() {
  const [form, setForm] = React.useState({
    name: "",
    first_name: "",
    last_name: "",
    phone_no: "",
    role: "",
    status: "",
    email: "",
    password: "",
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

  router.post("/users", {
    ...form,
    _token: token, // add CSRF token here
  }, {
    onSuccess: () => {
      toast.success("User created successfully 🎉");
      setForm({
        name: "",
        first_name: "",
        last_name: "",
        phone_no: "",
        role: "",
        status: "",
        email: "",
        password: "",
      });
    },
    onError: () => {
      toast.error("Failed to create user.");
    },
    onFinish: () => setLoading(false),
  });
};

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Create User" />

      <div className="p-6">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">

          {/* LEFT CARD - FORM */}
          <Card className="rounded-2xl shadow-md">
            <CardHeader>
              <CardTitle className="text-xl text-green-500 flex items-center gap-2">
                <UserPlus className="h-5 w-5" />
                Create New User
              </CardTitle>
            </CardHeader>

            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-6">

                {/* Username */}
                <div className="space-y-2">
                  <label className="text-sm font-medium">Username</label>
                  <Input
                    value={form.name}
                    onChange={(e) => handleChange("name", e.target.value)}
                    placeholder="Enter username"
                  />
                </div>

                {/* First & Last Name */}
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <label className="text-sm font-medium">First Name</label>
                    <Input
                      value={form.first_name}
                      onChange={(e) =>
                        handleChange("first_name", e.target.value)
                      }
                      placeholder="First name"
                    />
                  </div>

                  <div className="space-y-2">
                    <label className="text-sm font-medium">Last Name</label>
                    <Input
                      value={form.last_name}
                      onChange={(e) =>
                        handleChange("last_name", e.target.value)
                      }
                      placeholder="Last name"
                    />
                  </div>
                </div>

                {/* Phone */}
                <div className="space-y-2">
                  <label className="text-sm font-medium">Phone Number</label>
                  <Input
                    value={form.phone_no}
                    onChange={(e) =>
                      handleChange("phone_no", e.target.value)
                    }
                    placeholder="09xxxxxxxxx"
                  />
                </div>

                {/* Role */}
                <div className="space-y-2">
                  <label className="text-sm font-medium">Role</label>
                  <Select
                    value={form.role}
                    onValueChange={(v) => handleChange("role", v)}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select role" />
                    </SelectTrigger>
                    <SelectContent>
                      {roles.map((r) => (
                        <SelectItem key={r.value} value={r.value}>
                          {r.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                {/* Status */}
                <div className="space-y-2">
                  <label className="text-sm font-medium">Status</label>
                  <Select
                    value={form.status}
                    onValueChange={(v) => handleChange("status", v)}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select status" />
                    </SelectTrigger>
                    <SelectContent>
                      {statuses.map((s) => (
                        <SelectItem key={s.value} value={s.value}>
                          {s.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                {/* Email */}
                <div className="space-y-2">
                  <label className="text-sm font-medium">Email</label>
                  <Input
                    type="email"
                    value={form.email}
                    onChange={(e) => handleChange("email", e.target.value)}
                    placeholder="example@mail.com"
                  />
                </div>

                {/* Password */}
                <div className="space-y-2">
                  <label className="text-sm font-medium">Password</label>
                  <Input
                    type="password"
                    value={form.password}
                    onChange={(e) =>
                      handleChange("password", e.target.value)
                    }
                    placeholder="Enter password"
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
                    "Create User"
                  )}
                </Button>

              </form>
            </CardContent>
          </Card>


          {/* RIGHT CARD - INFO */}
          <Card className="rounded-2xl shadow-sm border border-muted">
            <CardHeader>
              <CardTitle className="text-lg text-green-500">
                Instructions
              </CardTitle>
            </CardHeader>

            <CardContent className="space-y-4 text-sm text-muted-foreground">

              <ul className="list-disc pl-5 space-y-2">
                <li>Username must be unique.</li>
                <li>Email must not already exist.</li>
                <li>Password should be at least 6 characters.</li>
                <li>Assign correct role based on responsibility.</li>
                <li>Inactive users cannot access the system.</li>
              </ul>

              <div className="rounded-lg bg-amber-50 p-4 text-amber-700">
                Tip: Admin role has full access permissions.
              </div>

            </CardContent>
          </Card>

        </div>
      </div>
    </AppLayout>
  );
}