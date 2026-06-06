<?php

namespace App\Console\Commands;

use App\Services\ArabicTextShaper;
use Illuminate\Console\Command;

class TestArabicPdf extends Command
{
    protected $signature = 'pdf:test-arabic';

    protected $description = 'Generate sample Arabic PDFs to verify mPDF rendering';

    public function handle(ArabicTextShaper $shaper): int
    {
        $ts = now()->format('Y-m-d-H-i-s');

        $products = $shaper->downloadPdfView('reports.pdf.products', [
            'top_products' => collect([(object) [
                'name' => 'بانادول اكسترا 500مجم',
                'code' => 'C89F7B49',
                'total_quantity' => 66888,
                'sales_count' => 174,
                'percentage' => 57662.07,
            ]]),
            'bottom_products' => collect([]),
            'by_company' => collect([]),
            'totals' => ['sales_count' => 2605, 'quantity_sold' => 434828, 'total_revenue' => 14911911.08],
            'filters' => ['from' => '2026-06-04', 'to' => null],
        ], "products-test-{$ts}.pdf");

        $pharmacy = $shaper->downloadPdfView('reports.pdf.pharmacy', [
            'pharmacy' => (object) [
                'name' => 'الصيدلية المركزية',
                'license_number' => '1',
                'phone' => '0123456789',
                'address' => 'شارع رمسيس',
                'province' => (object) ['name' => 'القاهرة'],
                'supplier' => (object) ['name' => 'المورد ( بين مزارع السيت )'],
                'warehouse' => (object) ['name' => 'المستودع الرئيسي'],
            ],
            'sales_stats' => (object) [
                'total_transactions' => 17,
                'total_quantity' => 758,
                'total_revenue' => 26714.61,
                'first_sale' => '2026-05-01',
                'last_sale' => '2026-06-01',
            ],
            'products_sold' => collect([(object) [
                'name' => 'سيريتات 400مجم/5مل شراب 120 مل',
                'code' => '5636CE0',
                'transaction_count' => 174,
                'total_quantity' => 66888,
                'total_revenue' => 57662.07,
            ]]),
            'sales_by_supplier' => collect([]),
            'sales_by_province' => collect([]),
            'sales_trend' => collect([]),
        ], "pharmacy-test-{$ts}.pdf");

        $dir = storage_path('app/pdf-tests');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $productsPath = "{$dir}/products-test-{$ts}.pdf";
        $pharmacyPath = "{$dir}/pharmacy-test-{$ts}.pdf";

        file_put_contents($productsPath, $products->getContent());
        file_put_contents($pharmacyPath, $pharmacy->getContent());

        $this->info('PDF engine: mPDF v8');
        $this->info("Products: {$productsPath}");
        $this->info("Pharmacy:  {$pharmacyPath}");
        $this->line('');
        $this->warn('Open these NEW files. Old files in Downloads are from DOMPDF (broken).');

        return self::SUCCESS;
    }
}
