<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class ReminderPesananPending extends Command
{
    protected $signature   = 'umkm:reminder-pesanan';
    protected $description = 'Kirim reminder untuk pesanan pending lebih dari 24 jam';

    public function __construct(private OrderService $order)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Mengecek pesanan pending...');
        $this->order->kirimReminderPending();
        $this->info('Reminder selesai.');
        return Command::SUCCESS;
    }
}
