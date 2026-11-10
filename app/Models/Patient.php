<?php

namespace App\Models;

use App\Billing\BillingCode;
use App\Billing\BillingRules;
use App\Enums\DeregistrationReason;
use App\Enums\Insurer;
use App\Support\Quarter;
use App\Support\SvNumber;
use Carbon\CarbonImmutable;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $date_of_birth
 * @property CarbonImmutable $enrolled_at
 * @property CarbonImmutable|null $deregistered_at
 * @property Insurer $insurer
 * @property DeregistrationReason|null $deregistration_reason
 */
#[Fillable([
    'first_name',
    'last_name',
    'sv_number',
    'date_of_birth',
    'insurer',
    'height_cm',
    'weight_kg',
    'notes',
    'enrolled_at',
    'deregistered_at',
    'deregistration_reason',
])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'immutable_date',
            'enrolled_at' => 'immutable_date',
            'deregistered_at' => 'immutable_date',
            'insurer' => Insurer::class,
            'deregistration_reason' => DeregistrationReason::class,
            'height_cm' => 'integer',
            'weight_kg' => 'decimal:1',
        ];
    }

    /**
     * @return HasMany<BillingRecord, $this>
     */
    public function billingRecords(): HasMany
    {
        return $this->hasMany(BillingRecord::class);
    }

    /**
     * Patients enrolled in Therapie Aktiv on at least one day of the given quarter.
     */
    #[Scope]
    protected function activeIn(Builder $query, Quarter $quarter): void
    {
        $query
            ->whereDate('enrolled_at', '<=', $quarter->end()->toDateString())
            ->where(function (Builder $query) use ($quarter): void {
                $query
                    ->whereNull('deregistered_at')
                    ->orWhereDate('deregistered_at', '>=', $quarter->start()->toDateString());
            });
    }

    /**
     * Patients that are currently enrolled (not deregistered as of today).
     */
    #[Scope]
    protected function currentlyEnrolled(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query
                ->whereNull('deregistered_at')
                ->orWhereDate('deregistered_at', '>', CarbonImmutable::today()->toDateString());
        });
    }

    public function isActiveIn(Quarter $quarter): bool
    {
        if ($this->enrolled_at->greaterThan($quarter->end())) {
            return false;
        }

        return $this->deregistered_at === null || $this->deregistered_at->greaterThanOrEqualTo($quarter->start());
    }

    public function isDeregistered(): bool
    {
        return $this->deregistered_at !== null;
    }

    /**
     * Programme quarter (1-based) for the given calendar quarter, or null if not enrolled in it.
     */
    public function programmeQuarterIn(Quarter $quarter): ?int
    {
        if (! $this->isActiveIn($quarter)) {
            return null;
        }

        return BillingRules::programmeQuarter($this->enrolled_at, $quarter);
    }

    public function billingCodeFor(Quarter $quarter): ?BillingCode
    {
        $programmeQuarter = $this->programmeQuarterIn($quarter);

        return $programmeQuarter === null ? null : BillingRules::codeFor($programmeQuarter);
    }

    /**
     * "Nachname, Vorname".
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->last_name}, {$this->first_name}");
    }

    /**
     * @return Attribute<string, string>
     */
    protected function svNumber(): Attribute
    {
        return Attribute::set(fn (string $value): string => SvNumber::normalize($value));
    }
}
