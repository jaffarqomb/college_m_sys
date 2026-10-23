<?php

namespace App\Models;

use Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_id
 * @property int $enrollment_id
 * @property string|null $score
 * @property string|null $feedback
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Assessment $assessment
 * @property-read Enrollment $enrollment
 */
#[Fillable(['assessment_id', 'enrollment_id', 'score', 'feedback'])]
class Grade extends Model
{
    /** @use HasFactory<GradeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Get the score as a percentage of the assessment max score.
     */
    public function percentage(): ?float
    {
        if ($this->score === null) {
            return null;
        }

        return round(((float) $this->score / max(0.01, (float) $this->assessment->max_score)) * 100, 2);
    }
}
