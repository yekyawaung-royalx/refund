<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Import Completed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;padding:40px 0;">
        <tr>
            <td align="center">

                <!-- Card Container -->
                <table width="600" cellpadding="0" cellspacing="0" 
                       style="background:#ffffff;border-radius:12px;overflow:hidden;
                              box-shadow:0 10px 25px rgba(0,0,0,0.08);">

                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(90deg,#0ea5e9,#2563eb);
                                   padding:25px;text-align:center;color:#ffffff;">
                            <h2 style="margin:0;font-size:22px;">
                                File Import Completed
                            </h2>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:30px;">

                            <p style="margin-top:0;font-size:15px;color:#374151;">
                                The uploaded file from <strong>{{ $upload_by }}</strong> has been processed successfully. 
                                Below is a brief summary of the results.
                            </p>

                            <!-- Summary Table -->
                            <table width="100%" cellpadding="8" cellspacing="0" 
                                   style="border-collapse:collapse;margin-top:20px;">

                                <tr>
                                    <td style="background:#f9fafb;border:1px solid #e5e7eb;">
                                        <strong>Title</strong>
                                    </td>
                                    <td style="border:1px solid #e5e7eb;">
                                        {{ $title }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="background:#f9fafb;border:1px solid #e5e7eb;">
                                        <strong>Total Rows</strong>
                                    </td>
                                    <td style="border:1px solid #e5e7eb;">
                                        {{ number_format($total) }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="background:#f9fafb;border:1px solid #e5e7eb;">
                                        <strong>Processed</strong>
                                    </td>
                                    <td style="border:1px solid #e5e7eb;color:#16a34a;font-weight:bold;">
                                        {{ number_format($processed) }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="background:#f9fafb;border:1px solid #e5e7eb;">
                                        <strong>Failed</strong>
                                    </td>
                                    <td style="border:1px solid #e5e7eb;
                                               color:{{ $failed > 0 ? '#dc2626' : '#16a34a' }};
                                               font-weight:bold;">
                                        {{ number_format($failed) }}
                                    </td>
                                </tr>

                            </table>

                            <!-- Status Box -->
                            <div style="margin-top:25px;
                                        padding:15px;
                                        border-radius:8px;
                                        background:{{ $failed > 0 ? '#fef2f2' : '#ecfdf5' }};
                                        color:{{ $failed > 0 ? '#b91c1c' : '#065f46' }};
                                        font-size:14px;">

                                @if($failed > 0)
                                    ⚠ Some rows failed during processing. Please review the system logs.
                                @else
                                    🎉 All rows were imported successfully without errors.
                                @endif

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