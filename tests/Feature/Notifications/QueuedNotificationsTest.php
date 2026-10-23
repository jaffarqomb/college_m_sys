<?php

use App\Actions\Academic\EnrollInSection;
use App\Actions\Academic\MarkAttendance;
use App\Actions\Academic\SaveSectionScores;
use App\Actions\Finance\RecordManualPayment;
use App\Actions\Timetable\PublishTimetableGeneration;
use App\Enums\AttendanceStatus;
use App\Enums\TimetableGenerationStatus;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\Student;
use App\Models\TimetableGeneration;
use App\Models\User;
use App\Notifications\AttendanceAlert;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\EnrollmentConfirmed;
use App\Notifications\GradesUpdated;
use App\Notifications\InvoicePublished;
use App\Notifications\PaymentRecorded;
use App\Notifications\TimetablePublished;
use App\Services\Finance\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * @return array{0: Student, 1: CourseSection}
 */
function notifStudentAndSection(): array
{
    $student = Student::factory()->create();

    return [$student, CourseSection::factory()->inCurrentTerm()->create()];
}

test('enrolling sends the student a queued confirmation notification', function () {
    [$student, $section] = notifStudentAndSection();
    Notification::fake();

    app(EnrollInSection::class)->handle($student, $section);

    Notification::assertSentTo($student->user, EnrollmentConfirmed::class, 1);
});

test('publishing an invoice notifies the student once, not on re-publish', function () {
    $invoice = Invoice::factory()->state(fn () => ['status' => 'draft'])->create();
    Notification::fake();

    app(InvoiceService::class)->publish($invoice);
    app(InvoiceService::class)->publish($invoice->refresh());

    Notification::assertSentTo($invoice->student->user, InvoicePublished::class, 1);
});

test('recording a manual payment notifies the invoice student', function () {
    $invoice = Invoice::factory()->create();
    Notification::fake();

    app(RecordManualPayment::class)->handle($invoice, 500, 'CASH-1');

    Notification::assertSentTo($invoice->student->user, PaymentRecorded::class);
});

test('saving section scores notifies every active enrolled student', function () {
    $section = CourseSection::factory()->inCurrentTerm()->create();

    $assessment = Assessment::query()->create([
        'section_id' => $section->id,
        'title' => 'Quiz 1',
        'type' => 'quiz',
        'weight' => 20,
        'max_score' => 50,
    ]);

    $enrolled = Enrollment::factory()->create(['section_id' => $section->id]);
    $dropped = Enrollment::factory()->dropped()->create(['section_id' => $section->id]);
    $other = Student::factory()->create();

    Notification::fake();

    app(SaveSectionScores::class)->handle($section, [
        $assessment->id => [$enrolled->id => 40],
    ]);

    Notification::assertSentTo($enrolled->student->user, GradesUpdated::class);
    Notification::assertNotSentTo($dropped->student->user, GradesUpdated::class);
    Notification::assertNotSentTo($other->user, GradesUpdated::class);
});

test('publishing a timetable generation notifies affected students and instructors once', function () {
    $term = AcademicTerm::factory()->current()->create();

    $section = CourseSection::factory()->create([
        'term_id' => $term->id,
        'status' => 'open',
    ]);

    $student = Student::factory()->create();
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'section_id' => $section->id,
    ]);

    $generation = TimetableGeneration::query()->create([
        'term_id' => $term->id,
        'user_id' => User::factory()->create()->id,
        'status' => TimetableGenerationStatus::Draft,
        'settings' => [],
        'assignments' => [
            ['section_id' => $section->id, 'slots' => [
                ['day' => 1, 'start' => '09:00', 'end' => '10:30', 'room_id' => Room::factory()->create()->id],
            ]],
        ],
        'conflicts' => [],
    ]);

    $instructorUser = $section->instructor->user;

    Notification::fake();

    $replaced = app(PublishTimetableGeneration::class)->handle($generation);

    expect($replaced)->toBe([$section->id]);

    Notification::assertSentTo($student->user, TimetablePublished::class, function (TimetablePublished $notification) use ($generation): bool {
        return $notification->term->is($generation->term);
    });
    Notification::assertSentTo($instructorUser, TimetablePublished::class, 1);
});

test('attendance below the threshold triggers exactly one queued alert (then throttles)', function () {
    [$student, $section] = notifStudentAndSection();
    config()->set('college.attendance.alert_threshold', 75.0);

    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'section_id' => $section->id,
    ]);

    Notification::fake();

    // Week 1: full attendance parity (present).
    app(MarkAttendance::class)->handle($section, now()->subWeeks(2)->toDateString(), [
        $enrollment->id => AttendanceStatus::Present,
    ]);

    Notification::assertNothingSent();

    // Week 2: the student is absent — percentage falls to 50%.
    app(MarkAttendance::class)->handle($section, now()->subWeek()->toDateString(), [
        $enrollment->id => AttendanceStatus::Absent,
    ]);

    Notification::assertSentTo($student->user, AttendanceAlert::class, 1);

    // Same percentage within the throttle window: no second alert.
    app(MarkAttendance::class)->handle($section, now()->toDateString(), [
        $enrollment->id => AttendanceStatus::Absent,
    ]);

    Notification::assertSentTo($student->user, AttendanceAlert::class, 1);
});

test('attendance alerts are disabled when the threshold is zero', function () {
    [$student, $section] = notifStudentAndSection();
    config()->set('college.attendance.alert_threshold', 0.0);

    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'section_id' => $section->id,
    ]);

    Notification::fake();

    app(MarkAttendance::class)->handle($section, now()->toDateString(), [
        $enrollment->id => AttendanceStatus::Absent,
    ]);

    Notification::assertNothingSent();
});

test('the sms channel is a no-op without a configured driver', function () {
    $user = User::factory()->create(['phone_number' => '+96550001122']);
    $enrollment = Enrollment::factory()->create();
    config()->set('services.sms.driver', null);

    $channel = app(SmsChannel::class);
    $channel->send($user, new EnrollmentConfirmed($enrollment->load('section.course', 'section.term')));

    expect($user->phone_number)->toBe('+96550001122');
});

test('the sms log driver routes the recipient and logs the message', function () {
    config()->set('services.sms.driver', 'log');
    $user = User::factory()->create(['phone_number' => '+96550001122']);

    $notification = new class('Test message body') extends BaseNotification
    {
        /**
         * @return array<int, string>
         */
        public function via(object $notifiable): array
        {
            return ['sms'];
        }

        public function toSms(object $notifiable): string
        {
            return 'Test message body';
        }
    };

    app(SmsChannel::class)->send($user, $notification);

    expect($user->routeNotificationForSms())->toBe('+96550001122');
});
