<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TokoController extends Controller
{
    public function edit(Request $request)
    {
        $shop = $request->attributes->get('admin_shop');
        return view('admin.toko.edit', compact('shop'));
    }

    public function update(Request $request)
    {
        $shop = $request->attributes->get('admin_shop');

        $validated = $request->validate([
            'nama_toko'            => 'required|string|max:100',
            'jenis_produk'         => 'nullable|string|max:100',
            'deskripsi'            => 'nullable|string|max:500',
            'alamat'               => 'nullable|string|max:255',
            'jam_buka'             => 'nullable|string|max:10',
            'jam_tutup'            => 'nullable|string|max:10',
            'nomor_rekening'       => 'nullable|string|max:30',
            'nama_bank'            => 'nullable|string|max:50',
            'nama_pemilik_rekening'=> 'nullable|string|max:100',
            'logo_url'             => 'nullable|url|max:500',
        ]);

        $shop->update($validated);

        return redirect()->route('admin.toko.edit')->with('success', 'Pengaturan toko berhasil disimpan.');
    }
}
