<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $shop->nama_toko) — UMKM AI</title>
    <meta name="description" content="@yield('description', $shop->deskripsi ?? $shop->nama_toko)">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --color-primary: {{ $theme?->warna_utama ?? '#16a34a' }};
            --color-secondary: {{ $theme?->warna_sekunder ?? '#15803d' }};
        }
        .btn-primary { background-color: var(--color-primary); }
        .btn-primary:hover { background-color: var(--color-secondary); }
        .text-primary { color: var(--color-primary); }
        .border-primary { border-color: var(--color-primary); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    {{-- Header --}}
    <header class="bg-white shadow-sm sticky top-0 z-10">
        <div class="max-w-2xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ route('storefront.toko', $shop->slug) }}" class="flex items-center gap-2">
                @if($shop->logo_url)
                    <img src="{{ $shop->logo_url }}" alt="{{ $shop->nama_toko }}" class="h-8 w-8 rounded-full object-cover">
                @endif
                <span class="font-bold text-gray-800 text-lg">{{ $shop->nama_toko }}</span>
            </a>
            <a href="{{ route('storefront.order', $shop->slug) }}"
               class="btn-primary text-white text-sm px-4 py-2 rounded-full font-medium transition">
                Pesan Sekarang
            </a>
        </div>
    </header>

    {{-- Content --}}
    <main class="max-w-2xl mx-auto px-4 py-6">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="text-center text-gray-400 text-xs py-6 mt-8">
        Toko ini dikelola dengan <span class="text-primary font-medium">UMKM AI</span>
    </footer>

</body>
</html>
