<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar — UMKM AI Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4 py-8">

<div class="w-full max-w-lg">

    {{-- Logo --}}
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 bg-green-600 rounded-2xl mb-4">
            <span class="text-2xl">🤖</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-800">Daftar UMKM AI</h1>
        <p class="text-gray-500 text-sm mt-1">Kelola toko online kamu dengan AI via WhatsApp</p>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl mb-4">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $err) <li>{{ $err }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.register') }}"
          class="bg-white rounded-2xl shadow-sm p-6 space-y-5">
        @csrf

        {{-- Akun --}}
        <div class="space-y-1">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Akun Kamu</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Nama Lengkap <span class="text-red-500">*</span>
            </label>
            <input type="text" name="nama" value="{{ old('nama') }}"
                   required placeholder="Budi Santoso"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                          focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Email <span class="text-red-500">*</span>
            </label>
            <input type="email" name="email" value="{{ old('email') }}"
                   required placeholder="kamu@email.com"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                          focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Password <span class="text-red-500">*</span>
                </label>
                <input type="password" name="password" required minlength="8"
                       placeholder="Min. 8 karakter"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Konfirmasi Password <span class="text-red-500">*</span>
                </label>
                <input type="password" name="password_confirmation" required
                       placeholder="Ulangi password"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
        </div>

        <div class="border-t border-gray-100 pt-1">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Info Toko</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Nama Toko <span class="text-red-500">*</span>
            </label>
            <input type="text" name="nama_toko" value="{{ old('nama_toko') }}"
                   required placeholder="Warung Kopi Pak Budi"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                          focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Nomor WhatsApp <span class="text-red-500">*</span>
            </label>
            <input type="text" name="wa_number" value="{{ old('wa_number') }}"
                   required placeholder="628123456789"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                          focus:outline-none focus:ring-2 focus:ring-green-500">
            <p class="text-xs text-gray-400 mt-1">Format internasional: 628xxx (tanpa + dan spasi)</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Jenis Produk <span class="text-red-500">*</span>
            </label>
            <input type="text" name="jenis_produk" value="{{ old('jenis_produk') }}"
                   required placeholder="contoh: Makanan & Minuman, Fashion, Elektronik"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                          focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>

        <button type="submit"
                class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold
                       py-3 rounded-xl text-sm transition">
            Daftar & Buat Toko
        </button>

    </form>

    <p class="text-center text-sm text-gray-500 mt-4">
        Sudah punya akun?
        <a href="{{ route('admin.login') }}" class="text-green-600 font-medium hover:underline">Masuk</a>
    </p>

</div>

</body>
</html>
