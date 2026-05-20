<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopAdmin;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function loginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        // Verifikasi bahwa user ini terhubung ke shop
        $shopAdmin = ShopAdmin::where('user_id', Auth::id())
            ->where('is_active', true)
            ->first();

        if (! $shopAdmin) {
            Auth::logout();
            return back()->withErrors(['email' => 'Akun tidak terhubung ke toko mana pun.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function __construct(private SubscriptionService $subscriptionService) {}

    public function registerForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama'         => 'required|string|max:100',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|min:8|confirmed',
            'nama_toko'    => 'required|string|max:100',
            'wa_number'    => 'required|string|max:20',
            'jenis_produk' => 'required|string|max:100',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'name'     => $validated['nama'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $slug = $this->generateUniqueSlug($validated['nama_toko']);

            $shop = Shop::create([
                'wa_number_owner' => $validated['wa_number'],
                'nama_toko'       => $validated['nama_toko'],
                'slug'            => $slug,
                'jenis_produk'    => $validated['jenis_produk'],
                'nama_owner'      => $validated['nama'],
                'status'          => 'active',
            ]);

            ShopAdmin::create([
                'shop_id'   => $shop->id,
                'user_id'   => $user->id,
                'wa_number' => $validated['wa_number'],
                'role'      => 'owner',
                'is_active' => true,
                'nama'      => $validated['nama'],
                'email'     => $validated['email'],
            ]);

            $this->subscriptionService->aktivasiTrial($shop->id);

            Auth::login($user);
            $request->session()->regenerate();
        });

        return redirect()->route('admin.dashboard')
            ->with('success', 'Selamat datang! Toko kamu sudah siap.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }

    private function generateUniqueSlug(string $namaToko): string
    {
        $base = Str::slug($namaToko);
        $slug = $base;
        $i    = 1;

        while (Shop::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
