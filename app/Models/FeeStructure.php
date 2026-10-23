<?php

namespace App\Models;

use Database\Factories\FeeStructureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A chargeable item for a term, scoped optionally to one program. Structures
 * with a null program apply to students of every program.
 *
 * @property int $id
 * @property int $term_id
 * @property int|null $program_id
 * @property string $name
 * @property string $amount
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AcademicTerm $term
 * @property-read Program|null $program
 */
#[Fillable(['term_id', 'program_id', 'name', 'amount', 'is_active'])]
class FeeStructure extends Model
{
    /** @use HasFactory<FeeStructureFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
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
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }
}
