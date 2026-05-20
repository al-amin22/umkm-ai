@extends('admin.layout')
@section('title', 'Langganan')

@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-800">💳 Langganan</h1>
</div>

<div class="grid md:grid-cols-3 gap-6">

    {{-- Status Langganan Aktif --}}
    <div class="md:col-span-2 space-y-4">

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <h2 class="font-semibold text-gray-700 mb-4 text-sm">Status Langganan Saat Ini</h2>

            @if($sub)
                @php
                    $statusColor = match($sub->status) {
                        'active'  => 'bg-green-100 text-green-700',
                        'grace'   => 'bg-yellow-100 text-yellow-700',
                        'expired' => 'bg-red-100 text-red-700',
                        default   => 'bg-gray-100 text-gray-600',
                    };
                    $statusLabel = match($sub->status) {
                        'active'  => 'Aktif',
                        'grace'   => 'Menunggu Pembayaran',
                        'expired' => 'Kedaluwarsa',
                        default   => ucfirst($sub->status),
                    };
                    $sisaHari = $sub->hariTersisa();
                @endphp
                <div class="flex items-start gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl font-extrabold text-gray-800 capitalize">{{ $sub->plan }}</span>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $statusColor }}">
                                {{ $statusLabel }}
                            </span>
                            @if($sub->isTrial())
                                <span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-medium rounded-full">
                                    🎁 Trial
                                </span>
                            @endif
                        </div>
                        <div class="space-y-1.5 text-sm text-gray-600">
                            <p>
                                <span class="text-gray-400">Mulai:</span>
                                {{ $sub->mulai_at->setTimezone('Asia/Jakarta')->format('d M Y') }}
                            </p>
                            <p>
                                <span class="text-gray-400">Berlaku sampai:</span>
                                {{ $sub->expired_at->setTimezone('Asia/Jakarta')->format('d M Y') }}
                            </p>
                            <p>
                                <span class="text-gray-400">Sisa:</span>
                                @if($sisaHari > 0)
                                    <strong class="{{ $sisaHari <= 7 ? 'text-red-600' : 'text-gray-800' }}">
                                        {{ $sisaHari }} hari
                                    </strong>
                                    @if($sisaHari <= 7)
                                        <span class="text-red-500 text-xs ml-1">⚠️ Segera perpanjang!</span>
                                    @endif
                                @else
                                    <strong class="text-red-600">Sudah kedaluwarsa</strong>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    <p class="text-4xl mb-3">🎁</p>
                    <p class="text-gray-600 font-medium mb-1">Belum ada langganan aktif</p>
                    <p class="text-sm text-gray-400">Pilih paket di bawah untuk mulai.</p>
                </div>
            @endif
        </div>

        {{-- Upgrade Plans --}}
        @if(!$sub || !$sub->isActive() || $sub->hariTersisa() <= 14 || $sub->isTrial())
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <h2 class="font-semibold text-gray-700 mb-4 text-sm">
                    {{ $sub && $sub->isActive() && !$sub->isTrial() ? 'Perpanjang Langganan' : 'Pilih Paket' }}
                </h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach($paketList as $kode => $p)
                        <div class="border-2 {{ $kode === 'growth' ? 'border-green-500' : 'border-gray-200' }} rounded-2xl p-5 relative">
                            @if($kode === 'growth')
                                <div class="absolute -top-3 left-4 bg-green-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                                    Hemat 32%
                                </div>
                            @endif
                            <h3 class="font-bold text-gray-800 text-lg capitalize">{{ $p['nama'] }}</h3>
                            <p class="text-2xl font-extrabold text-gray-900 mt-1">
                                Rp {{ number_format($p['harga'], 0, ',', '.') }}
                            </p>
                            <p class="text-xs text-gray-400 mb-4">
                                untuk {{ $p['hari'] }} hari
                                ({{ $p['hari'] >= 365 ? round($p['hari'] / 30) . ' bulan' : $p['hari'] . ' hari' }})
                            </p>
                            <form method="POST" action="{{ route('admin.langganan.checkout') }}">
                                @csrf
                                <input type="hidden" name="paket" value="{{ $kode }}">
                                <button type="submit"
                                        class="w-full {{ $kode === 'growth' ? 'bg-green-600 hover:bg-green-700 text-white' : 'border border-gray-300 hover:bg-gray-50 text-gray-700' }}
                                               font-semibold py-2.5 rounded-xl text-sm transition">
                                    Pilih {{ $p['nama'] }}
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-3">
                    Pembayaran diproses oleh Midtrans. Mendukung transfer bank, kartu kredit, e-wallet.
                </p>
            </div>
        @endif

    </div>

    {{-- Riwayat Pembayaran --}}
    <div class="space-y-4">
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-700 mb-3 text-sm">🧾 Riwayat Pembayaran</h2>
            @if($riwayat->isEmpty())
                <p class="text-xs text-gray-400 text-center py-4">Belum ada riwayat pembayaran.</p>
            @else
                <div class="space-y-3">
                    @foreach($riwayat as $log)
                        @php
                            $logBadge = match($log->status) {
                                'success' => 'bg-green-100 text-green-700',
                                'failed'  => 'bg-red-100 text-red-700',
                                default   => 'bg-yellow-100 text-yellow-700',
                            };
                        @endphp
                        <div class="border border-gray-100 rounded-xl p-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs font-medium text-gray-700">
                                        Rp {{ number_format($log->amount, 0, ',', '.') }}
                                    </p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        {{ $log->created_at->setTimezone('Asia/Jakarta')->format('d M Y') }}
                                    </p>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $logBadge }}">
                                    {{ ucfirst($log->status) }}
                                </span>
                            </div>
                            @if($log->reference_id)
                                <p class="text-xs text-gray-300 mt-1 font-mono truncate">{{ $log->reference_id }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Fitur per Plan --}}
        <div class="bg-gray-50 rounded-2xl p-5">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Fitur per Paket</p>
            @foreach([
                ['plan' => 'Trial', 'color' => 'text-purple-600', 'features' => ['Produk & stok','Pesanan','Toko online']],
                ['plan' => 'Starter', 'color' => 'text-blue-600', 'features' => ['+ Laporan keuangan','Konten AI','Data pelanggan']],
                ['plan' => 'Growth', 'color' => 'text-green-600', 'features' => ['+ RFM & analitik AI','Broadcast promosi','Trend mingguan']],
            ] as $tier)
                <div class="mb-3">
                    <p class="text-xs font-bold {{ $tier['color'] }} mb-1">{{ $tier['plan'] }}</p>
                    @foreach($tier['features'] as $f)
                        <p class="text-xs text-gray-500 leading-5">✓ {{ $f }}</p>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

</div>

@endsection
