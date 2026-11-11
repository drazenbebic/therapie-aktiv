<?php

namespace Tests\Feature;

use App\Billing\BillingCode;
use App\Billing\BillingListPdf;
use App\Billing\QuarterlyBilling;
use App\Filament\Pages\Billing;
use App\Filament\Widgets\BillingOverview;
use App\Models\BillingRecord;
use App\Models\Patient;
use App\Models\User;
use App\Support\Quarter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BillingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-11 09:00:00');
        $this->actingAs(User::factory()->create());
    }

    public function test_the_page_lists_the_patients_of_the_current_quarter(): void
    {
        $included = Patient::factory()->enrolledOn('2026-01-15')->create(); // Q4 2026 = 4th quarter → 97a
        $notYet = Patient::factory()->enrolledOn('2027-01-02')->create();

        $this->get('/abrechnung')->assertOk()->assertSee('Abrechnung Q4 2026');

        Livewire::test(Billing::class)
            ->assertSet('quarter', '2026-4')
            ->assertCanSeeTableRecords([$included])
            ->assertCanNotSeeTableRecords([$notYet])
            ->assertTableColumnStateSet('billing_code', BillingCode::Annual, $included)
            ->assertTableColumnStateSet('programme_quarter', 4, $included);
    }

    public function test_the_quarter_can_be_switched(): void
    {
        $patient = Patient::factory()->enrolledOn('2026-08-01')->create();

        Livewire::test(Billing::class)
            ->callAction('previousQuarter')
            ->assertSet('quarter', '2026-3')
            ->assertCanSeeTableRecords([$patient])
            ->callAction('previousQuarter')
            ->assertSet('quarter', '2026-2')
            ->assertCanNotSeeTableRecords([$patient])
            ->callAction('currentQuarter')
            ->assertSet('quarter', '2026-4');
    }

    public function test_the_quarter_can_be_opened_from_the_url(): void
    {
        $this->get('/abrechnung?quartal=2026-1')->assertOk()->assertSee('Abrechnung Q1 2026');
        $this->get('/abrechnung?quartal=quatsch')->assertOk()->assertSee('Abrechnung Q4 2026');
    }

    public function test_marking_as_billed_stores_records_and_hides_the_action(): void
    {
        Patient::factory()->count(3)->enrolledOn('2026-05-05')->create();

        Livewire::test(Billing::class)
            ->assertActionVisible('markAsBilled')
            ->assertActionHidden('undoBilling')
            ->callAction('markAsBilled')
            ->assertNotified('3 Patienten als abgerechnet markiert')
            ->assertActionHidden('markAsBilled')
            ->assertActionVisible('undoBilling');

        $this->assertSame(3, BillingRecord::query()->where('year', 2026)->where('quarter', 4)->count());
    }

    public function test_a_single_patient_is_counted_in_the_singular(): void
    {
        Patient::factory()->enrolledOn('2026-05-05')->create();

        Livewire::test(Billing::class)
            ->assertSee('1 Patient ·')
            ->callAction('markAsBilled')
            ->assertNotified('1 Patient als abgerechnet markiert');
    }

    public function test_billing_can_be_undone(): void
    {
        Patient::factory()->count(2)->enrolledOn('2026-05-05')->create();
        app(QuarterlyBilling::class)->markAsBilled(new Quarter(2026, 4));

        Livewire::test(Billing::class)
            ->callAction('undoBilling')
            ->assertActionVisible('markAsBilled');

        $this->assertSame(0, BillingRecord::query()->count());
    }

    public function test_the_pdf_can_be_downloaded(): void
    {
        Patient::factory()->enrolledOn('2026-05-05')->create();

        Livewire::test(Billing::class)
            ->callAction('downloadPdf')
            ->assertFileDownloaded('therapie-aktiv-abrechnung-2026-Q4.pdf');
    }

    public function test_the_pdf_is_a_valid_document(): void
    {
        Patient::factory()->enrolledOn('2026-05-05')->create(['last_name' => 'Öztürk']);

        $pdf = app(BillingListPdf::class)->render(new Quarter(2026, 4));

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_the_pdf_lists_patients_grouped_by_insurer_with_codes(): void
    {
        Patient::factory()->enrolledOn('2026-01-15')->create(['last_name' => 'Öztürk', 'first_name' => 'Ayşe']);
        Patient::factory()->enrolledOn('2026-05-05')->create(['last_name' => 'Gruber', 'first_name' => 'Franz']);

        $html = view('pdf.billing-list', [
            'quarter' => new Quarter(2026, 4),
            'groups' => app(QuarterlyBilling::class)->linesByInsurer(new Quarter(2026, 4)),
            'totalRegular' => 1,
            'totalAnnual' => 1,
            'generatedAt' => now(),
        ])->render();

        $this->assertStringContainsString('Öztürk, Ayşe', $html);
        $this->assertStringContainsString('Gruber, Franz', $html);
        $this->assertStringContainsString('97a', $html);
        $this->assertStringContainsString('2 Patienten (1× 97, 1× 97a)', $html);
    }

    public function test_the_dashboard_shows_the_overview(): void
    {
        Patient::factory()->enrolledOn('2026-05-05')->create();

        $this->get('/')->assertOk();

        Livewire::test(BillingOverview::class)
            ->assertSee('Abrechenbar Q4 2026')
            ->assertSee('Offen Q3 2026');
    }
}
