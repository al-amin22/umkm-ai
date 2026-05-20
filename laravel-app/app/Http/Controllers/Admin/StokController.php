<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StokController extends Controller
{
    // ── Riwayat Mutasi Stok ───────────────────────────────────────

    public function riwayat(Request $request, int $productId): View
    {
        $shop   = $request->attributes->get('admin_shop');
        $produk = Product::where('shop_id', $shop->id)->with('stock')->findOrFail($productId);

        $logs = StockLog::byProduct($productId)
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.stok.riwayat', compact('produk', 'logs'));
    }

    // ── Stock Opname ──────────────────────────────────────────────

    public function opname(Request $request): View
    {
        $shop   = $request->attributes->get('admin_shop');
        $produk = Product::where('shop_id', $shop->id)
            ->where('status', 'active')
            ->with('stock')
            ->orderBy('nama_produk')
            ->get();

        return view('admin.stok.opname', compact('shop', 'produk'));
    }

    public function simpanOpname(Request $request): RedirectResponse
    {
        $shop     = $request->attributes->get('admin_shop');
        $stokData = $request->input('stok', []);

        $updated = 0;

        DB::transaction(function () use ($shop, $stokData, &$updated) {
            foreach ($stokData as $productId => $jumlahFisik) {
                $jumlah = (int) $jumlahFisik;
                if ($jumlah < 0) continue;

                $produk = Product::where('shop_id', $shop->id)->find((int) $productId);
                if (! $produk) continue;

                $stock = $produk->stock;
                if (! $stock) {
                    Stock::create([
                        'product_id'      => $produk->id,
                        'jumlah_sekarang' => $jumlah,
                        'batas_minimum'   => 5,
                    ]);
                    $selisih = $jumlah;
                } else {
                    $selisih = $jumlah - $stock->jumlah_sekarang;
                    $stock->update(['jumlah_sekarang' => $jumlah]);
                }

                if ($selisih !== 0) {
                    StockLog::create([
                        'product_id'  => $produk->id,
                        'tipe'        => 'koreksi',
                        'jumlah'      => abs($selisih),
                        'keterangan'  => 'Opname fisik: ' . ($selisih > 0 ? "+{$selisih}" : "{$selisih}"),
                    ]);
                    $updated++;
                }
            }
        });

        return redirect()->route('admin.stok.opname')
            ->with('success', "{$updated} produk diperbarui dari hasil opname.");
    }
}
