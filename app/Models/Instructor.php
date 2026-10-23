<?php

namespace App\Models;

use Database\Factories\InstructorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $department_id
 * @property string $employee_number
 * @property string|null $title
 * @property string|null $office
 * @property string|null $office_hours
 * @property bool $is_head
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Department $department
 */
#[Fillable(['user_id', 'department_id', 'employee_number', 'title', 'office', 'office_hours', 'is_head'])]
class Instructor extends Model
{
    /** @use HasFactory<InstructorFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_head' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasOne<CourseSection, $this>
     */
    public function courseSection(): HasOne
    {
        return $this->hasOne(CourseSection::class, 'instructor_id');
    }

    /**
     * @return HasMany<CourseSection, $this>
     */
    public function courseSections(): HasMany
    {
        return $this->hasMany(CourseSection::class, 'instructor_id');
    }

    /**
     * Get the instructor's display name.
     */
    public function displayName(): string
    {
        $title = $this->title ? $this->title.' ' : '';

        return $title.$this->user->name;
    }

    /**
     * @return Collection<int, CourseSection>
     */
    public function sections(): Collection
    {
        return $this->courseSections()->get();
    }
}
