<?php

namespace App\Models;

use Database\Factories\AcademicTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property Carbon|null $registration_deadline
 * @property bool $is_current
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CourseSection> $courseSections
 * @property-read Collection<int, Invoice> $invoices
 */
#[Fillable(['name', 'code', 'start_date', 'end_date', 'registration_deadline', 'is_current'])]
class AcademicTerm extends Model
{
    /** @use HasFactory<AcademicTermFactory> */
    use HasFactory;

    /**
     * Boot the model and its global event hooks.
     */
    protected static function booted(): void
    {
        static::saving(function (self $term): void {
            if ($term->is_current === true) {
                self::query()
                    ->whereKeyNot($term->getKey())
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'registration_deadline' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include the current term.
     *
     * @param  Builder<static>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_current', true);
    }

    /**
     * Get the sections offered in this term.
     *
     * @return HasMany<CourseSection, $this>
     */
    public function courseSections(): HasMany
    {
        return $this->hasMany(CourseSection::class, 'term_id');
    }

    /**
     * Get the invoices billed for this term.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'term_id');
    }

    /**
     * Determine if registration is still open for this term.
     */
    public function registrationIsOpen(): bool
    {
        return $this->registration_deadline === null
            || $this->registration_deadline->isFuture();
    }
}
