<?php

namespace App\Filament\Resources\Patients\Actions;

use App\Enums\DeregistrationReason;
use App\Models\Patient;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

/**
 * Records a deregistration from the quarterly Therapie Aktiv list.
 */
class DeregisterPatientAction
{
    public static function make(): Action
    {
        return Action::make('deregister')
            ->label('Abmelden')
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->modalHeading(fn (Patient $record): string => "{$record->full_name} abmelden")
            ->modalDescription('Ab dem Quartal nach dem Abmeldedatum scheint der Patient nicht mehr in der Abrechnung auf.')
            ->modalSubmitActionLabel('Abmelden')
            ->visible(fn (Patient $record): bool => ! $record->isDeregistered())
            ->schema([
                DatePicker::make('deregistered_at')
                    ->label('Abgemeldet am')
                    ->default(now())
                    ->required()
                    ->afterOrEqual(fn (Patient $record): string => $record->enrolled_at->toDateString()),
                Select::make('deregistration_reason')
                    ->label('Grund')
                    ->options(DeregistrationReason::class)
                    ->default(DeregistrationReason::Deregistered)
                    ->required(),
            ])
            ->action(fn (Patient $record, array $data) => $record->update($data))
            ->successNotificationTitle('Patient abgemeldet');
    }
}
