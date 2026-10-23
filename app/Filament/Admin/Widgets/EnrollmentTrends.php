<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class EnrollmentTrends extends ChartWidget
{
    protected ?string $heading = 'Enrollment trends';

    protected ?string $description = 'Non-dropped enrollments per term (oldest to newest).';

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

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $terms = DB::table('academic_terms')
            ->orderBy('start_date')
            ->limit(6)
            ->get();

        if ($terms->isEmpty()) {
            return ['datasets' => [['label' => 'Enrollments', 'data' => []]], 'labels' => []];
        }

        $counts = DB::table('enrollments')
            ->join('course_sections', 'course_sections.id', '=', 'enrollments.section_id')
            ->whereIn('course_sections.term_id', $terms->pluck('id'))
            ->where('enrollments.status', '!=', 'dropped')
            ->selectRaw('course_sections.term_id as term_id, count(*) as total')
            ->groupBy('course_sections.term_id')
            ->pluck('total', 'term_id');

        return [
            'datasets' => [
                [
                    'label' => 'Enrollments',
                    'data' => $terms->map(fn (object $term): int => (int) ($counts[$term->id] ?? 0))->values()->all(),
                ],
            ],
            'labels' => $terms->pluck('name')->all(),
        ];
    }

    private static function panelRole(): ?UserRole
    {
        $user = auth()->user();

        return $user instanceof User ? $user->role : null;
    }
}
