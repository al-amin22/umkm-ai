@extends('storefront.layout')

@section('title', 'Buat Pesanan — ' . $shop->nama_toko)

@section('content')

<h1 class="text-xl font-bold text-gray-800 mb-4">Buat Pesanan</h1>

@if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('storefront.submitOrder', $shop->slug) }}" method="POST" id="orderForm">
    @csrf

    {{-- Pilih Produk --}}
    <div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
        <h2 class="font-bold text-gray-700 mb-3">Pilih Produk</h2>
        <div class="space-y-3" id="itemsContainer">
            <div class="item-row flex gap-2 items-center">
                <select name="items[0][product_id]" required
                    class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-primary">
                    <option value="">-- Pilih produk --</option>
                    @foreach($produk as $p)
                        <option value="{{ $p->id }}"
                            data-harga="{{ $p->harga }}"
                            {{ request('produk') == $p->id ? 'selected' : '' }}>
                            {{ $p->nama_produk }} — Rp {{ number_format($p->harga, 0, ',', '.') }}
                        </option>
                    @endforeach
                </select>
                <input type="number" name="items[0][quantity]" value="1" min="1" max="99" required
                    class="w-16 border border-gray-200 rounded-xl px-2 py-2 text-sm text-center focus:outline-none focus:border-primary">
            </div>
        </div>
        <button type="button" onclick="tambahItem()"
            class="mt-3 text-sm text-primary font-medium flex items-center gap-1">
            + Tambah produk lain
        </button>
    </div>

    {{-- Data Pembeli --}}
    <div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
        <h2 class="font-bold text-gray-700 mb-3">Data Pemesan</h2>
        <div class="space-y-3">
            <div>
                <label class="text-xs text-gray-500 font-medium">Nama Lengkap *</label>
                <input type="text" name="buyer_name" required value="{{ old('buyer_name') }}"
                    placeholder="Nama kamu"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="text-xs text-gray-500 font-medium">Nomor WhatsApp *</label>
                <input type="tel" name="buyer_phone" required value="{{ old('buyer_phone') }}"
                    placeholder="08xxxxxxxxxx"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="text-xs text-gray-500 font-medium">Alamat Lengkap *</label>
                <textarea name="buyer_address" required rows="2"
                    placeholder="Jl. ..."
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-primary resize-none">{{ old('buyer_address') }}</textarea>
            </div>
            <div>
                <label class="text-xs text-gray-500 font-medium">Kota</label>
                <input type="text" name="buyer_city" value="{{ old('buyer_city') }}"
                    placeholder="Jakarta, Bandung, ..."
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="text-xs text-gray-500 font-medium">Catatan (opsional)</label>
                <input type="text" name="catatan" value="{{ old('catatan') }}"
                    placeholder="Warna, varian, dll"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mt-1 focus:outline-none focus:border-primary">
            </div>
        </div>
    </div>

    <button type="submit"
        class="btn-primary text-white font-bold px-6 py-3 rounded-full w-full text-center transition">
        Kirim Pesanan
    </button>
    <p class="text-center text-xs text-gray-400 mt-2">Pemilik toko akan mengkonfirmasi pesananmu via WhatsApp.</p>
</form>

<script>
let itemCount = 1;
const produkOptions = @json($produk->map(fn($p) => ['id' => $p->id, 'nama' => $p->nama_produk, 'harga' => $p->harga]));

function tambahItem() {
    const container = document.getElementById('itemsContainer');
    const div = document.createElement('div');
    div.className = 'item-row flex gap-2 items-center';
    div.innerHTML = `
        <select name="items[${itemCount}][product_id]" required
            class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-primary">
            <option value="">-- Pilih produk --</option>
            ${produkOptions.map(p => `<option value="${p.id}" data-harga="${p.harga}">${p.nama} — Rp ${p.harga.toLocaleString('id-ID')}</option>`).join('')}
        </select>
        <input type="number" name="items[${itemCount}][quantity]" value="1" min="1" max="99" required
            class="w-16 border border-gray-200 rounded-xl px-2 py-2 text-sm text-center focus:outline-none focus:border-primary">
        <button type="button" onclick="this.parentElement.remove()" class="text-red-400 text-lg font-bold">×</button>
    `;
    container.appendChild(div);
    itemCount++;
}
</script>

@endsection
