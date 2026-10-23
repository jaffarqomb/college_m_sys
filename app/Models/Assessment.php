<?php

namespace App\Models;

use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $section_id
 * @property string $title
 * @property string $type
 * @property string $weight
 * @property string $max_score
 * @property Carbon|null $due_date
 * @property Carbon|null $conducted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseSection $section
 * @property-read Collection<int, Grade> $grades
 */
#[Fillable(['section_id', 'title', 'type', 'weight', 'max_score', 'due_date', 'conducted_at'])]
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'max_score' => 'decimal:2',
            'due_date' => 'date',
            'conducted_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<CourseSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'section_id');
    }

    /**
     * @return HasMany<Grade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'assessment_id');
    }
}
