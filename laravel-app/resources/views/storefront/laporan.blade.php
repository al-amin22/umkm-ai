<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan {{ $shop->nama_toko }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .bar-wrap { display:flex; align-items:flex-end; gap:4px; height:60px; }
        .bar      { flex:1; background:#22c55e; border-radius:4px 4px 0 0; min-height:4px; transition:height .3s; }
        .bar.dim  { background:#d1fae5; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
<div class="max-w-2xl mx-auto px-4 py-8 pb-16">

    {{-- Header --}}
    <div class="text-center mb-6">
        @if($shop->logo_url)
            <img src="{{ $shop->logo_url }}" alt="{{ $shop->nama_toko }}"
                 class="w-14 h-14 rounded-full object-cover mx-auto mb-2">
        @endif
        <h1 class="text-xl font-bold text-gray-800">📊 Laporan Penjualan</h1>
        <p class="text-gray-500 text-sm mt-1">
            {{ $shop->nama_toko }} · {{ now()->locale('id')->isoFormat('MMMM YYYY') }}
        </p>
        <p class="text-xs text-orange-400 mt-1">⚠️ Link ini hanya bisa dibuka sekali</p>
    </div>

    {{-- Kartu KPI utama --}}
    <div class="grid grid-cols-2 gap-3 mb-4">
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Omzet Bulan Ini</p>
            <p class="text-2xl font-bold text-green-600 mt-1">
                Rp {{ number_format($omzetIni, 0, ',', '.') }}
            </p>
            @if($growth !== null)
                <p class="text-xs mt-1 font-medium {{ $growth >= 0 ? 'text-green-500' : 'text-red-500' }}">
                    {{ $growth >= 0 ? '📈 +' : '📉 ' }}{{ $growth }}% vs bulan lalu
                </p>
            @endif
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Pesanan Selesai</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $ordersIni->count() }}</p>
            <p class="text-xs text-gray-400 mt-1">
                Avg: Rp {{ $ordersIni->count() > 0 ? number_format($omzetIni / $ordersIni->count(), 0, ',', '.') : '—' }}
            </p>
        </div>
    </div>

    {{-- KPI sekunder --}}
    <div class="grid grid-cols-3 gap-3 mb-6">
        <div class="bg-white rounded-2xl shadow-sm p-3 text-center">
            <p class="text-xs text-gray-400">Pelanggan</p>
            <p class="text-lg font-bold text-gray-800 mt-0.5">{{ $totalPelanggan }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-3 text-center">
            <p class="text-xs text-gray-400">Konversi</p>
            <p class="text-lg font-bold text-blue-600 mt-0.5">{{ $konversiPct }}%</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-3 text-center">
            <p class="text-xs text-gray-400">Dibatalkan</p>
            <p class="text-lg font-bold text-red-500 mt-0.5">{{ $pesananCancelled }}</p>
        </div>
    </div>

    {{-- Trend 4 minggu --}}
    @if(!empty($trendMingguan))
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
            <h2 class="font-bold text-gray-700 mb-4 text-sm">📅 Trend 4 Minggu Terakhir</h2>
            @php $maxOmzet = max(array_column($trendMingguan, 'omzet')) ?: 1; @endphp
            <div class="flex gap-2 items-end h-20 mb-2">
                @foreach($trendMingguan as $i => $minggu)
                    @php $heightPct = max(8, round(($minggu['omzet'] / $maxOmzet) * 100)); @endphp
                    <div class="flex-1 flex flex-col items-center gap-1">
                        <span class="text-xs text-gray-400 font-medium">
                            Rp{{ number_format($minggu['omzet']/1000, 0, ',', '.') }}k
                        </span>
                        <div class="w-full rounded-t-md {{ $i === count($trendMingguan)-1 ? 'bg-green-500' : 'bg-green-200' }}"
                             style="height:{{ $heightPct }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="flex gap-2">
                @foreach($trendMingguan as $minggu)
                    <div class="flex-1 text-center text-xs text-gray-400 truncate">{{ $minggu['label'] }}</div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Top Produk --}}
    @if($topProduk->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
            <h2 class="font-bold text-gray-700 mb-3 text-sm">🏆 Top Produk</h2>
            @php $maxTerjual = $topProduk->max('terjual') ?: 1; @endphp
            <div class="space-y-3">
                @foreach($topProduk as $i => $p)
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-700 font-medium">
                                {{ ['🥇','🥈','🥉','4.','5.'][$i] ?? ($i+1).'.') }} {{ $p['nama'] }}
                            </span>
                            <div class="text-right text-xs">
                                <span class="text-gray-500">{{ $p['terjual'] }}x</span>
                                <span class="ml-2 text-green-600 font-semibold">
                                    Rp {{ number_format($p['omzet'], 0, ',', '.') }}
                                </span>
                            </div>
                        </div>
                        <div class="bg-gray-100 rounded-full h-1.5">
                            <div class="bg-green-400 h-1.5 rounded-full"
                                 style="width:{{ round($p['terjual']/$maxTerjual*100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Daftar Pesanan --}}
    @if($ordersIni->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <h2 class="font-bold text-gray-700 mb-3 text-sm">📦 Pesanan Bulan Ini</h2>
            <div class="space-y-3">
                @foreach($ordersIni->take(20) as $o)
                    <div class="border-b border-gray-100 pb-3 last:border-0 last:pb-0">
                        <div class="flex justify-between items-start text-sm">
                            <div>
                                <span class="font-medium text-gray-800">
                                    {{ $o->nomor_pesanan ?? '#'.$o->id }} · {{ $o->buyer_name }}
                                </span>
                                <p class="text-gray-400 text-xs mt-0.5">
                                    {{ $o->created_at->setTimezone('Asia/Jakarta')->format('d M H:i') }}
                                </p>
                            </div>
                            <span class="font-bold text-green-600">
                                Rp {{ number_format($o->total_harga, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @endforeach
                @if($ordersIni->count() > 20)
                    <p class="text-xs text-gray-400 text-center pt-1">
                        ...dan {{ $ordersIni->count() - 20 }} pesanan lainnya
                    </p>
                @endif
            </div>
        </div>
    @endif

    <p class="text-center text-xs text-gray-400 mt-8">
        Dihasilkan oleh UMKM AI · {{ now()->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}
    </p>
</div>
</body>
</html>
