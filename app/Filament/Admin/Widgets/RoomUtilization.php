<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RoomUtilization extends ChartWidget
{
    protected ?string $heading = 'Room utilization (current term)';

    protected ?string $description = 'Weekly booked hours per room in the published timetable.';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $role = self::panelRole();

        return in_array($role, [UserRole::Registrar, UserRole::SystemAdmin], true);
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
        $term = AcademicTerm::query()
            ->current()
            ->with(['courseSections.sectionSchedules.room'])
            ->first();

        if ($term === null) {
            return ['datasets' => [['label' => 'Weekly hours', 'data' => []]], 'labels' => []];
        }

        $hours = [];

        foreach ($term->courseSections as $section) {
            foreach ($section->sectionSchedules as $schedule) {
                if ($schedule->room === null) {
                    continue;
                }

                $minutes = Carbon::parse($schedule->end_time)
                    ->diffInMinutes(Carbon::parse($schedule->start_time), absolute: true);

                $label = $schedule->room->code;

                if (! array_key_exists($label, $hours)) {
                    $hours[$label] = 0.0;
                }

                $hours[$label] = round($hours[$label] + $minutes / 60.0, 2);
            }
        }

        arsort($hours);

        return [
            'datasets' => [
                [
                    'label' => 'Weekly hours',
                    'data' => array_values($hours),
                ],
            ],
            'labels' => array_keys($hours),
        ];
    }

    protected function getOptions(): ?array
    {
        return [
            'indexAxis' => 'y',
        ];
    }

    private static function panelRole(): ?UserRole
    {
        $user = auth()->user();

        return $user instanceof User ? $user->role : null;
    }
}
