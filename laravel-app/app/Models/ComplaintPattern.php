<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ComplaintPattern extends Model
{
    protected $fillable = [
        'shop_id',
        'tipe_komplain',
        'jumlah',
        'periode',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeByPeriode(Builder $query, string $periode): Builder
    {
        return $query->where('periode', $periode);
    }

    public function scopeByShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }
}
