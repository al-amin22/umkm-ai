@extends('admin.layout')
@section('title', isset($produk) ? 'Edit Produk' : 'Tambah Produk')

@section('content')

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.produk.index') }}" class="text-gray-400 hover:text-gray-600">← Kembali</a>
    <h1 class="text-xl font-bold text-gray-800">
        {{ isset($produk) ? 'Edit Produk' : 'Tambah Produk Baru' }}
    </h1>
</div>

<div class="max-w-lg">
    <form method="POST"
          action="{{ isset($produk) ? route('admin.produk.update', $produk->id) : route('admin.produk.store') }}"
          class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
        @csrf
        @if(isset($produk)) @method('PUT') @endif

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk <span class="text-red-500">*</span></label>
            <input type="text" name="nama_produk"
                   value="{{ old('nama_produk', $produk->nama_produk ?? '') }}"
                   required placeholder="contoh: Kopi Arabika 250gr"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                          focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Harga (Rp) <span class="text-red-500">*</span></label>
            <input type="number" name="harga"
                   value="{{ old('harga', $produk->harga ?? '') }}"
                   required min="0" step="100" placeholder="25000"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                          focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
            <textarea name="deskripsi" rows="3" placeholder="Deskripsi produk (opsional)"
                      class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                             focus:outline-none focus:ring-2 focus:ring-green-500 resize-none">{{ old('deskripsi', $produk->deskripsi ?? '') }}</textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
            @if(isset($produk))
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stok Sekarang <span class="text-red-500">*</span></label>
                    <input type="number" name="stok_sekarang"
                           value="{{ old('stok_sekarang', $produk->stock?->jumlah_sekarang ?? 0) }}"
                           required min="0"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            @else
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stok Awal <span class="text-red-500">*</span></label>
                    <input type="number" name="stok_awal"
                           value="{{ old('stok_awal', 0) }}"
                           required min="0"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Stok <span class="text-red-500">*</span></label>
                <input type="number" name="batas_minimum"
                       value="{{ old('batas_minimum', $produk->stock?->batas_minimum ?? 5) }}"
                       required min="0"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500">
                <p class="text-xs text-gray-400 mt-1">Notifikasi kritis jika stok ≤ nilai ini</p>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status"
                    class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                           focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="active"    {{ old('status', $produk->status ?? 'active') === 'active'   ? 'selected' : '' }}>Aktif</option>
                <option value="inactive"  {{ old('status', $produk->status ?? '') === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
            </select>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white font-semibold
                           px-6 py-2.5 rounded-xl text-sm transition">
                {{ isset($produk) ? 'Simpan Perubahan' : 'Tambah Produk' }}
            </button>
            <a href="{{ route('admin.produk.index') }}"
               class="px-6 py-2.5 rounded-xl text-sm border border-gray-300 hover:bg-gray-50 transition">
                Batal
            </a>
        </div>
    </form>
</div>

@endsection
