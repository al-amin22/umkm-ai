<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPreference extends Model
{
    protected $fillable = [
        'shop_id',
        'gaya_bahasa',
        'emoji_preference',
        'panjang_konten',
        'contoh_disukai',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function toPromptContext(): string
    {
        $emoji = match ($this->emoji_preference) {
            'sering' => 'gunakan emoji secara sering',
            'jarang' => 'gunakan emoji secukupnya',
            'tidak'  => 'jangan gunakan emoji',
        };

        $panjang = match ($this->panjang_konten) {
            'pendek' => 'konten singkat (1-2 kalimat)',
            'sedang' => 'konten sedang (3-4 kalimat)',
            'panjang' => 'konten panjang (5+ kalimat)',
        };

        return "Gaya bahasa: {$this->gaya_bahasa}. {$emoji}. Panjang: {$panjang}.";
    }
}
