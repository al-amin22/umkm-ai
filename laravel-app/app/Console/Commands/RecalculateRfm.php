<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\CustomerService;
use Illuminate\Console\Command;

class RecalculateRfm extends Command
{
    protected $signature   = 'umkm:recalculate-rfm {--shop= : ID toko spesifik (opsional)}';
    protected $description = 'Hitung ulang skor RFM semua pelanggan';

    public function __construct(private CustomerService $customer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $shopId = $this->option('shop');

        if ($shopId) {
            $count = $this->customer->recalculateAllRfm((int) $shopId);
            $this->info("RFM dihitung ulang untuk {$count} pelanggan toko #{$shopId}.");
            return Command::SUCCESS;
        }

        $shops = Shop::where('status', 'active')->pluck('id');
        $total = 0;

        foreach ($shops as $id) {
            $count = $this->customer->recalculateAllRfm($id);
            $total += $count;
            $this->line("Toko #{$id}: {$count} pelanggan diproses.");
        }

        $this->info("Selesai. Total {$total} pelanggan RFM diperbarui.");
        return Command::SUCCESS;
    }
}
