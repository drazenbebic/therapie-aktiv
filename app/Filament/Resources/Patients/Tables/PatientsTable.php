<?php

namespace App\Filament\Resources\Patients\Tables;

use App\Billing\BillingCode;
use App\Enums\Insurer;
use App\Filament\Resources\Patients\Actions\DeregisterPatientAction;
use App\Models\Patient;
use App\Support\Quarter;
use App\Support\SvNumber;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PatientsTable
{
    public static function configure(Table $table): Table
    {
        $currentQuarter = Quarter::current();

        return $table
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')
                    ->label('Nachname')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('Vorname')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sv_number')
                    ->label('SV-Nummer')
                    ->formatStateUsing(fn (string $state): string => SvNumber::format($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('sv_number', 'like', '%'.SvNumber::normalize($search).'%')),
                TextColumn::make('date_of_birth')
                    ->label('Geburtsdatum')
                    ->date()
                    ->sortable(),
                TextColumn::make('insurer')
                    ->label('Kasse')
                    ->badge()
                    ->sortable(),
                TextColumn::make('enrolled_at')
                    ->label('Eingeschrieben')
                    ->date()
                    ->sortable(),
                TextColumn::make('programme_quarter')
                    ->label("Quartal im Programm ({$currentQuarter->label()})")
                    ->state(fn (Patient $record): ?int => $record->programmeQuarterIn($currentQuarter))
                    ->placeholder('–')
                    ->alignCenter(),
                TextColumn::make('billing_code')
                    ->label("Position {$currentQuarter->label()}")
                    ->state(fn (Patient $record): ?BillingCode => $record->billingCodeFor($currentQuarter))
                    ->badge()
                    ->placeholder('–')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (Patient $record): string => $record->deregistration_reason?->getLabel() ?? 'Aktiv')
                    ->description(fn (Patient $record): ?string => $record->deregistered_at?->format('d.m.Y'))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Aktiv' ? 'success' : 'gray'),
                TextColumn::make('height_cm')
                    ->label('Größe')
                    ->suffix(' cm')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('weight_kg')
                    ->label('Gewicht')
                    ->suffix(' kg')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktiv',
                        'deregistered' => 'Abgemeldet / verstorben',
                    ])
                    ->default('active')
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'active' => $query->whereNull('deregistered_at'),
                        'deregistered' => $query->whereNotNull('deregistered_at'),
                        default => $query,
                    }),
                SelectFilter::make('insurer')
                    ->label('Kasse')
                    ->options(Insurer::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeregisterPatientAction::make(),
            ])
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading('Keine Patienten gefunden');
    }
}
