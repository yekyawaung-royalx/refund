"use client";

import * as React from "react";
import { Head, router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Progress } from "@/components/ui/progress";

import { UploadCloud, Loader2, FileSpreadsheet, UploadIcon } from "lucide-react";
import { cn } from "@/lib/utils";
import { toast } from "sonner";

const breadcrumbs: BreadcrumbItem[] = [
  { title: "Dashboard", href: "/dashboard" },
  { title: "Refunds", href: "/refunds" },
  { title: "Upload File", href: "#" },
];

const categories = [
  { value: "no-refund", label: "No Refund (ငွေအမ်းရန်)" },
  { value: "refund", label: "Refund (ငွေအမ်းပြီး)" },
];

export default function UploadFile() {
  const [category, setCategory] = React.useState("no-refund");

  const today = new Date();
  const todayStr = today.toISOString().slice(0, 10).replace(/-/g, ""); // YYYYMMDD

  const getTitleFromCategory = (cat: string) => {
    const catLabel = categories.find(c => c.value === cat)?.label ?? "No Refund";
    const hyphenLabel = catLabel.split("(")[0].trim().replace(/\s+/g, "-");
    return `${todayStr}-${hyphenLabel}`;
  };

  const [title, setTitle] = React.useState(getTitleFromCategory(category));
  React.useEffect(() => setTitle(getTitleFromCategory(category)), [category]);

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
        onProgress: (event) => { if (event?.percentage) setProgress(event.percentage); },
        onSuccess: () => {
          toast.success("Upload successful.File validation started. Please wait...");
          setFile(null); 
          //setTitle(""); 
          //setCategory("");
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
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">

          {/* LEFT COLUMN - FORM */}
          <Card className="rounded-2xl shadow-md">
            <CardHeader>
              <CardTitle className="text-xl text-green-500 flex items-center gap-2">
                <UploadIcon className="h-5 w-5" />
                Upload File
              </CardTitle>
            </CardHeader>

            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-6">

                {/* Category as Radio Cards with visible radio button */}
<div className="space-y-2">
  <label className="text-sm font-medium">Category</label>
  <div className="grid grid-cols-2 gap-4 mt-2">
    {categories.map((c) => (
      <label
        key={c.value}
        className={cn(
          "cursor-pointer rounded-xl hover:bg-gray-800 border p-4 flex items-start gap-3 transition shadow-sm",
          category === c.value
            ? "border-green-500 text-green-500 bg-gray-900 shadow-lg"
            : "border-muted "
        )}
      >
        {/* Hidden radio input */}
        <input
          type="radio"
          name="category"
          value={c.value}
          className="peer sr-only"
          checked={category === c.value}
          onChange={() => setCategory(c.value)}
        />

        {/* Circle indicator */}
        <span
          className={cn(
            "w-5 h-5 rounded-full border flex-shrink-0 flex items-center justify-center transition",
            category === c.value
              ? "border-green-500 bg-green-500"
              : "border-gray-300 bg-gray-300"
          )}
        >
          {category === c.value ? (
            <span className="w-2.5 h-2.5 rounded-full bg-white" />
          ):(
            <span className="w-2.5 h-2.5 rounded-full bg-white/50" />
          )}
        </span>

        {/* Label content */}
        <div className="flex flex-col">
          <div className="flex items-center gap-2">
            <span className="text-md font-semibold">{c.label.split("(")[0].trim()}</span>
          </div>
          <span className="text-sm text-muted-foreground mt-1">
            {c.label.includes("(") ? c.label.split("(")[1].replace(")", "") : ""}
          </span>
        </div>
      </label>
    ))}
  </div>
</div>

                {/* Title */}
                <div className="space-y-2">
                  <label className="text-sm font-medium">Title</label>
                  <Input
                    placeholder="Enter title"
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                  />
                </div>

                {/* Drag & Drop */}
                <div
                  onDragOver={(e) => { e.preventDefault(); setDragActive(true); }}
                  onDragLeave={() => setDragActive(false)}
                  onDrop={handleDrop}
                  onClick={() => fileInputRef.current?.click()}
                  className={cn(
                    "border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition",
                    dragActive ? "border-green-500 bg-sky-50" : "border-muted"
                  )}
                >
                  <UploadCloud className="mx-auto mb-3 h-8 w-8 text-muted-foreground" />
                  <p className="text-sm text-muted-foreground">Drag & Drop CSV here</p>
                  {file && (
                    <div className="mt-3 flex items-center justify-center gap-2 text-sm text-green-600">
                      <FileSpreadsheet className="h-4 w-4" />
                      {file.name}
                    </div>
                  )}
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept=".csv"
                    className="hidden"
                    onChange={(e) => e.target.files && handleFile(e.target.files[0])}
                  />
                </div>

                {/* Progress */}
                {uploading && <Progress value={progress} className="h-2" />}

                {/* Submit */}
                <Button
                  type="submit"
                  disabled={uploading}
                  className="w-full bg-green-500 hover:bg-green-600 text-white"
                >
                  <UploadIcon />
                  {uploading ? (
                    <>
                      <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                      Uploading...
                    </>
                  ) : (
                    "Submit"
                  )}
                </Button>

              </form>
            </CardContent>
          </Card>

          {/* RIGHT COLUMN - MESSAGE */}
          <Card className="rounded-2xl shadow-sm border border-muted">
            <CardHeader>
              <CardTitle className="text-lg text-green-500">Instructions</CardTitle>
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