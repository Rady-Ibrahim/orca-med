<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير المبيعات</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .header { text-align: center; margin-bottom: 30px; }
        .filters { margin-bottom: 20px; font-size: 11px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>تقرير المبيعات</h1>
        <p>تاريخ التقرير: {{ now()->format('Y-m-d') }}</p>
    </div>

    <div class="filters">
        @if($filters['from'])
            <p>من: {{ $filters['from'] }}</p>
        @endif
        @if($filters['to'])
            <p>إلى: {{ $filters['to'] }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>التاريخ</th>
                <th>الصنف</th>
                <th>الكود</th>
                <th>الكمية</th>
                <th>السعر</th>
                <th>الخصم</th>
                <th>الإجمالي</th>
                <th>المحافظة</th>
                <th>الصيدلية</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sales as $sale)
                <tr>
                    <td>{{ $sale->sold_at?->format('Y-m-d') ?? '-' }}</td>
                    <td>{{ $sale->product?->name ?? '-' }}</td>
                    <td>{{ $sale->product?->code ?? '-' }}</td>
                    <td>{{ $sale->quantity }}</td>
                    <td>{{ number_format($sale->unit_price, 2) }}</td>
                    <td>{{ $sale->discount }}%</td>
                    <td>{{ number_format($sale->quantity * $sale->unit_price * (1 - $sale->discount / 100), 2) }}</td>
                    <td>{{ $sale->province?->name ?? '-' }}</td>
                    <td>{{ $sale->pharmacy?->name ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
