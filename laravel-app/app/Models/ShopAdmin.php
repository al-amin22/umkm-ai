<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ShopAdmin extends Model
{
    protected $fillable = [
        'shop_id',
        'user_id',
        'wa_number',
        'role',
        'is_active',
        'nama',
        'email',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOwners(Builder $query): Builder
    {
        return $query->where('role', 'owner');
    }

    public function scopeHelpers(Builder $query): Builder
    {
        return $query->where('role', 'helper');
    }
}
