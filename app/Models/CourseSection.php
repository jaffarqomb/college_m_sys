<?php

namespace App\Models;

use Database\Factories\CourseSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_id
 * @property int $term_id
 * @property int|null $instructor_id
 * @property string $section_number
 * @property int $capacity
 * @property bool $requires_lab
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Course $course
 * @property-read AcademicTerm $term
 * @property-read Instructor|null $instructor
 * @property-read Collection<int, SectionSchedule> $sectionSchedules
 * @property-read Collection<int, Enrollment> $enrollments
 */
#[Fillable(['course_id', 'term_id', 'instructor_id', 'section_number', 'capacity', 'requires_lab', 'status'])]
class CourseSection extends Model
{
    /** @use HasFactory<CourseSectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_lab' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<AcademicTerm, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    /**
     * @return BelongsTo<Instructor, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * @return HasMany<SectionSchedule, $this>
     */
    public function sectionSchedules(): HasMany
    {
        return $this->hasMany(SectionSchedule::class, 'section_id');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'section_id');
    }

    /**
     * @return HasMany<ClassSession, $this>
     */
    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'section_id');
    }

    /**
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'section_id');
    }

    /**
     * Scope a query to sections accepting enrollments.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'open');
    }

    /**
     * Get the number of filled seats in the section.
     */
    public function enrolledCount(): int
    {
        return $this->enrollments()->where('status', 'active')->count();
    }

    /**
     * Get the number of remaining seats in the section.
     */
    public function remainingSeats(): int
    {
        return max(0, $this->capacity - $this->enrolledCount());
    }

    /**
     * Get the course code with section number, e.g. "CS101-01".
     */
    public function fullCode(): string
    {
        return $this->course->code.'-'.$this->section_number;
    }
}
