<?php

namespace App\Http\Middleware;

use App\Models\ShopAdmin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminShop
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('admin.login');
        }

        $shopAdmin = ShopAdmin::where('user_id', Auth::id())
            ->where('is_active', true)
            ->with('shop')
            ->first();

        if (! $shopAdmin || ! $shopAdmin->shop) {
            Auth::logout();
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Akun tidak terhubung ke toko mana pun.']);
        }

        // Bagikan shop ke semua views dan request
        $request->attributes->set('admin_shop', $shopAdmin->shop);
        $request->attributes->set('shop_admin', $shopAdmin);
        view()->share('adminShop', $shopAdmin->shop);
        view()->share('shopAdmin', $shopAdmin);

        return $next($request);
    }
}
