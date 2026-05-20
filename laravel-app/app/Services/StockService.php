<?php

namespace App\Services;

use App\Events\StokKritis;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Stock;
use App\Models\StockLog;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(
        private WAService           $wa,
        private SessionService      $session,
        private NotificationService $notif,
    ) {}

    // ── Tambah Stok ───────────────────────────────────────────────

    public function handleTambahStok(string $waNumber, array $entities, Shop $shop): void
    {
        $namaProduk = $entities['nama_produk'] ?? null;
        $jumlah     = isset($entities['jumlah']) ? (int) $entities['jumlah'] : null;

        if (! $namaProduk) {
            $this->session->updateContext($waNumber, 'tambah_stok', [
                'context' => 'tambah_stok', 'shop_id' => $shop->id, 'step' => 'tanya_produk',
            ]);
            $this->wa->kirimPesan($waNumber, "❓ Stok produk mana yang ingin ditambah?");
            return;
        }

        if (! $jumlah) {
            $this->session->updateContext($waNumber, 'tambah_stok', [
                'context' => 'tambah_stok', 'shop_id' => $shop->id,
                'step' => 'tanya_jumlah', 'nama_produk' => $namaProduk,
            ]);
            $this->wa->kirimPesan($waNumber, "❓ Berapa jumlah yang ditambahkan untuk *{$namaProduk}*?");
            return;
        }

        $this->prosesPerubahanStok($waNumber, $shop, $namaProduk, $jumlah, 'tambah');
    }

    // ── Kurangi Stok ──────────────────────────────────────────────

    public function handleKurangiStok(string $waNumber, array $entities, Shop $shop): void
    {
        $namaProduk = $entities['nama_produk'] ?? null;
        $jumlah     = isset($entities['jumlah']) ? (int) $entities['jumlah'] : null;

        if (! $namaProduk || ! $jumlah) {
            $this->wa->kirimPesan($waNumber,
                "❓ Sebutkan produk dan jumlahnya.\nContoh: *kurangi stok kopi 5*"
            );
            return;
        }

        $this->prosesPerubahanStok($waNumber, $shop, $namaProduk, $jumlah, 'kurang');
    }

    // ── Koreksi Stok ──────────────────────────────────────────────

    public function handleKoreksiStok(string $waNumber, array $entities, Shop $shop): void
    {
        $namaProduk = $entities['nama_produk'] ?? null;
        $jumlah     = isset($entities['jumlah']) ? (int) $entities['jumlah'] : null;

        if (! $namaProduk || $jumlah === null) {
            $this->wa->kirimPesan($waNumber,
                "❓ Sebutkan produk dan stok yang benar.\nContoh: *stok kopi sekarang 30*"
            );
            return;
        }

        $produk = $this->cariProduk($shop->id, $namaProduk);
        if (! $produk) {
            $this->wa->kirimPesan($waNumber, "Produk *{$namaProduk}* tidak ditemukan.");
            return;
        }

        $stok = $this->getOrCreateStock($produk);
        $selisih = $jumlah - $stok->jumlah_sekarang;

        DB::transaction(function () use ($stok, $produk, $jumlah, $selisih) {
            $stok->update(['jumlah_sekarang' => $jumlah]);
            StockLog::create([
                'product_id' => $produk->id,
                'tipe'       => 'koreksi',
                'jumlah'     => abs($selisih),
                'keterangan' => "Koreksi: {$stok->jumlah_sekarang} → {$jumlah}",
            ]);
            $this->updateEstimasiHabis($stok);
        });

        $this->wa->kirimPesan($waNumber,
            "✅ Stok *{$produk->nama_produk}* dikoreksi ke *{$jumlah}* unit."
        );
        $this->cekDanNotifikasiKritis($produk, $stok->fresh(), $shop, $waNumber);
    }

    // ── Cek Stok ──────────────────────────────────────────────────

    public function handleCekStok(string $waNumber, array $entities, Shop $shop): void
    {
        $namaProduk = $entities['nama_produk'] ?? null;

        if ($namaProduk) {
            $this->cekStokSatuProduk($waNumber, $shop, $namaProduk);
        } else {
            $this->cekSemuaStok($waNumber, $shop);
        }
    }

    private function cekStokSatuProduk(string $waNumber, Shop $shop, string $namaProduk): void
    {
        $produk = $this->cariProduk($shop->id, $namaProduk);
        if (! $produk) {
            $this->wa->kirimPesan($waNumber, "Produk *{$namaProduk}* tidak ditemukan.");
            return;
        }

        $stok = $produk->stock;
        $status = $stok
            ? ($stok->isHabis() ? '🔴 Habis' : ($stok->isKritis() ? '⚠️ Kritis' : '🟢 Aman'))
            : '❓ Belum ada data stok';

        $jumlahSekarang = $stok?->jumlah_sekarang ?? 0;
        $batasMinimum   = $stok?->batas_minimum ?? 5;

        $lines = [
            "📦 *Stok: {$produk->nama_produk}*",
            "Jumlah: *{$jumlahSekarang}* unit",
            "Batas minimum: {$batasMinimum} unit",
            "Status: {$status}",
        ];

        if ($stok?->estimasi_habis) {
            $lines[] = "Estimasi habis: " . $stok->estimasi_habis->format('d M Y');
        }

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    private function cekSemuaStok(string $waNumber, Shop $shop): void
    {
        $produk = Product::where('shop_id', $shop->id)
            ->where('status', 'active')
            ->with('stock')
            ->orderBy('nama_produk')
            ->get();

        if ($produk->isEmpty()) {
            $this->wa->kirimPesan($waNumber, "Belum ada produk aktif di toko.");
            return;
        }

        $lines   = ["📦 *Stok Semua Produk*\n"];
        $kritis  = [];
        $habis   = [];

        foreach ($produk as $p) {
            $s      = $p->stock;
            $jumlah = $s?->jumlah_sekarang ?? 0;
            $icon   = $jumlah === 0 ? '🔴' : ($s?->isKritis() ? '⚠️' : '🟢');

            $lines[] = "{$icon} {$p->nama_produk}: *{$jumlah}* unit";

            if ($jumlah === 0) $habis[] = $p->nama_produk;
            elseif ($s?->isKritis()) $kritis[] = $p->nama_produk;
        }

        if ($habis) {
            $lines[] = "\n🔴 *Habis:* " . implode(', ', $habis);
        }
        if ($kritis) {
            $lines[] = "⚠️ *Hampir habis:* " . implode(', ', $kritis);
        }

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    // ── Stok Kritis ───────────────────────────────────────────────

    public function handleStokKritis(string $waNumber, Shop $shop): void
    {
        $kritis = Stock::whereHas('product', fn ($q) =>
                $q->where('shop_id', $shop->id)->where('status', 'active')
            )
            ->with('product')
            ->kritis()
            ->orderBy('jumlah_sekarang')
            ->get();

        if ($kritis->isEmpty()) {
            $this->wa->kirimPesan($waNumber, "✅ Semua stok produk aman, tidak ada yang kritis.");
            return;
        }

        $lines = ["⚠️ *Stok Kritis (" . $kritis->count() . " produk):*\n"];

        foreach ($kritis as $s) {
            $icon = $s->isHabis() ? '🔴 HABIS' : '⚠️ Kritis';
            $lines[] = "{$icon} *{$s->product->nama_produk}*";
            $lines[] = "   Sisa: {$s->jumlah_sekarang} unit (min: {$s->batas_minimum})";
        }

        $lines[] = "\nKetik *tambah stok [nama] [jumlah]* untuk restock.";

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    // ── Atur Batas Minimum ────────────────────────────────────────

    public function handleAturBatasStok(string $waNumber, array $entities, Shop $shop): void
    {
        $namaProduk = $entities['nama_produk'] ?? null;
        $batas      = isset($entities['jumlah']) ? (int) $entities['jumlah'] : null;

        if (! $namaProduk || ! $batas) {
            $this->wa->kirimPesan($waNumber,
                "❓ Contoh: *batas stok kopi 10* — artinya notifikasi kritis jika stok ≤ 10."
            );
            return;
        }

        $produk = $this->cariProduk($shop->id, $namaProduk);
        if (! $produk) {
            $this->wa->kirimPesan($waNumber, "Produk *{$namaProduk}* tidak ditemukan.");
            return;
        }

        $stok = $this->getOrCreateStock($produk);
        $stok->update(['batas_minimum' => $batas]);

        $this->wa->kirimPesan($waNumber,
            "✅ Batas minimum stok *{$produk->nama_produk}* diset ke *{$batas}* unit.\n"
            . "Kamu akan dapat notifikasi jika stok ≤ {$batas} unit."
        );
    }

    // ── Proses Perubahan Stok (Tambah / Kurang) ───────────────────

    public function prosesPerubahanStok(
        string $waNumber,
        Shop $shop,
        string $namaProduk,
        int $jumlah,
        string $tipe,
        string $keterangan = ''
    ): void {
        $produk = $this->cariProduk($shop->id, $namaProduk);

        if (! $produk) {
            $this->wa->kirimPesan($waNumber, "Produk *{$namaProduk}* tidak ditemukan.");
            $this->session->clearContext($waNumber);
            return;
        }

        $stok    = $this->getOrCreateStock($produk);
        $sebelum = $stok->jumlah_sekarang;

        if ($tipe === 'kurang' && $jumlah > $sebelum) {
            $this->wa->kirimPesan($waNumber,
                "⚠️ Stok *{$produk->nama_produk}* hanya *{$sebelum}* unit.\n"
                . "Tidak bisa mengurangi {$jumlah} unit."
            );
            return;
        }

        $sesudah = $tipe === 'tambah' ? $sebelum + $jumlah : $sebelum - $jumlah;

        DB::transaction(function () use ($stok, $produk, $jumlah, $tipe, $keterangan, $sesudah) {
            $stok->update(['jumlah_sekarang' => $sesudah]);
            StockLog::create([
                'product_id' => $produk->id,
                'tipe'       => $tipe,
                'jumlah'     => $jumlah,
                'keterangan' => $keterangan ?: null,
            ]);
            $this->updateEstimasiHabis($stok);
        });

        $icon  = $tipe === 'tambah' ? '✅ +' : '✅ -';
        $fresh = $stok->fresh();

        $this->wa->kirimPesan($waNumber,
            "{$icon}{$jumlah} stok *{$produk->nama_produk}*\n"
            . "Stok sekarang: *{$fresh->jumlah_sekarang}* unit"
        );

        $this->cekDanNotifikasiKritis($produk, $fresh, $shop, $waNumber);
        $this->session->clearContext($waNumber);
    }

    // ── Lanjutan Flow Stok ────────────────────────────────────────

    public function prosesJawabanStok(string $waNumber, string $pesan, Shop $shop): bool
    {
        $ctx = $this->session->getContextData($waNumber);

        if (($ctx['context'] ?? '') !== 'tambah_stok') {
            return false;
        }

        $step = $ctx['step'] ?? '';

        if ($step === 'tanya_produk') {
            $this->session->mergeContextData($waNumber, [
                'nama_produk' => trim($pesan),
                'step'        => 'tanya_jumlah',
            ]);
            $this->wa->kirimPesan($waNumber, "❓ Berapa jumlah yang ditambahkan?");
            return true;
        }

        if ($step === 'tanya_jumlah') {
            $jumlah = (int) preg_replace('/[^0-9]/', '', $pesan);
            if ($jumlah <= 0) {
                $this->wa->kirimPesan($waNumber, "Jumlah tidak valid. Masukkan angka.");
                return true;
            }
            $this->prosesPerubahanStok($waNumber, $shop, $ctx['nama_produk'], $jumlah, 'tambah');
            return true;
        }

        return false;
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function cariProduk(int $shopId, string $nama): ?Product
    {
        return Product::where('shop_id', $shopId)
            ->where('nama_produk', 'ilike', "%{$nama}%")
            ->first();
    }

    private function getOrCreateStock(Product $produk): Stock
    {
        return $produk->stock ?? Stock::create([
            'product_id'    => $produk->id,
            'jumlah_sekarang' => 0,
            'batas_minimum' => 5,
        ]);
    }

    private function updateEstimasiHabis(Stock $stok): void
    {
        $estimasi = $stok->hitungEstimasiHabis();
        if ($estimasi) {
            $stok->update(['estimasi_habis' => $estimasi]);
        }
    }

    private function cekDanNotifikasiKritis(Product $produk, Stock $stok, Shop $shop, string $waNumber): void
    {
        if ($stok->isHabis() || $stok->isKritis()) {
            // Event-driven: listener NotifikasiStokKritis handles WA notification
            StokKritis::dispatch($produk, $shop, $stok->jumlah_sekarang, $stok->batas_minimum);
        }
    }

    // ── Method Publik untuk OrderService ─────────────────────────

    public function kurangiStokOrder(int $productId, int $jumlah, string $keterangan = 'Penjualan'): bool
    {
        $berhasil = false;

        DB::transaction(function () use ($productId, $jumlah, $keterangan, &$berhasil) {
            $stok = Stock::where('product_id', $productId)->lockForUpdate()->first();
            if (! $stok || $stok->jumlah_sekarang < $jumlah) {
                return;
            }
            $stok->decrement('jumlah_sekarang', $jumlah);
            StockLog::create([
                'product_id' => $productId,
                'tipe'       => 'kurang',
                'jumlah'     => $jumlah,
                'keterangan' => $keterangan,
            ]);
            $berhasil = true;
        });

        return $berhasil;
    }

    public function kembalikanStokOrder(int $productId, int $jumlah, string $keterangan = 'Pembatalan'): void
    {
        DB::transaction(function () use ($productId, $jumlah, $keterangan) {
            Stock::where('product_id', $productId)->increment('jumlah_sekarang', $jumlah);
            StockLog::create([
                'product_id' => $productId,
                'tipe'       => 'tambah',
                'jumlah'     => $jumlah,
                'keterangan' => $keterangan,
            ]);
        });
    }
}
