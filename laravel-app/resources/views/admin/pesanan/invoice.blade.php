<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $pesanan->nomor_pesanan ?? '#'.$pesanan->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
    </style>
</head>
<body class="bg-gray-100 p-6">

{{-- Toolbar --}}
<div class="no-print mb-4 flex gap-3 max-w-2xl mx-auto">
    <a href="{{ route('admin.pesanan.show', $pesanan->id) }}"
       class="text-sm text-gray-500 hover:text-gray-700">← Kembali ke Detail</a>
    <button onclick="window.print()"
            class="ml-auto bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-5 py-2 rounded-xl transition">
        🖨️ Cetak / Simpan PDF
    </button>
</div>

{{-- Invoice Card --}}
<div class="bg-white max-w-2xl mx-auto rounded-2xl shadow-sm p-8 print:shadow-none print:rounded-none print:p-0">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ $shop->nama_toko }}</h1>
            @if($shop->deskripsi)
                <p class="text-sm text-gray-400 mt-0.5">{{ $shop->deskripsi }}</p>
            @endif
            @if($shop->no_rekening)
                <p class="text-xs text-gray-400 mt-1">Rekening: {{ $shop->no_rekening }}</p>
            @endif
        </div>
        <div class="text-right">
            <p class="text-lg font-bold text-green-600">INVOICE</p>
            <p class="text-sm text-gray-700 font-medium mt-1">
                {{ $pesanan->nomor_pesanan ?? 'INV-'.$pesanan->id }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">
                {{ $pesanan->created_at->setTimezone('Asia/Jakarta')->format('d M Y') }}
            </p>
            @php
                $badgeClass = match($pesanan->status) {
                    'done'      => 'bg-green-100 text-green-700',
                    'cancelled' => 'bg-red-100 text-red-700',
                    'shipped'   => 'bg-purple-100 text-purple-700',
                    'confirmed' => 'bg-blue-100 text-blue-700',
                    default     => 'bg-orange-100 text-orange-700',
                };
            @endphp
            <span class="inline-block mt-2 px-3 py-1 rounded-full text-xs font-medium {{ $badgeClass }}">
                {{ ucfirst($pesanan->status) }}
            </span>
        </div>
    </div>

    {{-- Billing Info --}}
    <div class="grid grid-cols-2 gap-6 mb-8 text-sm">
        <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Kepada</p>
            <p class="font-semibold text-gray-800">{{ $pesanan->buyer_name }}</p>
            <p class="text-gray-500">{{ $pesanan->buyer_phone }}</p>
            <p class="text-gray-500">{{ $pesanan->buyer_address }}
                @if($pesanan->buyer_city), {{ $pesanan->buyer_city }}@endif
            </p>
        </div>
        @if($pesanan->resi)
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Pengiriman</p>
                <p class="text-gray-500">No. Resi: <span class="font-medium text-gray-800">{{ $pesanan->resi }}</span></p>
            </div>
        @endif
    </div>

    {{-- Items Table --}}
    <table class="w-full text-sm mb-6">
        <thead>
            <tr class="border-b-2 border-gray-200">
                <th class="text-left text-gray-500 font-semibold py-2">Produk</th>
                <th class="text-center text-gray-500 font-semibold py-2 w-16">Qty</th>
                <th class="text-right text-gray-500 font-semibold py-2 w-32">Harga</th>
                <th class="text-right text-gray-500 font-semibold py-2 w-32">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pesanan->items as $item)
                <tr class="border-b border-gray-100">
                    <td class="py-3 text-gray-800">
                        {{ $item->product?->nama_produk ?? 'Produk dihapus' }}
                    </td>
                    <td class="py-3 text-center text-gray-600">{{ $item->quantity }}</td>
                    <td class="py-3 text-right text-gray-600">
                        Rp {{ number_format($item->harga_satuan, 0, ',', '.') }}
                    </td>
                    <td class="py-3 text-right font-medium text-gray-800">
                        Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-gray-200">
                <td colspan="3" class="pt-3 text-right font-bold text-gray-700">Total</td>
                <td class="pt-3 text-right font-bold text-green-600 text-lg">
                    Rp {{ number_format($pesanan->total_harga, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Catatan --}}
    @if($pesanan->catatan)
        <div class="bg-yellow-50 rounded-xl px-4 py-3 text-sm text-yellow-800 mb-6">
            <span class="font-medium">Catatan:</span> {{ $pesanan->catatan }}
        </div>
    @endif

    {{-- Footer --}}
    <div class="border-t border-gray-100 pt-5 text-center text-xs text-gray-400">
        <p>Terima kasih telah berbelanja di <strong>{{ $shop->nama_toko }}</strong></p>
        @if($shop->jam_operasional)
            <p class="mt-1">Jam operasional: {{ $shop->jam_operasional }}</p>
        @endif
    </div>
</div>

</body>
</html>
