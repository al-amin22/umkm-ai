<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFinance extends Model
{
    protected $fillable = [
        'product_id',
        'bahan_baku',
        'kemasan',
        'tenaga_kerja',
        'biaya_lain',
        'hpp_total',
        'harga_jual',
        'margin_persen',
    ];

    protected $casts = [
        'bahan_baku'    => 'decimal:2',
        'kemasan'       => 'decimal:2',
        'tenaga_kerja'  => 'decimal:2',
        'biaya_lain'    => 'decimal:2',
        'hpp_total'     => 'decimal:2',
        'harga_jual'    => 'decimal:2',
        'margin_persen' => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function hitungMargin(): float
    {
        if ($this->harga_jual <= 0) {
            return 0;
        }

        return round((($this->harga_jual - $this->hpp_total) / $this->harga_jual) * 100, 2);
    }

    public function hitungHppTotal(): float
    {
        return (float) $this->bahan_baku
             + (float) $this->kemasan
             + (float) $this->tenaga_kerja
             + (float) $this->biaya_lain;
    }

    public function getLaba(): float
    {
        return (float) $this->harga_jual - (float) $this->hpp_total;
    }
}
