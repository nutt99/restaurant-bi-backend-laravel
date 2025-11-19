<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // GET /api/dashboard/daily
    public function daily(Request $request)
    {
        $date = $request->query('date') ? Carbon::parse($request->query('date')) : Carbon::now();
        
        $total = Transaction::whereDate('date', $date->toDateString())
            ->sum('total');

        return response()->json(['success' => true, 'total' => (int)$total]);
    }

    // GET /api/dashboard/weekly
    public function weekly()
    {
        $now = Carbon::now();
        
        $total = Transaction::whereBetween('date', [
            $now->startOfWeek(), 
            $now->endOfWeek()
        ])->sum('total');

        return response()->json(['success' => true, 'total' => (int)$total]);
    }

    // GET /api/dashboard/monthly
    public function monthly()
    {
        $now = Carbon::now();

        $total = Transaction::whereMonth('date', $now->month)
            ->whereYear('date', $now->year)
            ->sum('total');

        return response()->json(['success' => true, 'total' => (int)$total]);
    }

    // GET /api/dashboard/summary
    public function summary()
    {
        $totalRevenue = Transaction::sum('total');
        $totalTransactions = Transaction::count();
        
        $avgOrder = $totalTransactions > 0 
            ? floor($totalRevenue / $totalTransactions) 
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'totalRevenue' => (int)$totalRevenue,
                'totalTransactions' => $totalTransactions,
                'averageOrderValue' => $avgOrder
            ]
        ]);
    }

    // GET /api/dashboard/top-menu
    public function topMenu()
    {
        $data = TransactionItem::select('menu_id', DB::raw('SUM(quantity) as totalSold'))
            ->with('menu:id,name')
            ->groupBy('menu_id')
            ->orderByDesc('totalSold')
            ->take(5)
            ->get()
            ->map(function($item) {
                return [
                    'menuId' => $item->menu_id,
                    'name' => $item->menu ? $item->menu->name : 'Unknown',
                    'totalSold' => (int)$item->totalSold
                ];
            });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // GET /api/dashboard/trend?days=7
    public function trend(Request $request)
    {
        $days = $request->query('days', 7);
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

        $data = Transaction::select(
                DB::raw('DATE(date) as date_only'), 
                DB::raw('SUM(total) as total')
            )
            ->where('date', '>=', $startDate)
            ->groupBy(DB::raw('DATE(date)'))
            ->orderBy('date_only', 'asc')
            ->get()
            ->map(function($item){
                return [
                    'date' => $item->date_only,
                    'total' => (int)$item->total
                ];
            });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // GET /api/dashboard/growth
    public function growth()
    {
        $now = Carbon::now();

        $thisWeek = Transaction::whereBetween('date', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->sum('total');
        $lastWeek = Transaction::whereBetween('date', [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()])->sum('total');

        // Hitung Bulan Ini vs Bulan Lalu
        $thisMonth = Transaction::whereMonth('date', $now->month)->whereYear('date', $now->year)->sum('total');
        $lastMonth = Transaction::whereMonth('date', $now->copy()->subMonth()->month)->whereYear('date', $now->copy()->subMonth()->year)->sum('total');

        return response()->json([
            'success' => true,
            'data' => [
                'weeklyGrowth' => $this->calcGrowth($thisWeek, $lastWeek),
                'monthlyGrowth' => $this->calcGrowth($thisMonth, $lastMonth)
            ]
        ]);
    }

    // GET /api/dashboard/best-sales-day
    public function bestSalesDay()
    {
        $now = Carbon::now();

        // Cari hari dengan omzet tertinggi di minggu ini
        $bestDay = Transaction::select(
                DB::raw('DATE(date) as date'), 
                DB::raw('SUM(total) as total')
            )
            ->whereBetween('date', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])
            ->groupBy('date')
            ->orderByDesc('total')
            ->first();

        if (!$bestDay) {
            return response()->json(['success' => true, 'data' => ['bestDay' => null, 'revenue' => 0]]);
        }

        // Konversi tanggal ke Nama Hari (Sunday, Monday, dst)
        $dayName = Carbon::parse($bestDay->date)->format('l');

        return response()->json([
            'success' => true, 
            'data' => [
                'bestDay' => $dayName,
                'revenue' => (int)$bestDay->total
            ]
        ]);
    }

    // Helper function (private)
    private function calcGrowth($current, $previous)
    {
        if (!$previous || $previous == 0) return 0;
        return round((($current - $previous) / $previous) * 100, 2);
    }
}