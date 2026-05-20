<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin — UMKM AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
<div class="w-full max-w-sm">
    <div class="text-center mb-8">
        <div class="text-4xl mb-2">🏪</div>
        <h1 class="text-2xl font-bold text-gray-800">UMKM AI</h1>
        <p class="text-gray-500 text-sm mt-1">Dashboard Admin</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-6">
        <form method="POST" action="{{ route('admin.login') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent
                              @error('email') border-red-400 @enderror">
                @error('email')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remember" class="rounded">
                Ingat saya
            </label>

            <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold
                           py-2.5 rounded-xl text-sm transition">
                Masuk
            </button>
        </form>
    </div>

    <p class="text-center text-xs text-gray-400 mt-6">
        Belum punya akses? Hubungi admin melalui WhatsApp.
    </p>
</div>
</body>
</html>
