<?php

namespace App\Services;

use App\Models\Pharmacy;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class PharmacyReportService
{
    /**
     * Get pharmacy report data
     */
    public function getPharmacyReportData(Pharmacy $pharmacy): array
    {
        $pharmacy->load(['supplier', 'province', 'warehouse']);

        // Get sales statistics
        $salesStats = Sale::where('pharmacy_id', $pharmacy->id)
            ->select(
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(quantity * unit_price * (1 - discount / 100)) as total_revenue'),
                DB::raw('MIN(sold_at) as first_sale'),
                DB::raw('MAX(sold_at) as last_sale')
            )
            ->first();

        // Get products sold
        $productsSold = Sale::where('pharmacy_id', $pharmacy->id)
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                'products.code',
                DB::raw('COUNT(sales.id) as transaction_count'),
                DB::raw('SUM(sales.quantity) as total_quantity'),
                DB::raw('SUM(sales.quantity * sales.unit_price * (1 - sales.discount / 100)) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.code')
            ->orderByDesc('total_revenue')
            ->get();

        // Get sales by supplier
        $salesBySupplier = Sale::where('pharmacy_id', $pharmacy->id)
            ->join('suppliers', 'sales.supplier_id', '=', 'suppliers.id')
            ->select(
                'suppliers.id',
                'suppliers.name',
                DB::raw('COUNT(sales.id) as transaction_count'),
                DB::raw('SUM(sales.quantity) as total_quantity'),
                DB::raw('SUM(sales.quantity * sales.unit_price * (1 - sales.discount / 100)) as total_revenue')
            )
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_revenue')
            ->get();

        // Get sales by province
        $salesByProvince = Sale::where('pharmacy_id', $pharmacy->id)
            ->join('provinces', 'sales.province_id', '=', 'provinces.id')
            ->select(
                'provinces.id',
                'provinces.name',
                DB::raw('COUNT(sales.id) as transaction_count'),
                DB::raw('SUM(sales.quantity) as total_quantity'),
                DB::raw('SUM(sales.quantity * sales.unit_price * (1 - sales.discount / 100)) as total_revenue')
            )
            ->groupBy('provinces.id', 'provinces.name')
            ->orderByDesc('total_revenue')
            ->get();

        // Get sales trend (last 30 days)
        $salesTrend = Sale::where('pharmacy_id', $pharmacy->id)
            ->where('sold_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('DATE(sold_at) as date'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(quantity * unit_price * (1 - discount / 100)) as total_revenue')
            )
            ->groupBy(DB::raw('DATE(sold_at)'))
            ->orderBy('date')
            ->get();

        return [
            'pharmacy' => $pharmacy,
            'sales_stats' => $salesStats,
            'products_sold' => $productsSold,
            'sales_by_supplier' => $salesBySupplier,
            'sales_by_province' => $salesByProvince,
            'sales_trend' => $salesTrend,
        ];
    }
}
