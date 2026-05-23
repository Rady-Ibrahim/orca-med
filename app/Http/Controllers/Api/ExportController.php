<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function sales(Request $request, SaleService $saleService): StreamedResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'product_id' => ['nullable', 'exists:products,id'],
            'province_id' => ['nullable', 'exists:provinces,id'],
        ]);

        $filters['per_page'] = 10000;
        $sales = $saleService->list($request->user(), $filters);

        return response()->streamDownload(function () use ($sales) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['التاريخ', 'الصنف', 'الكود', 'الكمية', 'المحافظة', 'الصيدلية']);

            foreach ($sales as $sale) {
                fputcsv($out, [
                    $sale->sold_at->format('Y-m-d'),
                    $sale->product?->name,
                    $sale->product?->code,
                    $sale->quantity,
                    $sale->province?->name,
                    $sale->pharmacy?->name,
                ]);
            }

            fclose($out);
        }, 'sales-report-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
