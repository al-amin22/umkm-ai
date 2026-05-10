<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class LaporanToken extends Model
{
    protected $fillable = [
        'shop_id',
        'token',
        'expired_at',
        'used_at',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('used_at')
                     ->where('expired_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expired_at', '<=', now());
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expired_at->isFuture();
    }

    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}
