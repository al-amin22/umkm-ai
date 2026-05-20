<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan {{ $shop->nama_toko }} — {{ now()->locale('id')->isoFormat('MMMM YYYY') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
<div class="max-w-2xl mx-auto px-4 py-8">

    {{-- Header --}}
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-gray-800">📊 Laporan Keuangan</h1>
        <p class="text-gray-500 text-sm mt-1">{{ $shop->nama_toko }} · {{ now()->locale('id')->isoFormat('MMMM YYYY') }}</p>
        <p class="text-xs text-orange-500 mt-1">Link ini hanya bisa dibuka sekali</p>
    </div>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs text-gray-400 font-medium">Omzet Bulan Ini</p>
            <p class="text-xl font-bold text-green-600 mt-1">Rp {{ number_format($omzetIni, 0, ',', '.') }}</p>
            @if($growth !== null)
                <p class="text-xs mt-1 {{ $growth >= 0 ? 'text-green-500' : 'text-red-500' }}">
                    {{ $growth >= 0 ? '📈 +' : '📉 ' }}{{ $growth }}% vs bulan lalu
                </p>
            @endif
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs text-gray-400 font-medium">Pesanan Selesai</p>
            <p class="text-xl font-bold text-gray-800 mt-1">{{ $ordersIni->count() }}</p>
            <p class="text-xs text-gray-400 mt-1">
                Rata-rata: Rp {{ $ordersIni->count() > 0 ? number_format($omzetIni / $ordersIni->count(), 0, ',', '.') : '—' }}
            </p>
        </div>
    </div>

    {{-- Top Produk --}}
    @if($topProduk->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-6">
            <h2 class="font-bold text-gray-700 mb-3">🏆 Top Produk</h2>
            <div class="space-y-2">
                @foreach($topProduk as $i => $p)
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-gray-400 w-5 text-center">{{ $i + 1 }}</span>
                            <span class="text-gray-700 font-medium">{{ $p['nama'] }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-gray-500">{{ $p['terjual'] }}x</span>
                            <span class="ml-2 text-green-600 font-medium">Rp {{ number_format($p['omzet'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Daftar Pesanan --}}
    @if($ordersIni->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <h2 class="font-bold text-gray-700 mb-3">📦 Pesanan Bulan Ini</h2>
            <div class="space-y-3">
                @foreach($ordersIni as $o)
                    <div class="border-b border-gray-100 pb-3 last:border-0 last:pb-0">
                        <div class="flex justify-between items-start text-sm">
                            <div>
                                <span class="font-medium text-gray-800">#{{ $o->id }} · {{ $o->buyer_name }}</span>
                                <p class="text-gray-400 text-xs mt-0.5">
                                    {{ $o->created_at->setTimezone('Asia/Jakarta')->format('d M H:i') }}
                                </p>
                            </div>
                            <span class="font-bold text-green-600">Rp {{ number_format($o->total_harga, 0, ',', '.') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <p class="text-center text-xs text-gray-400 mt-8">Dihasilkan oleh UMKM AI · {{ now()->format('d M Y H:i') }}</p>
</div>
</body>
</html>
