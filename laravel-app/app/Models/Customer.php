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
        'rfm_segment',
        'rfm_r',
        'rfm_f',
        'rfm_m',
    ];

    protected $casts = [
        'total_belanja' => 'decimal:2',
        'last_order_at' => 'datetime',
        'rfm_r'         => 'integer',
        'rfm_f'         => 'integer',
        'rfm_m'         => 'integer',
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

    // ── Scopes: RFM ────────────────────────────────────────────────

    public function scopeBySegment(Builder $query, string $segment): Builder
    {
        return $query->where('rfm_segment', $segment);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function getTierAttribute(): string
    {
        // Gunakan rfm_segment jika sudah dihitung, fallback ke monetary
        if ($this->rfm_segment && $this->rfm_segment !== 'Baru') {
            return $this->rfm_segment;
        }
        if ($this->total_belanja >= 5_000_000) return 'VIP';
        if ($this->total_belanja >= 1_000_000) return 'Regular';
        return 'Baru';
    }

    /**
     * Hitung skor RFM individual (1-5 scale) berdasarkan nilai aktual pelanggan.
     * Dipanggil oleh CustomerService::recalculateRfm() — tidak disimpan di sini.
     *
     * R (Recency): hari sejak last_order_at — semakin kecil semakin bagus
     * F (Frequency): total_pesanan
     * M (Monetary): total_belanja
     */
    public function hitungSkorR(): int
    {
        if (! $this->last_order_at) return 1;
        $days = (int) $this->last_order_at->diffInDays(now());
        return match (true) {
            $days <= 14  => 5,
            $days <= 30  => 4,
            $days <= 60  => 3,
            $days <= 90  => 2,
            default      => 1,
        };
    }

    public function hitungSkorF(): int
    {
        $f = $this->total_pesanan;
        return match (true) {
            $f >= 10 => 5,
            $f >= 6  => 4,
            $f >= 3  => 3,
            $f >= 2  => 2,
            default  => 1,
        };
    }

    public function hitungSkorM(): int
    {
        $m = (float) $this->total_belanja;
        return match (true) {
            $m >= 5_000_000 => 5,
            $m >= 2_000_000 => 4,
            $m >= 1_000_000 => 3,
            $m >= 500_000   => 2,
            default         => 1,
        };
    }

    /**
     * Klasifikasi segmen dari skor R, F, M.
     */
    public static function klasifikasiSegmen(int $r, int $f, int $m): string
    {
        $avg = ($r + $f + $m) / 3;

        if ($r >= 4 && $f >= 4 && $m >= 4)      return 'Champions';
        if ($f >= 3 && $m >= 3)                  return 'Loyal';
        if ($r >= 4 && $avg >= 3)                return 'Potensial';
        if ($r <= 2 && $f >= 3 && $m >= 3)       return 'Beresiko';
        if ($r <= 2 && $avg >= 3)                return 'Tidur';
        if ($r >= 4 && $f === 1)                 return 'Baru';
        return 'Biasa';
    }
}
