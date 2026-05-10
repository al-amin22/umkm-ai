<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Stock extends Model
{
    protected $fillable = [
        'product_id',
        'jumlah_sekarang',
        'batas_minimum',
        'rata_penjualan_harian',
        'estimasi_habis',
    ];

    protected $casts = [
        'rata_penjualan_harian' => 'decimal:2',
        'estimasi_habis'        => 'date',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeKritis(Builder $query): Builder
    {
        return $query->whereColumn('jumlah_sekarang', '<=', 'batas_minimum');
    }

    public function scopeHabis(Builder $query): Builder
    {
        return $query->where('jumlah_sekarang', 0);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isKritis(): bool
    {
        return $this->jumlah_sekarang <= $this->batas_minimum;
    }

    public function isHabis(): bool
    {
        return $this->jumlah_sekarang === 0;
    }

    public function hitungEstimasiHabis(): ?string
    {
        if ($this->rata_penjualan_harian <= 0 || $this->jumlah_sekarang <= 0) {
            return null;
        }

        $hariTersisa = (int) ceil($this->jumlah_sekarang / $this->rata_penjualan_harian);

        return now()->addDays($hariTersisa)->toDateString();
    }
}
