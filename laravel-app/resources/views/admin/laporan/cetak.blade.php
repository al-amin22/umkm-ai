<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan — {{ $shop->nama_toko }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #1f2937; background: #fff; }
        .container { max-width: 900px; margin: 0 auto; padding: 24px; }

        /* Header */
        .header { border-bottom: 2px solid #16a34a; padding-bottom: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header h1 { font-size: 20px; font-weight: bold; color: #16a34a; }
        .header p { color: #6b7280; font-size: 11px; margin-top: 4px; }
        .header .meta { text-align: right; color: #6b7280; font-size: 11px; }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
        .kpi-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; }
        .kpi-card .label { font-size: 10px; color: #6b7280; margin-bottom: 4px; }
        .kpi-card .value { font-size: 16px; font-weight: bold; color: #111827; }
        .kpi-card .value.green { color: #16a34a; }
        .kpi-card .sub { font-size: 10px; color: #9ca3af; margin-top: 2px; }

        /* Section */
        .section-title { font-size: 12px; font-weight: bold; color: #374151; margin-bottom: 10px; padding-bottom: 4px; border-bottom: 1px solid #e5e7eb; }

        /* Two columns */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; }

        /* Produk list */
        .produk-item { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 11px; }
        .produk-item .bar-wrap { flex: 1; margin: 0 8px; }
        .produk-item .bar-bg { background: #f3f4f6; height: 6px; border-radius: 3px; }
        .produk-item .bar-fill { background: #16a34a; height: 6px; border-radius: 3px; }

        /* Table */
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        thead tr { background: #f9fafb; }
        th { text-align: left; padding: 8px 10px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        td { padding: 7px 10px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        tr:last-child td { border-bottom: none; }

        /* Status badges */
        .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 10px; font-weight: 500; }
        .badge-done      { background: #dcfce7; color: #166534; }
        .badge-pending   { background: #ffedd5; color: #9a3412; }
        .badge-confirmed { background: #dbeafe; color: #1e40af; }
        .badge-shipped   { background: #f3e8ff; color: #6b21a8; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }

        /* Footer */
        .footer { margin-top: 24px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; display: flex; justify-content: space-between; }

        /* Print button */
        .print-btn { position: fixed; top: 16px; right: 16px; background: #16a34a; color: #fff; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 13px; }

        @media print {
            .print-btn { display: none; }
            body { background: white; }
            .container { padding: 0; }
        }
    </style>
</head>
<body>
<button class="print-btn" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>

<div class="container">

    {{-- Header --}}
    <div class="header">
        <div>
            <h1>{{ $shop->nama_toko }}</h1>
            <p>Laporan Penjualan</p>
            <p>Periode: {{ \Carbon\Carbon::parse($dari)->locale('id')->isoFormat('D MMMM Y') }} — {{ \Carbon\Carbon::parse($sampai)->locale('id')->isoFormat('D MMMM Y') }}</p>
        </div>
        <div class="meta">
            <p>Dicetak: {{ now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }}</p>
            @if($shop->nomor_hp)
                <p>WA: {{ $shop->nomor_hp }}</p>
            @endif
        </div>
    </div>

    {{-- KPI --}}
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="label">Total Omzet</div>
            <div class="value green">Rp {{ number_format($metrik['omzet'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card">
            <div class="label">Total Pesanan</div>
            <div class="value">{{ $metrik['total_pesanan'] }}</div>
            <div class="sub">Selesai: {{ $metrik['pesanan_done'] }} · Batal: {{ $metrik['pesanan_cancelled'] }}</div>
        </div>
        <div class="kpi-card">
            <div class="label">Konversi</div>
            <div class="value">{{ $metrik['konversi_pct'] }}%</div>
        </div>
        <div class="kpi-card">
            <div class="label">Rata-rata Order</div>
            <div class="value">Rp {{ number_format($metrik['avg_order_value'], 0, ',', '.') }}</div>
        </div>
    </div>

    {{-- Produk Terlaris --}}
    @if(!empty($metrik['produk_terlaris']))
    <div class="box" style="margin-bottom: 20px;">
        <div class="section-title">🏆 Produk Terlaris</div>
        @php $maxTerjual = max(array_column($metrik['produk_terlaris'], 'total_terjual')); @endphp
        @foreach($metrik['produk_terlaris'] as $i => $p)
            @php
                $medals = ['🥇','🥈','🥉','4.','5.'];
                $pct    = $maxTerjual > 0 ? round($p['total_terjual'] / $maxTerjual * 100) : 0;
            @endphp
            <div class="produk-item">
                <span style="width: 180px;">{{ $medals[$i] ?? '-' }} {{ $p['nama'] }}</span>
                <div class="bar-wrap">
                    <div class="bar-bg">
                        <div class="bar-fill" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                <span style="width: 60px; text-align: right;">{{ $p['total_terjual'] }}x</span>
                <span style="width: 100px; text-align: right;">Rp {{ number_format($p['total_omzet'], 0, ',', '.') }}</span>
            </div>
        @endforeach
    </div>
    @endif

    {{-- Order Table --}}
    <div class="section-title">📋 Daftar Pesanan ({{ $pesanan->count() }} pesanan)</div>
    <table>
        <thead>
            <tr>
                <th>No. Pesanan</th>
                <th>Tanggal</th>
                <th>Pembeli</th>
                <th>Item</th>
                <th style="text-align: right;">Total</th>
                <th style="text-align: center;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pesanan as $o)
                <tr>
                    <td style="font-weight: 500;">{{ $o->nomor_pesanan ?? '#'.$o->id }}</td>
                    <td style="color: #6b7280;">{{ $o->created_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td>
                    <td>
                        <div style="font-weight: 500;">{{ $o->buyer_name }}</div>
                        <div style="color: #9ca3af;">{{ $o->buyer_phone }}</div>
                    </td>
                    <td style="color: #6b7280;">
                        {{ $o->items->map(fn($i) => $i->quantity.'x '.($i->product?->nama_produk ?? '?'))->implode(', ') }}
                    </td>
                    <td style="text-align: right; font-weight: 600;">
                        Rp {{ number_format($o->total_harga, 0, ',', '.') }}
                    </td>
                    <td style="text-align: center;">
                        <span class="badge badge-{{ $o->status }}">{{ ucfirst($o->status) }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #9ca3af; padding: 20px;">
                        Tidak ada pesanan dalam periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Footer --}}
    <div class="footer">
        <span>{{ $shop->nama_toko }} — Laporan Otomatis UMKM AI Platform</span>
        <span>Periode: {{ $dari }} s.d. {{ $sampai }}</span>
    </div>

</div>
</body>
</html>
