<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Export Completed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;padding:40px 0;">
<tr>
<td align="center">

<table width="600" cellpadding="0" cellspacing="0"
       style="background:#ffffff;border-radius:12px;overflow:hidden;
              box-shadow:0 10px 25px rgba(0,0,0,0.08);">

<!-- Header -->
<tr>
<td style="background:linear-gradient(90deg,#16a34a,#15803d);
           padding:25px;text-align:center;color:#ffffff;">
    <h2 style="margin:0;font-size:22px;">
        File Export Completed
    </h2>
</td>
</tr>

<!-- Body -->
<tr>
<td style="padding:30px;">

<p style="margin-top:0;font-size:15px;color:#374151;">
    Your requested export has been generated successfully.
    Below are the export details.
</p>

<table width="100%" cellpadding="8" cellspacing="0"
       style="border-collapse:collapse;margin-top:20px;">

<tr>
<td style="background:#f9fafb;border:1px solid #e5e7eb;">
    <strong>Export Date</strong>
</td>
<td style="border:1px solid #e5e7eb;">
    {{ $exportDate }}
</td>
</tr>

<tr>
<td style="background:#f9fafb;border:1px solid #e5e7eb;">
    <strong>Total Rows</strong>
</td>
<td style="border:1px solid #e5e7eb;font-weight:bold;color:#16a34a;">
    {{ number_format($totalRows) }}
</td>
</tr>

<tr>
<td style="background:#f9fafb;border:1px solid #e5e7eb;">
    <strong>File Name</strong>
</td>
<td style="border:1px solid #e5e7eb;">
    {{ $fileName }}
</td>
</tr>

<tr>
<td style="background:#f9fafb;border:1px solid #e5e7eb;">
    <strong>Started At</strong>
</td>
<td style="border:1px solid #e5e7eb;">
    {{ $startTime }}
</td>
</tr>

<tr>
<td style="background:#f9fafb;border:1px solid #e5e7eb;">
    <strong>Completed At</strong>
</td>
<td style="border:1px solid #e5e7eb;">
    {{ $endTime }}
</td>
</tr>

<tr>
<td style="background:#f9fafb;border:1px solid #e5e7eb;">
    <strong>Duration</strong>
</td>
<td style="border:1px solid #e5e7eb;">
    {{ $duration }} seconds
</td>
</tr>

</table>

<div style="margin-top:25px;
            padding:15px;
            border-radius:8px;
            background:#ecfdf5;
            color:#065f46;
            font-size:14px;">
    🎉 Export completed successfully without errors.
</div>

</td>
</tr>

<!-- Footer -->
<tr>
<td style="padding:20px;text-align:center;
           font-size:12px;color:#9ca3af;
           border-top:1px solid #e5e7eb;">
    This is an automated notification from {{ config('app.name') }}.
    <br>
    © {{ date('Y') }} All rights reserved.
</td>
</tr>

</table>

</td>
</tr>
</table>

</body>
</html>