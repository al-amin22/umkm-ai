@extends('admin.layout')
@section('title', 'Pesanan')

@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-800">📦 Pesanan</h1>
</div>

{{-- Status Tabs --}}
<div class="flex gap-2 mb-4 overflow-x-auto pb-1">
    @foreach([
        'all'       => ['label' => 'Semua',      'color' => 'gray'],
        'pending'   => ['label' => 'Pending',     'color' => 'orange'],
        'confirmed' => ['label' => 'Dikonfirmasi','color' => 'blue'],
        'shipped'   => ['label' => 'Dikirim',     'color' => 'purple'],
        'done'      => ['label' => 'Selesai',     'color' => 'green'],
        'cancelled' => ['label' => 'Dibatalkan',  'color' => 'red'],
    ] as $key => $val)
        <a href="{{ route('admin.pesanan.index', ['status' => $key]) }}"
           class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-medium transition
                  {{ $status === $key
                      ? 'bg-green-600 text-white'
                      : 'bg-white text-gray-600 border border-gray-200 hover:border-gray-300' }}">
            {{ $val['label'] }}
            <span class="ml-1 opacity-70">({{ $counts[$key] }})</span>
        </a>
    @endforeach
</div>

{{-- Table --}}
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-left text-xs text-gray-500 uppercase tracking-wide">
                    <th class="px-5 py-3">No. Pesanan</th>
                    <th class="px-5 py-3">Pembeli</th>
                    <th class="px-5 py-3">Item</th>
                    <th class="px-5 py-3">Total</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3">Tanggal</th>
                    <th class="px-5 py-3"></th>
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
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-5 py-3 font-medium text-gray-800">
                            {{ $o->nomor_pesanan ?? '#'.$o->id }}
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
                        <td class="px-5 py-3 text-gray-400 text-xs">
                            {{ $o->created_at->setTimezone('Asia/Jakarta')->format('d M H:i') }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.pesanan.show', $o->id) }}"
                               class="text-xs text-green-600 hover:text-green-800 px-2 py-1 hover:bg-green-50 rounded-lg">
                                Detail →
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                            Tidak ada pesanan dengan status ini.
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
