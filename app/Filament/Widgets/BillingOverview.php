<?php

namespace App\Filament\Widgets;

use App\Billing\BillingCode;
use App\Billing\BillingLine;
use App\Billing\QuarterlyBilling;
use App\Filament\Pages\Billing;
use App\Models\Patient;
use App\Support\Quarter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BillingOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $billing = app(QuarterlyBilling::class);
        $current = Quarter::current();
        $previous = $current->previous();

        $currentLines = $billing->linesFor($current);
        $previousOpen = $billing->linesFor($previous)->reject(fn (BillingLine $line): bool => $line->isBilled())->count();

        return [
            Stat::make('Aktive Patienten', Patient::query()->currentlyEnrolled()->count())
                ->description('in Therapie Aktiv eingeschrieben'),
            Stat::make("Abrechenbar {$current->label()}", $currentLines->count())
                ->description($currentLines->where('code', BillingCode::Regular)->count().'× 97 · '
                    .$currentLines->where('code', BillingCode::Annual)->count().'× 97a')
                ->url(Billing::getUrl(['quartal' => $current->key()])),
            Stat::make("Offen {$previous->label()}", $previousOpen)
                ->description($previousOpen === 0 ? 'alles abgerechnet' : 'noch nicht als abgerechnet markiert')
                ->color($previousOpen === 0 ? 'success' : 'warning')
                ->url(Billing::getUrl(['quartal' => $previous->key()])),
        ];
    }
}
