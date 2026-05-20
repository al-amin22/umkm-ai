@extends('admin.layout')
@section('title', 'Dashboard')

@section('content')

<div class="mb-6">
    <h1 class="text-xl font-bold text-gray-800">Dashboard</h1>
    <p class="text-sm text-gray-500 mt-0.5">{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
</div>

{{-- KPI Cards --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow-sm p-4">
        <p class="text-xs text-gray-400 font-medium">Omzet Bulan Ini</p>
        <p class="text-xl font-bold text-green-600 mt-1">Rp {{ number_format($metrik['omzet'], 0, ',', '.') }}</p>
        @if($metrik['trend_pct'] !== null)
            <p class="text-xs mt-1 {{ $metrik['trend_pct'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                {{ $metrik['trend_pct'] >= 0 ? '↑ +' : '↓ ' }}{{ $metrik['trend_pct'] }}% vs sebelumnya
            </p>
        @endif
    </div>
    <div class="bg-white rounded-2xl shadow-sm p-4">
        <p class="text-xs text-gray-400 font-medium">Pesanan Pending</p>
        <p class="text-xl font-bold text-orange-500 mt-1">{{ $pesananPending }}</p>
        <a href="{{ route('admin.pesanan.index', ['status' => 'pending']) }}"
           class="text-xs text-green-600 mt-1 block">Lihat →</a>
    </div>
    <div class="bg-white rounded-2xl shadow-sm p-4">
        <p class="text-xs text-gray-400 font-medium">Perlu Dikirim</p>
        <p class="text-xl font-bold text-blue-500 mt-1">{{ $pesananKonfirmasi }}</p>
        <a href="{{ route('admin.pesanan.index', ['status' => 'confirmed']) }}"
           class="text-xs text-green-600 mt-1 block">Lihat →</a>
    </div>
    <div class="bg-white rounded-2xl shadow-sm p-4">
        <p class="text-xs text-gray-400 font-medium">Produk Aktif</p>
        <p class="text-xl font-bold text-gray-800 mt-1">{{ $totalProdukAktif }}</p>
        <a href="{{ route('admin.produk.index') }}"
           class="text-xs text-green-600 mt-1 block">Kelola →</a>
    </div>
</div>

{{-- Trend + Recent Orders --}}
<div class="grid md:grid-cols-2 gap-6">

    {{-- Trend 4 Minggu --}}
    <div class="bg-white rounded-2xl shadow-sm p-5">
        <h2 class="font-semibold text-gray-700 text-sm mb-4">📅 Trend 4 Minggu</h2>
        @php $maxOmzet = max(array_column($trendMingguan, 'omzet')) ?: 1; @endphp
        <div class="flex gap-3 items-end h-24 mb-2">
            @foreach($trendMingguan as $i => $minggu)
                @php $h = max(8, round(($minggu['omzet'] / $maxOmzet) * 100)); @endphp
                <div class="flex-1 flex flex-col items-center gap-1">
                    <span class="text-xs text-gray-400">
                        {{ number_format($minggu['omzet']/1000, 0) }}k
                    </span>
                    <div class="w-full rounded-t {{ $i === count($trendMingguan)-1 ? 'bg-green-500' : 'bg-green-200' }}"
                         style="height:{{ $h }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="flex gap-3">
            @foreach($trendMingguan as $m)
                <div class="flex-1 text-center text-xs text-gray-400 truncate">{{ $m['label'] }}</div>
            @endforeach
        </div>
    </div>

    {{-- Pesanan Terbaru --}}
    <div class="bg-white rounded-2xl shadow-sm p-5">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-semibold text-gray-700 text-sm">📦 Pesanan Terbaru</h2>
            <a href="{{ route('admin.pesanan.index') }}" class="text-xs text-green-600">Lihat semua →</a>
        </div>
        <div class="space-y-3">
            @forelse($recentOrders as $o)
                @php
                    $badge = match($o->status) {
                        'pending'   => 'bg-orange-100 text-orange-700',
                        'confirmed' => 'bg-blue-100 text-blue-700',
                        'shipped'   => 'bg-purple-100 text-purple-700',
                        'done'      => 'bg-green-100 text-green-700',
                        'cancelled' => 'bg-red-100 text-red-700',
                        default     => 'bg-gray-100 text-gray-700',
                    };
                @endphp
                <a href="{{ route('admin.pesanan.show', $o->id) }}"
                   class="flex items-center justify-between text-sm hover:bg-gray-50 rounded-lg px-1 py-1">
                    <div>
                        <span class="font-medium text-gray-800">
                            {{ $o->nomor_pesanan ?? '#'.$o->id }}
                        </span>
                        <span class="text-gray-400 ml-1 text-xs">· {{ $o->buyer_name }}</span>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $badge }}">{{ $o->status }}</span>
                </a>
            @empty
                <p class="text-sm text-gray-400">Belum ada pesanan.</p>
            @endforelse
        </div>
    </div>
</div>

@endsection
