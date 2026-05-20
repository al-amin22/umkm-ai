<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UMKM AI Platform — Kelola Toko via WhatsApp</title>
    <meta name="description" content="Platform AI untuk UMKM Indonesia. Kelola produk, pesanan, dan pelanggan langsung dari WhatsApp dengan bantuan AI.">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: '#16a34a', primaryDark: '#15803d' }
                }
            }
        }
    </script>
</head>
<body class="bg-white text-gray-800">

{{-- Navbar --}}
<nav class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur border-b border-gray-100">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center text-white text-sm">🤖</div>
            <span class="font-bold text-gray-800">UMKM AI</span>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.login') }}"
               class="text-sm text-gray-600 hover:text-gray-900 font-medium">Masuk</a>
            <a href="{{ route('admin.register') }}"
               class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-xl transition">
                Daftar Gratis
            </a>
        </div>
    </div>
</nav>

{{-- Hero --}}
<section class="pt-24 pb-16 md:pt-32 md:pb-24 bg-gradient-to-br from-green-50 to-white">
    <div class="max-w-6xl mx-auto px-4 text-center">
        <div class="inline-flex items-center gap-2 bg-green-100 text-green-700 text-xs font-semibold px-3 py-1.5 rounded-full mb-6">
            🚀 Sekarang tersedia untuk UMKM Indonesia
        </div>
        <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 leading-tight mb-6">
            Kelola Toko Online<br>
            <span class="text-green-600">Langsung dari WhatsApp</span>
        </h1>
        <p class="text-lg text-gray-500 max-w-2xl mx-auto mb-8">
            AI asisten bisnis yang membantu kamu mengelola produk, pesanan, stok, dan pelanggan —
            hanya lewat chat WhatsApp. Tidak perlu aplikasi tambahan.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ route('admin.register') }}"
               class="bg-green-600 hover:bg-green-700 text-white font-bold px-8 py-3.5 rounded-2xl text-sm transition shadow-lg shadow-green-200">
                Coba Gratis 14 Hari →
            </a>
            <a href="{{ route('admin.login') }}"
               class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium px-8 py-3.5 rounded-2xl text-sm transition">
                Sudah punya akun? Masuk
            </a>
        </div>
        <p class="text-xs text-gray-400 mt-4">Tanpa kartu kredit · Setup dalam 5 menit</p>
    </div>
</section>

{{-- Features --}}
<section class="py-16 md:py-20 max-w-6xl mx-auto px-4">
    <div class="text-center mb-12">
        <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3">Semua yang kamu butuhkan</h2>
        <p class="text-gray-500">Satu platform, semua fitur toko online lewat WhatsApp.</p>
    </div>
    <div class="grid md:grid-cols-3 gap-6">
        @foreach([
            ['icon' => '🛍️', 'title' => 'Manajemen Produk', 'desc' => 'Tambah, edit, dan hapus produk dengan chat. AI bantu deskripsi dan harga otomatis.'],
            ['icon' => '📦', 'title' => 'Kelola Pesanan', 'desc' => 'Notifikasi pesanan masuk, konfirmasi, kirim, hingga selesai — semua dari WA.'],
            ['icon' => '📊', 'title' => 'Stok Real-time', 'desc' => 'Pantau stok kapan saja. Dapat notifikasi otomatis saat stok hampir habis.'],
            ['icon' => '👥', 'title' => 'Data Pelanggan', 'desc' => 'Analisis pelanggan terbaik dengan RFM scoring dan kirim broadcast promosi.'],
            ['icon' => '💰', 'title' => 'Laporan Keuangan', 'desc' => 'Cek omzet, laba, dan HPP hanya dengan satu pesan. Export CSV atau cetak PDF.'],
            ['icon' => '🤖', 'title' => 'AI Business Coach', 'desc' => 'Minta insight bisnis, saran harga, atau konten promosi kapan saja.'],
        ] as $f)
            <div class="bg-gray-50 rounded-2xl p-6">
                <div class="text-3xl mb-3">{{ $f['icon'] }}</div>
                <h3 class="font-bold text-gray-800 mb-2">{{ $f['title'] }}</h3>
                <p class="text-sm text-gray-500">{{ $f['desc'] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- How it Works --}}
<section class="py-16 bg-gray-50">
    <div class="max-w-4xl mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3">Cara Kerja</h2>
            <p class="text-gray-500">Mulai berjualan lebih cerdas dalam 3 langkah.</p>
        </div>
        <div class="grid md:grid-cols-3 gap-8">
            @foreach([
                ['step' => '1', 'title' => 'Daftar & Buat Toko', 'desc' => 'Isi nama toko, jenis produk, dan nomor WhatsApp kamu. Selesai dalam 2 menit.'],
                ['step' => '2', 'title' => 'Sambungkan WhatsApp', 'desc' => 'Scan QR code untuk menghubungkan nomor WA toko ke sistem AI kami.'],
                ['step' => '3', 'title' => 'Kelola via Chat', 'desc' => 'Chat ke AI assistanmu di WA. Kirim perintah, terima laporan, semua otomatis.'],
            ] as $s)
                <div class="text-center">
                    <div class="w-12 h-12 bg-green-600 text-white font-bold text-lg rounded-2xl flex items-center justify-center mx-auto mb-4">
                        {{ $s['step'] }}
                    </div>
                    <h3 class="font-bold text-gray-800 mb-2">{{ $s['title'] }}</h3>
                    <p class="text-sm text-gray-500">{{ $s['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Pricing --}}
<section class="py-16 md:py-20 max-w-5xl mx-auto px-4">
    <div class="text-center mb-12">
        <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3">Harga Terjangkau</h2>
        <p class="text-gray-500">Mulai gratis, upgrade kapan siap.</p>
    </div>
    <div class="grid md:grid-cols-3 gap-6">
        @foreach([
            [
                'name'  => 'Trial',
                'price' => 'Gratis',
                'sub'   => '14 hari',
                'color' => 'border-gray-200',
                'btn'   => 'border border-gray-300 text-gray-700 hover:bg-gray-50',
                'features' => ['Manajemen produk & stok','Kelola pesanan','Storefront toko','AI assistant dasar'],
            ],
            [
                'name'  => 'Starter',
                'price' => 'Rp 49.000',
                'sub'   => '/bulan',
                'color' => 'border-green-500 shadow-lg shadow-green-100',
                'btn'   => 'bg-green-600 text-white hover:bg-green-700',
                'badge' => 'Populer',
                'features' => ['Semua fitur Trial','Laporan keuangan','Konten AI (caption dll)','Data pelanggan'],
            ],
            [
                'name'  => 'Growth',
                'price' => 'Rp 399.000',
                'sub'   => '/tahun (hemat 32%)',
                'color' => 'border-gray-200',
                'btn'   => 'border border-gray-300 text-gray-700 hover:bg-gray-50',
                'features' => ['Semua fitur Starter','Analitik RFM + AI insight','Broadcast promosi','Trend mingguan'],
            ],
        ] as $plan)
            <div class="relative border-2 {{ $plan['color'] }} rounded-2xl p-6">
                @if(isset($plan['badge']))
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-green-600 text-white text-xs font-bold px-3 py-1 rounded-full">
                        {{ $plan['badge'] }}
                    </div>
                @endif
                <h3 class="font-bold text-gray-800 text-lg">{{ $plan['name'] }}</h3>
                <div class="mt-2 mb-4">
                    <span class="text-2xl font-extrabold text-gray-900">{{ $plan['price'] }}</span>
                    <span class="text-sm text-gray-400 ml-1">{{ $plan['sub'] }}</span>
                </div>
                <ul class="space-y-2 mb-6">
                    @foreach($plan['features'] as $f)
                        <li class="flex items-center gap-2 text-sm text-gray-600">
                            <span class="text-green-500">✓</span> {{ $f }}
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('admin.register') }}"
                   class="block text-center py-2.5 rounded-xl text-sm font-semibold transition {{ $plan['btn'] }}">
                    Mulai Sekarang
                </a>
            </div>
        @endforeach
    </div>
</section>

{{-- CTA --}}
<section class="py-16 bg-green-600">
    <div class="max-w-3xl mx-auto px-4 text-center">
        <h2 class="text-2xl md:text-3xl font-bold text-white mb-4">
            Siap tingkatkan penjualan UMKM kamu?
        </h2>
        <p class="text-green-100 mb-8">Bergabung dengan ribuan pemilik UMKM yang sudah pakai UMKM AI.</p>
        <a href="{{ route('admin.register') }}"
           class="inline-block bg-white text-green-700 font-bold px-8 py-3.5 rounded-2xl text-sm hover:bg-green-50 transition shadow-lg">
            Daftar Gratis Sekarang →
        </a>
    </div>
</section>

{{-- Footer --}}
<footer class="bg-gray-900 text-gray-400 py-8">
    <div class="max-w-6xl mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 bg-green-600 rounded-lg flex items-center justify-center text-white text-xs">🤖</div>
            <span class="font-bold text-white text-sm">UMKM AI Platform</span>
        </div>
        <p class="text-xs">© {{ date('Y') }} UMKM AI Platform. Dibuat untuk UMKM Indonesia.</p>
        <div class="flex gap-4 text-xs">
            <a href="{{ route('admin.login') }}" class="hover:text-white">Masuk</a>
            <a href="{{ route('admin.register') }}" class="hover:text-white">Daftar</a>
        </div>
    </div>
</footer>

</body>
</html>
