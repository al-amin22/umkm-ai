<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PelangganController extends Controller
{
    public function index(Request $request): View
    {
        $shop    = $request->attributes->get('admin_shop');
        $search  = $request->query('q');
        $segment = $request->query('segment', 'all');

        $query = Customer::byShop($shop->id)
            ->orderByDesc('total_belanja');

        if ($search) {
            $query->search($search);
        }

        if ($segment !== 'all') {
            $query->bySegment($segment);
        }

        $pelanggan = $query->paginate(20)->withQueryString();

        $segmentCounts = [
            'all'        => Customer::byShop($shop->id)->count(),
            'Champions'  => Customer::byShop($shop->id)->bySegment('Champions')->count(),
            'Loyal'      => Customer::byShop($shop->id)->bySegment('Loyal')->count(),
            'Potensial'  => Customer::byShop($shop->id)->bySegment('Potensial')->count(),
            'Beresiko'   => Customer::byShop($shop->id)->bySegment('Beresiko')->count(),
            'Tidur'      => Customer::byShop($shop->id)->bySegment('Tidur')->count(),
            'Baru'       => Customer::byShop($shop->id)->bySegment('Baru')->count(),
        ];

        return view('admin.pelanggan.index', compact('pelanggan', 'segmentCounts', 'segment', 'search'));
    }

    public function show(Request $request, int $id): View
    {
        $shop     = $request->attributes->get('admin_shop');
        $pelanggan = Customer::byShop($shop->id)
            ->with(['orders' => fn ($q) => $q->latest()->limit(10)])
            ->findOrFail($id);

        return view('admin.pelanggan.show', compact('pelanggan'));
    }
}
