<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Stock;
use App\Services\CloudinaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProdukController extends Controller
{
    public function __construct(private CloudinaryService $cloudinary) {}
    public function index(Request $request): View
    {
        $shop  = $request->attributes->get('admin_shop');
        $cari  = $request->query('cari');

        $query = Product::where('shop_id', $shop->id)->with('stock');

        if ($cari) {
            $query->where('nama_produk', 'ilike', "%{$cari}%");
        }

        $produk = $query->orderBy('nama_produk')->paginate(20)->withQueryString();

        return view('admin.produk.index', compact('shop', 'produk', 'cari'));
    }

    public function create(Request $request): View
    {
        $shop = $request->attributes->get('admin_shop');
        return view('admin.produk.form', compact('shop'));
    }

    public function store(Request $request): RedirectResponse
    {
        $shop      = $request->attributes->get('admin_shop');
        $validated = $request->validate([
            'nama_produk'  => 'required|string|max:150',
            'harga'        => 'required|numeric|min:0',
            'deskripsi'    => 'nullable|string|max:1000',
            'stok_awal'    => 'required|integer|min:0',
            'batas_minimum'=> 'required|integer|min:0',
            'status'       => 'required|in:active,inactive',
            'foto'         => 'nullable|image|max:5120',
        ]);

        $fotoUrl      = null;
        $fotoPublicId = null;

        if ($request->hasFile('foto') && $request->file('foto')->isValid()) {
            $upload = $this->cloudinary->uploadFoto(
                $request->file('foto')->getRealPath(),
                "products/{$shop->id}"
            );
            $fotoUrl      = $upload['url']       ?? null;
            $fotoPublicId = $upload['public_id'] ?? null;
        }

        DB::transaction(function () use ($shop, $validated, $fotoUrl, $fotoPublicId) {
            $produk = Product::create([
                'shop_id'       => $shop->id,
                'nama_produk'   => $validated['nama_produk'],
                'harga'         => $validated['harga'],
                'deskripsi'     => $validated['deskripsi'] ?? null,
                'status'        => $validated['status'],
                'foto_url'      => $fotoUrl,
                'foto_public_id'=> $fotoPublicId,
            ]);

            Stock::create([
                'product_id'      => $produk->id,
                'jumlah_sekarang' => $validated['stok_awal'],
                'batas_minimum'   => $validated['batas_minimum'],
            ]);
        });

        Cache::forget("dashboard.produk.{$shop->id}");

        return redirect()->route('admin.produk.index')
            ->with('success', "Produk *{$validated['nama_produk']}* berhasil ditambahkan.");
    }

    public function edit(Request $request, int $id): View
    {
        $shop   = $request->attributes->get('admin_shop');
        $produk = Product::where('shop_id', $shop->id)->with('stock')->findOrFail($id);
        return view('admin.produk.form', compact('shop', 'produk'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $shop      = $request->attributes->get('admin_shop');
        $produk    = Product::where('shop_id', $shop->id)->with('stock')->findOrFail($id);
        $validated = $request->validate([
            'nama_produk'  => 'required|string|max:150',
            'harga'        => 'required|numeric|min:0',
            'deskripsi'    => 'nullable|string|max:1000',
            'stok_sekarang'=> 'required|integer|min:0',
            'batas_minimum'=> 'required|integer|min:0',
            'status'       => 'required|in:active,inactive',
            'foto'         => 'nullable|image|max:5120',
        ]);

        $fotoUrl      = $produk->foto_url;
        $fotoPublicId = $produk->foto_public_id;

        if ($request->hasFile('foto') && $request->file('foto')->isValid()) {
            // Delete old photo if exists
            if ($fotoPublicId) {
                $this->cloudinary->deleteFoto($fotoPublicId);
            }
            $upload = $this->cloudinary->uploadFoto(
                $request->file('foto')->getRealPath(),
                "products/{$shop->id}"
            );
            $fotoUrl      = $upload['url']       ?? $fotoUrl;
            $fotoPublicId = $upload['public_id'] ?? $fotoPublicId;
        }

        DB::transaction(function () use ($produk, $validated, $fotoUrl, $fotoPublicId) {
            $produk->update([
                'nama_produk'   => $validated['nama_produk'],
                'harga'         => $validated['harga'],
                'deskripsi'     => $validated['deskripsi'] ?? null,
                'status'        => $validated['status'],
                'foto_url'      => $fotoUrl,
                'foto_public_id'=> $fotoPublicId,
            ]);

            if ($produk->stock) {
                $produk->stock->update([
                    'jumlah_sekarang' => $validated['stok_sekarang'],
                    'batas_minimum'   => $validated['batas_minimum'],
                ]);
            } else {
                Stock::create([
                    'product_id'      => $produk->id,
                    'jumlah_sekarang' => $validated['stok_sekarang'],
                    'batas_minimum'   => $validated['batas_minimum'],
                ]);
            }
        });

        return redirect()->route('admin.produk.index')
            ->with('success', "Produk berhasil diperbarui.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $shop   = $request->attributes->get('admin_shop');
        $produk = Product::where('shop_id', $shop->id)->findOrFail($id);
        $nama   = $produk->nama_produk;
        $produk->update(['status' => 'inactive']);

        return redirect()->route('admin.produk.index')
            ->with('success', "Produk *{$nama}* dinonaktifkan.");
    }
}
