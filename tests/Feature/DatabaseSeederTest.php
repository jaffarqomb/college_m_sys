<?php

use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\CourseSection;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the demo seeder provisions the fixture staff accounts', function () {
    $this->seed(DatabaseSeeder::class);

    foreach ([
        'admin@college.test' => UserRole::SystemAdmin,
        'registrar@college.test' => UserRole::Registrar,
        'finance@college.test' => UserRole::FinanceOfficer,
        'dept.head@college.test' => UserRole::DepartmentHead,
    ] as $email => $role) {
        $user = User::query()->where('email', $email)->first();

        expect($user)->not->toBeNull()
            ->and($user?->role)->toBe($role);
    }
});

test('the demo seeder provisions students, the current term, invoices and a published timetable', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('role', UserRole::Student)->count())->toBeGreaterThan(0)
        ->and(AcademicTerm::query()->where('is_current', true)->exists())->toBeTrue()
        ->and(Invoice::query()->count())->toBeGreaterThan(0)
        ->and(CourseSection::query()->whereHas('sectionSchedules')->count())->toBeGreaterThan(0);
});
