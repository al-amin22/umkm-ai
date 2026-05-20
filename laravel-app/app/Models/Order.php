<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Order extends Model
{
    protected $fillable = [
        'shop_id',
        'customer_id',
        'nomor_pesanan',
        'buyer_name',
        'buyer_phone',
        'buyer_address',
        'buyer_city',
        'total_harga',
        'status',
        'reminder_count',
        'confirmed_at',
        'shipped_at',
        'done_at',
        'cancelled_at',
        'catatan',
    ];

    protected $casts = [
        'total_harga'  => 'decimal:2',
        'confirmed_at' => 'datetime',
        'shipped_at'   => 'datetime',
        'done_at'      => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeShipped(Builder $query): Builder
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDone(Builder $query): Builder
    {
        return $query->where('status', 'done');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'confirmed', 'shipped']);
    }

    public function scopeByShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function getTotalHargaFormatAttribute(): string
    {
        return 'Rp ' . number_format((float) $this->total_harga, 0, ',', '.');
    }

    /**
     * Generate nomor pesanan unik per toko per hari.
     * Format: ORD-YYYYMMDD-NNN (sekuensial per toko per hari, mulai dari 001)
     */
    public static function generateNomor(int $shopId): string
    {
        $today  = now()->format('Ymd');
        $prefix = "ORD-{$today}-";

        $last = self::where('shop_id', $shopId)
            ->where('nomor_pesanan', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('nomor_pesanan');

        $seq = $last
            ? (int) substr($last, strrpos($last, '-') + 1) + 1
            : 1;

        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
