<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $class_session_id
 * @property int $enrollment_id
 * @property AttendanceStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ClassSession $classSession
 * @property-read Enrollment $enrollment
 */
#[Fillable(['class_session_id', 'enrollment_id', 'status'])]
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<ClassSession, $this>
     */
    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Determine whether the student was present (including excused).
     */
    public function countsAsPresent(): bool
    {
        return in_array($this->status, [AttendanceStatus::Present, AttendanceStatus::Excused], true);
    }

    /**
     * Scope a query to present attendance records.
     *
     * @param  Builder<static>  $query
     */
    public function scopePresent(Builder $query): void
    {
        $query->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Excused->value]);
    }

    /**
     * Scope a query to absent attendance records.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAbsent(Builder $query): void
    {
        $query->where('status', AttendanceStatus::Absent->value);
    }
}
