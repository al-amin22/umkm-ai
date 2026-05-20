<?php

namespace App\Console\Commands;

use App\Models\WorkflowLog;
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
        $mulai = now();
        try {
            $this->order->kirimReminderPending();
            WorkflowLog::catat('reminder_pesanan', 'success', 'Selesai', null, now()->diffInMilliseconds($mulai));
        } catch (\Throwable $e) {
            WorkflowLog::catat('reminder_pesanan', 'failed', $e->getMessage(), null, now()->diffInMilliseconds($mulai));
        }
        $this->info('Reminder selesai.');
        return Command::SUCCESS;
    }
}
