<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير المنتجات</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .header { text-align: center; margin-bottom: 30px; }
        .filters { margin-bottom: 20px; font-size: 11px; color: #666; }
        .section { margin-top: 30px; }
        .section h2 { background-color: #f5f5f5; padding: 10px; }
        .totals { margin-top: 20px; padding: 15px; background-color: #f9f9f9; }
        .totals p { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>تقرير المنتجات</h1>
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

    <div class="totals">
        <h3>ملخص الإجماليات</h3>
        <p>عدد المبيعات: {{ $totals['sales_count'] ?? 0 }}</p>
        <p>الكمية المباعة: {{ $totals['quantity_sold'] ?? 0 }}</p>
        @if(isset($totals['total_revenue']))
            <p>إجمالي الإيرادات: {{ number_format($totals['total_revenue'], 2) }}</p>
        @endif
    </div>

    <div class="section">
        <h2>أعلى المنتجات مبيعاً</h2>
        <table>
            <thead>
                <tr>
                    <th>الصنف</th>
                    <th>الكود</th>
                    <th>الكمية</th>
                    <th>عدد المبيعات</th>
                    <th>النسبة المئوية</th>
                </tr>
            </thead>
            <tbody>
                @foreach($top_products as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->code ?? '-' }}</td>
                        <td>{{ $product->total_quantity }}</td>
                        <td>{{ $product->sales_count }}</td>
                        <td>{{ $product->percentage }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>أقل المنتجات مبيعاً</h2>
        <table>
            <thead>
                <tr>
                    <th>الصنف</th>
                    <th>الكود</th>
                    <th>الكمية</th>
                    <th>عدد المبيعات</th>
                    <th>النسبة المئوية</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bottom_products as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->code ?? '-' }}</td>
                        <td>{{ $product->total_quantity }}</td>
                        <td>{{ $product->sales_count }}</td>
                        <td>{{ $product->percentage }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>المنتجات حسب الشركة</h2>
        <table>
            <thead>
                <tr>
                    <th>الشركة</th>
                    <th>عدد المنتجات</th>
                    <th>إجمالي المبيعات</th>
                </tr>
            </thead>
            <tbody>
                @foreach($by_company as $company)
                    <tr>
                        <td>{{ $company->company_name }}</td>
                        <td>{{ $company->products_count }}</td>
                        <td>{{ $company->total_sold }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
