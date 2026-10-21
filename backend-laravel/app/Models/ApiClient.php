<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class ApiClient extends Model
{
    use HasFactory, HasApiTokens;

    protected $table = 'api_clients';

    protected $fillable = [
        'name',
        'client_id',
        'client_secret',
        'description',
        'is_active',
        'rate_limit_per_minute',
        'allowed_ips',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rate_limit_per_minute' => 'integer',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        // client_secret can be hidden from standard json serialization
    ];

    /**
     * Check if client subscription/validity is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check if given IP address is allowed.
     * Empty allowed_ips means any IP is allowed.
     */
    public function isIpAllowed(?string $ip): bool
    {
        if (empty($this->allowed_ips)) {
            return true;
        }

        if (empty($ip)) {
            return false;
        }

        $allowedList = array_map('trim', explode(',', $this->allowed_ips));

        return in_array($ip, $allowedList, true);
    }

    /**
     * Helper to generate unique client credentials.
     */
    public static function generateCredentials(): array
    {
        return [
            'client_id' => 'clnt_' . Str::random(24),
            'client_secret' => 'sec_' . Str::random(36),
        ];
    }
}
