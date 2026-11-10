<?php

namespace App\Billing;

use App\Support\Quarter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;

/**
 * The printable quarterly billing list, grouped by insurer.
 */
final class BillingListPdf
{
    public function __construct(private QuarterlyBilling $billing) {}

    /**
     * @return string Raw PDF bytes.
     */
    public function render(Quarter $quarter): string
    {
        $groups = $this->billing->linesByInsurer($quarter);
        $lines = $groups->flatten(1);

        $dompdf = Pdf::loadView('pdf.billing-list', [
            'quarter' => $quarter,
            'groups' => $groups,
            'totalRegular' => $lines->where('code', BillingCode::Regular)->count(),
            'totalAnnual' => $lines->where('code', BillingCode::Annual)->count(),
            'generatedAt' => CarbonImmutable::now(),
        ])
            ->setPaper('a4')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->getDomPDF();

        $dompdf->render();
        $this->addPageNumbers($dompdf);

        return (string) $dompdf->output();
    }

    /**
     * DomPDF can't resolve the total page count in CSS, so the footer's page numbers are drawn on the canvas.
     */
    private function addPageNumbers(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $text = 'Seite {PAGE_NUM} von {PAGE_COUNT}';
        $size = 7.5;
        $width = $dompdf->getFontMetrics()->getTextWidth('Seite 99 von 99', $font, $size);

        // A4 is 595.28 × 841.89 pt; the right page margin is 14 mm ≈ 39.7 pt.
        $canvas->page_text($canvas->get_width() - 39.7 - $width, $canvas->get_height() - 32, $text, $font, $size, [0.47, 0.47, 0.47]);
    }

    public function filename(Quarter $quarter): string
    {
        return "therapie-aktiv-abrechnung-{$quarter->year}-Q{$quarter->number}.pdf";
    }
}
