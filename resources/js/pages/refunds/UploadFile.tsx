"use client";

import * as React from "react";
import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Progress } from "@/components/ui/progress";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs"
import {
  UploadCloud,
  Loader2,
  FileSpreadsheet,
  UploadIcon,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { toast } from "sonner";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Refunds", href: "/refunds" },
  { title: "Upload File", href: "#" },
];

const waybill_categories = [
  {
    value: "receiver-postpaid",
    label: "Receiver Pay Postpaid",
  },
  { 
    value: "sender-postpaid", 
    label: "Sender Pay Postpaid" 
  },
  {
    value: "sender-prepaid",
    label: "Sender Pay Prepaid",
  },
];
const refunded_categories = [
  { value: "refunded", label: "Refunded" },
];

// permission mapping (scalable)
const permissionMap: Record<string, string> = {
  "sender-postpaid": "sender-postpaid",
  "sender-prepaid": "sender-prepaid",
  "receiver-postpaid": "receiver-postpaid",
  "refunded": "refund-upload",
};

export default function UploadFile() {
  const { auth } = usePage().props as any;

  const hasPermission = (perm: string) =>
    auth?.permissions?.includes(perm);

  const [category, setCategory] = React.useState("");
  const [title, setTitle] = React.useState("");
  const [activeTab, setActiveTab] = React.useState("waybills");

  // memoized filter
  const filteredWaybillCategories = React.useMemo(() => {
  return waybill_categories.filter((c) =>
    hasPermission(permissionMap[c.value])
  );
}, [auth?.permissions]);

const filteredRefundedCategories = React.useMemo(() => {
  return refunded_categories.filter((c) =>
    hasPermission(permissionMap[c.value])
  );
}, [auth?.permissions]);

const allCategories = [
  ...filteredWaybillCategories,
  ...filteredRefundedCategories,
];

  // fix invalid category automatically
  React.useEffect(() => {
  if (!allCategories.find((c) => c.value === category)) {
    setCategory(allCategories[0]?.value || "");
  }
}, [allCategories]);

  // title generator (safe)
  const today = new Date();
  const todayStr = today.toISOString().slice(0, 10).replace(/-/g, "");

  const getTitleFromCategory = (cat: string) => {
  const catLabel =
    allCategories.find((c) => c.value === cat)?.label || "";

  if (!catLabel) return "";

  const hyphenLabel = catLabel
    .split("(")[0]
    .trim()
    .replace(/\s+/g, "-")
    .replace(/\//g, "-");

  return `${todayStr}-${hyphenLabel}`;
};

React.useEffect(() => {
  setTitle(getTitleFromCategory(category));
}, [category]);

  const [file, setFile] = React.useState<File | null>(null);
  const [dragActive, setDragActive] = React.useState(false);
  const [progress, setProgress] = React.useState(0);
  const [uploading, setUploading] = React.useState(false);

  const fileInputRef = React.useRef<HTMLInputElement>(null);
  const MAX_SIZE_MB = 80;

  const validateFile = (file: File) => {
    const isCsvType = file.type === "text/csv";
    const isCsvExt = file.name.toLowerCase().endsWith(".csv");

    if (!isCsvType && !isCsvExt) {
      toast.error("Only CSV files are allowed.");
      return false;
    }
    return true;
  };

  const handleFile = (selected: File) => {
    if (!validateFile(selected)) return;

    const sizeMB = selected.size / 1024 / 1024;
    if (sizeMB > MAX_SIZE_MB) {
      toast.error(`File size exceeds ${MAX_SIZE_MB} MB.`);
      return;
    }

    setFile(selected);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setDragActive(false);

    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFile(e.dataTransfer.files[0]);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!category) {
      toast.error("No category selected.");
      return;
    }

    if (!file) {
      toast.warning("Please select a file first.");
      return;
    }

    setUploading(true);
    setProgress(0);

    router.post(
      "/refunds/uploaded-file",
      { title, category, file },
      {
        forceFormData: true,
        onProgress: (event) => {
          if (event?.percentage) setProgress(event.percentage);
        },
        onSuccess: () => {
          toast.success(
            "Upload successful. File validation started..."
          );
          setFile(null);
          if (fileInputRef.current) fileInputRef.current.value = "";
          setProgress(0);
        },
        onError: () => toast.error("Upload failed. Please try again."),
        onFinish: () => setUploading(false),
      }
    );
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Upload File" />

      <div className="p-6">
        <div className="grid grid-cols-1 lg:grid-cols-5 gap-8">

          {/* LEFT */}
          <Card className="lg:col-span-3 rounded-2xl shadow-md">
            <CardHeader>
              <CardTitle className="text-xl text-green-500 flex items-center gap-2">
                <UploadIcon className="h-5 w-5" />
                Upload File
              </CardTitle>
            </CardHeader>

            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-6">

                {/* CATEGORY */}
                <div className="space-y-2">
                 <Tabs
  value={activeTab}
  onValueChange={(value) => {
    setActiveTab(value);

    if (value === "waybills") {
      setCategory(
        filteredWaybillCategories[0]?.value || ""
      );
    }

    if (value === "refunded") {
      setCategory(
        filteredRefundedCategories[0]?.value || ""
      );
    }
  }}
  className="w-full"
>
      <TabsList>
        <TabsTrigger value="waybills">All Waybills</TabsTrigger>
        <TabsTrigger value="refunded">Refunded</TabsTrigger>
      </TabsList>
      <TabsContent value="waybills">
      {filteredWaybillCategories.length === 0 ? (
        <p className="text-sm text-red-500 mt-3">
          You don’t have permission to upload files.
        </p>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-2 mt-4">
          {filteredWaybillCategories.map((c) => (
            <label
              key={c.value}
              className={cn(
                "cursor-pointer rounded-2xl border transition-all shadow-sm flex items-start px-2 py-4",
                category === c.value
                  ? "border-green-500 bg-green-50 dark:bg-green-950/20 shadow-lg"
                  : "border-muted bg-white dark:bg-gray-900 hover:border-green-300"
              )}
            >
              <input
                type="radio"
                name="category"
                value={c.value}
                checked={category === c.value}
                onChange={() => setCategory(c.value)}
                className="sr-only"
              />

              <div
                className={cn(
                  "h-5 w-5 rounded-full border flex items-center justify-center",
                  category === c.value
                    ? "border-green-500"
                    : "border-gray-400"
                )}
              >
                {category === c.value && (
                  <div className="h-2.5 w-2.5 rounded-full bg-green-500" />
                )}
              </div>

              <div>
                <p className="font-semibold text-sm ml-2">
                  {c.label}
                </p>

                <p className="text-xs text-muted-foreground ml-2 mt-1">
                  {c.label} CSV file
                </p>
              </div>
            </label>
          ))}
        </div>
      )}
    </TabsContent>
      <TabsContent value="refunded">
      {filteredRefundedCategories.length === 0 ? (
        <p className="text-sm text-red-500 mt-3">
          You don’t have permission to upload files.
        </p>
      ) : (
        <div className="grid grid-cols-3 gap-4 mt-4">
          {filteredRefundedCategories.map((c) => (
            <label
              key={c.value}
              className={cn(
                "cursor-pointer rounded-2xl border transition-all shadow-sm flex items-start px-2 py-4",
                category === c.value
                  ? "border-green-500 bg-green-50 dark:bg-green-950/20 shadow-lg"
                  : "border-muted bg-white dark:bg-gray-900 hover:border-green-300"
              )}
            >
              <input
                type="radio"
                name="category"
                value={c.value}
                checked={category === c.value}
                onChange={() => setCategory(c.value)}
                className="sr-only"
              />

              <div
                className={cn(
                  "h-5 w-5 rounded-full border flex items-center justify-center",
                  category === c.value
                    ? "border-green-500"
                    : "border-gray-400"
                )}
              >
                {category === c.value && (
                  <div className="h-2.5 w-2.5 rounded-full bg-green-500" />
                )}
              </div>

              <div>
                <p className="font-semibold text-sm ml-2">
                  {c.label}
                </p>

                <p className="text-xs text-muted-foreground ml-2 mt-1">
                  {c.label} CSV file
                </p>
              </div>
            </label>
          ))}
        </div>
      )}
    </TabsContent>
      </Tabs>

                  
                </div>

                {/* TITLE */}
                <div className="space-y-2">
                  <label className="text-sm font-medium">Title</label>
                  <Input
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                  />
                </div>

                {/* DROPZONE */}
                <div
                  onDragOver={(e) => {
                    e.preventDefault();
                    setDragActive(true);
                  }}
                  onDragLeave={() => setDragActive(false)}
                  onDrop={handleDrop}
                  onClick={() => fileInputRef.current?.click()}
                  className={cn(
                    "border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer",
                    dragActive
                      ? "border-green-500 bg-sky-50"
                      : "border-gray-400"
                  )}
                >
                  <UploadCloud className="mx-auto mb-2" />
                  <p className="text-sm">Drag & Drop CSV here</p>

                  {file && (
                    <div className="mt-2 text-green-600 flex justify-center gap-2">
                      <FileSpreadsheet className="h-4 w-4" />
                      {file.name}
                    </div>
                  )}

                  <input
                    ref={fileInputRef}
                    type="file"
                    accept=".csv"
                    className="hidden"
                    onChange={(e) =>
                      e.target.files && handleFile(e.target.files[0])
                    }
                  />
                </div>

                {uploading && <Progress value={progress} />}

                <Button
                  type="submit"
                  disabled={uploading || allCategories.length === 0}
                  className="w-full bg-green-500 hover:bg-green-600 text-white"
                >
                  {uploading ? (
                    <>
                      <Loader2 className="animate-spin mr-2" />
                      Uploading...
                    </>
                  ) : (
                    "Submit"
                  )}
                </Button>

              </form>
            </CardContent>
          </Card>

          {/* RIGHT (unchanged) */}
          <Card className="lg:col-span-2 rounded-2xl shadow-sm border border-muted">
            <CardHeader>
              <CardTitle className="text-lg text-green-500">
                Instructions
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4 text-sm text-muted-foreground">
              <p>Please upload a valid CSV file.</p>
              <ul className="list-disc pl-5 space-y-2">
                <li>Accepted file types: <strong className="text-amber-600">CSV</strong>.</li>
                <li>Maximum file size: <strong className="text-amber-600">80 MB</strong>.</li>
                <li>Maximum rows per file: <strong className="text-amber-600">200,000</strong>.</li>
                <li>Ensure data columns match required format.</li>
                <li>Select correct category <strong className="text-amber-600">No Refund</strong> or <strong className="text-amber-600">Refund</strong> before uploading.</li>
                <li>Do not upload duplicate reports.</li>
                <li>
                <strong className="text-amber-600">No Refund</strong>: a refund needs <span className="text-red-500">to be processed</span>.
                <ul className="list-disc pl-5 mt-1 text-sm text-muted-foreground">
                  <li>Must be 33 columns in the file</li>
                </ul>
              </li>

              <li>
                <strong className="text-amber-600">Refund</strong>: a refund has <span className="text-green-500">already been processed</span>.
                <ul className="list-disc pl-5 mt-1 text-sm text-muted-foreground">
                  <li>Must be 7 columns in the file</li>
                </ul>
              </li>
              </ul>
              <div className="rounded-lg bg-amber-50 mt-10 p-4 text-amber-700">
                Tip: Large files may take longer to upload.
              </div>
            </CardContent>
          </Card>

        </div>
      </div>
    </AppLayout>
  );
}