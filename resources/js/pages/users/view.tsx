"use client";

import * as React from "react";
import { Head, usePage, router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Users", href: "/users" },
  { title: "User Details", href: "#" },
];



export default function UserDetailsPage() {
  const { auth, user, userPermissions, permissionSections } = usePage().props as any;
  const [loading, setLoading] = React.useState(false);
  const [checkedPermissions, setCheckedPermissions] = React.useState<Record<string, boolean>>({});

  React.useEffect(() => {
  const initial: Record<string, boolean> = {};

  permissionSections.forEach((section: any) => {
    section.permissions.forEach((perm: any) => {
      initial[perm.value] = (userPermissions ?? []).includes(perm.value);
    });
  });

  setCheckedPermissions(initial);
}, [userPermissions, permissionSections]);

  const togglePermission = (perm: string, value: boolean) => {
  setCheckedPermissions((prev) => ({
    ...prev,
    [perm]: value,
  }));
};

  const handleSave = () => {
    const selected = Object.keys(checkedPermissions).filter(
      (key) => checkedPermissions[key]
    );

    setLoading(true);

    router.post(`/users/${user.id}/permissions`, {
      permissions: selected,
    }, {
      onFinish: () => setLoading(false),
    });
  };
  console.log(user);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="User Details" />

      <div className="p-6">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">

          {/* LEFT CARD */}
          <Card className="rounded-2xl shadow-md">
            <CardHeader>
              <CardTitle className="text-xl text-green-500">
                User Information
              </CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">

  {/* Avatar */}
  <div className="flex flex-col items-center gap-3">
    <div className="w-28 h-28 rounded-full overflow-hidden border-4 border-sky-100 shadow-md">
      <img
        src={
          user.profile
            ? `/avatars/${user.profile}`
            : "/avatars/boy-01.png"
        }
        alt="User Avatar"
        className="w-full h-full object-cover"
      />
    </div>

    <div className="text-center">
      <p className="text-lg font-semibold">{user.avatar}{user.name}</p>
      <p className="text-sm text-muted-foreground">{user.email}</p>
    </div>
  </div>

  {/* User Info */}
  <div className="space-y-4">

    <div className="grid grid-cols-2 gap-4">
      <div>
        <p className="text-sm text-muted-foreground">Name</p>
        <p>{user.name}</p>
      </div>
      <div>
        <p className="text-sm text-muted-foreground">Email</p>
        <p>{user.email}</p>
      </div>
    </div>

    <div>
      <p className="text-sm text-muted-foreground">Phone</p>
      <p>{user.phone_no}</p>
    </div>

   <div className="grid grid-cols-2 gap-4">

  {/* ROLE */}
  <div className="space-y-1">
    <p className="text-sm text-muted-foreground">Role</p>

    <span className="inline-flex px-2 py-1 rounded-md text-xs font-medium bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400">
      {user.role}
    </span>
  </div>

  {/* STATUS */}
  <div className="space-y-1">
    <p className="text-sm text-muted-foreground">Status</p>

    <span
      className={`inline-flex px-2 py-1 rounded-md text-xs font-medium ${
        user.status === "active"
          ? "bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400"
          : "bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400"
      }`}
    >
      {user.status}
    </span>
  </div>

</div>

  </div>
</CardContent>
          </Card>

          {/* RIGHT CARD */}
          <Card className="rounded-2xl shadow-md">
            <CardHeader>
              <CardTitle className="text-xl text-green-500">
                Permissions
              </CardTitle>
            </CardHeader>

            <CardContent className="p-0">
  {/* SCROLL AREA */}
  <div className="max-h-[420px] overflow-y-auto px-6 py-4 space-y-6">
    
    {permissionSections.map((section: any) => (
      <div key={section.section}>
        
        {/* SECTION TITLE */}
        <h3 className="font-semibold text-sm text-muted-foreground uppercase tracking-wide mb-3">
          {section.section} Section
        </h3>

        <div className="space-y-2">
          {section.permissions.map((perm: any) => (
            <div
              key={perm.value}
              className="flex items-center justify-between rounded-md px-2 py-1 hover:bg-muted/40 transition"
            >
              <span className="text-sm">{perm.label}</span>

              <Checkbox
  checked={checkedPermissions[perm.value] || false}
  onCheckedChange={(checked) =>
    togglePermission(perm.value, checked === true)
  }
  disabled={auth?.user?.role !== "admin"}
  className="
    border-slate-400 dark:border-slate-500
  "
/>
            </div>
          ))}
        </div>
      </div>
    ))}
  </div>

  {/* FOOTER ACTION */}
  {auth?.user?.role === "admin" && (
    <div className="border-t p-4 bg-background sticky bottom-0">
      <Button
        disabled={loading}
        onClick={handleSave}
        className="w-full bg-green-500 hover:bg-green-600 text-white"
      >
        {loading ? "Saving..." : "Save Permissions"}
      </Button>
    </div>
  )}
</CardContent>
          </Card>

        </div>
      </div>
    </AppLayout>
  );
}