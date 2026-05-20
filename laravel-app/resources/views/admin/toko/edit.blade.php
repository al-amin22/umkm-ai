@extends('admin.layout')
@section('title', 'Pengaturan Toko')

@section('content')

<div class="flex items-center gap-3 mb-6">
    <h1 class="text-xl font-bold text-gray-800">🏪 Pengaturan Toko</h1>
</div>

<div class="max-w-2xl space-y-6">

    <form method="POST" action="{{ route('admin.toko.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Info Dasar --}}
        <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
            <h2 class="font-semibold text-gray-700 text-sm border-b border-gray-100 pb-3">📋 Informasi Dasar</h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Nama Toko <span class="text-red-500">*</span>
                </label>
                <input type="text" name="nama_toko"
                       value="{{ old('nama_toko', $shop->nama_toko) }}"
                       required maxlength="100"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Produk</label>
                <input type="text" name="jenis_produk"
                       value="{{ old('jenis_produk', $shop->jenis_produk) }}"
                       placeholder="contoh: Makanan & Minuman, Fashion, Elektronik"
                       maxlength="100"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Toko</label>
                <textarea name="deskripsi" rows="3" maxlength="500"
                          placeholder="Ceritakan sedikit tentang toko kamu..."
                          class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                                 focus:outline-none focus:ring-2 focus:ring-green-500 resize-none">{{ old('deskripsi', $shop->deskripsi) }}</textarea>
                <p class="text-xs text-gray-400 mt-1">Maks. 500 karakter. Ditampilkan di storefront.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                <input type="text" name="alamat"
                       value="{{ old('alamat', $shop->alamat) }}"
                       placeholder="Jl. Contoh No. 1, Kota"
                       maxlength="255"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jam Buka</label>
                    <input type="text" name="jam_buka"
                           value="{{ old('jam_buka', $shop->jam_buka) }}"
                           placeholder="08:00" maxlength="10"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jam Tutup</label>
                    <input type="text" name="jam_tutup"
                           value="{{ old('jam_tutup', $shop->jam_tutup) }}"
                           placeholder="21:00" maxlength="10"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>
        </div>

        {{-- Logo & Tampilan --}}
        <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
            <h2 class="font-semibold text-gray-700 text-sm border-b border-gray-100 pb-3">🎨 Logo & Tampilan</h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">URL Logo</label>
                <input type="url" name="logo_url"
                       value="{{ old('logo_url', $shop->logo_url) }}"
                       placeholder="https://..."
                       maxlength="500"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500">
                <p class="text-xs text-gray-400 mt-1">Link gambar logo toko (URL publik).</p>
            </div>

            @if($shop->logo_url)
                <div>
                    <p class="text-xs text-gray-500 mb-2">Preview logo saat ini:</p>
                    <img src="{{ $shop->logo_url }}" alt="Logo"
                         class="w-16 h-16 object-cover rounded-xl border border-gray-200"
                         onerror="this.style.display='none'">
                </div>
            @endif
        </div>

        {{-- Rekening Bank --}}
        <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
            <h2 class="font-semibold text-gray-700 text-sm border-b border-gray-100 pb-3">🏦 Rekening Bank</h2>
            <p class="text-xs text-gray-500">Ditampilkan kepada pembeli di halaman checkout.</p>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Bank</label>
                <input type="text" name="nama_bank"
                       value="{{ old('nama_bank', $shop->nama_bank) }}"
                       placeholder="BCA, BNI, Mandiri, dll."
                       maxlength="50"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Rekening</label>
                <input type="text" name="nomor_rekening"
                       value="{{ old('nomor_rekening', $shop->nomor_rekening) }}"
                       placeholder="0123456789"
                       maxlength="30"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Pemilik Rekening</label>
                <input type="text" name="nama_pemilik_rekening"
                       value="{{ old('nama_pemilik_rekening', $shop->nama_pemilik_rekening) }}"
                       placeholder="Sesuai buku tabungan"
                       maxlength="100"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
        </div>

        {{-- Info Read-only --}}
        <div class="bg-gray-50 rounded-2xl p-5 space-y-2 text-sm text-gray-500">
            <p class="font-medium text-gray-700 text-xs uppercase tracking-wide mb-2">Info Toko</p>
            <div class="flex gap-2">
                <span class="w-32">Slug Toko</span>
                <span class="font-mono text-xs bg-white border border-gray-200 px-2 py-0.5 rounded">{{ $shop->slug }}</span>
            </div>
            <div class="flex gap-2">
                <span class="w-32">WA Owner</span>
                <span>{{ $shop->wa_number_owner }}</span>
            </div>
            <div class="flex gap-2">
                <span class="w-32">Link Toko</span>
                <a href="{{ route('storefront.toko', $shop->slug) }}" target="_blank"
                   class="text-green-600 hover:underline text-xs">
                    /toko/{{ $shop->slug }} ↗
                </a>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white font-semibold
                           px-6 py-2.5 rounded-xl text-sm transition">
                Simpan Perubahan
            </button>
            <a href="{{ route('admin.dashboard') }}"
               class="px-6 py-2.5 rounded-xl text-sm border border-gray-300 hover:bg-gray-50 transition">
                Batal
            </a>
        </div>

    </form>

</div>

@endsection
