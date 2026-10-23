<?php

namespace App\Models;

use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $department_id
 * @property string $name
 * @property string $code
 * @property string $level
 * @property int $duration_years
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Department $department
 * @property-read Collection<int, Student> $students
 */
#[Fillable(['department_id', 'name', 'code', 'level', 'duration_years', 'description'])]
class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Get the human-readable label for the program level.
     */
    public function levelLabel(): string
    {
        return match ($this->level) {
            'associate' => 'Associate',
            'bachelor' => 'Bachelor',
            'master' => 'Master',
            'doctorate' => 'Doctorate',
            default => ucfirst($this->level),
        };
    }
}
