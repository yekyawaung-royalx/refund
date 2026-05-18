<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Upload extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'category',
        'type',
        'filename',
        'folder',
        'file_path',
        'failed_path',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'uploaded_by_id',
        'uploaded_by_name',
        'failed_rows',
        'status',
        'processed_duration',
        'attempts',
        'error_message',
    ];
}
