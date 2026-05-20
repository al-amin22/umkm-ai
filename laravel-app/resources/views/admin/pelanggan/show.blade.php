@extends('admin.layout')
@section('title', 'Detail Pelanggan')

@section('content')

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.pelanggan.index') }}" class="text-gray-400 hover:text-gray-600">← Kembali</a>
    <h1 class="text-xl font-bold text-gray-800">{{ $pelanggan->nama }}</h1>
    @php
        $segBadge = match($pelanggan->rfm_segment) {
            'Champions' => 'bg-yellow-100 text-yellow-700',
            'Loyal'     => 'bg-green-100 text-green-700',
            'Potensial' => 'bg-teal-100 text-teal-700',
            'Beresiko'  => 'bg-orange-100 text-orange-700',
            'Tidur'     => 'bg-gray-100 text-gray-500',
            'Baru'      => 'bg-blue-100 text-blue-700',
            default     => 'bg-gray-100 text-gray-600',
        };
    @endphp
    <span class="px-3 py-1 rounded-full text-sm font-medium {{ $segBadge }}">
        {{ $pelanggan->rfm_segment ?? 'Baru' }}
    </span>
</div>

<div class="grid md:grid-cols-3 gap-6">

    {{-- Info Pelanggan --}}
    <div class="space-y-4">
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm">👤 Informasi</h2>
            <div class="space-y-2 text-sm text-gray-600">
                <div class="flex gap-2">
                    <span class="text-gray-400 w-20">Nama</span>
                    <span class="font-medium text-gray-800">{{ $pelanggan->nama }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="text-gray-400 w-20">HP</span>
                    <span>{{ $pelanggan->nomor_hp }}</span>
                </div>
                @if($pelanggan->alamat)
                    <div class="flex gap-2">
                        <span class="text-gray-400 w-20">Alamat</span>
                        <span>{{ $pelanggan->alamat }}</span>
                    </div>
                @endif
                @if($pelanggan->kota)
                    <div class="flex gap-2">
                        <span class="text-gray-400 w-20">Kota</span>
                        <span>{{ $pelanggan->kota }}</span>
                    </div>
                @endif
                <div class="flex gap-2">
                    <span class="text-gray-400 w-20">Sejak</span>
                    <span>{{ $pelanggan->created_at->setTimezone('Asia/Jakarta')->format('d M Y') }}</span>
                </div>
            </div>
        </div>

        {{-- RFM Scores --}}
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm">📊 Skor RFM</h2>
            <div class="space-y-3">
                @foreach([
                    ['label' => 'Recency (kebaruan)', 'score' => $pelanggan->rfm_r, 'desc' => 'Seberapa baru terakhir order'],
                    ['label' => 'Frequency (frekuensi)', 'score' => $pelanggan->rfm_f, 'desc' => 'Seberapa sering order'],
                    ['label' => 'Monetary (nilai)', 'score' => $pelanggan->rfm_m, 'desc' => 'Seberapa besar total belanja'],
                ] as $rfm)
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-gray-600">{{ $rfm['label'] }}</span>
                            <span class="font-semibold text-gray-800">{{ $rfm['score'] }}/5</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full"
                                 style="width: {{ $rfm['score'] > 0 ? $rfm['score'] * 20 : 0 }}%"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $rfm['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Stats + Orders --}}
    <div class="md:col-span-2 space-y-4">

        {{-- KPI Cards --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs text-gray-500 mb-1">Total Pesanan</p>
                <p class="text-2xl font-bold text-gray-800">{{ $pelanggan->total_pesanan }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs text-gray-500 mb-1">Total Belanja</p>
                <p class="text-lg font-bold text-green-600">Rp {{ number_format($pelanggan->total_belanja, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm p-4">
                <p class="text-xs text-gray-500 mb-1">Terakhir Order</p>
                <p class="text-sm font-medium text-gray-700">
                    {{ $pelanggan->last_order_at?->setTimezone('Asia/Jakarta')->diffForHumans() ?? 'Belum pernah' }}
                </p>
            </div>
        </div>

        {{-- Riwayat Pesanan --}}
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-700 text-sm">📦 Riwayat Pesanan (10 terakhir)</h2>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-5 py-3">No. Pesanan</th>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3">Total</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pelanggan->orders as $o)
                        @php
                            $badge = match($o->status) {
                                'pending'   => 'bg-orange-100 text-orange-700',
                                'confirmed' => 'bg-blue-100 text-blue-700',
                                'shipped'   => 'bg-purple-100 text-purple-700',
                                'done'      => 'bg-green-100 text-green-700',
                                'cancelled' => 'bg-red-100 text-red-700',
                                default     => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-medium text-gray-800">
                                {{ $o->nomor_pesanan ?? '#'.$o->id }}
                            </td>
                            <td class="px-5 py-3 text-gray-400 text-xs">
                                {{ $o->created_at->setTimezone('Asia/Jakarta')->format('d M Y') }}
                            </td>
                            <td class="px-5 py-3 font-semibold text-gray-800">
                                Rp {{ number_format($o->total_harga, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                    {{ ucfirst($o->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.pesanan.show', $o->id) }}"
                                   class="text-xs text-green-600 hover:text-green-800">
                                    →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-gray-400 text-xs">
                                Belum ada riwayat pesanan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>

@endsection
