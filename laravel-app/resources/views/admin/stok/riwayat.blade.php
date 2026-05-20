@extends('admin.layout')
@section('title', 'Riwayat Stok')

@section('content')

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.produk.index') }}" class="text-gray-400 hover:text-gray-600">← Produk</a>
    <h1 class="text-xl font-bold text-gray-800">Riwayat Stok — {{ $produk->nama_produk }}</h1>
</div>

{{-- Ringkasan Stok --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow-sm p-5">
        <p class="text-xs text-gray-400 mb-1">Stok Sekarang</p>
        <p class="text-2xl font-bold {{ ($produk->stock?->jumlah_sekarang ?? 0) <= ($produk->stock?->batas_minimum ?? 5) ? 'text-red-600' : 'text-green-600' }}">
            {{ $produk->stock?->jumlah_sekarang ?? 0 }}
        </p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm p-5">
        <p class="text-xs text-gray-400 mb-1">Batas Minimum</p>
        <p class="text-2xl font-bold text-gray-700">{{ $produk->stock?->batas_minimum ?? 5 }}</p>
    </div>
    <div class="bg-white rounded-2xl shadow-sm p-5">
        <p class="text-xs text-gray-400 mb-1">Status</p>
        <span class="inline-block mt-1 px-3 py-1 rounded-full text-xs font-medium
            {{ $produk->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
            {{ $produk->status === 'active' ? 'Aktif' : 'Nonaktif' }}
        </span>
    </div>
    <div class="bg-white rounded-2xl shadow-sm p-5">
        <p class="text-xs text-gray-400 mb-1">Total Mutasi</p>
        <p class="text-2xl font-bold text-gray-700">{{ $logs->total() }}</p>
    </div>
</div>

{{-- Tabel Log --}}
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-700 text-sm">Riwayat Mutasi Stok</h2>
        <a href="{{ route('admin.stok.opname') }}"
           class="text-sm text-green-600 hover:text-green-700 font-medium">
            + Stock Opname
        </a>
    </div>

    @if($logs->isEmpty())
        <div class="px-5 py-12 text-center text-gray-400 text-sm">
            Belum ada riwayat mutasi stok untuk produk ini.
        </div>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left text-xs font-semibold text-gray-500 px-5 py-3 uppercase tracking-wide">Waktu</th>
                    <th class="text-left text-xs font-semibold text-gray-500 px-5 py-3 uppercase tracking-wide">Tipe</th>
                    <th class="text-right text-xs font-semibold text-gray-500 px-5 py-3 uppercase tracking-wide">Jumlah</th>
                    <th class="text-left text-xs font-semibold text-gray-500 px-5 py-3 uppercase tracking-wide">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-gray-500 whitespace-nowrap">
                            {{ $log->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}
                        </td>
                        <td class="px-5 py-3">
                            @php
                                $tipeClass = match($log->tipe) {
                                    'masuk'   => 'bg-green-100 text-green-700',
                                    'keluar'  => 'bg-red-100 text-red-700',
                                    'koreksi' => 'bg-blue-100 text-blue-700',
                                    default   => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $tipeClass }}">
                                {{ ucfirst($log->tipe) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right font-semibold
                            {{ $log->tipe === 'keluar' ? 'text-red-600' : 'text-green-600' }}">
                            {{ $log->tipe === 'keluar' ? '-' : '+' }}{{ $log->jumlah }}
                        </td>
                        <td class="px-5 py-3 text-gray-500">{{ $log->keterangan ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($logs->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">
                {{ $logs->links() }}
            </div>
        @endif
    @endif
</div>

@endsection
