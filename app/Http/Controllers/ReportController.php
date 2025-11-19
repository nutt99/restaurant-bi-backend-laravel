<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf; // Facade PDF
use Maatwebsite\Excel\Facades\Excel; // Facade Excel

class ReportController extends Controller
{
    private function getReportData($type, $start = null, $end = null)
    {
        $query = Transaction::with(['user', 'items.menu'])->orderBy('date', 'asc');
        $now = Carbon::now();
        $title = "Sales Report";
        $subtitle = "";

        if ($type == 'daily') {
            $query->whereDate('date', $now->toDateString());
            $subtitle = "Daily Report - " . $now->format('d M Y');
        } 
        elseif ($type == 'weekly') {
            $query->whereBetween('date', [
            $now->copy()->startOfWeek(), 
            $now->copy()->endOfWeek()
        ]);
            $subtitle = "Weekly Report";
        } 
        elseif ($type == 'monthly') {
            $query->whereMonth('date', $now->month)->whereYear('date', $now->year);
            $subtitle = "Monthly Report - " . $now->format('F Y');
        } 
        elseif ($type == 'range' && $start && $end) {
            $query->whereBetween('date', [Carbon::parse($start), Carbon::parse($end)]);
            $subtitle = "Range: $start to $end";
        }

        $transactions = $query->get();
        $totalRevenue = $transactions->sum('total');

        return compact('transactions', 'totalRevenue', 'title', 'subtitle');
    }

    public function index(Request $request)
    {
        $type = $request->query('type', 'daily');
        $data = $this->getReportData($type, $request->query('start'), $request->query('end'));
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function exportPdf(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 300);
        $type = $request->query('type', 'daily');
        $data = $this->getReportData($type, $request->query('start'), $request->query('end'));
        
        $data['date'] = Carbon::now()->format('d M Y H:i');

        $pdf = Pdf::loadView('sales', $data);
        
        return $pdf->download('report-'.$type.'.pdf');
    }

    public function exportExcel(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 300);
        $type = $request->query('type', 'daily');
        $data = $this->getReportData($type, $request->query('start'), $request->query('end'));
        $data['date'] = Carbon::now()->format('d M Y H:i');

        return Excel::download(new class($data) implements \Maatwebsite\Excel\Concerns\FromView {
            private $data;
            public function __construct($data) { $this->data = $data; }
            public function view(): \Illuminate\Contracts\View\View {
                return view('sales', $this->data);
            }
        }, 'report-'.$type.'.xlsx');
    }
}