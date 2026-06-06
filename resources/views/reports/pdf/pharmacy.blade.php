<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            font-family: dejavusans, sans-serif;
        }

        body {
            font-family: dejavusans, sans-serif;
            line-height: 1.6;
            color: #333;
            direction: rtl;
            text-align: right;
        }

        .header {
            text-align: right;
            margin-bottom: 30px;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 15px;
        }

        .header h1 {
            margin: 0;
            color: #0066cc;
            font-size: 24px;
        }

        .header p {
            margin: 5px 0;
            color: #666;
            font-size: 12px;
        }

        .pharmacy-info {
            background: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .pharmacy-info table {
            width: 100%;
        }

        .pharmacy-info td {
            padding: 8px 5px;
            border-bottom: 1px solid #ddd;
            text-align: right;
            font-size: 11px;
        }

        .pharmacy-info td:first-child {
            font-weight: bold;
            color: #0066cc;
            width: 30%;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            background: #0066cc;
            color: white;
            padding: 10px 15px;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: bold;
            text-align: right;
        }

        .stats {
            margin-bottom: 20px;
            overflow: hidden;
        }

        .stat-box {
            display: inline-block;
            background: #f9f9f9;
            padding: 12px;
            border-radius: 5px;
            border-right: 3px solid #0066cc;
            width: 23%;
            margin-right: 1%;
            margin-bottom: 10px;
            vertical-align: top;
            text-align: right;
            font-size: 11px;
        }

        .stat-label {
            font-size: 11px;
            color: #666;
        }

        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #0066cc;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            background: #e8f0ff;
            color: #0066cc;
            padding: 10px 5px;
            text-align: right;
            font-weight: bold;
            border: 1px solid #ddd;
            font-size: 12px;
        }

        td {
            padding: 10px 5px;
            border: 1px solid #ddd;
            text-align: right;
            font-size: 11px;
        }

        tr:nth-child(even) {
            background: #f9f9f9;
        }

        .total-row {
            background: #e8f0ff;
            font-weight: bold;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #0066cc;
            text-align: center;
            font-size: 11px;
            color: #666;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>تقرير الصيدلية</h1>
        <p>Pharmacy Report</p>
    </div>

    <div class="pharmacy-info">
        <table>
            <tr>
                <td>اسم الصيدلية:</td>
                <td>{{ $pharmacy->name }}</td>
                <td>المحافظة:</td>
                <td>{{ $pharmacy->province?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td>المورد:</td>
                <td>{{ $pharmacy->supplier?->name ?? '—' }}</td>
                <td>رقم الترخيص:</td>
                <td>{{ $pharmacy->license_number ?? '—' }}</td>
            </tr>
            <tr>
                <td>الهاتف:</td>
                <td>{{ $pharmacy->phone ?? '—' }}</td>
                <td>العنوان:</td>
                <td>{{ $pharmacy->address ?? '—' }}</td>
            </tr>
            <tr>
                <td>المستودع:</td>
                <td>{{ $pharmacy->warehouse?->name ?? '—' }}</td>
                <td>تاريخ التقرير:</td>
                <td>{{ now()->format('Y-m-d H:i') }}</td>
            </tr>
        </table>
    </div>

    @if ($sales_stats)
        <div class="section">
            <div class="section-title">ملخص المبيعات</div>
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-label">إجمالي المعاملات</div>
                    <div class="stat-value">{{ $sales_stats->total_transactions ?? 0 }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">إجمالي الكمية</div>
                    <div class="stat-value">{{ number_format($sales_stats->total_quantity ?? 0) }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">إجمالي الإيرادات</div>
                    <div class="stat-value">{{ number_format($sales_stats->total_revenue ?? 0, 2) }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">مدة النشاط</div>
                    <div class="stat-value">
                        @if ($sales_stats->first_sale && $sales_stats->last_sale)
                            {{ \Carbon\Carbon::parse($sales_stats->first_sale)->format('Y-m-d') }}
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($products_sold->count() > 0)
        <div class="section">
            <div class="section-title">أفضل 15 منتج مبيعاً</div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>اسم المنتج</th>
                        <th>الكود</th>
                        <th>عدد المعاملات</th>
                        <th>إجمالي الكمية</th>
                        <th>إجمالي الإيرادات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products_sold->take(15) as $index => $product)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->code }}</td>
                            <td>{{ $product->transaction_count }}</td>
                            <td>{{ number_format($product->total_quantity) }}</td>
                            <td>{{ number_format($product->total_revenue, 2) }}</td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($sales_by_supplier->count() > 0)
        <div class="section">
            <div class="section-title">المبيعات حسب المورد</div>
            <table>
                <thead>
                    <tr>
                        <th>المورد</th>
                        <th>عدد المعاملات</th>
                        <th>إجمالي الكمية</th>
                        <th>إجمالي الإيرادات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales_by_supplier as $supplier)
                        <tr>
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->transaction_count }}</td>
                            <td>{{ number_format($supplier->total_quantity) }}</td>
                            <td>{{ number_format($supplier->total_revenue, 2) }}</td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($sales_by_province->count() > 0)
        <div class="section">
            <div class="section-title">المبيعات حسب المحافظة</div>
            <table>
                <thead>
                    <tr>
                        <th>المحافظة</th>
                        <th>عدد المعاملات</th>
                        <th>إجمالي الكمية</th>
                        <th>إجمالي الإيرادات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales_by_province as $province)
                        <tr>
                            <td>{{ $province->name }}</td>
                            <td>{{ $province->transaction_count }}</td>
                            <td>{{ number_format($province->total_quantity) }}</td>
                            <td>{{ number_format($province->total_revenue, 2) }}</td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($sales_trend->count() > 0)
        <div class="section">
            <div class="section-title">اتجاه المبيعات (آخر 30 يوم)</div>
            <table>
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>عدد المعاملات</th>
                        <th>إجمالي الكمية</th>
                        <th>إجمالي الإيرادات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales_trend as $trend)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($trend->date)->format('Y-m-d') }}</td>
                            <td>{{ $trend->transaction_count }}</td>
                            <td>{{ number_format($trend->total_quantity) }}</td>
                            <td>{{ number_format($trend->total_revenue, 2) }}</td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">
        <p>تم إنشاء هذا التقرير بواسطة نظام إدارة المستودعات والصيدليات</p>
        <p>{{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
</body>

</html>
