<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class WaSession extends Model
{
    protected $fillable = [
        'wa_number',
        'shop_id',
        'active_context',
        'context_data',
        'last_activity',
        'is_locked',
    ];

    protected $casts = [
        'context_data'  => 'array',
        'last_activity' => 'datetime',
        'is_locked'     => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeLocked(Builder $query): Builder
    {
        return $query->where('is_locked', true);
    }

    public function scopeByContext(Builder $query, string $context): Builder
    {
        return $query->where('active_context', $context);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('last_activity', '>=', now()->subMinutes(30));
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function clearContext(): void
    {
        $this->update([
            'active_context' => null,
            'context_data'   => null,
            'is_locked'      => false,
        ]);
    }

    public function setContext(string $context, array $data = []): void
    {
        $this->update([
            'active_context' => $context,
            'context_data'   => $data,
            'last_activity'  => now(),
        ]);
    }
}
