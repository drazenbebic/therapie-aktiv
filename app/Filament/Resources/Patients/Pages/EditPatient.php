<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\Actions\DeregisterPatientAction;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Patient;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPatient extends EditRecord
{
    protected static string $resource = PatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeregisterPatientAction::make(),
            DeleteAction::make()
                ->modalDescription('Nur für versehentlich angelegte Patienten. Wer aus Therapie Aktiv ausscheidet, wird abgemeldet, nicht gelöscht.')
                ->visible(fn (Patient $record): bool => ! $record->billingRecords()->exists()),
        ];
    }
}
