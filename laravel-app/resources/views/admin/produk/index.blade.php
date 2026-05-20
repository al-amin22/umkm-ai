@extends('admin.layout')
@section('title', 'Produk')

@section('content')

<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl font-bold text-gray-800">🛍️ Produk</h1>
    <a href="{{ route('admin.produk.create') }}"
       class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-xl transition">
        + Tambah Produk
    </a>
</div>

{{-- Search --}}
<form method="GET" action="{{ route('admin.produk.index') }}" class="mb-4">
    <div class="flex gap-2">
        <input type="text" name="cari" value="{{ $cari }}" placeholder="Cari nama produk..."
               class="flex-1 border border-gray-300 rounded-xl px-4 py-2 text-sm
                      focus:outline-none focus:ring-2 focus:ring-green-500">
        <button type="submit"
                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-sm transition">
            Cari
        </button>
        @if($cari)
            <a href="{{ route('admin.produk.index') }}"
               class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-sm transition">
                ✕
            </a>
        @endif
    </div>
</form>

{{-- Table --}}
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-left text-xs text-gray-500 uppercase tracking-wide">
                    <th class="px-5 py-3">Produk</th>
                    <th class="px-5 py-3">Harga</th>
                    <th class="px-5 py-3 text-center">Stok</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($produk as $p)
                    @php $stok = $p->stock?->jumlah_sekarang ?? 0; @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                @if($p->foto_url)
                                    <img src="{{ $p->foto_url }}" class="w-10 h-10 rounded-lg object-cover">
                                @else
                                    <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-xl">🛍️</div>
                                @endif
                                <div>
                                    <p class="font-medium text-gray-800">{{ $p->nama_produk }}</p>
                                    @if($p->deskripsi)
                                        <p class="text-xs text-gray-400 truncate max-w-xs">{{ $p->deskripsi }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 font-medium text-gray-800">
                            Rp {{ number_format($p->harga, 0, ',', '.') }}
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($stok === 0)
                                <span class="text-red-500 font-medium">Habis</span>
                            @elseif($p->stock?->isKritis())
                                <span class="text-orange-500 font-medium">{{ $stok }} ⚠️</span>
                            @else
                                <span class="text-gray-700">{{ $stok }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($p->status === 'active')
                                <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">Aktif</span>
                            @else
                                <span class="bg-gray-100 text-gray-500 text-xs px-2 py-0.5 rounded-full">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.stok.riwayat', $p->id) }}"
                                   class="text-xs text-gray-500 hover:text-gray-700 px-2 py-1 hover:bg-gray-100 rounded-lg">
                                    Stok
                                </a>
                                <a href="{{ route('admin.produk.edit', $p->id) }}"
                                   class="text-xs text-blue-600 hover:text-blue-800 px-2 py-1 hover:bg-blue-50 rounded-lg">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route('admin.produk.destroy', $p->id) }}"
                                      onsubmit="return confirm('Nonaktifkan produk ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="text-xs text-red-500 hover:text-red-700 px-2 py-1 hover:bg-red-50 rounded-lg">
                                        Nonaktifkan
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-gray-400">
                            Belum ada produk.
                            <a href="{{ route('admin.produk.create') }}" class="text-green-600 ml-1">Tambah sekarang →</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($produk->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">
            {{ $produk->links() }}
        </div>
    @endif
</div>

@endsection
