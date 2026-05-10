<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Subscription extends Model
{
    protected $fillable = [
        'shop_id',
        'status',
        'plan',
        'mulai_at',
        'expired_at',
        'grace_until',
    ];

    protected $casts = [
        'mulai_at'    => 'datetime',
        'expired_at'  => 'datetime',
        'grace_until' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeGrace(Builder $query): Builder
    {
        return $query->where('status', 'grace');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired');
    }

    public function scopeByPlan(Builder $query, string $plan): Builder
    {
        return $query->where('plan', $plan);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expired_at->isFuture();
    }

    public function isGrace(): bool
    {
        return $this->status === 'grace'
            && $this->grace_until !== null
            && $this->grace_until->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->expired_at->isPast() && ! $this->isGrace());
    }

    public function isTrial(): bool
    {
        return $this->plan === 'trial';
    }

    public function hariTersisa(): int
    {
        if ($this->expired_at->isPast()) {
            return 0;
        }

        return (int) now()->diffInDays($this->expired_at);
    }
}
