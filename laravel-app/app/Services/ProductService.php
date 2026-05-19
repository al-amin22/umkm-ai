<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductFinance;
use App\Models\Shop;
use App\Models\Stock;
use App\Models\WaSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        private WAService        $wa,
        private SessionService   $session,
        private CloudinaryService $cloudinary,
        private GroqService      $groq,
    ) {}

    // ── Handler Entry Points (dipanggil CommandRouter) ─────────────

    public function handleTambahProduk(string $waNumber, array $entities, Shop $shop): void
    {
        $ctx = $this->session->getContextData($waNumber);

        // Cek apakah lanjutan dari flow sebelumnya
        if (($ctx['context'] ?? '') === 'tambah_produk') {
            $this->lanjutkanTambahProduk($waNumber, $entities, $shop, $ctx);
            return;
        }

        // Mulai flow baru
        $namaProduk = $entities['nama_produk'] ?? null;

        $this->session->updateContext($waNumber, 'tambah_produk', [
            'context'    => 'tambah_produk',
            'step'       => $namaProduk ? 'tanya_harga' : 'tanya_nama',
            'shop_id'    => $shop->id,
            'nama_produk'=> $namaProduk,
        ]);

        if ($namaProduk) {
            $this->wa->kirimPesan($waNumber,
                "Oke, tambah produk *{$namaProduk}*.\n\n❓ Berapa harganya? _(contoh: 25000)_"
            );
        } else {
            $this->wa->kirimPesan($waNumber,
                "📦 *Tambah Produk Baru*\n\n❓ Apa nama produknya?"
            );
        }
    }

    public function handleEditProduk(string $waNumber, array $entities, Shop $shop): void
    {
        $namaProduk = $entities['nama_produk'] ?? null;

        if (! $namaProduk) {
            $this->wa->kirimPesan($waNumber, "❓ Produk mana yang ingin diedit? Sebutkan namanya.");
            return;
        }

        $produk = $this->cariProduk($shop->id, $namaProduk);
        if (! $produk) {
            $this->wa->kirimPesan($waNumber,
                "Produk *{$namaProduk}* tidak ditemukan di tokomu.\n"
                . "Ketik *daftar produk* untuk melihat semua produk."
            );
            return;
        }

        // Cek apakah ada field yang langsung diset dari intent
        if ($entities['harga'] ?? null) {
            $this->updateHarga($waNumber, $produk, (float) $entities['harga']);
            return;
        }

        $this->session->updateContext($waNumber, 'edit_produk', [
            'context'    => 'edit_produk',
            'step'       => 'pilih_field',
            'product_id' => $produk->id,
            'shop_id'    => $shop->id,
        ]);

        $this->wa->kirimPesan($waNumber,
            "✏️ *Edit: {$produk->nama_produk}*\n"
            . "Harga: " . $this->wa->formatRupiah($produk->harga) . "\n\n"
            . "Apa yang ingin diubah?\n"
            . "• *nama* — ubah nama produk\n"
            . "• *harga [angka]* — ubah harga\n"
            . "• *deskripsi* — ubah deskripsi\n"
            . "• *status aktif/nonaktif/draft* — ubah status\n"
            . "• *foto* — ganti foto"
        );
    }

    public function handleHapusProduk(string $waNumber, array $entities, Shop $shop): void
    {
        $namaProduk = $entities['nama_produk'] ?? null;
        if (! $namaProduk) {
            $this->wa->kirimPesan($waNumber, "❓ Produk mana yang ingin dihapus?");
            return;
        }

        $produk = $this->cariProduk($shop->id, $namaProduk);
        if (! $produk) {
            $this->wa->kirimPesan($waNumber, "Produk *{$namaProduk}* tidak ditemukan.");
            return;
        }

        DB::transaction(function () use ($produk) {
            if ($produk->foto_public_id) {
                $this->cloudinary->deleteFoto($produk->foto_public_id);
            }
            $produk->stock()->delete();
            $produk->finance()->delete();
            $produk->delete();
        });

        $this->wa->kirimPesan($waNumber, "🗑️ Produk *{$namaProduk}* berhasil dihapus.");
        $this->session->clearContext($waNumber);
    }

    public function handleDaftarProduk(string $waNumber, Shop $shop): void
    {
        $produk = Product::where('shop_id', $shop->id)
            ->orderBy('status')
            ->orderBy('nama_produk')
            ->get();

        if ($produk->isEmpty()) {
            $this->wa->kirimPesan($waNumber,
                "Belum ada produk di toko *{$shop->nama_toko}*.\n"
                . "Ketik *tambah produk* untuk menambah produk pertama."
            );
            return;
        }

        $aktif    = $produk->where('status', 'active');
        $nonaktif = $produk->whereIn('status', ['draft', 'inactive']);

        $lines = ["📦 *Daftar Produk — {$shop->nama_toko}*\n"];

        if ($aktif->isNotEmpty()) {
            $lines[] = "🟢 *Aktif (" . $aktif->count() . "):*";
            foreach ($aktif as $p) {
                $lines[] = "• {$p->nama_produk} — " . $this->wa->formatRupiah($p->harga);
            }
        }

        if ($nonaktif->isNotEmpty()) {
            $lines[] = "\n⭕ *Nonaktif/Draft (" . $nonaktif->count() . "):*";
            foreach ($nonaktif as $p) {
                $statusLabel = match ($p->status) {
                    'draft'    => '[Draft]',
                    'inactive' => '[Nonaktif]',
                    default    => '',
                };
                $lines[] = "• {$statusLabel} {$p->nama_produk} — " . $this->wa->formatRupiah($p->harga);
            }
        }

        $lines[] = "\n_Total: {$produk->count()} produk_";

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    public function handleToggleStatus(string $waNumber, array $entities, Shop $shop, string $statusBaru): void
    {
        $namaProduk = $entities['nama_produk'] ?? null;
        if (! $namaProduk) {
            $this->wa->kirimPesan($waNumber, "❓ Produk mana yang ingin diubah statusnya?");
            return;
        }

        $produk = $this->cariProduk($shop->id, $namaProduk);
        if (! $produk) {
            $this->wa->kirimPesan($waNumber, "Produk *{$namaProduk}* tidak ditemukan.");
            return;
        }

        $produk->update(['status' => $statusBaru]);
        $label = match ($statusBaru) {
            'active'   => '🟢 diaktifkan',
            'inactive' => '🔴 dinonaktifkan',
            'draft'    => '📝 dijadikan draft',
        };

        $this->wa->kirimPesan($waNumber, "Produk *{$produk->nama_produk}* {$label}.");
    }

    // ── Lanjutan Flow Tambah Produk ───────────────────────────────

    public function prosesJawabanTambahProduk(string $waNumber, string $pesan, Shop $shop): bool
    {
        $ctx = $this->session->getContextData($waNumber);

        if (($ctx['context'] ?? '') !== 'tambah_produk') {
            return false;
        }

        $this->lanjutkanTambahProduk($waNumber, ['pesan_raw' => $pesan], $shop, $ctx);
        return true;
    }

    private function lanjutkanTambahProduk(string $waNumber, array $entities, Shop $shop, array $ctx): void
    {
        $step  = $ctx['step'] ?? 'tanya_nama';
        $pesan = $entities['pesan_raw'] ?? '';

        switch ($step) {
            case 'tanya_nama':
                $nama = trim($pesan);
                if (mb_strlen($nama) < 2) {
                    $this->wa->kirimPesan($waNumber, "Nama produk terlalu pendek. Coba lagi.");
                    return;
                }
                $this->session->mergeContextData($waNumber, [
                    'nama_produk' => $nama,
                    'step'        => 'tanya_harga',
                ]);
                $this->wa->kirimPesan($waNumber, "❓ Berapa harga *{$nama}*? _(contoh: 25000)_");
                break;

            case 'tanya_harga':
                $harga = (float) preg_replace('/[^0-9]/', '', $pesan);
                if ($harga <= 0) {
                    $this->wa->kirimPesan($waNumber, "Harga tidak valid. Masukkan angka, contoh: 25000");
                    return;
                }
                $this->session->mergeContextData($waNumber, [
                    'harga' => $harga,
                    'step'  => 'tanya_deskripsi',
                ]);
                $this->wa->kirimPesan($waNumber,
                    "❓ Ada deskripsi produk? _(ketik deskripsi atau \"skip\")_\n"
                    . "Atau ketik *generate* biar AI yang buatkan!"
                );
                break;

            case 'tanya_deskripsi':
                $deskripsi = null;
                if (strtolower(trim($pesan)) === 'generate') {
                    $deskripsi = $this->groq->generateDeskripsiProduk([
                        'nama'     => $ctx['nama_produk'],
                        'harga'    => $ctx['harga'],
                        'kategori' => $shop->jenis_produk,
                    ]);
                } elseif (strtolower(trim($pesan)) !== 'skip') {
                    $deskripsi = trim($pesan);
                }
                $this->session->mergeContextData($waNumber, [
                    'deskripsi' => $deskripsi,
                    'step'      => 'tanya_foto',
                ]);
                $this->wa->kirimPesan($waNumber,
                    "❓ Kirim foto produk, atau ketik *skip* jika belum ada foto."
                );
                break;

            case 'tanya_foto':
                // Foto di-handle di WAController saat tipe='gambar'
                // Jika teks, cek skip
                if (strtolower(trim($pesan)) === 'skip' || $ctx['foto_url'] ?? null) {
                    $this->simpanProdukBaru($waNumber, $shop, $ctx);
                } else {
                    $this->wa->kirimPesan($waNumber,
                        "Kirim foto produk dulu, atau ketik *skip* untuk lanjut tanpa foto."
                    );
                }
                break;
        }
    }

    public function prosesUploadFotoProduk(string $waNumber, string $base64, Shop $shop): void
    {
        $ctx = $this->session->getContextData($waNumber);

        if (($ctx['context'] ?? '') !== 'tambah_produk' && ($ctx['context'] ?? '') !== 'edit_produk') {
            return;
        }

        $this->wa->kirimPesan($waNumber, "⏳ Mengupload foto...");

        $result = $this->cloudinary->uploadFoto($base64, "shops/{$shop->id}/products");

        if (! $result) {
            $this->wa->kirimPesan($waNumber, "Gagal upload foto. Lanjut tanpa foto, atau coba lagi.");
            return;
        }

        if (($ctx['context'] ?? '') === 'tambah_produk') {
            $this->session->mergeContextData($waNumber, [
                'foto_url'       => $result['url'],
                'foto_public_id' => $result['public_id'],
            ]);
            $this->simpanProdukBaru($waNumber, $shop, array_merge($ctx, [
                'foto_url'       => $result['url'],
                'foto_public_id' => $result['public_id'],
            ]));
        } elseif (($ctx['context'] ?? '') === 'edit_produk') {
            $produk = Product::find($ctx['product_id']);
            if ($produk) {
                if ($produk->foto_public_id) {
                    $this->cloudinary->deleteFoto($produk->foto_public_id);
                }
                $produk->update([
                    'foto_url'       => $result['url'],
                    'foto_public_id' => $result['public_id'],
                ]);
                $this->wa->kirimPesan($waNumber, "✅ Foto *{$produk->nama_produk}* berhasil diperbarui.");
            }
            $this->session->clearContext($waNumber);
        }
    }

    private function simpanProdukBaru(string $waNumber, Shop $shop, array $ctx): void
    {
        $namaProduk = $ctx['nama_produk'] ?? null;
        $harga      = $ctx['harga'] ?? 0;

        if (! $namaProduk || $harga <= 0) {
            $this->wa->kirimPesan($waNumber, "Data produk tidak lengkap. Coba ulang dari awal.");
            $this->session->clearContext($waNumber);
            return;
        }

        $slug = $this->generateProductSlug($shop->id, $namaProduk);

        DB::transaction(function () use ($shop, $namaProduk, $harga, $ctx, $slug) {
            $produk = Product::create([
                'shop_id'        => $shop->id,
                'nama_produk'    => $namaProduk,
                'slug'           => $slug,
                'deskripsi'      => $ctx['deskripsi'] ?? null,
                'harga'          => $harga,
                'status'         => 'active',
                'foto_url'       => $ctx['foto_url'] ?? null,
                'foto_public_id' => $ctx['foto_public_id'] ?? null,
            ]);

            Stock::create(['product_id' => $produk->id, 'jumlah_sekarang' => 0]);
            ProductFinance::create(['product_id' => $produk->id, 'harga_jual' => $harga]);
        });

        $this->wa->kirimPesan($waNumber,
            "✅ *{$namaProduk}* berhasil ditambahkan!\n"
            . "💰 Harga: " . $this->wa->formatRupiah($harga) . "\n"
            . "📦 Stok: 0 (belum diisi)\n\n"
            . "Ketik *tambah stok {$namaProduk} [jumlah]* untuk isi stok awal."
        );

        $this->session->clearContext($waNumber);
    }

    // ── Lanjutan Flow Edit Produk ─────────────────────────────────

    public function prosesJawabanEditProduk(string $waNumber, string $pesan, Shop $shop): bool
    {
        $ctx = $this->session->getContextData($waNumber);

        if (($ctx['context'] ?? '') !== 'edit_produk') {
            return false;
        }

        $produk = Product::find($ctx['product_id']);
        if (! $produk) {
            $this->session->clearContext($waNumber);
            return false;
        }

        $step  = $ctx['step'] ?? 'pilih_field';
        $pesan = trim($pesan);

        if ($step === 'pilih_field') {
            if (str_starts_with(strtolower($pesan), 'harga')) {
                $harga = (float) preg_replace('/[^0-9]/', '', $pesan);
                if ($harga > 0) {
                    $this->updateHarga($waNumber, $produk, $harga);
                } else {
                    $this->session->mergeContextData($waNumber, ['step' => 'edit_harga']);
                    $this->wa->kirimPesan($waNumber, "❓ Berapa harga barunya?");
                }
            } elseif (strtolower($pesan) === 'nama') {
                $this->session->mergeContextData($waNumber, ['step' => 'edit_nama']);
                $this->wa->kirimPesan($waNumber, "❓ Nama produk yang baru?");
            } elseif (strtolower($pesan) === 'deskripsi') {
                $this->session->mergeContextData($waNumber, ['step' => 'edit_deskripsi']);
                $this->wa->kirimPesan($waNumber, "❓ Deskripsi baru untuk *{$produk->nama_produk}*?");
            } elseif (in_array(strtolower($pesan), ['aktif', 'active'])) {
                $produk->update(['status' => 'active']);
                $this->wa->kirimPesan($waNumber, "✅ Produk *{$produk->nama_produk}* diaktifkan.");
                $this->session->clearContext($waNumber);
            } elseif (in_array(strtolower($pesan), ['nonaktif', 'inactive'])) {
                $produk->update(['status' => 'inactive']);
                $this->wa->kirimPesan($waNumber, "✅ Produk *{$produk->nama_produk}* dinonaktifkan.");
                $this->session->clearContext($waNumber);
            }
        } elseif ($step === 'edit_harga') {
            $harga = (float) preg_replace('/[^0-9]/', '', $pesan);
            $this->updateHarga($waNumber, $produk, $harga);
        } elseif ($step === 'edit_nama') {
            $produk->update(['nama_produk' => $pesan]);
            $this->wa->kirimPesan($waNumber, "✅ Nama produk diperbarui menjadi *{$pesan}*.");
            $this->session->clearContext($waNumber);
        } elseif ($step === 'edit_deskripsi') {
            $produk->update(['deskripsi' => $pesan]);
            $this->wa->kirimPesan($waNumber, "✅ Deskripsi *{$produk->nama_produk}* diperbarui.");
            $this->session->clearContext($waNumber);
        }

        return true;
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function updateHarga(string $waNumber, Product $produk, float $harga): void
    {
        if ($harga <= 0) {
            $this->wa->kirimPesan($waNumber, "Harga tidak valid.");
            return;
        }
        $produk->update(['harga' => $harga]);
        $produk->finance()->update(['harga_jual' => $harga]);
        $this->wa->kirimPesan($waNumber,
            "✅ Harga *{$produk->nama_produk}* diperbarui: " . $this->wa->formatRupiah($harga)
        );
        $this->session->clearContext($waNumber);
    }

    private function cariProduk(int $shopId, string $nama): ?Product
    {
        return Product::where('shop_id', $shopId)
            ->where(function ($q) use ($nama) {
                $q->where('nama_produk', 'ilike', "%{$nama}%")
                  ->orWhere('slug', 'ilike', "%" . Str::slug($nama) . "%");
            })
            ->first();
    }

    private function generateProductSlug(int $shopId, string $nama): string
    {
        $base    = Str::slug($nama) ?: 'produk-' . Str::lower(Str::random(4));
        $slug    = $base;
        $counter = 2;

        while (Product::where('shop_id', $shopId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
