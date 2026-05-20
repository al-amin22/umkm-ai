<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Customer extends Model
{
    protected $fillable = [
        'shop_id',
        'nama',
        'nomor_hp',
        'alamat',
        'kota',
        'total_pesanan',
        'total_belanja',
        'last_order_at',
    ];

    protected $casts = [
        'total_belanja' => 'decimal:2',
        'last_order_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeByShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('nama', 'ilike', "%{$keyword}%")
              ->orWhere('nomor_hp', 'ilike', "%{$keyword}%");
        });
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function getTierAttribute(): string
    {
        if ($this->total_belanja >= 5_000_000) return 'VIP';
        if ($this->total_belanja >= 1_000_000) return 'Regular';
        return 'Baru';
    }
}
