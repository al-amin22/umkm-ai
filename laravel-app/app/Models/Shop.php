<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Shop extends Model
{
    protected $fillable = [
        'wa_number_owner',
        'wa_number_helper',
        'nama_toko',
        'slug',
        'jenis_produk',
        'nama_owner',
        'alamat',
        'jam_buka',
        'jam_tutup',
        'status',
        'buka_lagi_at',
        'nomor_rekening',
        'nama_bank',
        'nama_pemilik_rekening',
        'wa_nomor_darurat',
    ];

    protected $casts = [
        'buka_lagi_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function admins(): HasMany
    {
        return $this->hasMany(ShopAdmin::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function complaintPatterns(): HasMany
    {
        return $this->hasMany(ComplaintPattern::class);
    }

    public function contentPreference(): HasOne
    {
        return $this->hasOne(ContentPreference::class);
    }

    public function contentHistory(): HasMany
    {
        return $this->hasMany(ContentHistory::class);
    }

    public function theme(): HasOne
    {
        return $this->hasOne(ShopTheme::class);
    }

    public function notificationQueue(): HasMany
    {
        return $this->hasMany(NotificationQueue::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->whereIn('status', ['active', 'grace'])->latestOfMany();
    }

    public function paymentLogs(): HasMany
    {
        return $this->hasMany(PaymentLog::class);
    }

    public function waSession(): HasOne
    {
        return $this->hasOne(WaSession::class);
    }

    public function laporanTokens(): HasMany
    {
        return $this->hasMany(LaporanToken::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 'inactive');
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', 'suspended');
    }

    public function scopeByWaNumber(Builder $query, string $waNumber): Builder
    {
        return $query->where('wa_number_owner', $waNumber)
                     ->orWhere('wa_number_helper', $waNumber);
    }
}
