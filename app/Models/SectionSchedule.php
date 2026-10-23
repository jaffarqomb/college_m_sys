<?php

namespace App\Models;

use Database\Factories\SectionScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $section_id
 * @property int|null $room_id
 * @property int $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseSection $section
 * @property-read Room|null $room
 */
#[Fillable(['section_id', 'room_id', 'day_of_week', 'start_time', 'end_time'])]
class SectionSchedule extends Model
{
    /** @use HasFactory<SectionScheduleFactory> */
    use HasFactory;

    public const array DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /**
     * @return BelongsTo<CourseSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'section_id');
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Determine whether two schedule slots overlap in time.
     */
    public function overlaps(self $other): bool
    {
        return $this->start_time < $other->end_time
            && $other->start_time < $this->end_time;
    }

    /**
     * Get the human-readable day name.
     */
    public function dayLabel(): string
    {
        return self::DAYS[$this->day_of_week] ?? 'Unknown';
    }

    /**
     * Get the formatted time range, e.g. "09:00-10:30".
     */
    public function timeLabel(): string
    {
        return substr($this->start_time, 0, 5).'-'.substr($this->end_time, 0, 5);
    }
}
