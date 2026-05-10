<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'shop_id',
        'jeda_aktif',
        'jeda_sampai',
        'consecutive_ignored',
        'frekuensi_mode',
    ];

    protected $casts = [
        'jeda_aktif'  => 'boolean',
        'jeda_sampai' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isJedaAktif(): bool
    {
        if (! $this->jeda_aktif) {
            return false;
        }

        if ($this->jeda_sampai && $this->jeda_sampai->isPast()) {
            return false;
        }

        return true;
    }

    public function shouldReduceFrequency(): bool
    {
        return in_array($this->frekuensi_mode, ['reduced', 'minimal']);
    }
}
