<!DOCTYPE html>
<html>
<head>
    <title>Sales Report</title>
    <style>
        body { font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; font-size: 12px; }
        th { background-color: #f2f2f2; }
        .header { text-align: center; margin-bottom: 20px; }
        .total { font-weight: bold; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $title }}</h2>
        <p>{{ $subtitle }}</p>
        <p>Date: {{ $date }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Cashier</th>
                <th>Items</th>
                <th style="text-align: right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $t)
            <tr>
                <td>{{ \Carbon\Carbon::parse($t->date)->format('d M Y H:i') }}</td>
                <td>{{ $t->user->name ?? 'Unknown' }}</td>
                <td>
                    <ul style="margin: 0; padding-left: 15px;">
                        @foreach($t->items as $item)
                            <li>{{ $item->menu->name ?? '-' }} (x{{ $item->quantity }})</li>
                        @endforeach
                    </ul>
                </td>
                <td style="text-align: right">Rp {{ number_format($t->total, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="total">TOTAL REVENUE</td>
                <td class="total">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>