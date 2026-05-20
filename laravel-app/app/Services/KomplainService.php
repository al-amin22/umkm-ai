<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintPattern;
use App\Models\Shop;

class KomplainService
{
    public function __construct(
        private WAService      $wa,
        private GroqService    $groq,
        private SessionService $session,
    ) {}

    // ── Catat Komplain ────────────────────────────────────────────

    public function handleCatatKomplain(string $waNumber, array $entities, Shop $shop): void
    {
        $deskripsi = $entities['keterangan'] ?? null;

        if (! $deskripsi) {
            $this->session->updateContext($waNumber, 'catat_komplain', [
                'context' => 'catat_komplain',
                'shop_id' => $shop->id,
                'step'    => 'tanya_deskripsi',
            ]);
            $this->wa->kirimPesan($waNumber,
                "📝 *Catat Komplain Pelanggan*\n\nCeritakan komplain yang diterima:"
            );
            return;
        }

        $this->simpanKomplain($waNumber, $shop, $deskripsi);
    }

    public function prosesJawabanKomplain(string $waNumber, string $pesan, Shop $shop): bool
    {
        $ctx = $this->session->getContextData($waNumber);
        if (($ctx['context'] ?? '') !== 'catat_komplain') return false;

        if (($ctx['step'] ?? '') === 'tanya_deskripsi') {
            $this->simpanKomplain($waNumber, $shop, $pesan);
            return true;
        }

        return false;
    }

    private function simpanKomplain(string $waNumber, Shop $shop, string $deskripsi): void
    {
        $this->wa->kirimPesan($waNumber, "🤖 Menganalisis komplain...");

        $analisis = $this->groq->analisaKomplain($deskripsi, 'lainnya');

        $tipe    = $this->mapTipe($analisis['tipe'] ?? 'lainnya');
        $urgensi = $this->mapUrgensi($analisis['prioritas'] ?? 'sedang');

        $komplain = Complaint::create([
            'shop_id'         => $shop->id,
            'buyer_name'      => 'Pelanggan',
            'pesan_asli'      => $deskripsi,
            'pesan_ringkasan' => mb_substr($deskripsi, 0, 100),
            'draft_balasan'   => $analisis['saran_respons'] ?? '',
            'tipe'            => $tipe,
            'urgensi'         => $urgensi,
            'status'          => 'baru',
        ]);

        $this->updatePolaKomplain($shop->id, $tipe);

        $urgensiIcon = match ($urgensi) {
            'tinggi' => '🔴',
            'sedang' => '⚠️',
            default  => '🟡',
        };

        $this->wa->kirimPesan($waNumber,
            "📝 *Komplain #" . $komplain->id . " tercatat*\n\n"
            . "Tipe: {$tipe}\n"
            . "Urgensi: {$urgensiIcon} {$urgensi}\n\n"
            . "*Saran Balasan ke Pelanggan:*\n"
            . "_{$komplain->draft_balasan}_\n\n"
            . "Ketik *selesai komplain {$komplain->id}* jika sudah ditangani."
        );

        $this->session->clearContext($waNumber);
    }

    // ── Lihat Komplain ────────────────────────────────────────────

    public function handleLihatKomplain(string $waNumber, array $entities, Shop $shop): void
    {
        $statusFilter = $entities['status'] ?? 'baru';

        $komplains = Complaint::where('shop_id', $shop->id)
            ->where('status', $statusFilter)
            ->latest()
            ->limit(10)
            ->get();

        if ($komplains->isEmpty()) {
            $this->wa->kirimPesan($waNumber, "Tidak ada komplain dengan status *{$statusFilter}*. ✅");
            return;
        }

        $urgensiIcon = ['tinggi' => '🔴', 'sedang' => '⚠️', 'rendah' => '🟡'];

        $lines = ["😤 *Komplain {$statusFilter} (" . $komplains->count() . ")*\n"];
        foreach ($komplains as $k) {
            $icon    = $urgensiIcon[$k->urgensi] ?? '🟡';
            $tanggal = $k->created_at->setTimezone('Asia/Jakarta')->format('d/m');
            $preview = mb_substr($k->pesan_asli, 0, 50) . '...';
            $lines[] = "{$icon} *#{$k->id}* [{$k->tipe}] {$tanggal}";
            $lines[] = "   _{$preview}_\n";
        }

        $lines[] = "_Ketik *detail komplain [#id]* untuk info lengkap._";
        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    // ── Detail Komplain ───────────────────────────────────────────

    public function handleDetailKomplain(string $waNumber, array $entities, Shop $shop): void
    {
        $komplainId = $entities['order_id'] ?? $entities['jumlah'] ?? null;

        if (! $komplainId) {
            $this->wa->kirimPesan($waNumber, "❓ ID komplain mana? Contoh: *detail komplain 5*");
            return;
        }

        $komplain = Complaint::where('shop_id', $shop->id)->where('id', (int) $komplainId)->first();

        if (! $komplain) {
            $this->wa->kirimPesan($waNumber, "Komplain #{$komplainId} tidak ditemukan.");
            return;
        }

        $statusLabel = match ($komplain->status) {
            'baru'       => '🆕 Baru',
            'diteruskan' => '📤 Diteruskan',
            'dibalas'    => '💬 Dibalas',
            'selesai'    => '✅ Selesai',
            default      => $komplain->status,
        };

        $tanggal = $komplain->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i');

        $this->wa->kirimPesan($waNumber,
            "📋 *Detail Komplain #{$komplain->id}*\n\n"
            . "Status: {$statusLabel}\n"
            . "Tipe: {$komplain->tipe}\n"
            . "Urgensi: {$komplain->urgensi}\n"
            . "Tanggal: {$tanggal}\n\n"
            . "*Pesan Asli:*\n_{$komplain->pesan_asli}_\n\n"
            . "*Saran Balasan:*\n_{$komplain->draft_balasan}_"
            . ($komplain->status !== 'selesai'
                ? "\n\nKetik *selesai komplain {$komplain->id}* jika sudah ditangani."
                : "")
        );
    }

    // ── Selesaikan Komplain ───────────────────────────────────────

    public function handleSelesaiKomplain(string $waNumber, array $entities, Shop $shop): void
    {
        $komplainId = $entities['order_id'] ?? $entities['jumlah'] ?? null;

        if (! $komplainId) {
            $this->wa->kirimPesan($waNumber, "❓ ID komplain mana? Contoh: *selesai komplain 5*");
            return;
        }

        $komplain = Complaint::where('shop_id', $shop->id)
            ->where('id', (int) $komplainId)
            ->where('status', '!=', 'selesai')
            ->first();

        if (! $komplain) {
            $this->wa->kirimPesan($waNumber, "Komplain #{$komplainId} tidak ditemukan atau sudah selesai.");
            return;
        }

        $komplain->update(['status' => 'selesai']);

        $this->wa->kirimPesan($waNumber,
            "✅ Komplain #{$komplain->id} ditandai selesai!\nTipe: {$komplain->tipe}"
        );
    }

    // ── Pola Komplain ─────────────────────────────────────────────

    public function handlePolaKomplain(string $waNumber, Shop $shop): void
    {
        $pola = ComplaintPattern::where('shop_id', $shop->id)
            ->orderByDesc('jumlah')
            ->limit(5)
            ->get();

        if ($pola->isEmpty()) {
            $this->wa->kirimPesan($waNumber, "Belum ada pola komplain tercatat.");
            return;
        }

        $lines = ["📊 *Pola Komplain Terbanyak*\n"];
        foreach ($pola as $p) {
            $lines[] = "• {$p->tipe_komplain}: *{$p->jumlah}x* ({$p->periode})";
        }

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function mapTipe(string $tipe): string
    {
        return match (strtolower($tipe)) {
            'rusak', 'broken'     => 'rusak',
            'telat', 'late'       => 'telat',
            'salah_item', 'wrong' => 'salah_item',
            'kualitas', 'quality' => 'kualitas',
            default               => 'lainnya',
        };
    }

    private function mapUrgensi(string $prioritas): string
    {
        return match (strtolower($prioritas)) {
            'tinggi', 'high', 'urgent' => 'tinggi',
            'rendah', 'low'            => 'rendah',
            default                    => 'sedang',
        };
    }

    private function updatePolaKomplain(int $shopId, string $tipeKomplain): void
    {
        $periode = now()->format('Y-m');

        $pola = ComplaintPattern::where('shop_id', $shopId)
            ->where('tipe_komplain', $tipeKomplain)
            ->where('periode', $periode)
            ->first();

        if ($pola) {
            $pola->increment('jumlah');
        } else {
            ComplaintPattern::create([
                'shop_id'       => $shopId,
                'tipe_komplain' => $tipeKomplain,
                'jumlah'        => 1,
                'periode'       => $periode,
            ]);
        }
    }
}
