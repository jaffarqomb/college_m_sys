<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\LetterGrade;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\User;
use App\Services\Academic\GradeService;
use Filament\Widgets\ChartWidget;

class GradeDistribution extends ChartWidget
{
    protected ?string $heading = 'Grade distribution (current term)';

    protected ?string $description = 'Weighted final letter grades across graded enrollments.';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $role = self::panelRole();

        return in_array($role, [UserRole::Registrar, UserRole::DepartmentHead, UserRole::SystemAdmin], true);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $term = AcademicTerm::query()->current()->first();

        if ($term === null) {
            return ['datasets' => [['label' => 'Enrollments', 'data' => []]], 'labels' => []];
        }

        $service = app(GradeService::class);
        $labels = array_map(fn (LetterGrade $grade): string => $grade->label(), LetterGrade::cases());
        $counts = array_fill(0, count(LetterGrade::cases()), 0);

        $enrollments = $term->courseSections()
            ->whereHas('enrollments', fn ($query) => $query->where('status', 'active'))
            ->with(['course', 'enrollments' => fn ($query) => $query->where('status', 'active')->with('grades.assessment', 'attendanceRecords')])
            ->get()
            ->flatMap(fn ($section) => $section->enrollments);

        foreach ($enrollments as $enrollment) {
            $final = $service->weightedFinalGrade($enrollment);

            if ($final === null) {
                continue;
            }

            $index = array_search($final['letter'], LetterGrade::cases(), true);

            if ($index !== false) {
                $counts[$index]++;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Enrollments',
                    'data' => $counts,
                ],
            ],
            'labels' => $labels,
        ];
    }

    private static function panelRole(): ?UserRole
    {
        $user = auth()->user();

        return $user instanceof User ? $user->role : null;
    }
}
