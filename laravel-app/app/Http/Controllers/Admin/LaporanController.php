<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        $shop  = $request->attributes->get('admin_shop');
        $dari  = $request->input('dari',   now()->startOfMonth()->toDateString());
        $sampai = $request->input('sampai', now()->toDateString());

        $metrik  = $this->hitungMetrik($shop->id, $dari, $sampai);
        $pesanan = Order::where('shop_id', $shop->id)
            ->whereBetween('created_at', [
                Carbon::parse($dari)->startOfDay(),
                Carbon::parse($sampai)->endOfDay(),
            ])
            ->with('items.product')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.laporan.index', compact('metrik', 'pesanan', 'dari', 'sampai'));
    }

    public function exportCsv(Request $request)
    {
        $shop   = $request->attributes->get('admin_shop');
        $dari   = $request->input('dari',   now()->startOfMonth()->toDateString());
        $sampai = $request->input('sampai', now()->toDateString());

        $pesanan = Order::where('shop_id', $shop->id)
            ->whereBetween('created_at', [
                Carbon::parse($dari)->startOfDay(),
                Carbon::parse($sampai)->endOfDay(),
            ])
            ->with('items.product')
            ->orderByDesc('created_at')
            ->get();

        $filename = 'laporan_' . $dari . '_sd_' . $sampai . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($pesanan) {
            $handle = fopen('php://output', 'w');
            // BOM untuk Excel agar encoding UTF-8 terbaca
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'No. Pesanan', 'Tanggal', 'Pembeli', 'Telepon', 'Kota',
                'Item', 'Total (Rp)', 'Status',
            ]);

            foreach ($pesanan as $o) {
                $itemStr = $o->items->map(
                    fn ($i) => $i->quantity . 'x ' . ($i->product?->nama_produk ?? '?')
                )->implode('; ');

                fputcsv($handle, [
                    $o->nomor_pesanan ?? '#' . $o->id,
                    $o->created_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
                    $o->buyer_name,
                    $o->buyer_phone,
                    $o->buyer_city ?? '-',
                    $itemStr,
                    number_format($o->total_harga, 0, ',', '.'),
                    ucfirst($o->status),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function cetak(Request $request)
    {
        $shop   = $request->attributes->get('admin_shop');
        $dari   = $request->input('dari',   now()->startOfMonth()->toDateString());
        $sampai = $request->input('sampai', now()->toDateString());

        $metrik  = $this->hitungMetrik($shop->id, $dari, $sampai);
        $pesanan = Order::where('shop_id', $shop->id)
            ->whereBetween('created_at', [
                Carbon::parse($dari)->startOfDay(),
                Carbon::parse($sampai)->endOfDay(),
            ])
            ->with('items.product')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.laporan.cetak', compact('shop', 'metrik', 'pesanan', 'dari', 'sampai'));
    }

    // ── Private ───────────────────────────────────────────────────

    private function hitungMetrik(int $shopId, string $dari, string $sampai): array
    {
        $orders = Order::where('shop_id', $shopId)
            ->whereBetween('created_at', [
                Carbon::parse($dari)->startOfDay(),
                Carbon::parse($sampai)->endOfDay(),
            ])
            ->get();

        $done      = $orders->where('status', 'done');
        $cancelled = $orders->where('status', 'cancelled');
        $omzet     = (float) $done->sum('total_harga');

        $produkTerlaris = OrderItem::whereHas(
                'order',
                fn ($q) => $q->where('shop_id', $shopId)
                             ->whereBetween('created_at', [
                                 Carbon::parse($dari)->startOfDay(),
                                 Carbon::parse($sampai)->endOfDay(),
                             ])
                             ->where('status', 'done')
            )
            ->with('product')
            ->select('product_id', DB::raw('SUM(quantity) as total_terjual'), DB::raw('SUM(subtotal) as total_omzet'))
            ->groupBy('product_id')
            ->orderByDesc('total_terjual')
            ->limit(5)
            ->get()
            ->map(fn ($i) => [
                'nama'          => $i->product?->nama_produk ?? '-',
                'total_terjual' => (int) $i->total_terjual,
                'total_omzet'   => (float) $i->total_omzet,
            ])
            ->toArray();

        return [
            'omzet'             => $omzet,
            'total_pesanan'     => $orders->count(),
            'pesanan_done'      => $done->count(),
            'pesanan_cancelled' => $cancelled->count(),
            'konversi_pct'      => $orders->count() > 0
                ? round($done->count() / $orders->count() * 100, 1)
                : 0,
            'avg_order_value'   => $done->count() > 0 ? $omzet / $done->count() : 0,
            'produk_terlaris'   => $produkTerlaris,
        ];
    }
}
