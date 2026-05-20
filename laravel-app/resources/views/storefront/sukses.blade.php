@extends('storefront.layout')

@section('title', 'Pesanan Diterima — ' . $shop->nama_toko)

@section('content')

<div class="text-center py-10">
    <div class="text-7xl mb-4">🎉</div>
    <h1 class="text-2xl font-bold text-gray-800">Pesanan Diterima!</h1>
    <p class="text-gray-500 mt-2 text-sm">Pesanan <strong>#{{ $order->id }}</strong> dari <strong>{{ $order->buyer_name }}</strong> sudah masuk.</p>

    <div class="bg-white rounded-2xl shadow-sm p-5 mt-6 text-left">
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-3">Detail Pesanan</p>
        <div class="space-y-2 text-sm text-gray-700">
            <div class="flex justify-between">
                <span>Total</span>
                <span class="font-bold text-primary">Rp {{ number_format($order->total_harga, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between">
                <span>Alamat</span>
                <span class="text-right max-w-xs">{{ $order->buyer_address }}{{ $order->buyer_city ? ', ' . $order->buyer_city : '' }}</span>
            </div>
            <div class="flex justify-between">
                <span>Status</span>
                <span class="bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full text-xs font-medium">Menunggu Konfirmasi</span>
            </div>
        </div>
    </div>

    <p class="text-gray-500 text-sm mt-5">
        Pemilik toko akan menghubungimu via WhatsApp untuk konfirmasi.<br>
        Terima kasih sudah berbelanja di <strong>{{ $shop->nama_toko }}</strong>! 🙏
    </p>

    <a href="{{ route('storefront.toko', $shop->slug) }}"
       class="btn-primary inline-block text-white font-bold px-8 py-3 rounded-full mt-6 transition">
        Kembali ke Toko
    </a>
</div>

@endsection
