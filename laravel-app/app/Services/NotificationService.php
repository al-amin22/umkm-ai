<?php

namespace App\Services;

use App\Models\NotificationQueue;
use App\Models\NotificationPreference;
use App\Models\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private WAService $wa;

    // Jika consecutive_ignored melebihi ini → turunkan frekuensi
    private int $ignoreThreshold = 3;

    public function __construct(WAService $wa)
    {
        $this->wa = $wa;
    }

    // ── Dispatch Router ───────────────────────────────────────────

    public function dispatch(int $shopId, string $pesan, string $prioritas): void
    {
        if ($this->cekJedaAktif($shopId)) {
            Log::info("NotificationService: toko {$shopId} sedang jeda, notifikasi di-skip", [
                'prioritas' => $prioritas,
            ]);

            // Urgent tetap dikirim meski jeda
            if ($prioritas !== 'urgent') {
                return;
            }
        }

        match ($prioritas) {
            'urgent' => $this->sendUrgent($shopId, $pesan),
            'penting' => $this->queuePenting($shopId, $pesan),
            'info'    => $this->queueInfo($shopId, $pesan),
            default   => $this->queueInfo($shopId, $pesan),
        };
    }

    // ── Urgent: langsung kirim ────────────────────────────────────

    public function sendUrgent(int $shopId, string $pesan): void
    {
        $shop = Shop::find($shopId);
        if (! $shop) {
            return;
        }

        $kirim = $this->wa->kirimPesan($shop->wa_number_owner, $pesan);

        NotificationQueue::create([
            'shop_id'      => $shopId,
            'pesan'        => $pesan,
            'prioritas'    => 'urgent',
            'status'       => $kirim ? 'sent' : 'failed',
            'scheduled_at' => null,
            'sent_at'      => $kirim ? now() : null,
        ]);

        if (! $kirim) {
            Log::error("NotificationService: gagal kirim urgent ke toko {$shopId}");
        }
    }

    // ── Penting: bundle jam 8 pagi ────────────────────────────────

    public function queuePenting(int $shopId, string $pesan): void
    {
        $scheduledAt = $this->nextBundleTime('08:00');

        NotificationQueue::create([
            'shop_id'      => $shopId,
            'pesan'        => $pesan,
            'prioritas'    => 'penting',
            'status'       => 'pending',
            'scheduled_at' => $scheduledAt,
            'sent_at'      => null,
        ]);
    }

    // ── Info: bundle mingguan (Senin pagi) ────────────────────────

    public function queueInfo(int $shopId, string $pesan): void
    {
        $scheduledAt = $this->nextWeeklyBundle();

        NotificationQueue::create([
            'shop_id'      => $shopId,
            'pesan'        => $pesan,
            'prioritas'    => 'info',
            'status'       => 'pending',
            'scheduled_at' => $scheduledAt,
            'sent_at'      => null,
        ]);
    }

    // ── Send Bundled (dipanggil scheduler jam 8 pagi) ─────────────

    public function sendBundledNotifications(): void
    {
        $pending = NotificationQueue::scheduledNow()
            ->whereIn('prioritas', ['penting', 'info'])
            ->with('shop')
            ->get()
            ->groupBy('shop_id');

        foreach ($pending as $shopId => $notifications) {
            $shop = Shop::find($shopId);
            if (! $shop || $this->cekJedaAktif($shopId)) {
                continue;
            }

            $pref = NotificationPreference::firstOrCreate(
                ['shop_id' => $shopId],
                ['frekuensi_mode' => 'normal']
            );

            if ($pref->frekuensi_mode === 'minimal' && $notifications->count() < 3) {
                // Mode minimal: hanya kirim jika ada 3+ notifikasi
                continue;
            }

            $bundledPesan = $this->buildBundledMessage($notifications);
            $kirim        = $this->wa->kirimPesan($shop->wa_number_owner, $bundledPesan);

            $notifications->each(function ($notif) use ($kirim) {
                $notif->update([
                    'status'  => $kirim ? 'bundled' : 'failed',
                    'sent_at' => $kirim ? now() : null,
                ]);
            });

            Log::info("NotificationService: bundle terkirim ke toko {$shopId}", [
                'jumlah' => $notifications->count(),
                'kirim'  => $kirim,
            ]);
        }
    }

    // ── Jeda Management ───────────────────────────────────────────

    public function cekJedaAktif(int $shopId): bool
    {
        $pref = NotificationPreference::where('shop_id', $shopId)->first();

        if (! $pref || ! $pref->jeda_aktif) {
            return false;
        }

        if ($pref->jeda_sampai && $pref->jeda_sampai->isPast()) {
            // Jeda sudah berakhir, reset otomatis
            $pref->update(['jeda_aktif' => false, 'jeda_sampai' => null]);
            return false;
        }

        return true;
    }

    public function aktivasiJeda(int $shopId, int $jamDurasi): void
    {
        $jedaSampai = now()->addHours($jamDurasi);

        NotificationPreference::updateOrCreate(
            ['shop_id' => $shopId],
            [
                'jeda_aktif'  => true,
                'jeda_sampai' => $jedaSampai,
            ]
        );

        Log::info("NotificationService: jeda aktif toko {$shopId} sampai {$jedaSampai}");
    }

    public function nonaktifkanJeda(int $shopId): void
    {
        NotificationPreference::where('shop_id', $shopId)->update([
            'jeda_aktif'  => false,
            'jeda_sampai' => null,
        ]);
    }

    // ── Consecutive Ignored Logic ─────────────────────────────────

    public function cekConsecutiveIgnored(int $shopId): int
    {
        $pref = NotificationPreference::where('shop_id', $shopId)->first();

        if (! $pref) {
            return 0;
        }

        // Jika melebihi threshold → turunkan frekuensi otomatis
        if ($pref->consecutive_ignored > $this->ignoreThreshold) {
            $modeBaru = match (true) {
                $pref->consecutive_ignored > $this->ignoreThreshold * 3 => 'minimal',
                $pref->consecutive_ignored > $this->ignoreThreshold     => 'reduced',
                default                                                  => 'normal',
            };

            if ($pref->frekuensi_mode !== $modeBaru) {
                $pref->update(['frekuensi_mode' => $modeBaru]);
                Log::info("NotificationService: frekuensi toko {$shopId} diturunkan ke {$modeBaru}");
            }
        }

        return $pref->consecutive_ignored;
    }

    public function incrementIgnored(int $shopId): void
    {
        $pref = NotificationPreference::firstOrCreate(['shop_id' => $shopId]);
        $pref->increment('consecutive_ignored');
        $this->cekConsecutiveIgnored($shopId);
    }

    public function resetIgnored(int $shopId): void
    {
        NotificationPreference::where('shop_id', $shopId)->update([
            'consecutive_ignored' => 0,
            'frekuensi_mode'      => 'normal',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function buildBundledMessage(\Illuminate\Support\Collection $notifications): string
    {
        $lines   = ["📋 *Ringkasan Notifikasi*\n"];
        $counter = 1;

        foreach ($notifications as $notif) {
            $lines[] = "{$counter}. {$notif->pesan}";
            $counter++;
        }

        $lines[] = "\n_" . now()->format('d M Y H:i') . "_";

        return implode("\n", $lines);
    }

    private function nextBundleTime(string $waktu): Carbon
    {
        [$jam, $menit] = explode(':', $waktu);
        $target        = now()->setTime((int) $jam, (int) $menit, 0);

        return $target->isPast() ? $target->addDay() : $target;
    }

    private function nextWeeklyBundle(): Carbon
    {
        // Senin jam 08:00 berikutnya
        $target = now()->next(Carbon::MONDAY)->setTime(8, 0, 0);
        return $target;
    }
}
