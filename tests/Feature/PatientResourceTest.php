<?php

namespace Tests\Feature;

use App\Enums\DeregistrationReason;
use App\Enums\Insurer;
use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Models\BillingRecord;
use App\Models\Patient;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PatientResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_guests_are_redirected_to_the_login(): void
    {
        auth()->logout();

        $this->get('/patients')->assertRedirect('/login');
    }

    public function test_the_list_shows_active_patients_by_default(): void
    {
        $active = Patient::factory()->create();
        $deregistered = Patient::factory()->deregisteredOn(now())->create();

        $this->get('/patients')->assertOk();

        Livewire::test(ListPatients::class)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$deregistered]);
    }

    public function test_a_patient_can_be_created(): void
    {
        Livewire::test(CreatePatient::class)
            ->fillForm([
                'last_name' => 'Huber',
                'first_name' => 'Maria',
                'sv_number' => '1237 010180',
                'date_of_birth' => '1980-01-01',
                'insurer' => Insurer::Oegk->value,
                'enrolled_at' => '2026-03-29',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $patient = Patient::query()->sole();
        $this->assertSame('1237010180', $patient->sv_number);
        $this->assertSame('2026-03-29', $patient->enrolled_at->toDateString());
        $this->assertSame(Insurer::Oegk, $patient->insurer);
    }

    public function test_the_birth_date_is_filled_from_the_sv_number(): void
    {
        Livewire::test(CreatePatient::class)
            ->fillForm(['sv_number' => '1237010180'])
            ->assertSchemaStateSet(['date_of_birth' => '1980-01-01']);
    }

    public function test_an_invalid_sv_number_is_rejected(): void
    {
        Livewire::test(CreatePatient::class)
            ->fillForm([
                'last_name' => 'Huber',
                'first_name' => 'Maria',
                'sv_number' => '1238010180',
                'date_of_birth' => '1980-01-01',
                'insurer' => Insurer::Oegk->value,
                'enrolled_at' => '2026-03-29',
            ])
            ->call('create')
            ->assertHasFormErrors(['sv_number']);

        $this->assertSame(0, Patient::query()->count());
    }

    public function test_a_duplicate_sv_number_is_rejected_even_with_spaces(): void
    {
        Patient::factory()->create(['sv_number' => '1237010180']);

        Livewire::test(CreatePatient::class)
            ->fillForm([
                'last_name' => 'Huber',
                'first_name' => 'Maria',
                'sv_number' => '1237 010180',
                'date_of_birth' => '1980-01-01',
                'insurer' => Insurer::Oegk->value,
                'enrolled_at' => '2026-03-29',
            ])
            ->call('create')
            ->assertHasFormErrors(['sv_number']);
    }

    public function test_editing_keeps_the_own_sv_number_valid(): void
    {
        $patient = Patient::factory()->create(['sv_number' => '1237010180']);

        Livewire::test(EditPatient::class, ['record' => $patient->getRouteKey()])
            ->assertSchemaStateSet(['sv_number' => '1237 010180'])
            ->fillForm(['last_name' => 'Neuer Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Neuer Name', $patient->fresh()->last_name);
        $this->assertSame('1237010180', $patient->fresh()->sv_number);
    }

    public function test_deregistration_needs_a_reason_and_a_date_after_enrolment(): void
    {
        $patient = Patient::factory()->enrolledOn('2026-01-10')->create();

        Livewire::test(EditPatient::class, ['record' => $patient->getRouteKey()])
            ->fillForm(['deregistered_at' => '2025-12-31'])
            ->call('save')
            ->assertHasFormErrors(['deregistered_at', 'deregistration_reason']);
    }

    public function test_a_patient_can_be_deregistered_from_the_list(): void
    {
        $patient = Patient::factory()->enrolledOn('2025-01-10')->create();

        Livewire::test(ListPatients::class)
            ->callTableAction('deregister', $patient, data: [
                'deregistered_at' => '2026-10-02',
                'deregistration_reason' => DeregistrationReason::Deceased->value,
            ])
            ->assertHasNoTableActionErrors();

        $patient->refresh();
        $this->assertSame('2026-10-02', $patient->deregistered_at->toDateString());
        $this->assertSame(DeregistrationReason::Deceased, $patient->deregistration_reason);
    }

    public function test_patients_with_billing_history_cannot_be_deleted(): void
    {
        $billed = BillingRecord::factory()->create()->patient;
        $fresh = Patient::factory()->create();

        Livewire::test(EditPatient::class, ['record' => $billed->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);

        Livewire::test(EditPatient::class, ['record' => $fresh->getRouteKey()])
            ->assertActionVisible(DeleteAction::class);
    }
}
