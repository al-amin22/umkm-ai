@extends('admin.layout')
@section('title', 'Detail Pesanan')

@section('content')

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.pesanan.index') }}" class="text-gray-400 hover:text-gray-600">← Kembali</a>
    <h1 class="text-xl font-bold text-gray-800">
        {{ $pesanan->nomor_pesanan ?? '#'.$pesanan->id }}
    </h1>
    <a href="{{ route('admin.pesanan.invoice', $pesanan->id) }}" target="_blank"
       class="ml-auto text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-4 py-2 rounded-xl transition">
        🖨️ Invoice
    </a>
    @php
        $badge = match($pesanan->status) {
            'pending'   => 'bg-orange-100 text-orange-700',
            'confirmed' => 'bg-blue-100 text-blue-700',
            'shipped'   => 'bg-purple-100 text-purple-700',
            'done'      => 'bg-green-100 text-green-700',
            'cancelled' => 'bg-red-100 text-red-700',
            default     => 'bg-gray-100 text-gray-600',
        };
    @endphp
    <span class="px-3 py-1 rounded-full text-sm font-medium {{ $badge }}">{{ ucfirst($pesanan->status) }}</span>
</div>

<div class="grid md:grid-cols-3 gap-6">

    {{-- Detail Pesanan --}}
    <div class="md:col-span-2 space-y-4">

        {{-- Item Pesanan --}}
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm">📦 Item Pesanan</h2>
            <div class="space-y-3">
                @foreach($pesanan->items as $item)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-lg">🛍️</div>
                            <div>
                                <p class="font-medium text-gray-800 text-sm">
                                    {{ $item->product?->nama_produk ?? 'Produk dihapus' }}
                                </p>
                                <p class="text-xs text-gray-400">
                                    {{ $item->quantity }}x @ Rp {{ number_format($item->harga_saat_pesan, 0, ',', '.') }}
                                </p>
                            </div>
                        </div>
                        <span class="font-semibold text-gray-800 text-sm">
                            Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                        </span>
                    </div>
                @endforeach
                <div class="border-t border-gray-100 pt-3 flex justify-between font-bold">
                    <span class="text-gray-700">Total</span>
                    <span class="text-green-600 text-lg">Rp {{ number_format($pesanan->total_harga, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        {{-- Aksi --}}
        @if($pesanan->status === 'pending')
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-700 mb-3 text-sm">⚡ Aksi</h2>
                <div class="flex gap-3 flex-wrap">
                    <form method="POST" action="{{ route('admin.pesanan.konfirmasi', $pesanan->id) }}">
                        @csrf
                        <button type="submit"
                                class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-5 py-2 rounded-xl transition">
                            ✅ Konfirmasi
                        </button>
                    </form>
                    <button onclick="document.getElementById('modal-batal').classList.remove('hidden')"
                            class="bg-red-50 hover:bg-red-100 text-red-600 text-sm font-medium px-5 py-2 rounded-xl transition">
                        ❌ Batalkan
                    </button>
                </div>
            </div>
        @elseif($pesanan->status === 'confirmed')
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-700 mb-3 text-sm">⚡ Aksi</h2>
                <form method="POST" action="{{ route('admin.pesanan.kirim', $pesanan->id) }}"
                      class="flex gap-3 flex-wrap items-end">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">No. Resi (opsional)</label>
                        <input type="text" name="resi" placeholder="JNE123456789"
                               class="border border-gray-300 rounded-xl px-3 py-2 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-xl transition">
                        🚚 Tandai Dikirim
                    </button>
                </form>
            </div>
        @elseif($pesanan->status === 'shipped')
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-700 mb-3 text-sm">⚡ Aksi</h2>
                <form method="POST" action="{{ route('admin.pesanan.selesai', $pesanan->id) }}">
                    @csrf
                    <button type="submit"
                            class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-5 py-2 rounded-xl transition">
                        🎉 Tandai Selesai
                    </button>
                </form>
            </div>
        @endif

    </div>

    {{-- Sidebar Info --}}
    <div class="space-y-4">

        {{-- Pembeli --}}
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm">👤 Pembeli</h2>
            <div class="space-y-2 text-sm">
                <p class="font-medium text-gray-800">{{ $pesanan->buyer_name }}</p>
                <p class="text-gray-500">📱 {{ $pesanan->buyer_phone }}</p>
                <p class="text-gray-500">📍 {{ $pesanan->buyer_address }}
                    @if($pesanan->buyer_city), {{ $pesanan->buyer_city }}@endif
                </p>
                @if($pesanan->catatan)
                    <div class="bg-yellow-50 rounded-lg px-3 py-2 text-xs text-yellow-800 mt-2">
                        📝 {{ $pesanan->catatan }}
                    </div>
                @endif
            </div>
            @if($pesanan->customer)
                <div class="border-t border-gray-100 mt-3 pt-3">
                    <p class="text-xs text-gray-400">Pelanggan terdaftar</p>
                    <p class="text-xs text-gray-600 mt-0.5">
                        {{ $pesanan->customer->total_pesanan }}x pesanan total ·
                        Rp {{ number_format($pesanan->customer->total_belanja, 0, ',', '.') }}
                    </p>
                    <span class="text-xs text-green-600 font-medium">{{ $pesanan->customer->tier }}</span>
                </div>
            @endif
        </div>

        {{-- Timeline --}}
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm">🕐 Timeline</h2>
            <div class="space-y-2 text-xs text-gray-500">
                <div class="flex gap-2">
                    <span class="text-gray-400">Dibuat</span>
                    <span>{{ $pesanan->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</span>
                </div>
                @if($pesanan->confirmed_at)
                    <div class="flex gap-2">
                        <span class="text-gray-400">Dikonfirmasi</span>
                        <span>{{ $pesanan->confirmed_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</span>
                    </div>
                @endif
                @if($pesanan->shipped_at)
                    <div class="flex gap-2">
                        <span class="text-gray-400">Dikirim</span>
                        <span>{{ $pesanan->shipped_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</span>
                    </div>
                @endif
                @if($pesanan->done_at)
                    <div class="flex gap-2">
                        <span class="text-gray-400">Selesai</span>
                        <span>{{ $pesanan->done_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</span>
                    </div>
                @endif
                @if($pesanan->cancelled_at)
                    <div class="flex gap-2 text-red-400">
                        <span>Dibatalkan</span>
                        <span>{{ $pesanan->cancelled_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Modal Batalkan --}}
<div id="modal-batal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50 px-4">
    <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm">
        <h3 class="font-bold text-gray-800 mb-3">Batalkan Pesanan?</h3>
        <form method="POST" action="{{ route('admin.pesanan.batal', $pesanan->id) }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm text-gray-600 mb-1">Alasan (opsional)</label>
                <input type="text" name="alasan" placeholder="Stok habis, dll."
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-red-400">
            </div>
            <div class="flex gap-3">
                <button type="submit"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                    Ya, Batalkan
                </button>
                <button type="button"
                        onclick="document.getElementById('modal-batal').classList.add('hidden')"
                        class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold py-2.5 rounded-xl text-sm transition">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
