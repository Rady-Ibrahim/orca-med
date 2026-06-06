<?php

namespace App\Services;

use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class ArabicTextShaper
{
    /**
     * Render a Blade view as RTL Arabic PDF and return a download response.
     */
    public function downloadPdfView(string $view, array $data, string $filename): Response
    {
        $html = view($view, $data)->render();
        $output = $this->renderHtml($html);

        return new Response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => strlen($output),
        ]);
    }

    /**
     * Render raw HTML to PDF bytes using mPDF (native Arabic/RTL support).
     */
    public function renderHtml(string $html): string
    {
        $tempDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'tempDir' => $tempDir,
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
        ]);

        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->SetDirectionality('rtl');

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
