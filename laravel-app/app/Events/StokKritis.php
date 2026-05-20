<?php

namespace App\Events;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StokKritis
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly Shop    $shop,
        public readonly int     $jumlahSekarang,
        public readonly int     $batasMinimum,
    ) {}
}
