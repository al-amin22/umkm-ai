@extends('admin.layout')
@section('title', 'Pelanggan')

@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-800">👥 Pelanggan</h1>
</div>

{{-- Search --}}
<form method="GET" action="{{ route('admin.pelanggan.index') }}" class="mb-4">
    <input type="hidden" name="segment" value="{{ $segment }}">
    <div class="flex gap-2">
        <input type="text" name="q" value="{{ $search }}"
               placeholder="Cari nama atau nomor HP..."
               class="flex-1 max-w-sm border border-gray-300 rounded-xl px-4 py-2 text-sm
                      focus:outline-none focus:ring-2 focus:ring-green-500">
        <button type="submit"
                class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-xl transition">
            Cari
        </button>
        @if($search)
            <a href="{{ route('admin.pelanggan.index', ['segment' => $segment]) }}"
               class="border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm px-4 py-2 rounded-xl transition">
                Reset
            </a>
        @endif
    </div>
</form>

{{-- Segment Tabs --}}
<div class="flex gap-2 mb-4 overflow-x-auto pb-1">
    @php
        $segmentColors = [
            'all'       => ['label' => 'Semua'],
            'Champions' => ['label' => '🏆 Champions'],
            'Loyal'     => ['label' => '💚 Loyal'],
            'Potensial' => ['label' => '🌱 Potensial'],
            'Baru'      => ['label' => '🆕 Baru'],
            'Beresiko'  => ['label' => '⚠️ Beresiko'],
            'Tidur'     => ['label' => '😴 Tidur'],
        ];
    @endphp
    @foreach($segmentColors as $key => $val)
        <a href="{{ route('admin.pelanggan.index', ['segment' => $key, 'q' => $search]) }}"
           class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-medium transition
                  {{ $segment === $key
                      ? 'bg-green-600 text-white'
                      : 'bg-white text-gray-600 border border-gray-200 hover:border-gray-300' }}">
            {{ $val['label'] }}
            <span class="ml-1 opacity-70">({{ $segmentCounts[$key] }})</span>
        </a>
    @endforeach
</div>

{{-- Table --}}
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-left text-xs text-gray-500 uppercase tracking-wide">
                    <th class="px-5 py-3">Pelanggan</th>
                    <th class="px-5 py-3">Kota</th>
                    <th class="px-5 py-3 text-center">Segmen</th>
                    <th class="px-5 py-3">Total Pesanan</th>
                    <th class="px-5 py-3">Total Belanja</th>
                    <th class="px-5 py-3">Terakhir Order</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($pelanggan as $p)
                    @php
                        $segBadge = match($p->rfm_segment) {
                            'Champions' => 'bg-yellow-100 text-yellow-700',
                            'Loyal'     => 'bg-green-100 text-green-700',
                            'Potensial' => 'bg-teal-100 text-teal-700',
                            'Beresiko'  => 'bg-orange-100 text-orange-700',
                            'Tidur'     => 'bg-gray-100 text-gray-500',
                            'Baru'      => 'bg-blue-100 text-blue-700',
                            default     => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-800">{{ $p->nama }}</p>
                            <p class="text-xs text-gray-400">{{ $p->nomor_hp }}</p>
                        </td>
                        <td class="px-5 py-3 text-gray-500 text-xs">{{ $p->kota ?? '-' }}</td>
                        <td class="px-5 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $segBadge }}">
                                {{ $p->rfm_segment ?? 'Baru' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-700">{{ $p->total_pesanan }}x</td>
                        <td class="px-5 py-3 font-medium text-gray-800">
                            Rp {{ number_format($p->total_belanja, 0, ',', '.') }}
                        </td>
                        <td class="px-5 py-3 text-gray-400 text-xs">
                            {{ $p->last_order_at?->setTimezone('Asia/Jakarta')->diffForHumans() ?? '-' }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.pelanggan.show', $p->id) }}"
                               class="text-xs text-green-600 hover:text-green-800 px-2 py-1 hover:bg-green-50 rounded-lg">
                                Detail →
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                            Belum ada pelanggan dalam segmen ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($pelanggan->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">
            {{ $pelanggan->links() }}
        </div>
    @endif
</div>

@endsection
