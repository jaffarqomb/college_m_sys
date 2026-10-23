<?php

namespace App\Actions\Academic;

use App\Models\Enrollment;
use Illuminate\Validation\ValidationException;

/**
 * Lets a student drop an active enrollment while the term is still running.
 * ValidationException messages are keyed under "drop".
 */
class DropEnrollment
{
    public function handle(Enrollment $enrollment): Enrollment
    {
        if ($enrollment->status !== 'active') {
            throw ValidationException::withMessages([
                'drop' => 'Only active enrollments can be dropped.',
            ]);
        }

        $termEndsAt = $enrollment->section->term->end_date;

        if ($termEndsAt->isPast()) {
            throw ValidationException::withMessages([
                'drop' => 'The term has ended; contact the registrar\'s office.',
            ]);
        }

        $enrollment->update([
            'status' => 'dropped',
            'dropped_at' => now(),
        ]);

        return $enrollment->refresh();
    }
}
