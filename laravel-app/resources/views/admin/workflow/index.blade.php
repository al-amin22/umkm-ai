@extends('admin.layout')
@section('title', 'Workflow Monitoring')

@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-800">Workflow Monitoring</h1>
</div>

{{-- Status Counts --}}
<div class="grid grid-cols-3 gap-4 mb-6">
    @foreach([
        ['all',     'Semua',   'bg-gray-100 text-gray-700'],
        ['success', 'Sukses',  'bg-green-100 text-green-700'],
        ['failed',  'Gagal',   'bg-red-100 text-red-700'],
    ] as [$key, $label, $class])
        <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}"
           class="bg-white rounded-2xl shadow-sm p-5 text-center hover:shadow-md transition
                  {{ $status === $key ? 'ring-2 ring-green-500' : '' }}">
            <span class="inline-block px-3 py-1 rounded-full text-xs font-medium {{ $class }} mb-2">{{ $label }}</span>
            <p class="text-2xl font-bold text-gray-700">{{ $statusCounts[$key] }}</p>
        </a>
    @endforeach
</div>

{{-- Filter --}}
<form method="GET" action="{{ route('admin.workflow.index') }}" class="flex gap-3 mb-4 flex-wrap">
    <input type="hidden" name="status" value="{{ $status }}">
    <select name="nama"
            class="border border-gray-300 rounded-xl px-3 py-2 text-sm
                   focus:outline-none focus:ring-2 focus:ring-green-500">
        <option value="">Semua Workflow</option>
        @foreach($namaList as $n)
            <option value="{{ $n }}" {{ $nama === $n ? 'selected' : '' }}>{{ $n }}</option>
        @endforeach
    </select>
    <button type="submit"
            class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-xl transition">
        Filter
    </button>
    @if($nama)
        <a href="{{ route('admin.workflow.index', ['status' => $status]) }}"
           class="text-sm text-gray-500 hover:text-gray-700 px-3 py-2">
            Reset
        </a>
    @endif
</form>

{{-- Tabel Log --}}
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    @if($logs->isEmpty())
        <div class="px-5 py-16 text-center text-gray-400 text-sm">
            Belum ada log workflow.
        </div>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left text-xs font-semibold text-gray-500 px-5 py-3 uppercase tracking-wide">Waktu</th>
                    <th class="text-left text-xs font-semibold text-gray-500 px-5 py-3 uppercase tracking-wide">Workflow</th>
                    <th class="text-left text-xs font-semibold text-gray-500 px-5 py-3 uppercase tracking-wide">Status</th>
                    <th class="text-right text-xs font-semibold text-gray-500 px-5 py-3 uppercase tracking-wide">Durasi</th>
                    <th class="text-left text-xs font-semibold text-gray-500 px-5 py-3 uppercase tracking-wide">Pesan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-gray-500 whitespace-nowrap text-xs">
                            {{ $log->dijalankan_at->setTimezone('Asia/Jakarta')->format('d M Y H:i:s') }}
                        </td>
                        <td class="px-5 py-3 font-medium text-gray-800">
                            {{ $log->nama_workflow }}
                        </td>
                        <td class="px-5 py-3">
                            @php
                                $sc = match($log->status) {
                                    'success' => 'bg-green-100 text-green-700',
                                    'failed'  => 'bg-red-100 text-red-700',
                                    default   => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sc }}">
                                {{ ucfirst($log->status) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right text-gray-500 text-xs whitespace-nowrap">
                            @if($log->durasi_ms !== null)
                                {{ number_format($log->durasi_ms / 1000, 2) }}s
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-5 py-3 text-gray-500 text-xs max-w-xs truncate">
                            {{ $log->pesan ?? '—' }}
                        </td>
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
