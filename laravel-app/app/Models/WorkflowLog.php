<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class WorkflowLog extends Model
{
    protected $fillable = [
        'shop_id',
        'nama_workflow',
        'status',
        'pesan',
        'durasi_ms',
        'konteks',
        'dijalankan_at',
    ];

    protected $casts = [
        'konteks'       => 'array',
        'dijalankan_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function scopeByShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeGagal(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public static function catat(
        string $namaWorkflow,
        string $status,
        string $pesan = '',
        ?int $shopId = null,
        ?int $durasiMs = null,
        array $konteks = []
    ): self {
        return self::create([
            'shop_id'        => $shopId,
            'nama_workflow'  => $namaWorkflow,
            'status'         => $status,
            'pesan'          => $pesan,
            'durasi_ms'      => $durasiMs,
            'konteks'        => $konteks ?: null,
            'dijalankan_at'  => now(),
        ]);
    }
}
