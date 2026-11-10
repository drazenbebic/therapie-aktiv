<?php

namespace App\Filament\Pages;

use App\Billing\BillingCode;
use App\Billing\BillingLine;
use App\Billing\BillingListPdf;
use App\Billing\QuarterlyBilling;
use App\Enums\Insurer;
use App\Models\Patient;
use App\Support\Quarter;
use App\Support\SvNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Billing extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.billing';

    protected static ?string $slug = 'abrechnung';

    protected static ?string $navigationLabel = 'Abrechnung';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyEuro;

    protected static ?int $navigationSort = 2;

    /** Selected quarter as "2026-4"; kept in the URL so reloads stay on the same quarter. */
    #[Url(as: 'quartal')]
    public ?string $quarter = null;

    public function mount(): void
    {
        $this->quarter = $this->selectedQuarter()->key();
    }

    public function selectedQuarter(): Quarter
    {
        try {
            return $this->quarter === null ? Quarter::current() : Quarter::fromKey($this->quarter);
        } catch (InvalidArgumentException) {
            return Quarter::current();
        }
    }

    public function getTitle(): string
    {
        return 'Abrechnung';
    }

    public function getHeading(): string
    {
        return 'Abrechnung '.$this->selectedQuarter()->label();
    }

    public function getSubheading(): string
    {
        $quarter = $this->selectedQuarter();
        $lines = $this->lines();
        $billed = $lines->filter(fn (BillingLine $line): bool => $line->isBilled());

        $parts = [
            $quarter->start()->format('d.m.Y').' – '.$quarter->end()->format('d.m.Y'),
            $lines->count().' Patienten',
            $lines->where('code', BillingCode::Regular)->count().'× 97, '.$lines->where('code', BillingCode::Annual)->count().'× 97a',
        ];

        $parts[] = match (true) {
            $lines->isEmpty() => 'nichts abzurechnen',
            $billed->count() === $lines->count() => 'abgerechnet am '.$billed->first()->record->billed_at->format('d.m.Y'),
            $billed->isNotEmpty() => ($lines->count() - $billed->count()).' noch offen',
            default => 'noch nicht abgerechnet',
        };

        return implode(' · ', $parts);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Patient::query()
                ->activeIn($this->selectedQuarter())
                ->with(['billingRecords' => fn ($query) => $query
                    ->where('year', $this->selectedQuarter()->year)
                    ->where('quarter', $this->selectedQuarter()->number)]))
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('last_name')->orderBy('first_name'))
            ->defaultGroup(
                Group::make('insurer')
                    ->label('Kasse')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn (Patient $record): string => $record->insurer->getLabel())
                    ->orderQueryUsing(fn (Builder $query): Builder => $query->orderByRaw(self::insurerOrderSql())),
            )
            ->groupingSettingsHidden()
            ->paginated(false)
            ->columns([
                TextColumn::make('last_name')
                    ->label('Name')
                    ->formatStateUsing(fn (Patient $record): string => $record->full_name)
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('sv_number')
                    ->label('SV-Nummer')
                    ->formatStateUsing(fn (string $state): string => SvNumber::format($state)),
                TextColumn::make('date_of_birth')
                    ->label('Geburtsdatum')
                    ->date(),
                TextColumn::make('enrolled_at')
                    ->label('Eingeschrieben')
                    ->date()
                    ->description(fn (Patient $record): ?string => $record->deregistered_at
                        ? 'abgemeldet '.$record->deregistered_at->format('d.m.Y')
                        : null),
                TextColumn::make('programme_quarter')
                    ->label('Quartal im Programm')
                    ->state(fn (Patient $record): ?int => $this->lineFor($record)?->programmeQuarter)
                    ->alignCenter(),
                TextColumn::make('billing_code')
                    ->label('Position')
                    ->state(fn (Patient $record): ?BillingCode => $this->lineFor($record)?->code)
                    ->badge()
                    ->alignCenter(),
                TextColumn::make('billed_at')
                    ->label('Abgerechnet')
                    ->state(fn (Patient $record): ?string => $this->lineFor($record)?->record?->billed_at->format('d.m.Y'))
                    ->placeholder('offen')
                    ->icon(fn (?string $state): Heroicon => $state ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedClock)
                    ->color(fn (?string $state): string => $state ? 'success' : 'warning'),
            ])
            ->emptyStateHeading('Keine Patienten in diesem Quartal')
            ->emptyStateDescription('Es ist niemand eingeschrieben, der in diesem Quartal abgerechnet werden kann.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previousQuarter')
                ->label('Vorheriges Quartal')
                ->hiddenLabel()
                ->tooltip('Vorheriges Quartal')
                ->icon(Heroicon::ChevronLeft)
                ->color('gray')
                ->action(fn () => $this->switchTo($this->selectedQuarter()->previous())),
            Action::make('currentQuarter')
                ->label('Aktuelles Quartal')
                ->color('gray')
                ->visible(fn (): bool => $this->selectedQuarter() != Quarter::current())
                ->action(fn () => $this->switchTo(Quarter::current())),
            Action::make('nextQuarter')
                ->label('Nächstes Quartal')
                ->hiddenLabel()
                ->tooltip('Nächstes Quartal')
                ->icon(Heroicon::ChevronRight)
                ->color('gray')
                ->action(fn () => $this->switchTo($this->selectedQuarter()->next())),
            Action::make('downloadPdf')
                ->label('PDF herunterladen')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->disabled(fn (): bool => $this->lines()->isEmpty())
                ->action(fn (BillingListPdf $pdf): StreamedResponse => response()->streamDownload(
                    fn () => print ($pdf->render($this->selectedQuarter())),
                    $pdf->filename($this->selectedQuarter()),
                    ['Content-Type' => 'application/pdf'],
                )),
            Action::make('markAsBilled')
                ->label('Als abgerechnet markieren')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (): bool => $this->lines()->contains(fn (BillingLine $line): bool => ! $line->isBilled()))
                ->requiresConfirmation()
                ->modalHeading(fn (): string => $this->selectedQuarter()->label().' als abgerechnet markieren?')
                ->modalDescription(fn (): string => 'Für '.$this->lines()->reject(fn (BillingLine $line): bool => $line->isBilled())->count()
                    .' Patienten wird gespeichert, dass dieses Quartal mit der angezeigten Position abgerechnet wurde.')
                ->action(function (QuarterlyBilling $billing): void {
                    $count = $billing->markAsBilled($this->selectedQuarter());
                    $this->cachedLines = null;
                    $this->resetTable();

                    Notification::make()
                        ->title("{$count} Patienten als abgerechnet markiert")
                        ->success()
                        ->send();
                }),
            ActionGroup::make([
                Action::make('undoBilling')
                    ->label('Abrechnung zurücksetzen')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (): bool => $this->lines()->contains(fn (BillingLine $line): bool => $line->isBilled()))
                    ->requiresConfirmation()
                    ->modalHeading(fn (): string => 'Abrechnung '.$this->selectedQuarter()->label().' zurücksetzen?')
                    ->modalDescription('Entfernt die gespeicherte Abrechnung dieses Quartals, z. B. wenn es versehentlich als abgerechnet markiert wurde.')
                    ->action(function (QuarterlyBilling $billing): void {
                        $billing->undoBilling($this->selectedQuarter());
                        $this->cachedLines = null;
                        $this->resetTable();

                        Notification::make()
                            ->title('Abrechnung zurückgesetzt')
                            ->success()
                            ->send();
                    }),
            ])->color('gray'),
        ];
    }

    private function switchTo(Quarter $quarter): void
    {
        $this->quarter = $quarter->key();
        $this->cachedLines = null;
        $this->resetTable();
    }

    /** @var Collection<int, BillingLine>|null */
    private ?Collection $cachedLines = null;

    /**
     * @return Collection<int, BillingLine>
     */
    private function lines(): Collection
    {
        return $this->cachedLines ??= app(QuarterlyBilling::class)->linesFor($this->selectedQuarter());
    }

    private function lineFor(Patient $patient): ?BillingLine
    {
        return app(QuarterlyBilling::class)->lineFor($patient, $this->selectedQuarter());
    }

    /**
     * SQL ordering that puts insurers in the order of the {@see Insurer} enum.
     */
    private static function insurerOrderSql(): string
    {
        $cases = collect(Insurer::cases())
            ->map(fn (Insurer $insurer, int $index): string => "WHEN '{$insurer->value}' THEN {$index}")
            ->implode(' ');

        return "CASE insurer {$cases} END";
    }
}
