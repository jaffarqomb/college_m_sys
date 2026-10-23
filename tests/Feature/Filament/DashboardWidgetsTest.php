<?php

use App\Actions\Academic\MarkAttendance;
use App\Actions\Academic\SaveSectionScores;
use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Filament\Admin\Widgets\AcademicOverview;
use App\Filament\Admin\Widgets\AttendanceRates;
use App\Filament\Admin\Widgets\EnrollmentTrends;
use App\Filament\Admin\Widgets\GradeDistribution;
use App\Filament\Admin\Widgets\RevenueOverview;
use App\Filament\Admin\Widgets\RoomUtilization;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Room;
use App\Models\SectionSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function widgetStaffUser(UserRole $role): User
{
    return User::factory()->asRole($role)->create();
}

/**
 * @return array{0: AcademicTerm, 1: CourseSection}
 */
function widgetTermSection(): array
{
    $term = AcademicTerm::factory()->current()->create();

    return [$term, CourseSection::factory()->create(['term_id' => $term->id])];
}

test('the enrollment trends chart counts non-dropped enrollments per term', function () {
    [$term, $section] = widgetTermSection();

    Enrollment::factory()->times(2)->create(['section_id' => $section->id]);
    Enrollment::factory()->create(['section_id' => $section->id, 'status' => 'completed']);
    Enrollment::factory()->dropped()->create(['section_id' => $section->id]);

    actingAs(widgetStaffUser(UserRole::Registrar));

    $data = Livewire::test(EnrollmentTrends::class)->instance()->getData();

    $index = array_search($term->name, $data['labels'], true);

    expect($index)->not->toBeFalse()
        ->and($data['datasets'][0]['data'][$index])->toBe(3);
});

test('the attendance rates chart averages per-section percentages', function () {
    [$term, $section] = widgetTermSection();

    $enrollment = Enrollment::factory()->create(['section_id' => $section->id]);

    app(MarkAttendance::class)->handle($section, '2026-11-01', [$enrollment->id => AttendanceStatus::Present]);
    app(MarkAttendance::class)->handle($section, '2026-11-02', [$enrollment->id => AttendanceStatus::Absent]);

    // A section without held sessions stays out of the chart.
    CourseSection::factory()->create(['term_id' => $term->id]);

    actingAs(widgetStaffUser(UserRole::Registrar));

    $data = Livewire::test(AttendanceRates::class)->instance()->getData();

    $index = array_search($section->fullCode(), $data['labels'], true);

    expect($index)->not->toBeFalse()
        ->and($data['datasets'][0]['data'][$index])->toBe(50.0)
        ->and($data['labels'])->toHaveLength(1);
});

test('the grade distribution chart counts weighted final letters', function () {
    [$term, $section] = widgetTermSection();

    $assessment = Assessment::query()->create([
        'section_id' => $section->id,
        'title' => 'Quiz 1',
        'type' => 'quiz',
        'weight' => 20,
        'max_score' => 50,
    ]);

    $enrollment = Enrollment::factory()->create(['section_id' => $section->id]);

    app(SaveSectionScores::class)->handle($section, [$assessment->id => [$enrollment->id => 45]]);

    actingAs(widgetStaffUser(UserRole::Registrar));

    $data = Livewire::test(GradeDistribution::class)->instance()->getData();

    $letters = $data['labels'];

    expect($letters)->toContain('B', 'C', 'D', 'F')
        ->and(in_array('A', $letters, true))->toBeTrue();
});

test('the room utilization chart sums weekly booked hours per room', function () {
    [$term, $section] = widgetTermSection();

    $room = Room::factory()->create();

    SectionSchedule::query()->create([
        'section_id' => $section->id,
        'room_id' => $room->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
    ]);

    actingAs(widgetStaffUser(UserRole::Registrar));

    $data = Livewire::test(RoomUtilization::class)->instance()->getData();

    $index = array_search($room->code, $data['labels'], true);

    expect($index)->not->toBeFalse()
        ->and($data['datasets'][0]['data'][$index])->toBe(2.0);
});

test('the revenue stats sum invoiced, collected and outstanding for the term', function () {
    [$term] = widgetTermSection();

    $invoice = Invoice::factory()->create(['term_id' => $term->id, 'total' => 700]);

    Payment::factory()->succeeded()->create(['invoice_id' => $invoice->id, 'amount' => 300]);
    Payment::factory()->failed()->create(['invoice_id' => $invoice->id, 'amount' => 700]);

    actingAs(widgetStaffUser(UserRole::FinanceOfficer));

    Livewire::test(RevenueOverview::class)
        ->assertSee('Invoiced')
        ->assertSee('700.000 KWD')
        ->assertSee('Collected')
        ->assertSee('300.000 KWD')
        ->assertSee('Outstanding')
        ->assertSee('400.000 KWD');
});

test('the revenue overview shows a placeholder when no current term is set', function () {
    actingAs(widgetStaffUser(UserRole::FinanceOfficer));

    Livewire::test(RevenueOverview::class)
        ->assertSee('No current term is set');
});

test('the academic overview stays visible to every panel role', function () {
    actingAs(widgetStaffUser(UserRole::FinanceOfficer));

    expect(AcademicOverview::canView())->toBeTrue();
});

test('widgets are role-scoped through canView', function (UserRole $role, array $visible) {
    actingAs(widgetStaffUser($role));

    expect(EnrollmentTrends::canView())->toBe(in_array('enrollments', $visible, true))
        ->and(AttendanceRates::canView())->toBe(in_array('attendance', $visible, true))
        ->and(GradeDistribution::canView())->toBe(in_array('grades', $visible, true))
        ->and(RevenueOverview::canView())->toBe(in_array('revenue', $visible, true))
        ->and(RoomUtilization::canView())->toBe(in_array('rooms', $visible, true));
})->with([
    [UserRole::Registrar, ['enrollments', 'attendance', 'grades', 'revenue', 'rooms']],
    [UserRole::DepartmentHead, ['enrollments', 'attendance', 'grades']],
    [UserRole::FinanceOfficer, ['revenue']],
    [UserRole::SystemAdmin, ['enrollments', 'attendance', 'grades', 'revenue', 'rooms']],
    [UserRole::Instructor, []],
]);
