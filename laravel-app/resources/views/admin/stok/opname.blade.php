@extends('admin.layout')
@section('title', 'Stock Opname')

@section('content')

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-800">Stock Opname</h1>
        <p class="text-sm text-gray-400 mt-0.5">Masukkan jumlah fisik stok untuk setiap produk</p>
    </div>
</div>

<form method="POST" action="{{ route('admin.stok.opname.simpan') }}">
    @csrf

    <div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-700 text-sm">Daftar Produk Aktif</h2>
        </div>

        @if($produk->isEmpty())
            <div class="px-5 py-12 text-center text-gray-400 text-sm">
                Tidak ada produk aktif.
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left text-xs font-semibold text-gray-500 px-5 py-3 uppercase tracking-wide">Produk</th>
                        <th class="text-right text-xs font-semibold text-gray-500 px-5 py-3 uppercase tracking-wide">Stok Sistem</th>
                        <th class="text-xs font-semibold text-gray-500 px-5 py-3 uppercase tracking-wide w-40">Stok Fisik</th>
                        <th class="text-xs font-semibold text-gray-500 px-5 py-3 uppercase tracking-wide w-28">Riwayat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($produk as $p)
                        @php $stokSistem = $p->stock?->jumlah_sekarang ?? 0; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-800">{{ $p->nama_produk }}</p>
                                @if($p->kategori)
                                    <p class="text-xs text-gray-400">{{ $p->kategori }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <span class="font-semibold
                                    {{ $stokSistem <= ($p->stock?->batas_minimum ?? 5) ? 'text-red-600' : 'text-gray-700' }}">
                                    {{ $stokSistem }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <input type="number"
                                       name="stok[{{ $p->id }}]"
                                       value="{{ $stokSistem }}"
                                       min="0"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-center
                                              focus:outline-none focus:ring-2 focus:ring-green-500">
                            </td>
                            <td class="px-5 py-3 text-center">
                                <a href="{{ route('admin.stok.riwayat', $p->id) }}"
                                   class="text-xs text-green-600 hover:text-green-700 font-medium">
                                    Lihat →
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($produk->isNotEmpty())
        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white font-semibold
                           px-6 py-2.5 rounded-xl text-sm transition">
                💾 Simpan Hasil Opname
            </button>
            <p class="text-xs text-gray-400">
                Perbedaan stok fisik vs sistem akan dicatat sebagai koreksi otomatis.
            </p>
        </div>
    @endif
</form>

@endsection
