<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ContentHistory extends Model
{
    protected $fillable = [
        'shop_id',
        'konten',
        'tipe',
        'feedback',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeSuka(Builder $query): Builder
    {
        return $query->where('feedback', 'suka');
    }

    public function scopeTidakSuka(Builder $query): Builder
    {
        return $query->where('feedback', 'tidak_suka');
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
