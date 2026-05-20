<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private WAService           $wa,
        private SessionService      $session,
        private StockService        $stock,
        private NotificationService $notif,
        private CustomerService     $customer,
    ) {}

    // ── Lihat Pesanan ─────────────────────────────────────────────

    public function handleLihatPesanan(string $waNumber, array $entities, Shop $shop): void
    {
        $statusFilter = $entities['status'] ?? null;

        $query = Order::where('shop_id', $shop->id)->with('items.product');

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        } else {
            $query->whereIn('status', ['pending', 'confirmed']);
        }

        $pesanan = $query->latest()->limit(10)->get();

        if ($pesanan->isEmpty()) {
            $label = $statusFilter ?? 'pending/konfirmasi';
            $this->wa->kirimPesan($waNumber, "Tidak ada pesanan {$label} saat ini. 🎉");
            return;
        }

        $lines = ["🛍️ *Pesanan Aktif*\n"];

        foreach ($pesanan as $p) {
            $statusIcon = match ($p->status) {
                'pending'   => '⏳',
                'confirmed' => '✅',
                'shipped'   => '🚚',
                default     => '📦',
            };

            $namaItem = $p->items->map(fn ($i) => "{$i->quantity}x {$i->product?->nama_produk}")->implode(', ');
            $lines[]  = "{$statusIcon} *#{$p->id}* — {$p->buyer_name}";
            $lines[]  = "   {$namaItem}";
            $lines[]  = "   💰 " . $this->wa->formatRupiah($p->total_harga);
            $lines[]  = "";
        }

        $lines[] = "_Ketik *detail [#id]* untuk info lengkap._";
        $lines[] = "_Ketik *konfirmasi [#id]* untuk konfirmasi._";

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    // ── Detail Pesanan ────────────────────────────────────────────

    public function handleDetailPesanan(string $waNumber, array $entities, Shop $shop): void
    {
        $orderId = $entities['order_id'] ?? $entities['jumlah'] ?? null;

        if (! $orderId) {
            $this->wa->kirimPesan($waNumber, "❓ ID pesanan mana? Contoh: *detail 42*");
            return;
        }

        $pesanan = Order::where('shop_id', $shop->id)
            ->where('id', (int) $orderId)
            ->with('items.product')
            ->first();

        if (! $pesanan) {
            $this->wa->kirimPesan($waNumber, "Pesanan #{$orderId} tidak ditemukan.");
            return;
        }

        $statusLabel = match ($pesanan->status) {
            'pending'   => '⏳ Menunggu konfirmasi',
            'confirmed' => '✅ Dikonfirmasi',
            'shipped'   => '🚚 Dikirim',
            'done'      => '🎉 Selesai',
            'cancelled' => '❌ Dibatalkan',
        };

        $lines = [
            "📦 *Detail Pesanan #{$pesanan->id}*",
            "Status: {$statusLabel}",
            "Pembeli: {$pesanan->buyer_name}",
            "HP: {$pesanan->buyer_phone}",
            "Alamat: {$pesanan->buyer_address}" . ($pesanan->buyer_city ? ", {$pesanan->buyer_city}" : ""),
            "",
            "*Item:*",
        ];

        foreach ($pesanan->items as $item) {
            $lines[] = "• {$item->quantity}x {$item->product?->nama_produk} = " . $this->wa->formatRupiah($item->subtotal);
        }

        $lines[] = "─────────────────";
        $lines[] = "Total: *" . $this->wa->formatRupiah($pesanan->total_harga) . "*";

        if ($pesanan->catatan) {
            $lines[] = "\nCatatan: _{$pesanan->catatan}_";
        }

        $tanggal = $pesanan->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i');
        $lines[] = "\nDipesan: {$tanggal}";

        $this->wa->kirimPesan($waNumber, implode("\n", $lines));
    }

    // ── Konfirmasi Pesanan ────────────────────────────────────────

    public function handleKonfirmasiPesanan(string $waNumber, array $entities, Shop $shop): void
    {
        $orderId = $entities['order_id'] ?? $entities['jumlah'] ?? null;

        if (! $orderId) {
            $this->wa->kirimPesan($waNumber, "❓ ID pesanan mana yang dikonfirmasi? Contoh: *konfirmasi 42*");
            return;
        }

        $pesanan = Order::where('shop_id', $shop->id)
            ->where('id', (int) $orderId)
            ->where('status', 'pending')
            ->with('items.product')
            ->first();

        if (! $pesanan) {
            $this->wa->kirimPesan($waNumber,
                "Pesanan #{$orderId} tidak ditemukan atau bukan status pending."
            );
            return;
        }

        // Cek ketersediaan stok untuk semua item
        $stokKurang = [];
        foreach ($pesanan->items as $item) {
            if ($item->product) {
                $stok = $item->product->stock;
                if (! $stok || $stok->jumlah_sekarang < $item->quantity) {
                    $tersedia = $stok?->jumlah_sekarang ?? 0;
                    $stokKurang[] = "{$item->product->nama_produk} (tersedia: {$tersedia}, butuh: {$item->quantity})";
                }
            }
        }

        if ($stokKurang) {
            $this->wa->kirimPesan($waNumber,
                "⚠️ Stok tidak cukup untuk dikonfirmasi:\n• " . implode("\n• ", $stokKurang)
            );
            return;
        }

        DB::transaction(function () use ($pesanan) {
            $pesanan->update([
                'status'       => 'confirmed',
                'confirmed_at' => now(),
            ]);

            foreach ($pesanan->items as $item) {
                if ($item->product_id) {
                    $this->stock->kurangiStokOrder(
                        $item->product_id,
                        $item->quantity,
                        "Pesanan #{$pesanan->id}"
                    );
                }
            }
        });

        $namaItem = $pesanan->items->map(fn ($i) => "{$i->quantity}x {$i->product?->nama_produk}")->implode(', ');

        $this->wa->kirimPesan($waNumber,
            "✅ *Pesanan #{$pesanan->id} dikonfirmasi!*\n"
            . "Pembeli: {$pesanan->buyer_name}\n"
            . "Item: {$namaItem}\n"
            . "Total: " . $this->wa->formatRupiah($pesanan->total_harga) . "\n\n"
            . "Stok sudah dikurangi otomatis."
        );

        // Notifikasi jika ada stok yang jadi kritis setelah konfirmasi
        foreach ($pesanan->items as $item) {
            if ($item->product?->stock?->isKritis()) {
                $this->notif->dispatch($shop->id,
                    "⚠️ Stok *{$item->product->nama_produk}* kritis setelah pesanan #{$pesanan->id}!",
                    'penting'
                );
            }
        }
    }

    // ── Update ke Shipped ─────────────────────────────────────────

    public function handleShippedPesanan(string $waNumber, array $entities, Shop $shop): void
    {
        $orderId = $entities['order_id'] ?? $entities['jumlah'] ?? null;
        $resi    = $entities['resi'] ?? $entities['nama_produk'] ?? null;

        if (! $orderId) {
            $this->wa->kirimPesan($waNumber,
                "❓ Format: *kirim [#id] [nomor resi]*\nContoh: *kirim 42 JNE1234567890*"
            );
            return;
        }

        $pesanan = Order::where('shop_id', $shop->id)
            ->where('id', (int) $orderId)
            ->where('status', 'confirmed')
            ->first();

        if (! $pesanan) {
            $this->wa->kirimPesan($waNumber,
                "Pesanan #{$orderId} tidak ditemukan atau belum dikonfirmasi."
            );
            return;
        }

        $catatan = $pesanan->catatan ?? '';
        if ($resi) {
            $catatan = "Resi: {$resi}" . ($catatan ? "\n{$catatan}" : '');
        }

        $pesanan->update([
            'status'     => 'shipped',
            'shipped_at' => now(),
            'catatan'    => $catatan,
        ]);

        $resiInfo = $resi ? "\nNo. Resi: *{$resi}*" : '';
        $this->wa->kirimPesan($waNumber,
            "🚚 *Pesanan #{$pesanan->id} sedang dikirim!*\n"
            . "Pembeli: {$pesanan->buyer_name}{$resiInfo}"
        );
    }

    // ── Selesaikan Pesanan ────────────────────────────────────────

    public function handleSelesaiPesanan(string $waNumber, array $entities, Shop $shop): void
    {
        $orderId = $entities['order_id'] ?? $entities['jumlah'] ?? null;

        if (! $orderId) {
            $this->wa->kirimPesan($waNumber, "❓ ID pesanan mana? Contoh: *selesai 42*");
            return;
        }

        $pesanan = Order::where('shop_id', $shop->id)
            ->where('id', (int) $orderId)
            ->whereIn('status', ['confirmed', 'shipped'])
            ->first();

        if (! $pesanan) {
            $this->wa->kirimPesan($waNumber, "Pesanan #{$orderId} tidak ditemukan.");
            return;
        }

        $pesanan->update(['status' => 'done', 'done_at' => now()]);

        // Update statistik pelanggan setelah pesanan selesai
        $this->customer->updateStatsOnDone($pesanan->fresh());

        $this->wa->kirimPesan($waNumber,
            "🎉 *Pesanan #{$pesanan->id} selesai!*\n"
            . "Pembeli: {$pesanan->buyer_name}\n"
            . "Total: " . $this->wa->formatRupiah($pesanan->total_harga)
        );
    }

    // ── Batalkan Pesanan ──────────────────────────────────────────

    public function handleBatalPesanan(string $waNumber, array $entities, Shop $shop): void
    {
        $orderId = $entities['order_id'] ?? $entities['jumlah'] ?? null;
        $alasan  = $entities['keterangan'] ?? null;

        if (! $orderId) {
            $this->wa->kirimPesan($waNumber, "❓ ID pesanan mana? Contoh: *batal 42*");
            return;
        }

        $pesanan = Order::where('shop_id', $shop->id)
            ->where('id', (int) $orderId)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->with('items')
            ->first();

        if (! $pesanan) {
            $this->wa->kirimPesan($waNumber,
                "Pesanan #{$orderId} tidak ditemukan atau tidak bisa dibatalkan."
            );
            return;
        }

        // Simpan status sebelum update — dipakai untuk cek stok setelah transaksi
        $statusSebelum = $pesanan->status;

        DB::transaction(function () use ($pesanan, $alasan, $statusSebelum) {
            if (in_array($statusSebelum, ['confirmed', 'shipped'])) {
                foreach ($pesanan->items as $item) {
                    if ($item->product_id) {
                        $this->stock->kembalikanStokOrder(
                            $item->product_id,
                            $item->quantity,
                            "Pembatalan pesanan #{$pesanan->id}"
                        );
                    }
                }
            }

            $pesanan->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
                'catatan'      => $alasan ? "Alasan batal: {$alasan}" : $pesanan->catatan,
            ]);
        });

        $alasanInfo = $alasan ? "\nAlasan: {$alasan}" : '';
        $stokInfo   = in_array($statusSebelum, ['confirmed', 'shipped']) ? "\nStok sudah dikembalikan." : '';
        $this->wa->kirimPesan($waNumber,
            "❌ *Pesanan #{$pesanan->id} dibatalkan.*{$alasanInfo}{$stokInfo}"
        );
    }

    // ── Reminder Pesanan Pending 24 Jam ───────────────────────────

    public function kirimReminderPending(): void
    {
        $cutoff = now()->subHours(24);

        Order::where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->where('reminder_count', '<', 3)
            ->with('shop')
            ->get()
            ->each(function (Order $order) {
                $shop = $order->shop;
                if (! $shop) return;

                $jam = $order->created_at->setTimezone('Asia/Jakarta')->diffForHumans();

                $this->notif->sendUrgent($shop->id,
                    "⏰ *Reminder Pesanan #" . $order->id . "*\n"
                    . "Dari: {$order->buyer_name}\n"
                    . "Total: " . $this->wa->formatRupiah($order->total_harga) . "\n"
                    . "Masuk {$jam} belum dikonfirmasi!\n\n"
                    . "Ketik *konfirmasi {$order->id}* atau *batal {$order->id}*"
                );

                $order->increment('reminder_count');
            });
    }
}
