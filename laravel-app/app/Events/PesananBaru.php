<?php

namespace App\Events;

use App\Models\Order;
use App\Models\Shop;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PesananBaru
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly Shop  $shop,
    ) {}
}
