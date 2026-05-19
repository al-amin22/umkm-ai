<?php

namespace App\Services;

use App\Models\ContentHistory;
use App\Models\ContentPreference;
use App\Models\Product;
use App\Models\Shop;

class KontenService
{
    public function __construct(
        private WAService    $wa,
        private GroqService  $groq,
        private SessionService $session,
    ) {}

    // ── Buat Konten ───────────────────────────────────────────────

    public function handleBuatKonten(string $waNumber, array $entities, Shop $shop): void
    {
        $jenis      = $entities['jenis_konten'] ?? 'caption';
        $namaProduk = $entities['nama_produk'] ?? null;

        $produk = null;
        if ($namaProduk) {
            $produk = Product::where('shop_id', $shop->id)
                ->where('nama_produk', 'ilike', "%{$namaProduk}%")
                ->first();

            if (! $produk) {
                $this->wa->kirimPesan($waNumber, "Produk *{$namaProduk}* tidak ditemukan.");
                return;
            }
        }

        $pref = ContentPreference::firstOrCreate(
            ['shop_id' => $shop->id],
            ['tone' => 'friendly', 'platform' => 'instagram', 'bahasa' => 'id']
        );

        $this->wa->kirimPesan($waNumber, "✍️ Membuat {$jenis} via AI...");

        $konten = $this->groq->generateKonten([
            'jenis'       => $jenis,
            'produk'      => $produk?->nama_produk,
            'deskripsi'   => $produk?->deskripsi,
            'harga'       => $produk ? $this->wa->formatRupiah($produk->harga) : null,
            'tone'        => $pref->tone,
            'platform'    => $pref->platform,
            'nama_toko'   => $shop->nama_toko,
            'jenis_produk'=> $shop->jenis_produk,
        ]);

        if (! $konten) {
            $this->wa->kirimPesan($waNumber, "Maaf, gagal membuat konten. Coba lagi nanti.");
            return;
        }

        ContentHistory::create([
            'shop_id'      => $shop->id,
            'product_id'   => $produk?->id,
            'jenis_konten' => $jenis,
            'konten'       => $konten,
            'platform'     => $pref->platform,
        ]);

        $this->wa->kirimPesan($waNumber,
            "✍️ *{$this->labelJenis($jenis)}*\n"
            . ($produk ? "Produk: {$produk->nama_produk}\n\n" : "\n")
            . $konten . "\n\n"
            . "_Ketik *konten lagi* untuk variasi baru._"
        );
    }

    // ── Riwayat Konten ────────────────────────────────────────────

    public function handleRiwayatKonten(string $waNumber, Shop $shop): void
    {
        $histories = ContentHistory::where('shop_id', $shop->id)
            ->with('product')
            ->latest()
            ->limit(5)
            ->get();

        if ($histories->isEmpty()) {
            $this->wa->kirimPesan($waNumber, "Belum ada riwayat konten. Ketik *buat caption* untuk mulai.");
            return;
        }

        $lines = ["📝 *Riwayat Konten Terakhir*\n"];
        foreach ($histories as $i => $h) {
            $label    = $this->labelJenis($h->jenis_konten);
            $produkNm = $h->product?->nama_produk ?? 'Umum';
            $tanggal  = $h->created_at->setTimezone('Asia/Jakarta')->format('d/m H:i');
            $preview  = mb_substr($h->konten, 0, 60) . '...';
            $lines[]  = "*" . ($i + 1) . ".* [{$label}] {$produkNm} — {$tanggal}";
            $lines[]  = "_{$preview}_\n";
        }

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    // ── Setting Preferensi Konten ─────────────────────────────────

    public function handleSettingKonten(string $waNumber, array $entities, Shop $shop): void
    {
        $tone     = $entities['tone'] ?? null;
        $platform = $entities['platform'] ?? null;

        if (! $tone && ! $platform) {
            $pref = ContentPreference::where('shop_id', $shop->id)->first();

            $this->wa->kirimPesan($waNumber,
                "⚙️ *Setting Konten*\n\n"
                . "Tone saat ini: *" . ($pref?->tone ?? 'friendly') . "*\n"
                . "Platform: *" . ($pref?->platform ?? 'instagram') . "*\n\n"
                . "Ubah dengan:\n"
                . "• *set tone formal* / *set tone santai* / *set tone promosi*\n"
                . "• *set platform instagram* / *set platform tiktok* / *set platform whatsapp*"
            );
            return;
        }

        $update = [];
        if ($tone)     $update['tone']     = $tone;
        if ($platform) $update['platform'] = $platform;

        ContentPreference::updateOrCreate(['shop_id' => $shop->id], $update);

        $parts = [];
        if ($tone)     $parts[] = "tone: *{$tone}*";
        if ($platform) $parts[] = "platform: *{$platform}*";

        $this->wa->kirimPesan($waNumber, "✅ Setting konten diperbarui: " . implode(', ', $parts));
    }

    // ── Helper ────────────────────────────────────────────────────

    private function labelJenis(string $jenis): string
    {
        return match ($jenis) {
            'caption'   => 'Caption Instagram',
            'story'     => 'Script Story',
            'promo'     => 'Teks Promo',
            'whatsapp'  => 'Broadcast WA',
            default     => ucfirst($jenis),
        };
    }
}
