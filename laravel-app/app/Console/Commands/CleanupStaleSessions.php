<?php

namespace App\Console\Commands;

use App\Services\SessionService;
use Illuminate\Console\Command;

class CleanupStaleSessions extends Command
{
    protected $signature   = 'umkm:cleanup-sessions';
    protected $description = 'Hapus sesi WA yang sudah tidak aktif lebih dari 24 jam';

    public function __construct(private SessionService $session)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Membersihkan stale sessions...');
        $this->session->cleanupStaleSessions();
        $this->info('Cleanup selesai.');
        return Command::SUCCESS;
    }
}
