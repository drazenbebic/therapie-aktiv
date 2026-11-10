<?php

namespace App\Models;

use App\Billing\BillingCode;
use App\Support\Quarter;
use Database\Factories\BillingRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quarter that was billed to the patient's insurer. The code is stored as billed, so later edits
 * to the patient's enrolment date don't rewrite history.
 *
 * @property BillingCode $code
 */
#[Fillable(['patient_id', 'year', 'quarter', 'programme_quarter', 'code', 'billed_at'])]
class BillingRecord extends Model
{
    /** @use HasFactory<BillingRecordFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'quarter' => 'integer',
            'programme_quarter' => 'integer',
            'code' => BillingCode::class,
            'billed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return Attribute<Quarter, never>
     */
    protected function calendarQuarter(): Attribute
    {
        return Attribute::get(fn (): Quarter => new Quarter($this->year, $this->quarter));
    }
}
