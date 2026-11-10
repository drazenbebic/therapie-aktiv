<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Enums\DeregistrationReason;
use App\Enums\Insurer;
use App\Models\Patient;
use App\Support\SvNumber;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Patient')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('last_name')
                            ->label('Nachname')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('first_name')
                            ->label('Vorname')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('sv_number')
                            ->label('SV-Nummer (e-card)')
                            ->placeholder('1234 010180')
                            ->required()
                            ->maxLength(11)
                            ->live(onBlur: true)
                            ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : SvNumber::format($state))
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : SvNumber::normalize($state))
                            ->rule(fn (?Patient $record): Closure => self::svNumberRule($record))
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                if ($state === null || filled($get('date_of_birth')) || ! SvNumber::isValid($state)) {
                                    return;
                                }

                                $set('date_of_birth', SvNumber::birthDate($state)?->toDateString());
                            }),
                        DatePicker::make('date_of_birth')
                            ->label('Geburtsdatum')
                            ->helperText('Wird aus der SV-Nummer übernommen, wenn leer.')
                            ->required()
                            ->maxDate(now()),
                        Select::make('insurer')
                            ->label('Krankenkasse')
                            ->options(Insurer::class)
                            ->required(),
                    ]),
                Section::make('Therapie Aktiv')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        DatePicker::make('enrolled_at')
                            ->label('Eingeschrieben am')
                            ->required(),
                        DatePicker::make('deregistered_at')
                            ->label('Abgemeldet am')
                            ->afterOrEqual('enrolled_at')
                            ->requiredWith('deregistration_reason'),
                        Select::make('deregistration_reason')
                            ->label('Grund der Abmeldung')
                            ->options(DeregistrationReason::class)
                            ->requiredWith('deregistered_at'),
                    ]),
                Section::make('Weitere Angaben')
                    ->columnSpanFull()
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextInput::make('height_cm')
                            ->label('Größe')
                            ->suffix('cm')
                            ->integer()
                            ->minValue(50)
                            ->maxValue(250),
                        TextInput::make('weight_kg')
                            ->label('Gewicht')
                            ->suffix('kg')
                            ->numeric()
                            ->step(0.1)
                            ->minValue(20)
                            ->maxValue(400),
                        Textarea::make('notes')
                            ->label('Notizen')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Checks the check digit and uniqueness of the SV number, ignoring spaces.
     */
    private static function svNumberRule(?Patient $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            $number = SvNumber::normalize((string) $value);

            if (! SvNumber::isValid($number)) {
                $fail('Die SV-Nummer ist ungültig (10 Ziffern, Prüfziffer stimmt nicht).');

                return;
            }

            $taken = Patient::query()
                ->where('sv_number', $number)
                ->when($record?->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->exists();

            if ($taken) {
                $fail('Ein Patient mit dieser SV-Nummer ist bereits erfasst.');
            }
        };
    }
}
