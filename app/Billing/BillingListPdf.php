<?php

namespace App\Billing;

use App\Support\Quarter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;

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

        return Pdf::loadView('pdf.billing-list', [
            'quarter' => $quarter,
            'groups' => $groups,
            'totalRegular' => $lines->where('code', BillingCode::Regular)->count(),
            'totalAnnual' => $lines->where('code', BillingCode::Annual)->count(),
            'generatedAt' => CarbonImmutable::now(),
        ])
            ->setPaper('a4')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->output();
    }

    public function filename(Quarter $quarter): string
    {
        return "therapie-aktiv-abrechnung-{$quarter->year}-Q{$quarter->number}.pdf";
    }
}
