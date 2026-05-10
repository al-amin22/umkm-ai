<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Complaint extends Model
{
    protected $fillable = [
        'shop_id',
        'order_id',
        'buyer_name',
        'pesan_asli',
        'pesan_ringkasan',
        'draft_balasan',
        'tipe',
        'urgensi',
        'status',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeBaru(Builder $query): Builder
    {
        return $query->where('status', 'baru');
    }

    public function scopeSelesai(Builder $query): Builder
    {
        return $query->where('status', 'selesai');
    }

    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('urgensi', 'tinggi');
    }

    public function scopeByTipe(Builder $query, string $tipe): Builder
    {
        return $query->where('tipe', $tipe);
    }

    public function scopeByShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }
}
