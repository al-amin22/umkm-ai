<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopTheme extends Model
{
    protected $fillable = [
        'shop_id',
        'template_id',
        'warna_utama',
        'warna_sekunder',
        'banner_url',
        'banner_public_id',
        'last_updated',
    ];

    protected $casts = [
        'last_updated' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
