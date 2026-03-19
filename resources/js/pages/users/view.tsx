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

const permissionSections = {
  "File Permission": [
    { label: "Upload File", value: "upload-file" },
    { label: "Delete File", value: "delete-file" },
  ],
  "User Permission": [
    { label: "Create User", value: "create-user" },
    { label: "Edit User", value: "edit-user" },
    { label: "Update User", value: "update-user" },
    { label: "Delete User", value: "delete-user" },
  ],
  "Report Permission": [
    { label: "View Report", value: "view-report" },
    { label: "Export Report", value: "export-report" },
    { label: "Download Report", value: "download-report" },
    { label: "Delete Report", value: "delete-report" },
  ],
};

export default function UserDetailsPage() {
  const { user, userPermissions } = usePage().props as any;

  const [checkedPermissions, setCheckedPermissions] = React.useState<Record<string, boolean>>({});

  React.useEffect(() => {
  const initial: Record<string, boolean> = {};

  Object.values(permissionSections)
    .flat()
    .forEach((perm) => {
      initial[perm.value] = userPermissions?.includes(perm.value);
    });

  setCheckedPermissions(initial);
}, [userPermissions]);

  const togglePermission = (perm: string) => {
  setCheckedPermissions((prev) => ({
    ...prev,
    [perm]: !prev[perm],
  }));
};

  const handleSave = () => {
    const selected = Object.keys(checkedPermissions).filter(
      (key) => checkedPermissions[key]
    );

    router.post(`/users/${user.id}/permissions`, {
      permissions: selected,
    });
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="User Details" />

      <div className="p-6">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">

          {/* LEFT CARD */}
          <Card className="rounded-2xl shadow-md">
            <CardHeader>
              <CardTitle className="text-xl text-sky-500">
                User Information
              </CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">

  {/* Avatar */}
  <div className="flex flex-col items-center gap-3">
    <div className="w-28 h-28 rounded-full overflow-hidden border-4 border-sky-100 shadow-md">
      <img
        src={user.avatar ?? "/avatars/boy-01.png"}
        alt="User Avatar"
        className="w-full h-full object-cover"
      />
    </div>

    <div className="text-center">
      <p className="text-lg font-semibold">{user.name}</p>
      <p className="text-sm text-muted-foreground">{user.email}</p>
    </div>
  </div>

  {/* User Info */}
  <div className="space-y-4">

    <div className="grid grid-cols-2 gap-4">
      <div>
        <p className="text-sm text-muted-foreground">First Name</p>
        <p>{user.first_name}</p>
      </div>
      <div>
        <p className="text-sm text-muted-foreground">Last Name</p>
        <p>{user.last_name}</p>
      </div>
    </div>

    <div>
      <p className="text-sm text-muted-foreground">Phone</p>
      <p>{user.phone_no}</p>
    </div>

    <div>
      <p className="text-sm text-muted-foreground">Role</p>
      <span className="px-2 py-1 rounded-lg bg-sky-100 text-sky-700">
        {user.role}
      </span>
    </div>

    <div>
      <p className="text-sm text-muted-foreground">Status</p>
      <span
        className={`px-2 py-1 rounded-lg ${
          user.status === "Active"
            ? "bg-green-100 text-green-700"
            : "bg-red-100 text-red-700"
        }`}
      >
        {user.status}
      </span>
    </div>

  </div>
</CardContent>
          </Card>

          {/* RIGHT CARD */}
          <Card className="rounded-2xl shadow-md">
            <CardHeader>
              <CardTitle className="text-xl text-sky-500">
                Permissions
              </CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">
              {Object.entries(permissionSections).map(([section, perms]) => (
                <div key={section}>
                  <h3 className="font-semibold mb-2">{section}</h3>
                  <div className="space-y-2">
                    {perms.map((perm) => (
  <div key={perm.value} className="flex justify-between items-center">
    <span>{perm.label}</span>
    <Checkbox
      checked={checkedPermissions[perm.value]}
      onCheckedChange={() => togglePermission(perm.value)}
    />
  </div>
))}
                  </div>
                </div>
              ))}

              <Button
                onClick={handleSave}
                className="w-full bg-sky-500 hover:bg-sky-600 text-white"
              >
                Save Permissions
              </Button>
            </CardContent>
          </Card>

        </div>
      </div>
    </AppLayout>
  );
}