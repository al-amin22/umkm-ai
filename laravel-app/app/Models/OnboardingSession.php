<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class OnboardingSession extends Model
{
    protected $fillable = [
        'wa_number',
        'step_terakhir',
        'data_terkumpul',
        'completed_at',
    ];

    protected $casts = [
        'data_terkumpul' => 'array',
        'completed_at'   => 'datetime',
    ];

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeByWaNumber(Builder $query, string $waNumber): Builder
    {
        return $query->where('wa_number', $waNumber);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
