<?php

use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ClassSession;
use App\Models\ContactMessage;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Instructor;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;

test('user roles are cast and panel access is restricted to staff roles', function () {
    $student = User::factory()->create(['role' => UserRole::Student->value]);
    $registrar = User::factory()->asRole(UserRole::Registrar)->create();
    $admin = User::factory()->systemAdmin()->create();
    $finance = User::factory()->asRole(UserRole::FinanceOfficer)->create();
    $instructor = User::factory()->asRole(UserRole::Instructor)->create();

    expect($student->role)->toBe(UserRole::Student)
        ->and($student->canAccessPanel(filament()->getPanel('admin')))->toBeFalse()
        ->and($registrar->canAccessPanel(filament()->getPanel('admin')))->toBeTrue()
        ->and($admin->canAccessPanel(filament()->getPanel('admin')))->toBeTrue()
        ->and($finance->canAccessPanel(filament()->getPanel('admin')))->toBeTrue()
        ->and($instructor->canAccessPanel(filament()->getPanel('admin')))->toBeFalse()
        ->and($admin->isSystemAdmin())->toBeTrue();
});

test('non-admin users do not bypass authorization while system admins do', function () {
    $student = User::factory()->create();
    $admin = User::factory()->systemAdmin()->create();

    expect($student->can('view-any-department'))->toBeFalse()
        ->and($admin->can('view-any-department'))->toBeTrue();
});

test('domain relationships are wired correctly', function () {
    $department = Department::factory()->create();
    $program = Program::factory()->for($department)->create();
    $student = Student::factory()->for(Program::find($program->id))->create();

    $term = AcademicTerm::factory()->current()->create();
    $course = Course::factory()->for($department)->create();
    $instructor = Instructor::factory()->create();

    $section = CourseSection::factory()
        ->for($course)
        ->for($term, 'term')
        ->for($instructor)
        ->create();

    $enrollment = Enrollment::factory()
        ->for($student)
        ->for($section, 'section')
        ->create();

    $session = ClassSession::factory()
        ->for($section, 'section')
        ->create();

    AttendanceRecord::factory()
        ->for($session, 'classSession')
        ->for($enrollment)
        ->absent()
        ->create();

    $assessment = Assessment::factory()
        ->for($section, 'section')
        ->create();

    $grade = Grade::factory()
        ->for($assessment)
        ->for($enrollment)
        ->create(['score' => 85]);

    expect($program->department->is($department))->toBeTrue()
        ->and($student->program->is($program))->toBeTrue()
        ->and($section->course->is($course))->toBeTrue()
        ->and($section->term->is($term))->toBeTrue()
        ->and($section->instructor->is($instructor))->toBeTrue()
        ->and($enrollment->student->is($student))->toBeTrue()
        ->and($enrollment->section->is($section))->toBeTrue()
        ->and($enrollment->attendanceRecords->count())->toBe(1)
        ->and($enrollment->attendanceRecords->first()->countsAsPresent())->toBeFalse()
        ->and($grade->enrollment->is($enrollment))->toBeTrue()
        ->and($grade->percentage())->toBe(85.0)
        ->and($term->is_current)->toBeTrue()
        ->and($term->courseSections->first()->is($section))->toBeTrue();
});

test('course prerequisite relationships connect in both directions', function () {
    $math = Course::factory()->create();
    $cs = Course::factory()->create();

    $cs->prerequisites()->attach($math);

    expect($cs->prerequisites->modelKeys())->toContain($math->id)
        ->and($math->requiredBy->modelKeys())->toContain($cs->id);
});

test('finance aggregates compute from related records', function () {
    $invoice = Invoice::factory()->create(['total' => 5000]);

    InvoiceItem::factory()->for($invoice)->create(['amount' => 4000]);
    InvoiceItem::factory()->for($invoice)->create(['amount' => 1000]);
    Payment::factory()->for($invoice)->succeeded()->create(['amount' => 2000]);
    Payment::factory()->for($invoice)->create(['amount' => 3000]); // pending, not counted

    expect($invoice->paidTotal())->toBe(2000.0)
        ->and($invoice->balance())->toBe(3000.0);
});

test('public-facing message models persist', function () {
    $contact = ContactMessage::factory()->create();
    $session = ChatSession::factory()->create();
    $message = ChatMessage::factory()->for($session, 'session')->fromBot()->create();

    expect($contact->is_read)->toBeFalse()
        ->and($message->session->is($session))->toBeTrue()
        ->and($message->role)->toBe('bot');
});
