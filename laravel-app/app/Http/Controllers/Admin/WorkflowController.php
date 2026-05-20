<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkflowLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkflowController extends Controller
{
    public function index(Request $request): View
    {
        $shop   = $request->attributes->get('admin_shop');
        $status = $request->query('status', 'all');
        $nama   = $request->query('nama');

        $query = WorkflowLog::where(function ($q) use ($shop) {
            $q->where('shop_id', $shop->id)->orWhereNull('shop_id');
        })->orderByDesc('dijalankan_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($nama) {
            $query->where('nama_workflow', $nama);
        }

        $logs = $query->paginate(30)->withQueryString();

        $statusCounts = [
            'all'     => WorkflowLog::where(fn ($q) => $q->where('shop_id', $shop->id)->orWhereNull('shop_id'))->count(),
            'success' => WorkflowLog::where(fn ($q) => $q->where('shop_id', $shop->id)->orWhereNull('shop_id'))->where('status', 'success')->count(),
            'failed'  => WorkflowLog::where(fn ($q) => $q->where('shop_id', $shop->id)->orWhereNull('shop_id'))->where('status', 'failed')->count(),
        ];

        $namaList = WorkflowLog::where(fn ($q) => $q->where('shop_id', $shop->id)->orWhereNull('shop_id'))
            ->distinct()
            ->pluck('nama_workflow')
            ->sort()
            ->values();

        return view('admin.workflow.index', compact('logs', 'status', 'nama', 'statusCounts', 'namaList'));
    }
}
