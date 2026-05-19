<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFinance;
use App\Models\Shop;
use Illuminate\Support\Carbon;

class KeuanganService
{
    public function __construct(
        private WAService    $wa,
        private SessionService $session,
        private GroqService  $groq,
    ) {}

    // ── Hitung HPP ────────────────────────────────────────────────

    public function handleHitungHpp(string $waNumber, array $entities, Shop $shop): void
    {
        $namaProduk = $entities['nama_produk'] ?? null;

        if (! $namaProduk) {
            $this->wa->kirimPesan($waNumber, "❓ Produk mana yang ingin dihitung HPP-nya?");
            return;
        }

        $produk = Product::where('shop_id', $shop->id)
            ->where('nama_produk', 'ilike', "%{$namaProduk}%")
            ->first();

        if (! $produk) {
            $this->wa->kirimPesan($waNumber, "Produk *{$namaProduk}* tidak ditemukan.");
            return;
        }

        $finance = $produk->finance ?? ProductFinance::create([
            'product_id' => $produk->id,
            'harga_jual' => $produk->harga,
        ]);

        $this->session->updateContext($waNumber, 'hitung_hpp', [
            'context'    => 'hitung_hpp',
            'step'       => 'bahan_baku',
            'product_id' => $produk->id,
            'shop_id'    => $shop->id,
            'nama'       => $produk->nama_produk,
        ]);

        $existing = $finance->hpp_total > 0
            ? "\n_HPP saat ini: " . $this->wa->formatRupiah($finance->hpp_total) . "_\n"
            : "";

        $this->wa->kirimPesan($waNumber,
            "📊 *Hitung HPP — {$produk->nama_produk}*{$existing}\n"
            . "Mari isi biaya per unit produk:\n\n"
            . "❓ *Biaya bahan baku?*\n"
            . "_Contoh: 8000_\n"
            . "_Ketik 0 jika tidak ada_"
        );
    }

    public function prosesJawabanHpp(string $waNumber, string $pesan, Shop $shop): bool
    {
        $ctx = $this->session->getContextData($waNumber);
        if (($ctx['context'] ?? '') !== 'hitung_hpp') return false;

        $nilai  = (float) preg_replace('/[^0-9.]/', '', $pesan);
        $step   = $ctx['step'];
        $fields = ['bahan_baku', 'kemasan', 'tenaga_kerja', 'biaya_lain'];
        $labels = [
            'bahan_baku'   => 'bahan baku',
            'kemasan'      => 'kemasan',
            'tenaga_kerja' => 'tenaga kerja',
            'biaya_lain'   => 'biaya lain',
        ];
        $nextLabels = [
            'bahan_baku'   => "❓ *Biaya kemasan?*",
            'kemasan'      => "❓ *Biaya tenaga kerja?*",
            'tenaga_kerja' => "❓ *Biaya lain-lain?* _(listrik, gas, dll)_",
            'biaya_lain'   => null,
        ];

        if (! in_array($step, $fields)) return false;

        $this->session->mergeContextData($waNumber, [$step => $nilai]);
        $updatedCtx = $this->session->getContextData($waNumber);

        if ($step === 'biaya_lain') {
            // Semua biaya sudah diisi → simpan dan tampilkan ringkasan
            $this->simpanHppDanTampilkan($waNumber, $updatedCtx, $shop);
        } else {
            $this->session->mergeContextData($waNumber, ['step' => $fields[array_search($step, $fields) + 1]]);
            $this->wa->kirimPesan($waNumber, $nextLabels[$step]);
        }

        return true;
    }

    private function simpanHppDanTampilkan(string $waNumber, array $ctx, Shop $shop): void
    {
        $bahanBaku   = (float) ($ctx['bahan_baku'] ?? 0);
        $kemasan     = (float) ($ctx['kemasan'] ?? 0);
        $tenagaKerja = (float) ($ctx['tenaga_kerja'] ?? 0);
        $biayaLain   = (float) ($ctx['biaya_lain'] ?? 0);
        $hppTotal    = $bahanBaku + $kemasan + $tenagaKerja + $biayaLain;

        $produk  = Product::find($ctx['product_id']);
        $finance = ProductFinance::where('product_id', $ctx['product_id'])->first();

        if ($finance) {
            $hargaJual = $finance->harga_jual > 0 ? $finance->harga_jual : $produk->harga;
            $margin    = $hargaJual > 0 ? round((($hargaJual - $hppTotal) / $hargaJual) * 100, 1) : 0;

            $finance->update([
                'bahan_baku'   => $bahanBaku,
                'kemasan'      => $kemasan,
                'tenaga_kerja' => $tenagaKerja,
                'biaya_lain'   => $biayaLain,
                'hpp_total'    => $hppTotal,
                'harga_jual'   => $hargaJual,
                'margin_persen'=> $margin,
            ]);

            $marginIcon = $margin >= 30 ? '✅' : ($margin >= 15 ? '⚠️' : '🔴');

            $this->wa->kirimPesan($waNumber,
                "📊 *Ringkasan HPP — {$produk->nama_produk}*\n\n"
                . "Bahan baku:   " . $this->wa->formatRupiah($bahanBaku) . "\n"
                . "Kemasan:      " . $this->wa->formatRupiah($kemasan) . "\n"
                . "Tenaga kerja: " . $this->wa->formatRupiah($tenagaKerja) . "\n"
                . "Biaya lain:   " . $this->wa->formatRupiah($biayaLain) . "\n"
                . "─────────────────────\n"
                . "HPP Total:    *" . $this->wa->formatRupiah($hppTotal) . "*\n"
                . "Harga Jual:   " . $this->wa->formatRupiah($hargaJual) . "\n"
                . "Margin:       {$marginIcon} *{$margin}%*\n\n"
                . ($margin < 20 ? "⚠️ Margin kecil. Ketik *saran harga {$produk->nama_produk}* untuk rekomendasi AI." : "")
            );
        }

        $this->session->clearContext($waNumber);
    }

    // ── Saran Harga via AI ────────────────────────────────────────

    public function handleSaranHarga(string $waNumber, array $entities, Shop $shop): void
    {
        $namaProduk = $entities['nama_produk'] ?? null;

        if (! $namaProduk) {
            $this->wa->kirimPesan($waNumber, "❓ Produk mana yang ingin dapat saran harga?");
            return;
        }

        $produk = Product::where('shop_id', $shop->id)
            ->where('nama_produk', 'ilike', "%{$namaProduk}%")
            ->with('finance')
            ->first();

        if (! $produk) {
            $this->wa->kirimPesan($waNumber, "Produk *{$namaProduk}* tidak ditemukan.");
            return;
        }

        $hpp = $produk->finance?->hpp_total ?? 0;

        if ($hpp <= 0) {
            $this->wa->kirimPesan($waNumber,
                "HPP *{$namaProduk}* belum dihitung.\n"
                . "Ketik *hitung hpp {$namaProduk}* dulu ya."
            );
            return;
        }

        $this->wa->kirimPesan($waNumber, "⏳ Menganalisis harga terbaik via AI...");

        $saran = $this->groq->generateSaranHarga($hpp, $shop->jenis_produk);

        $hargaSaran   = $saran['harga_saran'] ?? 0;
        $marginPersen = $saran['margin_persen'] ?? 0;
        $alasan       = $saran['alasan'] ?? '';
        $rentangMin   = $saran['rentang_harga']['min'] ?? 0;
        $rentangMax   = $saran['rentang_harga']['max'] ?? 0;

        $this->wa->kirimPesan($waNumber,
            "💡 *Saran Harga AI — {$produk->nama_produk}*\n\n"
            . "HPP: " . $this->wa->formatRupiah($hpp) . "\n"
            . "Harga saran: *" . $this->wa->formatRupiah($hargaSaran) . "*\n"
            . "Margin: *{$marginPersen}%*\n"
            . "Rentang: " . $this->wa->formatRupiah($rentangMin) . " – " . $this->wa->formatRupiah($rentangMax) . "\n\n"
            . "_{$alasan}_\n\n"
            . "Ketik *edit harga {$namaProduk} {$hargaSaran}* untuk terapkan."
        );
    }

    // ── Ringkasan Keuangan ────────────────────────────────────────

    public function handleRingkasanKeuangan(string $waNumber, Shop $shop): void
    {
        $bulanIni  = now()->startOfMonth();
        $bulanLalu = now()->subMonth()->startOfMonth();

        $ordersIni  = Order::where('shop_id', $shop->id)
            ->where('status', 'done')
            ->where('created_at', '>=', $bulanIni)
            ->get();

        $ordersLalu = Order::where('shop_id', $shop->id)
            ->where('status', 'done')
            ->whereBetween('created_at', [$bulanLalu, $bulanIni])
            ->get();

        $omzetIni   = $ordersIni->sum('total_harga');
        $omzetLalu  = $ordersLalu->sum('total_harga');
        $growth     = $omzetLalu > 0
            ? round((($omzetIni - $omzetLalu) / $omzetLalu) * 100, 1)
            : null;

        // HPP rata-rata
        $hppRataRata = ProductFinance::whereHas('product', fn ($q) =>
            $q->where('shop_id', $shop->id)->where('status', 'active')
        )->avg('hpp_total') ?? 0;

        $growthText = $growth !== null
            ? ($growth >= 0 ? "📈 +{$growth}%" : "📉 {$growth}%") . " vs bulan lalu"
            : "";

        $bulanLabel = now()->locale('id')->isoFormat('MMMM YYYY');

        $this->wa->kirimPesan($waNumber,
            "💰 *Ringkasan Keuangan — {$bulanLabel}*\n\n"
            . "Omzet bulan ini: *" . $this->wa->formatRupiah($omzetIni) . "*\n"
            . ($growthText ? "{$growthText}\n" : "")
            . "Total pesanan selesai: {$ordersIni->count()}\n"
            . "Rata-rata per pesanan: " . ($ordersIni->count() > 0 ? $this->wa->formatRupiah($omzetIni / $ordersIni->count()) : "—") . "\n"
            . "HPP rata-rata produk: " . ($hppRataRata > 0 ? $this->wa->formatRupiah($hppRataRata) : "Belum dihitung") . "\n\n"
            . "_Ketik *laporan* untuk laporan lengkap dengan link._"
        );
    }

    // ── Cek Margin ────────────────────────────────────────────────

    public function handleCekMargin(string $waNumber, array $entities, Shop $shop): void
    {
        $namaProduk = $entities['nama_produk'] ?? null;

        if ($namaProduk) {
            $produk = Product::where('shop_id', $shop->id)
                ->where('nama_produk', 'ilike', "%{$namaProduk}%")
                ->with('finance')
                ->first();

            if (! $produk?->finance) {
                $this->wa->kirimPesan($waNumber,
                    "HPP *{$namaProduk}* belum dihitung. Ketik *hitung hpp {$namaProduk}*."
                );
                return;
            }

            $f = $produk->finance;
            $this->wa->kirimPesan($waNumber,
                "📊 *Margin — {$produk->nama_produk}*\n"
                . "HPP: " . $this->wa->formatRupiah($f->hpp_total) . "\n"
                . "Harga jual: " . $this->wa->formatRupiah($f->harga_jual) . "\n"
                . "Margin: *{$f->margin_persen}%*\n"
                . "Laba per unit: *" . $this->wa->formatRupiah($f->getLaba()) . "*"
            );
            return;
        }

        // Semua produk
        $finances = ProductFinance::whereHas('product', fn ($q) =>
            $q->where('shop_id', $shop->id)->where('status', 'active')
        )->with('product')->orderBy('margin_persen')->get();

        if ($finances->isEmpty()) {
            $this->wa->kirimPesan($waNumber, "Belum ada data HPP produk.");
            return;
        }

        $lines = ["📊 *Margin Semua Produk*\n"];
        foreach ($finances as $f) {
            $icon    = $f->margin_persen >= 30 ? '✅' : ($f->margin_persen >= 15 ? '⚠️' : '🔴');
            $lines[] = "{$icon} {$f->product->nama_produk}: *{$f->margin_persen}%*";
        }

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }
}
