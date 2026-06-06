<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$shaper = app(\App\Services\ArabicTextShaper::class);

$html = $shaper->fixHtml(<<<'HTML'
<!DOCTYPE html>
<html lang="ar" dir="ltr">
<head><meta charset="UTF-8">
<style>body { font-family: 'DejaVu Sans', sans-serif; text-align: right; font-size: 14px; }</style>
</head>
<body>
<h1>تقرير المنتجات</h1>
<p>تاريخ التقرير: 2026-06-04</p>
<p>عدد المبيعات: 2605</p>
<table border="1" cellpadding="5">
<tr><th>الصنف</th><th>الكود</th><th>الكمية</th></tr>
<tr><td>بانادول اكسترا</td><td>C89F7B49</td><td>57662</td></tr>
</table>
</body>
</html>
HTML);

$pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setOption([
    'defaultFont' => 'DejaVu Sans',
    'isFontSubsettingEnabled' => true,
]);

$path = __DIR__ . '/storage/app/arabic-test.pdf';
file_put_contents($path, $pdf->output());
echo "PDF saved to: $path\n";
echo "Size: " . filesize($path) . " bytes\n";
