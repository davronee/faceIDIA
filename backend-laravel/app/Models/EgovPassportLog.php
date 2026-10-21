<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EgovPassportLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'egov_passport_logs';

    protected $fillable = [
        'document',
        'birth_date',
        'pinfl',
        'full_name',
        'status',
        'error_message',
        'raw_response',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'raw_response' => 'array',
        'created_at' => 'datetime',
    ];
}
