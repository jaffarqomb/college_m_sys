<?php

namespace App\Models;

use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $building
 * @property string $code
 * @property int $capacity
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SectionSchedule> $sectionSchedules
 */
#[Fillable(['building', 'code', 'capacity', 'type'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    /**
     * Scope a query to rooms of the given type.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Get the scheduled class slots assigned to this room.
     *
     * @return HasMany<SectionSchedule, $this>
     */
    public function sectionSchedules(): HasMany
    {
        return $this->hasMany(SectionSchedule::class);
    }
}
