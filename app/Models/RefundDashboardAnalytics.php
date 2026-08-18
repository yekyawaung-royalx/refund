<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundDashboardAnalytics extends Model
{
    protected $table = 'refund_dashboard_analytics';

    protected $fillable = [

        'this_month_all_waybills',
        'all_waybills',

        'this_month_no_refund_waybills',
        'all_no_refund_waybills',

        'this_month_refunded_waybills',
        'all_refunded_waybills',

        'this_month_no_refund_export_waybills',
    ];

    
}