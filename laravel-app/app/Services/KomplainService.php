<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintPattern;
use App\Models\Shop;

class KomplainService
{
    public function __construct(
        private WAService   $wa,
        private GroqService $groq,
        private SessionService $session,
    ) {}

    // ── Catat Komplain ────────────────────────────────────────────

    public function handleCatatKomplain(string $waNumber, array $entities, Shop $shop): void
    {
        $deskripsi = $entities['keterangan'] ?? null;
        $kategori  = $entities['kategori'] ?? 'lainnya';

        if (! $deskripsi) {
            $this->session->updateContext($waNumber, 'catat_komplain', [
                'context'  => 'catat_komplain',
                'shop_id'  => $shop->id,
                'kategori' => $kategori,
                'step'     => 'tanya_deskripsi',
            ]);
            $this->wa->kirimPesan($waNumber,
                "📝 *Catat Komplain Pelanggan*\n\n"
                . "Ceritakan komplain yang diterima:"
            );
            return;
        }

        $this->simpanKomplain($waNumber, $shop, $deskripsi, $kategori);
    }

    public function prosesJawabanKomplain(string $waNumber, string $pesan, Shop $shop): bool
    {
        $ctx = $this->session->getContextData($waNumber);
        if (($ctx['context'] ?? '') !== 'catat_komplain') return false;

        $step = $ctx['step'] ?? '';

        if ($step === 'tanya_deskripsi') {
            $this->simpanKomplain($waNumber, $shop, $pesan, $ctx['kategori'] ?? 'lainnya');
            return true;
        }

        return false;
    }

    private function simpanKomplain(string $waNumber, Shop $shop, string $deskripsi, string $kategori): void
    {
        $this->wa->kirimPesan($waNumber, "🤖 Menganalisis dan menyiapkan respons...");

        $analisis = $this->groq->analisaKomplain($deskripsi, $kategori);

        $komplain = Complaint::create([
            'shop_id'          => $shop->id,
            'deskripsi'        => $deskripsi,
            'kategori'         => $kategori,
            'sentimen'         => $analisis['sentimen'] ?? 'negatif',
            'saran_respons'    => $analisis['saran_respons'] ?? '',
            'prioritas'        => $analisis['prioritas'] ?? 'sedang',
            'status'           => 'open',
        ]);

        // Update pola komplain
        $this->updatePolaKomplain($shop->id, $kategori);

        $prioritasIcon = match ($komplain->prioritas) {
            'tinggi' => '🔴',
            'sedang' => '⚠️',
            default  => '🟡',
        };

        $this->wa->kirimPesan($waNumber,
            "📝 *Komplain #" . $komplain->id . " tercatat*\n\n"
            . "Kategori: {$kategori}\n"
            . "Prioritas: {$prioritasIcon} {$komplain->prioritas}\n\n"
            . "*Saran Respons ke Pelanggan:*\n"
            . "_{$komplain->saran_respons}_\n\n"
            . "Ketik *selesai komplain {$komplain->id}* jika sudah ditangani."
        );

        $this->session->clearContext($waNumber);
    }

    // ── Lihat Komplain ────────────────────────────────────────────

    public function handleLihatKomplain(string $waNumber, array $entities, Shop $shop): void
    {
        $statusFilter = $entities['status'] ?? 'open';

        $komplains = Complaint::where('shop_id', $shop->id)
            ->where('status', $statusFilter)
            ->latest()
            ->limit(10)
            ->get();

        if ($komplains->isEmpty()) {
            $this->wa->kirimPesan($waNumber, "Tidak ada komplain dengan status *{$statusFilter}*. ✅");
            return;
        }

        $prioritasIcon = ['tinggi' => '🔴', 'sedang' => '⚠️', 'rendah' => '🟡'];

        $lines = ["😤 *Komplain {$statusFilter} (" . $komplains->count() . ")*\n"];
        foreach ($komplains as $k) {
            $icon    = $prioritasIcon[$k->prioritas] ?? '🟡';
            $tanggal = $k->created_at->setTimezone('Asia/Jakarta')->format('d/m');
            $preview = mb_substr($k->deskripsi, 0, 50) . '...';
            $lines[] = "{$icon} *#{$k->id}* [{$k->kategori}] {$tanggal}";
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

        $komplain = Complaint::where('shop_id', $shop->id)
            ->where('id', (int) $komplainId)
            ->first();

        if (! $komplain) {
            $this->wa->kirimPesan($waNumber, "Komplain #{$komplainId} tidak ditemukan.");
            return;
        }

        $tanggal = $komplain->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i');
        $statusLabel = $komplain->status === 'open' ? '🔓 Open' : '✅ Selesai';

        $this->wa->kirimPesan($waNumber,
            "📋 *Detail Komplain #{$komplain->id}*\n\n"
            . "Status: {$statusLabel}\n"
            . "Kategori: {$komplain->kategori}\n"
            . "Prioritas: {$komplain->prioritas}\n"
            . "Tanggal: {$tanggal}\n\n"
            . "*Deskripsi:*\n_{$komplain->deskripsi}_\n\n"
            . "*Saran Respons:*\n_{$komplain->saran_respons}_"
            . ($komplain->status === 'open'
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
            ->where('status', 'open')
            ->first();

        if (! $komplain) {
            $this->wa->kirimPesan($waNumber, "Komplain #{$komplainId} tidak ditemukan atau sudah selesai.");
            return;
        }

        $komplain->update(['status' => 'resolved', 'resolved_at' => now()]);

        $this->wa->kirimPesan($waNumber,
            "✅ Komplain #{$komplain->id} ditandai selesai!\n"
            . "Kategori: {$komplain->kategori}"
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
            $lines[] = "• {$p->kategori}: *{$p->jumlah}x*";
            if ($p->saran_perbaikan) {
                $lines[] = "  _{$p->saran_perbaikan}_";
            }
        }

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    // ── Helper ────────────────────────────────────────────────────

    private function updatePolaKomplain(int $shopId, string $kategori): void
    {
        $pola = ComplaintPattern::where('shop_id', $shopId)
            ->where('kategori', $kategori)
            ->first();

        if ($pola) {
            $pola->increment('jumlah');
        } else {
            ComplaintPattern::create([
                'shop_id'  => $shopId,
                'kategori' => $kategori,
                'jumlah'   => 1,
            ]);
        }
    }
}
