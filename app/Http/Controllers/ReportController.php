<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf; // Facade PDF
use Maatwebsite\Excel\Facades\Excel; // Facade Excel

use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
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

    public function exportExcel(Request $request)
{
    ini_set('memory_limit', '-1');
    ini_set('max_execution_time', 300);
    
    $type = $request->query('type', 'daily');
    $data = $this->getReportData($type, $request->query('start'), $request->query('end'));
    $data['date'] = Carbon::now()->format('d M Y H:i');

    return Excel::download(new class($data) 
        implements FromView, WithStyles, ShouldAutoSize, WithEvents 
    {   
        private $data;

        public function __construct($data) { 
            $this->data = $data; 
        }

        public function view(): \Illuminate\Contracts\View\View {
            return view('sales', $this->data);
        }

        public function styles(Worksheet $sheet)
        {
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $fullRange = 'A1:' . $highestColumn . $highestRow;

            return [
                // STYLE UMUM
                $fullRange => [
                    'font' => [
                        'name' => 'Calibri',
                        'size' => 11,
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ]
                ],

                // HEADER BARIS 1
                5 => [
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['argb' => 'FFFFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF374151'], // abu gelap
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]
            ];
        }

        public function registerEvents(): array
        {
            return [
                AfterSheet::class => function(AfterSheet $event) {
                    $sheet = $event->sheet->getDelegate();
                    $highestRow = $sheet->getHighestRow();
                    $highestColumn = $sheet->getHighestColumn();

                    $sheet = $event->sheet->getDelegate();

                    // Tentukan kolom terakhir, misal sampai D
                    $highestColumn = $sheet->getHighestColumn();

                    // --- MERGE & CENTER UNTUK TITLE SECTION ---
                    for ($i = 1; $i <= 4; $i++) {
                        $sheet->mergeCells("A{$i}:{$highestColumn}{$i}");
                        $sheet->getStyle("A{$i}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("A{$i}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                        $sheet->getRowDimension($i)->setRowHeight(20);
                    }

                    // Sales Report dibuat lebih menonjol
                    $sheet->getStyle("A1")->getFont()->setBold(true)->setSize(14);
                    $sheet->getStyle("A1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                    // Baris 2–4 tetap rata tengah, font normal
                    $sheet->getStyle("A2:A4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);


                    /** BORDER */
                    $sheet->getStyle("A1:{$highestColumn}{$highestRow}")
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);

                    /** ROW ZEBRA */
                    for ($i = 2; $i <= $highestRow; $i++) {
                        if ($i % 2 == 0) { // baris genap
                            $sheet->getStyle("A{$i}:{$highestColumn}{$i}")
                                ->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFF3F4F6'); // abu very light
                        }
                    }

                    /** HEADER HEIGHT */
                    $sheet->getRowDimension(1)->setRowHeight(22);

                    /** FREEZE HEADER */
                    $sheet->freezePane('A2');

                    /** FORMAT ANGKA (opsional) */
                    // Contoh: kolom "Total", misalnya di kolom D
                    // $sheet->getStyle("D2:D{$highestRow}")
                    //     ->getNumberFormat()
                    //     ->setFormatCode('#,##0');
                }
            ];
        }

    }, 'report-'.$type.'.xlsx');
}

}