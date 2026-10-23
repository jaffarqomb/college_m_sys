<?php

namespace Database\Seeders;

use App\Actions\Finance\GenerateTermInvoices;
use App\Actions\Finance\RecordManualPayment;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Grade;
use App\Models\Instructor;
use App\Models\Invoice;
use App\Models\Program;
use App\Models\Room;
use App\Models\SectionSchedule;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds a realistic academic demo dataset that is safe to re-run:
 * every entity is created through firstOrCreate/updateOrCreate keyed on
 * its unique business attribute (code, email, student/employee number).
 *
 * All demo accounts use the password "password".
 */
class AcademicDemoSeeder extends Seeder
{
    public function run(): void
    {
        $departments = $this->seedDepartments();
        $programs = $this->seedPrograms($departments);
        $terms = $this->seedTerms();

        $rooms = collect([
            ['building' => 'A', 'code' => 'A-101', 'capacity' => 40, 'type' => 'lecture'],
            ['building' => 'A', 'code' => 'A-102', 'capacity' => 30, 'type' => 'lecture'],
            ['building' => 'B', 'code' => 'B-201', 'capacity' => 50, 'type' => 'lecture'],
            ['building' => 'A', 'code' => 'LAB-1', 'capacity' => 24, 'type' => 'lab'],
            ['building' => 'C', 'code' => 'LAB-2', 'capacity' => 30, 'type' => 'lab'],
        ])->map(fn (array $attributes): Room => Room::query()->firstOrCreate(
            ['code' => $attributes['code']],
            $attributes,
        ));

        $courses = $this->seedCourses($departments);
        $instructors = $this->seedStaffAndInstructors($departments);
        $sections = $this->seedSections($terms, $courses, $instructors);
        $this->seedStudents($programs);
        $this->seedEnrollments($sections);
        $this->seedGradingData($sections);
        $this->seedFinanceData($programs, $terms);

        // Attach course prerequisites (CS201 ← CS101, CS210 ← CS101, CS330 ← CS201, MA201 ← MA101).
        $courses['CS201']->prerequisites()->sync([$courses['CS101']->id]);
        $courses['CS210']->prerequisites()->sync([$courses['CS101']->id]);
        $courses['CS330']->prerequisites()->sync([$courses['CS201']->id]);
        $courses['MA201']->prerequisites()->sync([$courses['MA101']->id]);
    }

    /**
     * @return array<string, Department>
     */
    private function seedDepartments(): array
    {
        return collect([
            ['name' => 'Computer Science', 'code' => 'CS', 'description' => 'Programs and research in computing and software engineering.'],
            ['name' => 'Mathematics', 'code' => 'MA', 'description' => 'Pure and applied mathematics, statistics and data science foundations.'],
            ['name' => 'Electrical Engineering', 'code' => 'EE', 'description' => 'Electronics, circuits, power and communication systems.'],
        ])->mapWithKeys(function (array $attributes): array {
            $department = Department::query()->firstOrCreate(
                ['code' => $attributes['code']],
                $attributes,
            );

            return [$attributes['code'] => $department];
        })->all();
    }

    /**
     * @param  array<string, Department>  $departments
     * @return array<string, Program>
     */
    private function seedPrograms(array $departments): array
    {
        return collect([
            ['department' => 'CS', 'name' => 'Bachelor of Science in Computer Science', 'code' => 'BS-CS', 'level' => 'bachelor', 'duration_years' => 4],
            ['department' => 'CS', 'name' => 'Bachelor of Science in Data Science', 'code' => 'BS-DS', 'level' => 'bachelor', 'duration_years' => 4],
            ['department' => 'MA', 'name' => 'Bachelor of Science in Mathematics', 'code' => 'BSC-MATH', 'level' => 'bachelor', 'duration_years' => 4],
        ])->mapWithKeys(function (array $attributes) use ($departments): array {
            $program = Program::query()->firstOrCreate(
                ['code' => $attributes['code']],
                [
                    'department_id' => $departments[$attributes['department']]->id,
                    'name' => $attributes['name'],
                    'level' => $attributes['level'],
                    'duration_years' => $attributes['duration_years'],
                ],
            );

            return [$attributes['code'] => $program];
        })->all();
    }

    /**
     * @return array<string, AcademicTerm>
     */
    private function seedTerms(): array
    {
        $fall = AcademicTerm::query()->firstOrCreate(
            ['code' => 'FALL-2026'],
            [
                'name' => 'Fall 2026',
                'start_date' => '2026-08-31',
                'end_date' => '2026-12-18',
                'registration_deadline' => '2026-11-07',
                'is_current' => true,
            ],
        );

        AcademicTerm::query()->firstOrCreate(
            ['code' => 'SPRING-2027'],
            [
                'name' => 'Spring 2027',
                'start_date' => '2027-01-18',
                'end_date' => '2027-05-14',
                'registration_deadline' => '2026-12-15',
                'is_current' => false,
            ],
        );

        // The boot() invariant clears is_current elsewhere; re-assert to be safe.
        AcademicTerm::query()->whereKey($fall->id)->update(['is_current' => true]);

        return ['FALL-2026' => $fall];
    }

    /**
     * @param  array<string, Department>  $departments
     * @return array<string, Course>
     */
    private function seedCourses(array $departments): array
    {
        $rows = [
            ['dept' => 'CS', 'code' => 'CS101', 'title' => 'Introduction to Programming', 'credits' => 4, 'year_level' => 1, 'requires_lab' => false],
            ['dept' => 'CS', 'code' => 'CS201', 'title' => 'Data Structures', 'credits' => 4, 'year_level' => 2, 'requires_lab' => false],
            ['dept' => 'CS', 'code' => 'CS210', 'title' => 'Database Systems', 'credits' => 3, 'year_level' => 2, 'requires_lab' => true],
            ['dept' => 'CS', 'code' => 'CS330', 'title' => 'Operating Systems', 'credits' => 3, 'year_level' => 3, 'requires_lab' => true],
            ['dept' => 'MA', 'code' => 'MA101', 'title' => 'Calculus I', 'credits' => 4, 'year_level' => 1, 'requires_lab' => false],
            ['dept' => 'MA', 'code' => 'MA201', 'title' => 'Linear Algebra', 'credits' => 3, 'year_level' => 2, 'requires_lab' => false],
            ['dept' => 'EE', 'code' => 'EE101', 'title' => 'Circuit Analysis', 'credits' => 3, 'year_level' => 1, 'requires_lab' => true],
        ];

        return collect($rows)->mapWithKeys(function (array $row) use ($departments): array {
            $course = Course::query()->firstOrCreate(
                ['code' => $row['code']],
                [
                    'department_id' => $departments[$row['dept']]->id,
                    'title' => $row['title'],
                    'credits' => $row['credits'],
                    'year_level' => $row['year_level'],
                    'requires_lab' => $row['requires_lab'],
                    'is_active' => true,
                ],
            );

            return [$row['code'] => $course];
        })->all();
    }

    /**
     * Creates staff accounts plus instructors (each instructor owns a user
     * login). The Computer Science head doubles as the department-head demo
     * user for the Filament panel.
     *
     * @param  array<string, Department>  $departments
     * @return array<string, Instructor>
     */
    private function seedStaffAndInstructors(array $departments): array
    {
        $this->createStaffUser('admin@college.test', 'Noor Admin', UserRole::SystemAdmin);
        $this->createStaffUser('registrar@college.test', 'Fatima Noor', UserRole::Registrar);
        $this->createStaffUser('finance@college.test', 'Hassan Baraka', UserRole::FinanceOfficer);

        $instructors = collect([
            [
                'email' => 'dept.head@college.test', 'name' => 'Dr. Amina Yusuf', 'role' => UserRole::DepartmentHead,
                'dept' => 'CS', 'employee_number' => 'EMP-2001', 'title' => 'Professor', 'office' => 'CS-Block 2', 'office_hours' => 'Mon 14:00-16:00', 'is_head' => true,
            ],
            [
                'email' => 'peter.otieno@college.test', 'name' => 'Dr. Peter Otieno', 'role' => UserRole::Instructor,
                'dept' => 'CS', 'employee_number' => 'EMP-2002', 'title' => 'Associate Professor', 'office' => 'CS-Block 2', 'office_hours' => 'Wed 10:00-12:00', 'is_head' => false,
            ],
            [
                'email' => 'grace.wanjiru@college.test', 'name' => 'Dr. Grace Wanjiru', 'role' => UserRole::Instructor,
                'dept' => 'MA', 'employee_number' => 'EMP-2003', 'title' => 'Professor', 'office' => 'MA-Block 1', 'office_hours' => 'Tue 13:00-15:00', 'is_head' => true,
            ],
            [
                'email' => 'sarah.njeri@college.test', 'name' => 'Dr. Sarah Njeri', 'role' => UserRole::Instructor,
                'dept' => 'MA', 'employee_number' => 'EMP-2004', 'title' => 'Senior Lecturer', 'office' => 'MA-Block 1', 'office_hours' => 'Thu 09:00-11:00', 'is_head' => false,
            ],
            [
                'email' => 'david.kimani@college.test', 'name' => 'Engr. David Kimani', 'role' => UserRole::Instructor,
                'dept' => 'EE', 'employee_number' => 'EMP-2005', 'title' => 'Senior Lecturer', 'office' => 'EE-Block 3', 'office_hours' => 'Fri 10:00-12:00', 'is_head' => true,
            ],
        ]);

        return $instructors->mapWithKeys(function (array $row) use ($departments): array {
            $user = $this->createStaffUser($row['email'], $row['name'], $row['role']);

            $instructor = Instructor::query()->firstOrCreate(
                ['employee_number' => $row['employee_number']],
                [
                    'user_id' => $user->id,
                    'department_id' => $departments[$row['dept']]->id,
                    'title' => $row['title'],
                    'office' => $row['office'],
                    'office_hours' => $row['office_hours'],
                    'is_head' => $row['is_head'],
                ],
            );

            if ($instructor->user_id !== $user->id) {
                $instructor->update(['user_id' => $user->id]);
            }

            return [$row['employee_number'] => $instructor];
        })->all();
    }

    private function createStaffUser(string $email, string $name, UserRole $role): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make('password')],
        );

        $user->forceFill([
            'name' => $name,
            'password' => Hash::make('password'),
            'role' => $role,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * @param  array<string, AcademicTerm>  $terms
     * @param  array<string, Course>  $courses
     * @param  array<string, Instructor>  $instructors
     * @return array<string, CourseSection>
     */
    private function seedSections(array $terms, array $courses, array $instructors): array
    {
        $fall = $terms['FALL-2026'];
        $rooms = Room::query()->pluck('id', 'code');

        $base = [
            'term_id' => $fall->id,
            'status' => 'open',
            'requires_lab' => false,
        ];

        $rows = [
            ['key' => 'CS101-01', 'course' => 'CS101', 'instructor' => 'EMP-2001', 'capacity' => 30, 'room' => 'A-101', 'slots' => [[1, '09:00:00', '10:30:00'], [3, '09:00:00', '10:30:00']]],
            ['key' => 'CS101-02', 'course' => 'CS101', 'instructor' => 'EMP-2002', 'capacity' => 8, 'room' => 'A-102', 'slots' => [[2, '11:00:00', '12:30:00'], [4, '11:00:00', '12:30:00']]],
            ['key' => 'CS201-01', 'course' => 'CS201', 'instructor' => 'EMP-2002', 'capacity' => 25, 'room' => 'A-102', 'slots' => [[1, '13:00:00', '14:30:00'], [3, '13:00:00', '14:30:00']]],
            ['key' => 'CS210-01', 'course' => 'CS210', 'instructor' => 'EMP-2001', 'capacity' => 20, 'room' => 'LAB-1', 'requires_lab' => true, 'slots' => [[2, '09:00:00', '11:00:00']]],
            ['key' => 'MA101-01', 'course' => 'MA101', 'instructor' => 'EMP-2003', 'capacity' => 40, 'room' => 'B-201', 'slots' => [[1, '11:00:00', '12:30:00'], [4, '11:00:00', '12:30:00']]],
            ['key' => 'MA101-02', 'course' => 'MA101', 'instructor' => 'EMP-2004', 'capacity' => 40, 'room' => 'B-201', 'status' => 'closed', 'slots' => [[2, '09:00:00', '10:30:00']]],
            ['key' => 'MA201-01', 'course' => 'MA201', 'instructor' => 'EMP-2004', 'capacity' => 18, 'room' => 'B-201', 'slots' => [[3, '09:00:00', '10:30:00']]],
            ['key' => 'EE101-01', 'course' => 'EE101', 'instructor' => 'EMP-2005', 'capacity' => 24, 'room' => 'LAB-2', 'requires_lab' => true, 'slots' => [[5, '08:00:00', '10:30:00']]],
        ];

        return collect($rows)->mapWithKeys(function (array $row) use ($courses, $instructors, $rooms, $base): array {
            $section = CourseSection::query()->firstOrCreate(
                ['course_id' => $courses[$row['course']]->id, 'term_id' => $base['term_id'], 'section_number' => substr($row['key'], -2)],
                [
                    'instructor_id' => $instructors[$row['instructor']]->id,
                    'capacity' => $row['capacity'],
                    'requires_lab' => $row['requires_lab'] ?? $base['requires_lab'],
                    'status' => $row['status'] ?? $base['status'],
                ],
            );

            foreach ($row['slots'] as $slot) {
                SectionSchedule::query()->firstOrCreate(
                    [
                        'section_id' => $section->id,
                        'day_of_week' => $slot[0],
                        'start_time' => $slot[1],
                    ],
                    [
                        'end_time' => $slot[2],
                        'room_id' => $rooms[$row['room']],
                    ],
                );
            }

            return [$row['key'] => $section];
        })->all();
    }

    /**
     * @param  array<string, Program>  $programs
     */
    private function seedStudents(array $programs): void
    {
        $people = [
            ['n' => 1, 'name' => 'John Mwangi', 'program' => 'BS-CS'],
            ['n' => 2, 'name' => 'Mary Achieng', 'program' => 'BS-CS'],
            ['n' => 3, 'name' => 'Brian Otieno', 'program' => 'BS-CS'],
            ['n' => 4, 'name' => 'Fatuma Ali', 'program' => 'BS-CS'],
            ['n' => 5, 'name' => 'Kevin Mutiso', 'program' => 'BS-CS'],
            ['n' => 6, 'name' => 'Aisha Hassan', 'program' => 'BS-CS'],
            ['n' => 7, 'name' => 'Daniel Kamau', 'program' => 'BS-DS'],
            ['n' => 8, 'name' => 'Lucy Wambui', 'program' => 'BS-DS'],
            ['n' => 9, 'name' => 'Peter Maina', 'program' => 'BSC-MATH'],
            ['n' => 10, 'name' => 'Zainab Mohamed', 'program' => 'BSC-MATH'],
        ];

        foreach ($people as $person) {
            $user = User::query()->firstOrCreate(
                ['email' => 'student'.$person['n'].'@college.test'],
                ['name' => $person['name'], 'password' => Hash::make('password')],
            );

            $user->forceFill([
                'name' => $person['name'],
                'password' => Hash::make('password'),
                'role' => UserRole::Student,
                'email_verified_at' => now(),
            ])->save();

            // Key on user_id (unique); the derived student number stays stable
            // across re-runs and satisfies both unique constraints.
            $student = Student::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'student_number' => sprintf('S-%06d', 100000 + (int) $person['n']),
                    'program_id' => $programs[$person['program']]->id,
                    'admission_date' => '2024-09-02',
                    'status' => 'active',
                ],
            );
        }
    }

    /**
     * @param  array<string, CourseSection>  $sections
     */
    private function seedEnrollments(array $sections): void
    {
        $students = Student::query()->whereIn('student_number', ['S-100001', 'S-100002', 'S-100003', 'S-100004', 'S-100005', 'S-100006', 'S-100007', 'S-100008', 'S-100009', 'S-100010'])
            ->get()
            ->keyBy(fn (Student $student) => $student->student_number);

        $active = [
            'S-100001' => ['CS101-01', 'MA101-01'],
            'S-100002' => ['CS101-01', 'MA101-01'],
            'S-100003' => ['CS101-02', 'MA101-01'],
            'S-100005' => ['CS201-01', 'CS210-01'],
            'S-100006' => ['CS201-01', 'MA101-01'],
            'S-100007' => ['CS201-01'],
            'S-100008' => ['CS210-01', 'MA101-01'],
            'S-100009' => ['MA201-01'],
            'S-100010' => ['MA201-01', 'MA101-01'],
        ];

        foreach ($active as $studentNumber => $sectionKeys) {
            foreach ($sectionKeys as $sectionKey) {
                Enrollment::query()->firstOrCreate([
                    'student_id' => $students[$studentNumber]->id,
                    'section_id' => $sections[$sectionKey]->id,
                ], [
                    'status' => 'active',
                    'enrolled_at' => now(),
                ]);
            }
        }

        // One dropped enrollment so the registrar sees a non-trivial history.
        Enrollment::query()->updateOrCreate(
            [
                'student_id' => $students['S-100004']->id,
                'section_id' => $sections['CS101-01']->id,
            ],
            ['status' => 'dropped', 'enrolled_at' => now()->subDays(10), 'dropped_at' => now()->subDays(2)],
        );
    }

    /**
     * Seeds class sessions, attendance, assessments and grades for two demo
     * sections so the attendance islands, gradebook and transcript have
     * realistic content immediately.
     *
     * @param  array<string, CourseSection>  $sections
     */
    private function seedGradingData(array $sections): void
    {
        $plans = [
            'CS101-01' => [
                'sessions' => [
                    ['offset' => 21, 'topic' => 'Variables & data types', 'statuses' => ['present', 'present']],
                    ['offset' => 19, 'topic' => 'Control flow', 'statuses' => ['present', 'late']],
                    ['offset' => 17, 'topic' => 'Functions', 'statuses' => ['present', 'present']],
                    ['offset' => 14, 'topic' => 'Arrays', 'statuses' => ['absent', 'present']],
                ],
                'assessments' => [
                    ['title' => 'Quiz 1', 'type' => 'quiz', 'weight' => 10, 'max' => 25, 'scores' => [[20.0], [20.0]]],
                    ['title' => 'Assignment 1', 'type' => 'assignment', 'weight' => 20, 'max' => 50, 'scores' => [[40.0], [36.0]]],
                    ['title' => 'Midterm Exam', 'type' => 'midterm', 'weight' => 30, 'max' => 50, 'scores' => [[43.0], [42.0]]],
                    ['title' => 'Final Exam', 'type' => 'final', 'weight' => 40, 'max' => 100, 'scores' => [[82.0], []]],
                ],
            ],
            'CS201-01' => [
                'sessions' => [
                    ['offset' => 20, 'topic' => 'Lists & complexity', 'statuses' => ['present', 'present', 'excused']],
                    ['offset' => 18, 'topic' => 'Stacks & queues', 'statuses' => ['present', 'late', 'present']],
                    ['offset' => 15, 'topic' => 'Trees', 'statuses' => ['present', 'absent', 'present']],
                ],
                'assessments' => [
                    ['title' => 'Quiz 1', 'type' => 'quiz', 'weight' => 15, 'max' => 30, 'scores' => [[24.0], [28.0], [21.0]]],
                    ['title' => 'Midterm Exam', 'type' => 'midterm', 'weight' => 35, 'max' => 50, 'scores' => [[44.0], [40.0], [39.0]]],
                    ['title' => 'Final Exam', 'type' => 'final', 'weight' => 50, 'max' => 100, 'scores' => [[], [85.0], []]],
                ],
            ],
        ];

        foreach ($plans as $sectionKey => $plan) {
            $section = $sections[$sectionKey];

            $enrollments = $section->enrollments()
                ->where('status', 'active')
                ->orderBy('id')
                ->get()
                ->values();

            if ($enrollments->isEmpty()) {
                continue;
            }

            foreach ($plan['sessions'] as $sessionPlan) {
                $existingSession = ClassSession::query()
                    ->where('section_id', $section->id)
                    ->whereDate('session_date', now()->subDays($sessionPlan['offset'])->toDateString())
                    ->first();

                $session = $existingSession ?? ClassSession::query()->create([
                    'section_id' => $section->id,
                    'session_date' => now()->subDays($sessionPlan['offset'])->toDateString(),
                    'topic' => $sessionPlan['topic'],
                    'status' => 'held',
                ]);

                foreach ($enrollments as $position => $enrollment) {
                    $status = $sessionPlan['statuses'][$position] ?? 'present';

                    AttendanceRecord::query()->updateOrCreate(
                        [
                            'class_session_id' => $session->id,
                            'enrollment_id' => $enrollment->id,
                        ],
                        ['status' => $status],
                    );
                }
            }

            foreach ($plan['assessments'] as $assessmentPlan) {
                $assessment = Assessment::query()->firstOrCreate(
                    [
                        'section_id' => $section->id,
                        'title' => $assessmentPlan['title'],
                    ],
                    [
                        'type' => $assessmentPlan['type'],
                        'weight' => $assessmentPlan['weight'],
                        'max_score' => $assessmentPlan['max'],
                        'conducted_at' => now()->subWeeks(2),
                        'due_date' => now()->addWeeks(2),
                    ],
                );

                foreach ($enrollments as $position => $enrollment) {
                    $score = $assessmentPlan['scores'][$position][0] ?? null;

                    if ($score === null) {
                        Grade::query()->where('assessment_id', $assessment->id)
                            ->where('enrollment_id', $enrollment->id)
                            ->delete();

                        continue;
                    }

                    Grade::query()->updateOrCreate(
                        [
                            'assessment_id' => $assessment->id,
                            'enrollment_id' => $enrollment->id,
                        ],
                        ['score' => $score],
                    );
                }
            }
        }
    }

    /**
     * Term fee structures, the billing run and three demo invoice states
     * (partially paid, fully paid, overdue) so every finance screen has
     * content immediately. Entirely safe to re-run.
     *
     * @param  array<string, Program>  $programs
     * @param  array<string, AcademicTerm>  $terms
     */
    private function seedFinanceData(array $programs, array $terms): void
    {
        $fall = $terms['FALL-2026'];

        if (FeeStructure::query()->where('term_id', $fall->id)->doesntExist()) {
            $fees = collect([
                ['program' => null, 'name' => 'Registration fee', 'amount' => 250.0],
                ['program' => 'BS-CS', 'name' => 'BS-CS tuition (per term)', 'amount' => 3200.0],
                ['program' => 'BS-DS', 'name' => 'BS-DS tuition (per term)', 'amount' => 3000.0],
                ['program' => 'BSC-MATH', 'name' => 'Mathematics tuition (per term)', 'amount' => 2800.0],
            ]);

            foreach ($fees as $fee) {
                FeeStructure::query()->create([
                    'term_id' => $fall->id,
                    'program_id' => $fee['program'] !== null ? $programs[$fee['program']]->id : null,
                    'name' => $fee['name'],
                    'amount' => $fee['amount'],
                    'is_active' => true,
                ]);
            }
        }

        if (Invoice::query()->where('term_id', $fall->id)->doesntExist()) {
            app(GenerateTermInvoices::class)->handle($fall);
        }

        Invoice::query()
            ->where('term_id', $fall->id)
            ->where('status', 'draft')
            ->each(fn (Invoice $invoice) => app(InvoiceService::class)->publish($invoice));

        $invoiceFor = fn (string $email): ?Invoice => Invoice::query()
            ->where('term_id', $fall->id)
            ->whereHas('student.user', fn ($query) => $query->where('email', $email))
            ->first();

        // John Mwangi settled half of his bill at the finance office.
        $partial = $invoiceFor('student1@college.test');

        if ($partial !== null
            && $partial->status === 'unpaid'
            && $partial->paidTotal() === 0.0) {
            app(RecordManualPayment::class)->handle(
                $partial,
                round($partial->balance() / 2, 2),
                'CASH-10024',
                'Demo: counter payment at the finance office.',
            );
        }

        // Brian Otieno has a fully settled invoice.
        $paid = $invoiceFor('student3@college.test');

        if ($paid !== null
            && $paid->status === 'unpaid'
            && $paid->paidTotal() === 0.0) {
            app(RecordManualPayment::class)->handle($paid, (float) $paid->balance(), 'CASH-10031', 'Demo: term settled in full.');
        }

        // Lucy Wambui never paid and her due date has passed — the daily
        // overdue pass turns this and any other lapsed invoice red.
        $overdue = $invoiceFor('student8@college.test');

        if ($overdue !== null
            && in_array($overdue->status, ['unpaid', 'partial'], true)
            && $overdue->paidTotal() === 0.0) {
            $overdue->update(['due_date' => now()->subDays(14)->toDateString()]);

            app(InvoiceService::class)->recomputeStatus($overdue->refresh());
        }
    }
}
