<?php

namespace App\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $department_id
 * @property string $code
 * @property string $title
 * @property int $credits
 * @property int $year_level
 * @property string|null $description
 * @property bool $requires_lab
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Department $department
 * @property-read Collection<int, Course> $prerequisites
 * @property-read Collection<int, Course> $requiredBy
 * @property-read Collection<int, CourseSection> $courseSections
 */
#[Fillable(['department_id', 'code', 'title', 'credits', 'year_level', 'description', 'requires_lab', 'is_active'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_lab' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Courses that must be completed before enrolling in this course.
     *
     * @return BelongsToMany<Course, $this>
     */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_prerequisites', 'course_id', 'prerequisite_course_id');
    }

    /**
     * Courses that list this course as a prerequisite.
     *
     * @return BelongsToMany<Course, $this>
     */
    public function requiredBy(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_prerequisites', 'prerequisite_course_id', 'course_id');
    }

    /**
     * @return HasMany<CourseSection, $this>
     */
    public function courseSections(): HasMany
    {
        return $this->hasMany(CourseSection::class, 'course_id');
    }

    /**
     * Scope a query to only include active courses.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
