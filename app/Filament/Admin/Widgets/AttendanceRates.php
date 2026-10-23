<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Academic\GradeService;
use Filament\Widgets\ChartWidget;

class AttendanceRates extends ChartWidget
{
    protected ?string $heading = 'Attendance rates (current term)';

    protected ?string $description = 'Average attendance percentage per section with held sessions.';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $role = self::panelRole();

        return in_array($role, [UserRole::Registrar, UserRole::DepartmentHead, UserRole::SystemAdmin], true);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getMaxHeight(): ?string
    {
        return '280px';
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $term = AcademicTerm::query()->current()->first();

        if ($term === null) {
            return ['datasets' => [['label' => 'Attendance %', 'data' => []]], 'labels' => []];
        }

        $service = app(GradeService::class);

        $rows = [];

        foreach ($term->courseSections()->orderBy('course_id')->get() as $section) {
            $enrollments = $section->enrollments()
                ->where('status', 'active')
                ->with('attendanceRecords.classSession')
                ->get();

            $percentages = $enrollments
                ->map(fn (Enrollment $enrollment): ?float => $service->attendancePercent($enrollment))
                ->filter(fn (?float $percentage): bool => $percentage !== null);

            if ($percentages->isEmpty()) {
                continue;
            }

            $rows[] = [
                'label' => $section->fullCode(),
                'percentage' => round((float) $percentages->avg(), 2),
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Attendance %',
                    'data' => array_column($rows, 'percentage'),
                ],
            ],
            'labels' => array_column($rows, 'label'),
        ];
    }

    private static function panelRole(): ?UserRole
    {
        $user = auth()->user();

        return $user instanceof User ? $user->role : null;
    }
}
