<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * A device authenticates directly as itself (not by borrowing a user's
 * token) — its Sanctum token's `tokenable` is the Device record. The scan
 * API only accepts tokens whose tokenable is an active Device, so a token
 * that isn't tied to a registered device can never be used to log scans.
 */
class Device extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'device_key',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (Device $device) => $device->tokens()->delete());
    }

    /**
     * Revokes any existing tokens and issues a fresh one. Returns the
     * plaintext token, which — per Sanctum — can only ever be read once.
     */
    public function issueFreshToken(): string
    {
        $this->tokens()->delete();

        return $this->createToken($this->device_key)->plainTextToken;
    }
}
