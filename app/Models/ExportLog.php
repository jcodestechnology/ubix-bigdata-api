<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExportLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'file_name',
        'file_size',
        'filters',
        'error_message',
        'downloaded_at',
        'processed_at'
    ];

    protected $casts = [
        'filters' => 'array',
        'downloaded_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}