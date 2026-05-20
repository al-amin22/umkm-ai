@extends('storefront.layout')

@section('title', $produk->nama_produk . ' — ' . $shop->nama_toko)
@section('description', $produk->deskripsi ?? $produk->nama_produk)

@section('content')

<a href="{{ route('storefront.toko', $shop->slug) }}" class="inline-flex items-center gap-1 text-gray-500 text-sm mb-4">
    ← Kembali
</a>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    @if($produk->foto_url)
        <img src="{{ $produk->foto_url }}" alt="{{ $produk->nama_produk }}"
             class="w-full h-64 object-cover">
    @else
        <div class="w-full h-64 bg-gray-100 flex items-center justify-center text-6xl">🛍️</div>
    @endif

    <div class="p-5">
        <h1 class="text-xl font-bold text-gray-800">{{ $produk->nama_produk }}</h1>
        <p class="text-primary text-2xl font-bold mt-1">Rp {{ number_format($produk->harga, 0, ',', '.') }}</p>

        @php $stok = $produk->stock?->jumlah_sekarang ?? 0; @endphp
        <span class="inline-block mt-2 text-xs px-2 py-1 rounded-full font-medium
            {{ $stok === 0 ? 'bg-red-100 text-red-600' : ($stok <= 5 ? 'bg-orange-100 text-orange-600' : 'bg-green-100 text-green-700') }}">
            {{ $stok === 0 ? 'Stok Habis' : ($stok <= 5 ? "Sisa {$stok} unit" : 'Tersedia') }}
        </span>

        @if($produk->deskripsi)
            <p class="text-gray-600 text-sm mt-4 leading-relaxed">{{ $produk->deskripsi }}</p>
        @endif

        @if($stok > 0)
            <a href="{{ route('storefront.order', $shop->slug) }}?produk={{ $produk->id }}"
               class="btn-primary text-white font-bold px-6 py-3 rounded-full mt-5 block text-center transition">
                Pesan Sekarang
            </a>
        @else
            <button disabled class="bg-gray-200 text-gray-400 font-bold px-6 py-3 rounded-full mt-5 w-full cursor-not-allowed">
                Stok Habis
            </button>
        @endif
    </div>
</div>

@endsection
