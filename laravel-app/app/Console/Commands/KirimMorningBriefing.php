<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\WorkflowLog;
use App\Services\LaporanService;
use Illuminate\Console\Command;

class KirimMorningBriefing extends Command
{
    protected $signature   = 'umkm:morning-briefing';
    protected $description = 'Kirim morning briefing harian ke semua toko aktif';

    public function __construct(private LaporanService $laporan)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $shops = Shop::where('status', 'active')->get();

        $this->info("Mengirim morning briefing ke {$shops->count()} toko...");

        foreach ($shops as $shop) {
            $mulai = now();
            try {
                $this->laporan->generateMorningBriefing($shop->id);
                WorkflowLog::catat('morning_briefing', 'success',
                    "Dikirim ke {$shop->wa_number_owner}",
                    $shop->id,
                    now()->diffInMilliseconds($mulai)
                );
            } catch (\Throwable $e) {
                $this->error("Gagal kirim ke shop #{$shop->id}: {$e->getMessage()}");
                WorkflowLog::catat('morning_briefing', 'failed',
                    $e->getMessage(),
                    $shop->id,
                    now()->diffInMilliseconds($mulai)
                );
            }
        }

        $this->info('Morning briefing selesai.');
        return Command::SUCCESS;
    }
}
