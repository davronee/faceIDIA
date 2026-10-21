<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Person extends Model
{
    use HasFactory, HasApiTokens;

    protected $table = 'persons';

    protected $fillable = [
        'full_name',
        'pinfl',
        'doc_seria',
        'doc_number',
        'surname_latin',
        'name_latin',
        'patronym_latin',
        'birth_date',
        'birth_place',
        'birth_country',
        'nationality',
        'citizenship',
        'gender',
        'doc_give_place',
        'doc_date_begin',
        'doc_date_end',
        'phone_number',
        'department',
        'status',
        'photo_url',
        'passport_photo_url',
        'rejection_reason',
        'face_embedding',
        'egov_data',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'doc_date_begin' => 'date',
        'doc_date_end' => 'date',
        'gender' => 'integer',
        'egov_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getSecurePhotoUrlAttribute(): ?string
    {
        $raw = $this->getRawOriginal('photo_url');
        return $raw ? route('admin.biometrics.photo', $this->id) : null;
    }

    public function getSecurePassportPhotoUrlAttribute(): ?string
    {
        $raw = $this->getRawOriginal('passport_photo_url');
        return $raw ? route('admin.biometrics.passport_photo', $this->id) : null;
    }

    public function getDocumentAttribute(): string
    {
        $doc = trim("{$this->doc_seria} {$this->doc_number}");
        return !empty($doc) ? $doc : '—';
    }

    public function getGenderTextAttribute(): string
    {
        return match($this->gender) {
            1 => 'Erkak',
            2 => 'Ayol',
            default => '—',
        };
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
