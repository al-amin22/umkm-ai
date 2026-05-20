@extends('admin.layout')
@section('title', 'Laporan')

@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-800">📈 Laporan Penjualan</h1>
</div>

{{-- Filter --}}
<form method="GET" action="{{ route('admin.laporan.index') }}"
      class="bg-white rounded-2xl shadow-sm p-5 mb-6">
    <div class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Dari Tanggal</label>
            <input type="date" name="dari" value="{{ $dari }}"
                   class="border border-gray-300 rounded-xl px-3 py-2 text-sm
                          focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Sampai Tanggal</label>
            <input type="date" name="sampai" value="{{ $sampai }}"
                   class="border border-gray-300 rounded-xl px-3 py-2 text-sm
                          focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>
        <button type="submit"
                class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-5 py-2 rounded-xl transition">
            Tampilkan
        </button>
        <a href="{{ route('admin.laporan.csv', ['dari' => $dari, 'sampai' => $sampai]) }}"
           class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-2 rounded-xl transition flex items-center gap-1">
            ⬇️ CSV
        </a>
        <a href="{{ route('admin.laporan.cetak', ['dari' => $dari, 'sampai' => $sampai]) }}" target="_blank"
           class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-2 rounded-xl transition flex items-center gap-1">
            🖨️ Cetak/PDF
        </a>
    </div>
    <p class="text-xs text-gray-400 mt-3">
        Periode: <strong>{{ \Carbon\Carbon::parse($dari)->locale('id')->isoFormat('D MMMM Y') }}</strong>
        — <strong>{{ \Carbon\Carbon::parse($sampai)->locale('id')->isoFormat('D MMMM Y') }}</strong>
    </p>
</form>

{{-- KPI Cards --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1">Total Omzet</p>
        <p class="text-xl font-bold text-green-600">Rp {{ number_format($metrik['omzet'], 0, ',', '.') }}</p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1">Total Pesanan</p>
        <p class="text-xl font-bold text-gray-800">{{ $metrik['total_pesanan'] }}</p>
        <p class="text-xs text-gray-400 mt-0.5">Selesai: {{ $metrik['pesanan_done'] }} · Batal: {{ $metrik['pesanan_cancelled'] }}</p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1">Konversi</p>
        <p class="text-xl font-bold text-gray-800">{{ $metrik['konversi_pct'] }}%</p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1">Rata-rata Order</p>
        <p class="text-xl font-bold text-gray-800">Rp {{ number_format($metrik['avg_order_value'], 0, ',', '.') }}</p>
    </div>
</div>

<div class="grid md:grid-cols-3 gap-6 mb-6">
    {{-- Produk Terlaris --}}
    <div class="bg-white rounded-2xl shadow-sm p-5">
        <h2 class="font-semibold text-gray-700 mb-3 text-sm">🏆 Produk Terlaris</h2>
        @if(empty($metrik['produk_terlaris']))
            <p class="text-xs text-gray-400">Belum ada data.</p>
        @else
            @php $maxTerjual = max(array_column($metrik['produk_terlaris'], 'total_terjual')); @endphp
            <div class="space-y-3">
                @foreach($metrik['produk_terlaris'] as $i => $p)
                    @php
                        $medals = ['🥇','🥈','🥉','4️⃣','5️⃣'];
                        $pct    = $maxTerjual > 0 ? round($p['total_terjual'] / $maxTerjual * 100) : 0;
                    @endphp
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-700">{{ $medals[$i] ?? '•' }} {{ $p['nama'] }}</span>
                            <span class="text-gray-500">{{ $p['total_terjual'] }}x</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                            <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Distribusi Status --}}
    <div class="bg-white rounded-2xl shadow-sm p-5 md:col-span-2">
        <h2 class="font-semibold text-gray-700 mb-3 text-sm">📊 Distribusi Status Pesanan</h2>
        @php
            $total = $metrik['total_pesanan'];
            $statuses = [
                'done'      => ['label' => 'Selesai',    'color' => 'bg-green-500',  'count' => $metrik['pesanan_done']],
                'cancelled' => ['label' => 'Dibatalkan', 'color' => 'bg-red-400',    'count' => $metrik['pesanan_cancelled']],
                'other'     => ['label' => 'Lainnya',    'color' => 'bg-gray-300',   'count' => max(0, $total - $metrik['pesanan_done'] - $metrik['pesanan_cancelled'])],
            ];
        @endphp
        <div class="space-y-3">
            @foreach($statuses as $s)
                @php $pct = $total > 0 ? round($s['count'] / $total * 100, 1) : 0; @endphp
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">{{ $s['label'] }}</span>
                        <span class="text-gray-500">{{ $s['count'] }} ({{ $pct }}%)</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div class="{{ $s['color'] }} h-2 rounded-full transition-all" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Order List --}}
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-700 text-sm">📋 Daftar Pesanan</h2>
        <span class="text-xs text-gray-400">{{ $pesanan->total() }} pesanan</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-left text-xs text-gray-500 uppercase tracking-wide">
                    <th class="px-5 py-3">No. Pesanan</th>
                    <th class="px-5 py-3">Tanggal</th>
                    <th class="px-5 py-3">Pembeli</th>
                    <th class="px-5 py-3">Item</th>
                    <th class="px-5 py-3">Total</th>
                    <th class="px-5 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($pesanan as $o)
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
                            <a href="{{ route('admin.pesanan.show', $o->id) }}" class="hover:text-green-600">
                                {{ $o->nomor_pesanan ?? '#'.$o->id }}
                            </a>
                        </td>
                        <td class="px-5 py-3 text-gray-400 text-xs">
                            {{ $o->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}
                        </td>
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-700">{{ $o->buyer_name }}</p>
                            <p class="text-xs text-gray-400">{{ $o->buyer_phone }}</p>
                        </td>
                        <td class="px-5 py-3 text-gray-500 text-xs max-w-xs truncate">
                            {{ $o->items->map(fn($i) => $i->quantity.'x '.($i->product?->nama_produk ?? '?'))->implode(', ') }}
                        </td>
                        <td class="px-5 py-3 font-semibold text-gray-800">
                            Rp {{ number_format($o->total_harga, 0, ',', '.') }}
                        </td>
                        <td class="px-5 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                {{ ucfirst($o->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                            Tidak ada pesanan dalam periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($pesanan->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">
            {{ $pesanan->links() }}
        </div>
    @endif
</div>

@endsection
