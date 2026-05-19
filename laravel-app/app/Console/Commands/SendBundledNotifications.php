<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendBundledNotifications extends Command
{
    protected $signature   = 'umkm:send-notifications {--weekly : Kirim bundle info mingguan}';
    protected $description = 'Kirim bundle notifikasi penting (harian) atau info (mingguan)';

    public function __construct(private NotificationService $notif)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('weekly')) {
            $this->info('Mengirim bundle notifikasi info mingguan...');
            $this->notif->sendBundledNotifications('info');
        } else {
            $this->info('Mengirim bundle notifikasi penting harian...');
            $this->notif->sendBundledNotifications('penting');
        }

        $this->info('Bundle notifikasi selesai.');
        return Command::SUCCESS;
    }
}
