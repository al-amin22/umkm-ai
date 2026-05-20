@extends('storefront.layout')

@section('title', $shop->nama_toko)
@section('description', $shop->deskripsi ?? 'Toko online ' . $shop->nama_toko)

@section('content')

{{-- Hero --}}
<div class="text-center mb-6">
    @if($shop->banner_url)
        <img src="{{ $shop->banner_url }}" alt="Banner {{ $shop->nama_toko }}"
             class="w-full h-40 object-cover rounded-2xl mb-4">
    @endif
    <h1 class="text-2xl font-bold text-gray-800">{{ $shop->nama_toko }}</h1>
    @if($shop->deskripsi)
        <p class="text-gray-500 mt-1 text-sm">{{ $shop->deskripsi }}</p>
    @endif
    @if($shop->nomor_wa)
        <a href="https://wa.me/{{ preg_replace('/\D/', '', $shop->wa_number_owner) }}"
           target="_blank"
           class="inline-flex items-center gap-1 text-green-600 text-sm mt-2 font-medium">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            Chat WhatsApp
        </a>
    @endif
</div>

{{-- Produk --}}
<section>
    <h2 class="text-lg font-bold text-gray-700 mb-3">Produk Kami</h2>

    @if($produk->isEmpty())
        <div class="text-center py-12 text-gray-400">
            <p class="text-4xl mb-2">🛒</p>
            <p>Belum ada produk tersedia.</p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3">
            @foreach($produk as $p)
                @php $stok = $p->stock?->jumlah_sekarang ?? 0; @endphp
                <a href="{{ route('storefront.produk', [$shop->slug, $p->id]) }}"
                   class="bg-white rounded-2xl shadow-sm overflow-hidden hover:shadow-md transition {{ $stok === 0 ? 'opacity-60' : '' }}">
                    @if($p->foto_url)
                        <img src="{{ $p->foto_url }}" alt="{{ $p->nama_produk }}"
                             class="w-full h-36 object-cover">
                    @else
                        <div class="w-full h-36 bg-gray-100 flex items-center justify-center text-4xl">🛍️</div>
                    @endif
                    <div class="p-3">
                        <p class="font-semibold text-gray-800 text-sm truncate">{{ $p->nama_produk }}</p>
                        <p class="text-primary font-bold text-sm mt-0.5">Rp {{ number_format($p->harga, 0, ',', '.') }}</p>
                        @if($stok === 0)
                            <span class="text-xs text-red-500 font-medium">Habis</span>
                        @elseif($stok <= 5)
                            <span class="text-xs text-orange-500">Sisa {{ $stok }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>

{{-- CTA order --}}
@if($produk->isNotEmpty())
    <div class="fixed bottom-4 left-0 right-0 flex justify-center px-4">
        <a href="{{ route('storefront.order', $shop->slug) }}"
           class="btn-primary text-white font-bold px-8 py-3 rounded-full shadow-lg text-sm transition w-full max-w-xs text-center">
            Buat Pesanan
        </a>
    </div>
    <div class="h-16"></div>
@endif

@endsection
