<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'biometric_audit_logs';

    protected $fillable = [
        'person_id',
        'person_name',
        'action',
        'status',
        'similarity_distance',
        'ip_address',
        'user_agent',
        'details',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'similarity_distance' => 'float',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
