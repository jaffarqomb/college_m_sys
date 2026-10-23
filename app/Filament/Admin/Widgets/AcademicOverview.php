<?php

namespace App\Filament\Admin\Widgets;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AcademicOverview extends BaseWidget
{
    protected ?string $heading = 'Academic overview';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $currentTerm = AcademicTerm::query()->current()->first();

        return [
            Stat::make('Active students', Student::query()->active()->count())
                ->description('Enrolled in at least one program')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make('Instructors', Instructor::count())
                ->description('Teaching staff on record')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Courses', Course::query()->active()->count())
                ->description('Active catalog courses')
                ->descriptionIcon('heroicon-m-book-open')
                ->color('success'),

            Stat::make('Open sections', CourseSection::query()->open()->count())
                ->description($currentTerm ? "Current term: {$currentTerm->name}" : 'No current term set')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),

            Stat::make('Active enrollments', Enrollment::query()->active()->count())
                ->description('Students currently taking classes')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('danger'),

            Stat::make('Active User', User::query()->active()->count())
                ->description('User with active accounts')
                ->descriptionIcon('heroicon-m-user')
                ->color('secondary'),
        ];
    }
}
