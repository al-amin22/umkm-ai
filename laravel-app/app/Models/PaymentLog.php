<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class PaymentLog extends Model
{
    protected $fillable = [
        'shop_id',
        'tipe',
        'reference_id',
        'amount',
        'payment_method',
        'status',
        'webhook_payload',
        'processed_at',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'webhook_payload' => 'array',
        'processed_at'    => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeSuccess(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeLangganan(Builder $query): Builder
    {
        return $query->where('tipe', 'langganan');
    }

    public function scopeOrder(Builder $query): Builder
    {
        return $query->where('tipe', 'order');
    }
}
