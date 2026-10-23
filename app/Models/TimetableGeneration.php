<?php

namespace App\Models;

use App\Enums\TimetableGenerationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A draft (or published) workload proposal produced by the timetable engine.
 *
 * Sections of the term with existing schedules act as fixed constraints
 * unless the "regenerate_existing" setting is enabled.
 *
 * @property int $id
 * @property int $term_id
 * @property int $user_id
 * @property TimetableGenerationStatus $status
 * @property array<string, mixed> $settings
 * @property list<TimetablePlacement> $assignments
 * @property list<TimetableConflict> $conflicts
 * @property float|null $fitness
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AcademicTerm $term
 * @property-read User $user
 *
 * @phpstan-type TimetablePlacement array{section_id: int, code: string, course: string, instructor: string|null, fixed: bool, slots: list<array{day: int, start: string, end: string, room_id: int|null}>}
 * @phpstan-type TimetableConflict array{section_id: int, section: string, course: string, reason: string}
 */
#[Fillable(['term_id', 'user_id', 'status', 'settings', 'assignments', 'conflicts', 'fitness', 'published_at'])]
class TimetableGeneration extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TimetableGenerationStatus::class,
            'settings' => 'array',
            'assignments' => 'array',
            'conflicts' => 'array',
            'fitness' => 'float',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AcademicTerm, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to generations in draft state.
     *
     * @param  Builder<static>  $query
     */
    public function scopeDrafts(Builder $query): void
    {
        $query->where('status', TimetableGenerationStatus::Draft->value);
    }

    /**
     * Get the latest generation created for a term.
     */
    public static function latestForTerm(int $termId): ?self
    {
        return static::query()->where('term_id', $termId)->latest('id')->first();
    }
}
