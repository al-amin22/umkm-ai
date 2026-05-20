<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — {{ $adminShop->nama_toko }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { primary: '#16a34a' } } } }</script>
</head>
<body class="bg-gray-100 min-h-screen">

{{-- Sidebar --}}
<div class="flex min-h-screen">
    <aside class="w-56 bg-white shadow-sm flex-shrink-0 hidden md:flex flex-col">
        <div class="px-5 py-4 border-b">
            <p class="font-bold text-gray-800 truncate">{{ $adminShop->nama_toko }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $shopAdmin->role === 'owner' ? '👑 Owner' : '👤 Helper' }}</p>
        </div>
        <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
            <a href="{{ route('admin.dashboard') }}"
               class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('admin.dashboard') ? 'bg-green-50 text-green-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                📊 Dashboard
            </a>
            <a href="{{ route('admin.pesanan.index') }}"
               class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('admin.pesanan*') ? 'bg-green-50 text-green-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                📦 Pesanan
            </a>
            <a href="{{ route('admin.produk.index') }}"
               class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('admin.produk*') ? 'bg-green-50 text-green-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                🛍️ Produk
            </a>
            <a href="{{ route('admin.pelanggan.index') }}"
               class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('admin.pelanggan*') ? 'bg-green-50 text-green-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                👥 Pelanggan
            </a>
            <a href="{{ route('admin.laporan.index') }}"
               class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('admin.laporan*') ? 'bg-green-50 text-green-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                📈 Laporan
            </a>
            <a href="{{ route('admin.langganan.index') }}"
               class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('admin.langganan*') ? 'bg-green-50 text-green-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                💳 Langganan
            </a>
            <a href="{{ route('admin.toko.edit') }}"
               class="flex items-center gap-2 px-3 py-2 rounded-lg {{ request()->routeIs('admin.toko*') ? 'bg-green-50 text-green-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                ⚙️ Pengaturan
            </a>
            <a href="{{ route('storefront.toko', $adminShop->slug) }}" target="_blank"
               class="flex items-center gap-2 px-3 py-2 rounded-lg text-gray-600 hover:bg-gray-50">
                🌐 Lihat Toko ↗
            </a>
        </nav>
        <div class="px-3 py-3 border-t">
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit"
                        class="w-full text-left px-3 py-2 text-sm text-red-500 hover:bg-red-50 rounded-lg">
                    🚪 Keluar
                </button>
            </form>
        </div>
    </aside>

    {{-- Main Content --}}
    <div class="flex-1 flex flex-col min-w-0">
        {{-- Top bar (mobile) --}}
        <header class="bg-white shadow-sm px-4 py-3 flex items-center justify-between md:hidden">
            <span class="font-bold text-gray-800">{{ $adminShop->nama_toko }}</span>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.pesanan.index') }}" class="text-gray-600">📦</a>
                <a href="{{ route('admin.produk.index') }}" class="text-gray-600">🛍️</a>
                <form method="POST" action="{{ route('admin.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-red-500">🚪</button>
                </form>
            </div>
        </header>

        <main class="flex-1 px-4 md:px-8 py-6">
            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl">
                    {{ session('success') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $err) <li>{{ $err }}</li> @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>

</body>
</html>
