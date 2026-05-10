<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class StockLog extends Model
{
    protected $fillable = [
        'product_id',
        'tipe',
        'jumlah',
        'keterangan',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeTambah(Builder $query): Builder
    {
        return $query->where('tipe', 'tambah');
    }

    public function scopeKurang(Builder $query): Builder
    {
        return $query->where('tipe', 'kurang');
    }

    public function scopeKoreksi(Builder $query): Builder
    {
        return $query->where('tipe', 'koreksi');
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }
}
